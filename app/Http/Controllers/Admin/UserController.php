<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BalanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    private const IMPORT_DEFAULT_PASSWORD = '123456789';

    public function index(Request $request): View
    {
        $actor = $request->user();

        $base = User::query();

        if (! $actor->isRole('admin')) {
            $base->whereHas('role', fn ($q) => $q->where('slug', '!=', 'admin'));
        }

        $stats = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'inactive' => (clone $base)->where('status', 'inactive')->count(),
        ];

        $users = (clone $base)
            ->with(['role', 'department', 'manager'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = trim((string) $request->input('q'));
                $q->where(function ($w) use ($keyword) {
                    $w->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('employee_code', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->integer('role_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $departments = Department::orderBy('name')->get();
        $roles = $this->assignableRoles($actor);
        $allRoles = Role::orderBy('id')->get();

        return view('admin.users.index', compact('actor', 'users', 'stats', 'departments', 'roles', 'allRoles'));
    }

    public function create(Request $request): View
    {
        $actor = $request->user();
        $roles = $this->assignableRoles($actor);
        $departments = Department::where('active', true)->orderBy('name')->get();
        $managers = $this->managerOptions();
        $nextCode = $this->nextEmployeeCode();
        $defaultRoleId = ($roles->firstWhere('slug', 'employee') ?? $roles->first(fn (Role $role) => $role->slug !== 'admin'))?->id;

        return view('admin.users.create', compact('actor', 'roles', 'departments', 'managers', 'nextCode', 'defaultRoleId'));
    }

    public function store(Request $request, BalanceService $balances): RedirectResponse
    {
        $actor = $request->user();
        $roles = $this->assignableRoles($actor);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'employee_code' => ['nullable', 'string', 'max:30', 'unique:users,employee_code'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', Rule::in($roles->pluck('id')->all())],
            'department_id' => ['nullable', 'exists:departments,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'position' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'join_date' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'role_id.in' => 'Bạn không được phép gán vai trò này.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'email.unique' => 'Email này đã được sử dụng.',
        ]);

        $data['employee_code'] = ($data['employee_code'] ?? null) ?: $this->nextEmployeeCode();

        $user = User::create($data);
        $balances->ensureForUser($user, now()->year);

        AuditLogger::log('user.create', 'users', "Tạo tài khoản {$user->name} ({$user->employee_code})", $user);

        return redirect()->route('admin.users.index')->with('success', "Đã tạo tài khoản cho {$user->name}.");
    }

    public function import(Request $request, BalanceService $balances): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ], [
            'file.required' => 'Vui lòng chọn tệp danh sách nhân sự.',
            'file.mimes' => 'Tệp phải ở định dạng Excel (.xlsx, .xls) hoặc CSV.',
            'file.max' => 'Tệp tối đa 10MB.',
        ]);

        $upload = $request->file('file');

        try {
            $reader = match (strtolower($upload->getClientOriginalExtension())) {
                'xlsx' => new XlsxReader,
                'xls' => new XlsReader,
                default => new CsvReader,
            };
            $reader->setReadDataOnly(false);
            $rows = $reader->load($upload->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Không đọc được tệp. Vui lòng tải lại file mẫu (.xlsx) hoặc dùng CSV UTF-8.');
        }

        $headerIndex = null;

        foreach ($rows as $index => $row) {
            if (! $this->isEmptyImportRow($row)) {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            return back()->with('error', 'Tệp không có dữ liệu.');
        }

        $columns = $this->importColumns($rows[$headerIndex]);
        $missing = array_diff(['name', 'role'], array_keys($columns));

        if ($missing) {
            return back()->with('error', 'Tệp thiếu cột bắt buộc: Họ và tên, Vai trò. Hãy dùng file mẫu mới nhất.');
        }

        $roles = $this->assignableRoles($request->user());
        $roleLookup = [];

        foreach ($roles as $role) {
            $roleLookup[$this->normalizeText($role->name)] = $role;
            $roleLookup[$this->normalizeText($role->slug)] = $role;
        }

        $departmentLookup = [];

        foreach (Department::all() as $department) {
            $departmentLookup[$this->normalizeText($department->name)] = $department;

            if ($department->code) {
                $departmentLookup[$this->normalizeText($department->code)] = $department;
            }
        }

        $errors = [];
        $created = 0;
        $seenCodes = [];

        foreach ($rows as $index => $row) {
            if ($index <= $headerIndex || $this->isEmptyImportRow($row)) {
                continue;
            }

            $line = $index + 1;
            $get = function (string $key) use ($row, $columns) {
                if (! array_key_exists($key, $columns)) {
                    return null;
                }

                return $this->importString($row[$columns[$key]] ?? null);
            };

            $name = trim((string) $get('name'));
            $employeeCode = $get('employee_code');
            $roleName = (string) $get('role');
            $departmentName = $get('department');
            $position = $get('position');
            $phone = $get('phone');
            $rowErrors = [];

            if ($name === '') {
                $rowErrors[] = 'thiếu họ và tên';
            }

            if ($employeeCode !== null) {
                $codeKey = mb_strtoupper($employeeCode);

                if (isset($seenCodes[$codeKey]) || User::whereRaw('UPPER(employee_code) = ?', [$codeKey])->exists()) {
                    $rowErrors[] = "mã nhân viên {$employeeCode} đã tồn tại";
                }
            }

            $role = $roleLookup[$this->normalizeText($roleName)] ?? null;

            if ($roleName === '') {
                $rowErrors[] = 'thiếu vai trò';
            } elseif (! $role) {
                $rowErrors[] = "vai trò \"{$roleName}\" không tồn tại hoặc bạn không được phép gán";
            }

            $department = null;

            if ($departmentName !== null) {
                $department = $departmentLookup[$this->normalizeText($departmentName)] ?? null;

                if (! $department) {
                    $rowErrors[] = "phòng ban \"{$departmentName}\" không tồn tại";
                }
            }

            $joinDate = null;
            $joinRaw = $get('join_date');

            if ($joinRaw !== null) {
                $joinDate = $this->parseImportDate($joinRaw);

                if (! $joinDate) {
                    $rowErrors[] = 'ngày vào làm không đúng định dạng (dd/mm/yyyy)';
                }
            }

            if ($rowErrors) {
                $errors[] = 'Dòng '.$line.': '.implode('; ', $rowErrors);

                continue;
            }

            try {
                $user = User::create([
                    'name' => $name,
                    'email' => $this->generateImportEmail($name),
                    'employee_code' => $employeeCode ?: $this->nextEmployeeCode(),
                    'password' => self::IMPORT_DEFAULT_PASSWORD,
                    'role_id' => $role->id,
                    'department_id' => $department?->id,
                    'manager_id' => null,
                    'position' => $position,
                    'phone' => $phone,
                    'join_date' => $joinDate,
                    'status' => 'active',
                ]);
            } catch (\Throwable $e) {
                report($e);
                $errors[] = 'Dòng '.$line.': không thể tạo tài khoản.';

                continue;
            }

            $balances->ensureForUser($user, now()->year);

            if ($employeeCode) {
                $seenCodes[mb_strtoupper($employeeCode)] = true;
            }

            $created++;
        }

        AuditLogger::log('user.import', 'users', "Import {$created} tài khoản nhân sự từ tệp {$upload->getClientOriginalName()}");

        if (! $created && ! $errors) {
            return back()->with('error', 'Tệp không có dòng dữ liệu nào để import.');
        }

        if ($errors) {
            $details = array_slice($errors, 0, 100);

            if (count($errors) > 100) {
                $details[] = '... và '.(count($errors) - 100).' lỗi khác.';
            }

            return redirect()->route('admin.users.index')
                ->with($created ? 'success' : 'error', $created
                    ? "Đã import {$created} tài khoản. ".count($errors).' dòng bị bỏ qua, xem chi tiết bên dưới.'
                    : 'Không import được tài khoản nào. '.count($errors).' dòng lỗi, xem chi tiết bên dưới.')
                ->with('import_errors', $details);
        }

        return redirect()->route('admin.users.index')->with('success', "Đã import {$created} tài khoản nhân sự.");
    }

    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('Everland Leave & OT')
            ->setTitle('Mẫu import nhân sự');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Nhân sự');

        $headers = ['Họ và tên', 'Mã nhân viên', 'Vai trò', 'Phòng ban', 'Chức danh', 'Số điện thoại', 'Ngày vào làm'];
        $sheet->fromArray($headers, null, 'A1');

        $roleNames = Role::orderBy('id')->pluck('name');
        $departmentNames = Department::orderBy('name')->pluck('name');

        $sheet->fromArray([
            'Nguyễn Văn A',
            'EVG-0101',
            $roleNames->first(fn ($name) => $name === 'Nhân viên') ?? $roleNames->first() ?? '',
            $departmentNames->first() ?? '',
            'Nhân viên Kinh doanh',
            '0901234567',
            '01/03/2026',
        ], null, 'A2');

        $lastColumn = 'G';
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0A2759');
        $sheet->getStyle('A2:'.$lastColumn.'2')->getFont()->setItalic(true)->getColor()->setARGB('FF8A98B3');

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setWidth(22);
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(26);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(26);

        foreach (['B', 'F', 'G'] as $column) {
            $sheet->getStyle($column.'2:'.$column.'500')->getNumberFormat()->setFormatCode('@');
        }

        $sheet->freezePane('A2');

        $lists = $spreadsheet->createSheet();
        $lists->setTitle('Danh mục');
        $lists->fromArray(['Vai trò', 'Phòng ban'], null, 'A1');

        foreach ($roleNames->values() as $index => $name) {
            $lists->setCellValue('A'.($index + 2), $name);
        }

        foreach ($departmentNames->values() as $index => $name) {
            $lists->setCellValue('B'.($index + 2), $name);
        }

        $lists->getColumnDimension('A')->setWidth(28);
        $lists->getColumnDimension('B')->setWidth(34);
        $lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $this->attachListValidation($sheet, 'C', $roleNames->count(), 'A', false, 'Vai trò');
        $this->attachListValidation($sheet, 'D', $departmentNames->count(), 'B', true, 'Phòng ban');

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Hướng dẫn');

        $lines = [
            'MẪU IMPORT NHÂN SỰ – EVERLAND LEAVE & OT',
            '1. Nhập dữ liệu vào sheet "Nhân sự", giữ nguyên dòng tiêu đề ở dòng 1.',
            '2. Xoá dòng ví dụ (dòng 2) trước khi import dữ liệu thật.',
            '3. Cột bắt buộc: Họ và tên, Vai trò.',
            '4. Vai trò và Phòng ban: chọn từ danh sách trong ô (nguồn tại sheet ẩn "Danh mục"). Phòng ban có thể bỏ trống.',
            '5. Email công ty tự sinh theo họ tên: tên gọi + chữ cái đầu của họ/tên đệm, ví dụ Phạm Trần Thuận → thuan.pt@everland.vn. Nếu trùng sẽ thêm số tăng dần: thuan.pt1@everland.vn, thuan.pt2@everland.vn...',
            '6. Mật khẩu mặc định: 123456789. Người dùng nên đổi mật khẩu sau lần đăng nhập đầu tiên.',
            '7. Ngày vào làm: định dạng dd/mm/yyyy.',
            '8. Mã nhân viên: bỏ trống hệ thống tự sinh (EVG-xxxx).',
            '9. Hỗ trợ tệp .xlsx, .xls và .csv (UTF-8).',
            '',
            'Vai trò hiện có: '.($roleNames->implode(', ') ?: '(chưa có vai trò)'),
            'Phòng ban hiện có: '.($departmentNames->implode(', ') ?: '(chưa có phòng ban)'),
        ];

        foreach ($lines as $index => $line) {
            $guide->setCellValue('A'.($index + 1), $line);
        }

        $guide->getColumnDimension('A')->setWidth(120);
        $guide->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $guide->getStyle('A1:A'.count($lines))->getAlignment()->setWrapText(true);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'mau-import-nhan-su.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    protected function attachListValidation(Worksheet $sheet, string $column, int $count, string $listColumn, bool $allowBlank, string $label): void
    {
        if ($count < 1) {
            return;
        }

        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank($allowBlank);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle($label.' không hợp lệ');
        $validation->setError('Vui lòng chọn '.mb_strtolower($label).' trong danh sách.');
        $validation->setFormula1("'Danh mục'!$".$listColumn.'$2:$'.$listColumn.'$'.($count + 1));

        for ($row = 2; $row <= 500; $row++) {
            $sheet->getCell($column.$row)->setDataValidation(clone $validation);
        }
    }

    public function edit(Request $request, User $user): View|RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        if ($user->is_protected) {
            return redirect()->route('admin.users.index')
                ->with('error', $this->protectedMessage($user));
        }

        $roles = $this->assignableRoles($actor);
        $departments = Department::orderBy('name')->get();

        $managers = User::active()
            ->where('id', '!=', $user->id)
            ->whereNotIn('id', $user->teamIds())
            ->orderBy('name')
            ->get();

        return view('admin.users.edit', compact('actor', 'user', 'roles', 'departments', 'managers'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        if ($user->is_protected) {
            return back()->with('error', $this->protectedMessage($user));
        }

        $roles = $this->assignableRoles($actor);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'employee_code' => ['nullable', 'string', 'max:30', Rule::unique('users', 'employee_code')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', Rule::in($roles->pluck('id')->all())],
            'department_id' => ['nullable', 'exists:departments,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'position' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'join_date' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'role_id.in' => 'Bạn không được phép gán vai trò này.',
        ]);

        if ($user->id === $actor->id && $data['status'] === 'inactive') {
            return back()->withInput()->withErrors(['status' => 'Bạn không thể tự khoá tài khoản của mình.']);
        }

        if (! empty($data['manager_id'])) {
            if ((int) $data['manager_id'] === $user->id || in_array((int) $data['manager_id'], $user->teamIds(), true)) {
                return back()->withInput()->withErrors(['manager_id' => 'Người quản lý không hợp lệ (gây vòng lặp cấp bậc).']);
            }
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        AuditLogger::log('user.update', 'users', "Cập nhật tài khoản {$user->name}", $user);

        return redirect()->route('admin.users.index')->with('success', "Đã cập nhật tài khoản {$user->name}.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        if ($user->is_protected) {
            return back()->with('error', $this->protectedMessage($user));
        }

        if ($user->id === $actor->id) {
            return back()->with('error', 'Bạn không thể tự khoá tài khoản của mình.');
        }

        if ($user->status === 'active' && $user->isRole('admin')) {
            $activeAdmins = User::active()->whereHas('role', fn ($q) => $q->where('slug', 'admin'))->count();
            if ($activeAdmins <= 1) {
                return back()->with('error', 'Không thể khoá quản trị viên hoạt động cuối cùng.');
            }
        }

        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);

        AuditLogger::log('user.toggle', 'users', "Đổi trạng thái tài khoản {$user->name} thành {$user->status}", $user);

        return back()->with('success', 'Đã '.($user->status === 'active' ? 'mở khoá' : 'khoá')." tài khoản {$user->name}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        if ($user->is_protected) {
            return back()->with('error', $this->protectedMessage($user));
        }

        if ($user->id === $actor->id) {
            return back()->with('error', 'Bạn không thể xoá tài khoản của chính mình.');
        }

        if ($user->status === 'active' && $user->isRole('admin')) {
            $activeAdmins = User::active()->whereHas('role', fn ($q) => $q->where('slug', 'admin'))->count();
            if ($activeAdmins <= 1) {
                return back()->with('error', 'Không thể xoá quản trị viên hoạt động cuối cùng.');
            }
        }

        if ($user->leaveRequests()->exists() || $user->overtimeRequests()->exists()) {
            return back()->with('error', "Không thể xoá {$user->name} vì đã phát sinh đơn nghỉ/OT. Hãy khoá tài khoản thay vì xoá.");
        }

        if ($user->approvals()->exists()) {
            return back()->with('error', "Không thể xoá {$user->name} vì đã tham gia luồng duyệt đơn của người khác. Hãy khoá tài khoản thay vì xoá.");
        }

        Department::where('manager_id', $user->id)->update(['manager_id' => null]);

        $name = $user->name;
        $user->delete();

        AuditLogger::log('user.delete', 'users', "Xoá tài khoản {$name}");

        return back()->with('success', "Đã xoá tài khoản {$name}.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        if ($user->is_protected) {
            return back()->with('error', $this->protectedMessage($user));
        }

        $data = $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'new_password.required' => 'Vui lòng nhập mật khẩu mới.',
            'new_password.min' => 'Mật khẩu tối thiểu 8 ký tự.',
            'new_password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        $user->update(['password' => $data['new_password']]);

        AuditLogger::log('user.reset_password', 'users', "Đặt lại mật khẩu cho {$user->name}", $user);

        return back()->with('success', "Đã đặt lại mật khẩu cho {$user->name}.");
    }

    protected function assignableRoles(User $actor): Collection
    {
        $roles = Role::orderBy('id')->get();

        if ($actor->isRole('admin')) {
            return $roles;
        }

        return $roles->filter(fn (Role $role) => $role->slug !== 'admin')->values();
    }

    protected function authorizeTarget(User $actor, User $target): void
    {
        if ($actor->isRole('admin')) {
            return;
        }

        if ($target->isRole('admin')) {
            abort(403, 'Chỉ quản trị hệ thống mới có thể thao tác trên tài khoản admin.');
        }
    }

    protected function protectedMessage(User $user): string
    {
        return "Tài khoản {$user->name} là tài khoản hệ thống được bảo vệ: không thể sửa, khoá, đặt lại mật khẩu hoặc xoá.";
    }

    protected function generateImportEmail(string $name): string
    {
        $words = array_values(array_filter(explode(' ', $this->normalizeText($name)), fn ($word) => $word !== ''));
        $given = $words ? end($words) : 'nhanvien';
        $initials = '';

        foreach (array_slice($words, 0, -1) as $word) {
            $initials .= $word[0];
        }

        $local = $given.($initials !== '' ? '.'.$initials : '');
        $candidate = $local;
        $suffix = 0;

        while (User::whereRaw('LOWER(email) = ?', [$candidate.'@everland.vn'])->exists()) {
            $suffix++;
            $candidate = $local.$suffix;
        }

        return $candidate.'@everland.vn';
    }

    protected function importColumns(array $headers): array
    {
        $aliases = [
            'name' => ['ho va ten', 'ho ten', 'ten', 'ten nhan vien', 'nhan vien', 'full name', 'name'],
            'employee_code' => ['ma nhan vien', 'ma nv', 'ma so nhan vien', 'employee code', 'code'],
            'role' => ['vai tro', 'role', 'vai tro he thong'],
            'department' => ['phong ban', 'bo phan', 'don vi', 'department'],
            'position' => ['chuc danh', 'chuc vu', 'position'],
            'phone' => ['so dien thoai', 'dien thoai', 'sdt', 'phone'],
            'join_date' => ['ngay vao lam', 'ngay vao', 'join date'],
        ];

        $lookup = [];

        foreach ($headers as $index => $header) {
            $key = $this->normalizeText(is_scalar($header) ? (string) $header : null);

            if ($key !== '') {
                $lookup[$key] = $index;
            }
        }

        $columns = [];

        foreach ($aliases as $field => $labels) {
            foreach ($labels as $label) {
                if (array_key_exists($label, $lookup)) {
                    $columns[$field] = $lookup[$label];
                    break;
                }
            }
        }

        return $columns;
    }

    protected function normalizeText(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            $value = $normalized === false ? $value : $normalized;
        }

        $value = preg_replace('/\p{Mn}+/u', '', $value) ?? $value;

        // iconv on Windows drops diacritics unreliably, so fold Vietnamese letters explicitly.
        $map = [
            'a' => 'àáạảãâầấậẩẫăằắặẳẵäå',
            'e' => 'èéẹẻẽêềếệểễë',
            'i' => 'ìíịỉĩî',
            'o' => 'òóọỏõôồốộổỗơờớợởỡö',
            'u' => 'ùúụủũưừứựửữûü',
            'y' => 'ỳýỵỷỹ',
            'd' => 'đ',
            'c' => 'ç',
        ];

        $search = [];
        $replace = [];

        foreach ($map as $ascii => $letters) {
            foreach (mb_str_split($letters) as $letter) {
                $search[] = $letter;
                $replace[] = $ascii;
            }
        }

        $value = str_replace($search, $replace, $value);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
    }

    protected function importString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return floor($value) == $value ? number_format($value, 0, '', '') : (string) $value;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function isEmptyImportRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function parseImportDate($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'm/d/Y', 'd/m/Y H:i:s', 'Y-m-d H:i:s'] as $format) {
            if (! Carbon::hasFormat($value, $format)) {
                continue;
            }

            try {
                return Carbon::createFromFormat('!'.$format, $value)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    protected function managerOptions(): Collection
    {
        return User::active()
            ->orderBy('name')
            ->get();
    }

    protected function nextEmployeeCode(): string
    {
        $number = User::count() + 1;

        do {
            $code = 'EVG-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while (User::where('employee_code', $code)->exists());

        return $code;
    }
}
