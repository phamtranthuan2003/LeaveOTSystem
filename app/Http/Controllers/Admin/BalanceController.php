<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceController extends Controller
{
    public function index(Request $request): View
    {
        $currentYear = LeaveBalance::cycleYear();
        $year = (int) $request->input('year', $currentYear);
        if ($year < $currentYear - 3 || $year > $currentYear + 1) {
            $year = $currentYear;
        }

        $leaveTypes = LeaveType::where('deduct_balance', true)->orderBy('id')->get();
        $annual = $leaveTypes->firstWhere('id', LeaveType::annual()?->id) ?? $leaveTypes->first();
        $leaveTypeId = (int) $request->input('leave_type_id', $annual?->id);
        $selectedType = $leaveTypes->firstWhere('id', $leaveTypeId) ?? $annual;

        $userQuery = User::query()
            ->where('status', 'active')
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = trim((string) $request->input('q'));
                $q->where(fn ($w) => $w->where('name', 'like', "%{$keyword}%")
                    ->orWhere('employee_code', 'like', "%{$keyword}%"));
            });

        $allIds = (clone $userQuery)->pluck('id');

        $allBalances = $selectedType
            ? LeaveBalance::where('year', $year)
                ->where('leave_type_id', $selectedType->id)
                ->whereIn('user_id', $allIds)
                ->get()
                ->keyBy('user_id')
            : collect();

        $summary = [
            'entitled' => (float) $allBalances->sum(fn ($b) => $b->totalEntitled()),
            'used' => (float) $allBalances->sum('used_days'),
            'remaining' => (float) $allBalances->sum(fn ($b) => $b->remaining()),
        ];

        $users = $userQuery->with(['department', 'role'])->orderBy('name')->paginate(15)->withQueryString();

        $pageBalances = $selectedType
            ? LeaveBalance::where('year', $year)
                ->where('leave_type_id', $selectedType->id)
                ->whereIn('user_id', $users->pluck('id'))
                ->get()
                ->keyBy('user_id')
            : collect();

        $pageTypeBalances = LeaveBalance::where('year', $year)
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $departments = Department::orderBy('name')->get();
        $years = range($currentYear + 1, $currentYear - 3);

        return view('admin.balances.index', compact(
            'year', 'years', 'leaveTypes', 'selectedType', 'users', 'pageBalances',
            'pageTypeBalances', 'summary', 'departments'
        ));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'entitled_days' => ['required', 'numeric', 'min:0', 'max:60'],
            'carried_over' => ['required', 'numeric', 'min:0', 'max:60'],
            'used_days' => ['required', 'numeric', 'min:0', 'max:120'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'entitled_days.required' => 'Vui lòng nhập số ngày được hưởng.',
        ]);

        $balance = LeaveBalance::updateOrCreate(
            [
                'user_id' => $user->id,
                'year' => $data['year'],
                'leave_type_id' => $data['leave_type_id'],
            ],
            [
                'entitled_days' => $data['entitled_days'],
                'carried_over' => $data['carried_over'],
                'used_days' => $data['used_days'],
                'note' => $data['note'] ?? null,
            ]
        );

        AuditLogger::log('balance.update', 'balances', "Điều chỉnh quỹ phép {$user->name} năm {$data['year']}", $balance, [
            'entitled_days' => $balance->entitled_days,
            'used_days' => $balance->used_days,
        ]);

        return back()->with('success', "Đã cập nhật quỹ phép cho {$user->name}.");
    }

    public function adjust(Request $request, User $user, BalanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'days' => ['required', 'numeric', 'min:-60', 'max:60'],
            'note' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'days.required' => 'Vui lòng nhập số ngày cần cộng hoặc trừ.',
            'days.min' => 'Mỗi lần chỉ được trừ tối đa 60 ngày.',
            'days.max' => 'Mỗi lần chỉ được cộng tối đa 60 ngày.',
            'note.required' => 'Vui lòng nhập lý do điều chỉnh.',
            'note.min' => 'Lý do điều chỉnh cần tối thiểu 3 ký tự.',
        ]);

        $days = round((float) $data['days'], 1);

        if ($days === 0.0) {
            return back()->with('error', 'Số ngày điều chỉnh phải khác 0.');
        }

        $leaveType = LeaveType::where('deduct_balance', true)->findOrFail($data['leave_type_id']);

        $result = $service->adjust($user, $leaveType, (int) $data['year'], $days);

        if ($result === null) {
            return back()->with('error', "Không thể trừ {$this->daysText(abs($days))}: quỹ {$leaveType->name} của {$user->name} không đủ ngày.");
        }

        $balance = $result['balance'];
        $verb = $days > 0 ? 'cộng' : 'trừ';
        $amount = $this->daysText(abs($days));

        AuditLogger::log('balance.adjust', 'balances', "{$verb} {$amount} {$leaveType->name} cho {$user->name} năm {$data['year']}", $balance, [
            'before' => $result['before'],
            'after' => $result['after'],
            'reason' => $data['note'],
        ]);

        AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Quỹ phép đã được cập nhật',
            'body' => "{$request->user()->name} đã {$verb} {$amount} {$leaveType->name} năm {$data['year']}. Quỹ còn lại: {$this->daysText($balance->remaining())}.",
            'link' => route('balances.index'),
            'type' => $days > 0 ? 'success' : 'info',
        ]);

        return back()->with('success', "Đã {$verb} {$amount} {$leaveType->name} cho {$user->name}.");
    }

    public function export(Request $request): StreamedResponse
    {
        $currentYear = LeaveBalance::cycleYear();
        $year = (int) $request->input('year', $currentYear);
        if ($year < $currentYear - 3 || $year > $currentYear + 1) {
            $year = $currentYear;
        }

        $leaveTypes = LeaveType::where('deduct_balance', true)->orderBy('id')->get();
        $annual = $leaveTypes->firstWhere('id', LeaveType::annual()?->id) ?? $leaveTypes->first();
        $leaveTypeId = (int) $request->input('leave_type_id', $annual?->id);
        $selectedType = $leaveTypes->firstWhere('id', $leaveTypeId) ?? $annual;

        $users = User::query()
            ->where('status', 'active')
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = trim((string) $request->input('q'));
                $q->where(fn ($w) => $w->where('name', 'like', "%{$keyword}%")
                    ->orWhere('employee_code', 'like', "%{$keyword}%"));
            })
            ->with('department')
            ->orderBy('name')
            ->get();

        $balances = $selectedType
            ? LeaveBalance::where('year', $year)
                ->where('leave_type_id', $selectedType->id)
                ->whereIn('user_id', $users->pluck('id'))
                ->get()
                ->keyBy('user_id')
            : collect();

        $departmentName = $request->filled('department_id')
            ? (Department::find($request->integer('department_id'))?->name ?? '—')
            : 'Tất cả';

        $rows = [];
        $totals = ['entitled' => 0.0, 'carried' => 0.0, 'used' => 0.0, 'remaining' => 0.0];

        foreach ($users as $employee) {
            $balance = $balances->get($employee->id);
            $entitled = $balance ? $balance->totalEntitled() : (float) ($selectedType?->default_days ?? 0);
            $carried = (float) ($balance?->carried_over ?? 0);
            $used = (float) ($balance?->used_days ?? 0);
            $remaining = $balance ? $balance->remaining() : $entitled;

            $totals['entitled'] += $entitled;
            $totals['carried'] += $carried;
            $totals['used'] += $used;
            $totals['remaining'] += $remaining;

            $rows[] = [
                $employee->employee_code ?: $employee->email,
                $employee->name,
                $employee->department?->name ?? '—',
                $selectedType?->name ?? '—',
                $entitled,
                $carried,
                $used,
                $remaining,
                $balance?->note ?? '',
            ];
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('Everland Leave & OT')
            ->setTitle('Quỹ phép năm '.$year);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Quỹ phép');

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'QUỸ PHÉP NĂM '.$year);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FF1D4ED8');

        $infoRows = [
            ['Loại phép', $selectedType?->name ?? '—'],
            ['Phòng ban', $departmentName],
            ['Từ khoá', $request->filled('q') ? trim((string) $request->input('q')) : '—'],
            ['Số nhân sự', $users->count()],
            ['Xuất lúc', now()->format('d/m/Y H:i')],
            ['Người xuất', $request->user()->name],
        ];

        $row = 3;
        foreach ($infoRows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }

        $row++;
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'TỔNG QUAN');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FF1D4ED8');
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE7EEFF');
        $row++;

        foreach ([
            ['Ngày được hưởng', round($totals['entitled'], 1)],
            ['Đã sử dụng', round($totals['used'], 1)],
            ['Còn lại', round($totals['remaining'], 1)],
            ['Nhân sự đang hoạt động', $users->count()],
        ] as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }
        $sheet->getStyle('B'.($row - 4).':B'.($row - 2))->getNumberFormat()->setFormatCode('0.0');

        $row++;
        $headers = ['Mã nhân viên', 'Họ tên', 'Phòng ban', 'Loại phép', 'Được hưởng', 'Chuyển năm', 'Đã dùng', 'Còn lại', 'Ghi chú'];
        foreach ($headers as $index => $header) {
            $cell = chr(65 + $index).$row;
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F5FB');
        }

        $headerRow = $row;
        $row++;

        foreach ($rows as $values) {
            foreach ($values as $index => $value) {
                $sheet->setCellValue(chr(65 + $index).$row, $value);
            }
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Tổng cộng');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        foreach (['E' => 'entitled', 'F' => 'carried', 'G' => 'used', 'H' => 'remaining'] as $column => $key) {
            $sheet->setCellValue("{$column}{$row}", round($totals[$key], 1));
            $sheet->getStyle("{$column}{$row}")->getFont()->setBold(true);
        }
        $sheet->getStyle("E{$headerRow}:H{$row}")->getNumberFormat()->setFormatCode('0.0');

        if ($row - 1 >= $headerRow) {
            $sheet->setAutoFilter("A{$headerRow}:I".($row - 1));
        }
        $sheet->freezePane('A'.($headerRow + 1));

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setWidth(18);
        }
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(26);
        $sheet->getColumnDimension('C')->setWidth(26);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('I')->setWidth(32);

        $filename = 'quy-phep-'.$year.'-'.Str::slug($selectedType?->name ?? 'tat-ca').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    protected function daysText(float $days): string
    {
        return rtrim(rtrim(number_format($days, 1, ',', '.'), '0'), ',').' ngày';
    }
}
