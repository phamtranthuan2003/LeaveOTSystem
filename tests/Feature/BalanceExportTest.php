<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class BalanceExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $hr;

    private User $employee;

    private User $otherEmployee;

    private LeaveType $annual;

    private Department $accounting;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['slug' => 'admin', 'name' => 'Quản trị hệ thống']);

        $hrRole = Role::create(['slug' => 'hr', 'name' => 'Nhân sự']);
        $hrRole->permissions()->attach(
            Permission::firstOrCreate(
                ['slug' => 'balance.view.all'],
                ['name' => 'Xem quỹ phép toàn công ty', 'group' => 'Quỹ phép']
            )->id
        );

        $employeeRole = Role::create(['slug' => 'employee', 'name' => 'Nhân viên']);

        $this->admin = User::create([
            'name' => 'Quản trị viên',
            'email' => 'admin@test.vn',
            'password' => 'password123',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $this->hr = User::create([
            'name' => 'Nhân sự',
            'email' => 'hr@test.vn',
            'password' => 'password123',
            'role_id' => $hrRole->id,
            'status' => 'active',
        ]);

        $this->accounting = Department::create(['code' => 'KT', 'name' => 'Phòng Kế toán']);
        $engineering = Department::create(['code' => 'KT2', 'name' => 'Phòng Kỹ thuật']);

        $this->annual = LeaveType::create([
            'code' => 'AL',
            'name' => 'Phép năm',
            'color' => '#2f6bff',
            'default_days' => 12,
            'deduct_balance' => true,
            'requires_attachment' => false,
            'is_paid' => true,
            'active' => true,
        ]);

        LeaveType::create([
            'code' => 'SL',
            'name' => 'Nghỉ ốm',
            'color' => '#f0a020',
            'default_days' => 5,
            'deduct_balance' => false,
            'requires_attachment' => false,
            'is_paid' => true,
            'active' => true,
        ]);

        $this->employee = User::create([
            'name' => 'Nguyễn Văn A',
            'email' => 'a@test.vn',
            'employee_code' => 'EVG-0001',
            'password' => 'password123',
            'role_id' => $employeeRole->id,
            'department_id' => $this->accounting->id,
            'status' => 'active',
        ]);

        $this->otherEmployee = User::create([
            'name' => 'Nguyễn Văn B',
            'email' => 'b@test.vn',
            'employee_code' => 'EVG-0002',
            'password' => 'password123',
            'role_id' => $employeeRole->id,
            'department_id' => $engineering->id,
            'status' => 'active',
        ]);
    }

    public function test_export_contains_balance_rows_totals_and_filters_by_leave_type(): void
    {
        $year = LeaveBalance::cycleYear();

        LeaveBalance::create([
            'user_id' => $this->employee->id,
            'year' => $year,
            'leave_type_id' => $this->annual->id,
            'entitled_days' => 5,
            'carried_over' => 2,
            'used_days' => 1,
            'note' => 'Chuyển từ năm trước',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.balances.export', [
            'year' => $year,
            'leave_type_id' => $this->annual->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('quy-phep-'.$year, $response->headers->get('Content-Disposition'));

        [$spreadsheet, $path] = $this->loadWorkbook($response->streamedContent());

        $cells = $this->flatValues($spreadsheet);

        foreach ([
            'QUỸ PHÉP NĂM '.$year,
            'TỔNG QUAN', 'Ngày được hưởng', 'Đã sử dụng', 'Nhân sự đang hoạt động',
            'Mã nhân viên', 'Họ tên', 'Phòng ban', 'Được hưởng', 'Chuyển năm', 'Đã dùng', 'Còn lại', 'Ghi chú',
            'EVG-0001', 'Nguyễn Văn A', 'Phòng Kế toán', $this->annual->name,
            '7', '2', '1', '6', 'Chuyển từ năm trước',
            'EVG-0002', 'Nguyễn Văn B', 'Tổng cộng', '43', '42',
        ] as $expected) {
            $this->assertContains($expected, $cells, "File quỹ phép thiếu giá trị: {$expected}");
        }

        $this->assertNotContains('Nghỉ ốm', $cells, 'File chỉ được chứa loại phép đang chọn.');

        @unlink($path);
    }

    public function test_export_respects_department_and_keyword_filters(): void
    {
        $year = LeaveBalance::cycleYear();
        $query = ['year' => $year, 'leave_type_id' => $this->annual->id];

        $byDepartment = $this->actingAs($this->admin)->get(route('admin.balances.export', $query + [
            'department_id' => $this->accounting->id,
        ]));
        $byDepartment->assertOk();
        [$spreadsheet, $path] = $this->loadWorkbook($byDepartment->streamedContent());
        $cells = $this->flatValues($spreadsheet);

        $this->assertContains('Nguyễn Văn A', $cells);
        $this->assertNotContains('Nguyễn Văn B', $cells);
        @unlink($path);

        $byKeyword = $this->actingAs($this->admin)->get(route('admin.balances.export', $query + [
            'q' => 'Nguyễn Văn B',
        ]));
        $byKeyword->assertOk();
        [$spreadsheet, $path] = $this->loadWorkbook($byKeyword->streamedContent());
        $cells = $this->flatValues($spreadsheet);

        $this->assertContains('Nguyễn Văn B', $cells);
        $this->assertNotContains('Nguyễn Văn A', $cells);
        @unlink($path);
    }

    public function test_hr_with_permission_can_export_but_employee_cannot(): void
    {
        $query = ['year' => LeaveBalance::cycleYear(), 'leave_type_id' => $this->annual->id];

        $this->actingAs($this->hr)->get(route('admin.balances.export', $query))->assertOk();
        $this->actingAs($this->employee)->get(route('admin.balances.export', $query))->assertForbidden();
    }

    /**
     * @return array{0: Spreadsheet, 1: string}
     */
    private function loadWorkbook(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($path, $content);

        return [IOFactory::createReader('Xlsx')->load($path), $path];
    }

    /**
     * @return string[]
     */
    private function flatValues(Spreadsheet $spreadsheet, string $sheetName = 'Quỹ phép'): array
    {
        $values = [];

        foreach ($spreadsheet->getSheetByName($sheetName)->toArray(null, true, false) as $row) {
            foreach ($row as $value) {
                if (is_scalar($value) && (string) $value !== '') {
                    $values[] = is_float($value) ? (string) round($value, 4) : (string) $value;
                }
            }
        }

        return $values;
    }
}
