<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Role $employeeRole;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['slug' => 'admin', 'name' => 'Quản trị hệ thống']);
        $this->employeeRole = Role::create(['slug' => 'employee', 'name' => 'Nhân viên']);

        $this->department = Department::create([
            'code' => 'KD',
            'name' => 'Phòng Kinh doanh',
            'level' => 1,
            'active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Quản trị viên',
            'email' => 'admin@test.vn',
            'password' => 'password123',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);
    }

    private function csv(array $rows): UploadedFile
    {
        $header = ['Họ và tên', 'Mã nhân viên', 'Vai trò', 'Phòng ban', 'Chức danh', 'Số điện thoại', 'Ngày vào làm'];

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $header);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('nhan-su.csv', $content);
    }

    public function test_admin_can_import_employee_with_auto_email_and_default_password(): void
    {
        $file = $this->csv([
            ['Phạm Trần Thuận', 'EVG-0200', 'Nhân viên', 'Phòng Kinh doanh', 'Chuyên viên', '0912345678', '01/03/2026'],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.import'), ['file' => $file])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success')
            ->assertSessionMissing('import_errors');

        $user = User::where('email', 'thuan.pt@everland.vn')->first();

        $this->assertNotNull($user);
        $this->assertSame('Phạm Trần Thuận', $user->name);
        $this->assertSame('EVG-0200', $user->employee_code);
        $this->assertSame($this->employeeRole->id, (int) $user->role_id);
        $this->assertSame($this->department->id, (int) $user->department_id);
        $this->assertSame('Chuyên viên', $user->position);
        $this->assertSame('0912345678', $user->phone);
        $this->assertSame('2026-03-01', $user->join_date->format('Y-m-d'));
        $this->assertSame('active', $user->status);
        $this->assertNull($user->manager_id);
        $this->assertNull($user->date_of_birth);
        $this->assertNull($user->address);
        $this->assertTrue(Hash::check('123456789', $user->password));
    }

    public function test_duplicate_names_get_unique_emails(): void
    {
        $file = $this->csv([
            ['Phạm Trần Thuận', 'EVG-0200', 'Nhân viên', 'Phòng Kinh doanh', '', '', ''],
            ['Phạm Trần Thuận', 'EVG-0201', 'Nhân viên', 'Phòng Kinh doanh', '', '', ''],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.import'), ['file' => $file])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'thuan.pt@everland.vn', 'employee_code' => 'EVG-0200']);
        $this->assertDatabaseHas('users', ['email' => 'thuan.pt1@everland.vn', 'employee_code' => 'EVG-0201']);
    }

    public function test_import_reports_rows_with_invalid_role_or_department(): void
    {
        $file = $this->csv([
            ['Người Sai Vai Trò', '', 'Vai trò không có', 'Phòng Kinh doanh', '', '', ''],
            ['Người Sai Phòng Ban', '', 'Nhân viên', 'Phòng Không Có', '', '', ''],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.import'), ['file' => $file])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error')
            ->assertSessionHas('import_errors');

        $errors = session('import_errors');

        $this->assertCount(2, $errors);
        $this->assertStringContainsString('Dòng 2', $errors[0]);
        $this->assertStringContainsString('vai trò', $errors[0]);
        $this->assertStringContainsString('Dòng 3', $errors[1]);
        $this->assertStringContainsString('phòng ban', $errors[1]);
        $this->assertSame(1, User::count());
    }

    public function test_import_template_has_new_columns_and_dropdowns(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.import.template'));

        $response->assertOk();
        $this->assertStringContainsString('mau-import-nhan-su.xlsx', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $workbook = (new XlsxReader)->load($path);
        $sheet = $workbook->getSheetByName('Nhân sự');

        $headers = $sheet->rangeToArray('A1:H1')[0];

        $this->assertSame(
            ['Họ và tên', 'Mã nhân viên', 'Vai trò', 'Phòng ban', 'Chức danh', 'Số điện thoại', 'Ngày vào làm', null],
            $headers
        );

        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getCell('C2')->getDataValidation()->getType());
        $this->assertSame(DataValidation::TYPE_LIST, $sheet->getCell('D2')->getDataValidation()->getType());
        $this->assertNotNull($workbook->getSheetByName('Danh mục'));

        unlink($path);
    }

    public function test_employee_cannot_import_users(): void
    {
        $employee = User::create([
            'name' => 'Nhân viên',
            'email' => 'nhanvien@test.vn',
            'password' => 'password123',
            'role_id' => $this->employeeRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($employee)
            ->post(route('admin.users.import'), ['file' => $this->csv([['X', '', 'Nhân viên', '', '', '', '']])])
            ->assertForbidden();

        $this->assertSame(2, User::count());
    }
}
