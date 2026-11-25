<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;
use ZipArchive;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private Department $department;
    private LeaveType $annual;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['slug' => 'admin', 'name' => 'Quản trị hệ thống']);

        $this->admin = User::create([
            'name'     => 'Quản trị viên',
            'email'    => 'admin@test.vn',
            'password' => 'password123',
            'role_id'  => $adminRole->id,
            'status'   => 'active',
        ]);

        $this->department = Department::create(['code' => 'KT', 'name' => 'Phòng Kế toán']);

        $this->employee = User::create([
            'name'          => 'Nguyễn Văn A',
            'email'         => 'a@test.vn',
            'password'      => 'password123',
            'role_id'       => $adminRole->id,
            'employee_code' => 'EVG-0001',
            'position'      => 'Kế toán viên',
            'department_id' => $this->department->id,
            'status'        => 'active',
        ]);

        $this->annual = LeaveType::create([
            'code'                => 'AL',
            'name'                => 'Phép năm',
            'color'               => '#2f6bff',
            'default_days'        => 12,
            'deduct_balance'      => true,
            'requires_attachment' => false,
            'is_paid'             => true,
            'active'              => true,
        ]);
    }

    public function test_leave_export_is_an_excel_workbook_with_charts_and_full_details(): void
    {
        $approved = $this->makeLeave('approved', 3, now()->startOfYear()->addMonths(10)->addDays(9));

        $this->makeLeave('approved', 1.5, now()->startOfYear()->addMonths(10)->addDays(14));
        $this->makeLeave('pending', 2, now()->startOfYear()->addMonths(10)->addDays(19));
        $this->makeLeave('rejected', 1, now()->startOfYear()->addMonths(10)->addDays(24));
        $this->makeLeave('cancelled', 0.5, now()->startOfYear()->addMonths(10)->addDays(27));

        $response = $this->actingAs($this->admin)->get(route('reports.export', [
            'tab'  => 'leave',
            'year' => now()->year,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('bao-cao-leave-' . now()->year . '.xlsx', $response->headers->get('Content-Disposition'));

        [$spreadsheet, $path] = $this->loadWorkbook($response->streamedContent());

        $this->assertSame(['Báo cáo'], $spreadsheet->getSheetNames());

        $cells = $this->flatValues($spreadsheet);

        foreach ([
            'BÁO CÁO NGHỈ PHÉP',
            'Năm ' . now()->year,
            'TỔNG QUAN',
            'Tổng số đơn', '5',
            'Từ chối', '1',
            'Đã huỷ', '1',
            'XU HƯỚNG THEO THÁNG · ' . now()->year,
            'T11', '4.5',
            'THEO PHÒNG BAN',
            'Phòng Kế toán', '4.5',
            'PHÂN BỔ TRẠNG THÁI',
            'Đã duyệt', '2', '40%',
            'THEO LOẠI NGHỈ PHÉP',
            'Phép năm', '100%',
            'CHI TIẾT ĐƠN NGHỈ PHÉP',
            'Người bàn giao', 'Lý do', 'Ngày quyết định',
            'EVG-0001', 'Kế toán viên',
            $approved->code,
        ] as $expected) {
            $this->assertContains($expected, $cells, "Workbook thiếu giá trị: {$expected}");
        }

        $this->assertSame(4, $this->countCharts($path));

        $charts = $this->chartXml($path);
        $this->assertCount(4, $charts);
        foreach ($charts as $chartXml) {
            $this->assertStringNotContainsString('<c:marker', $chartXml, 'Biểu đồ cột không được chứa marker (Excel sẽ báo lỗi nội dung)');
            $this->assertStringNotContainsString('<c:symbol', $chartXml);
            $this->assertStringContainsString('<a:srgbClr val="', $chartXml, 'Series phải có màu tô hợp lệ');
        }
        $this->assertStringContainsString('<a:srgbClr val="2F6BFF"/>', $charts[0]);

        $zip = new ZipArchive();
        $zip->open($path);
        $sheetRels = (string) $zip->getFromName('xl/worksheets/_rels/sheet1.xml.rels');
        $zip->close();
        $this->assertStringContainsString('drawing', $sheetRels, 'Biểu đồ phải được neo trên sheet dữ liệu');

        @unlink($path);
    }

    public function test_overtime_export_contains_sections_and_full_details(): void
    {
        $ot = OvertimeRequest::create([
            'code'       => 'OT-' . now()->year . '-0001',
            'user_id'    => $this->employee->id,
            'ot_date'    => now()->startOfYear()->addMonths(10)->addDays(9),
            'start_time' => '18:00',
            'end_time'   => '20:00',
            'hours'      => 2,
            'ot_type'    => 'weekend',
            'multiplier' => 2,
            'project'    => 'Dự án Alpha',
            'reason'     => 'Hoàn thiện báo cáo quý',
            'status'     => 'approved',
            'current_step' => 1,
            'total_steps'  => 1,
            'decided_at'   => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.export', [
            'tab'  => 'overtime',
            'year' => now()->year,
        ]));

        $response->assertOk();

        [$spreadsheet, $path] = $this->loadWorkbook($response->streamedContent());

        $cells = $this->flatValues($spreadsheet);

        foreach ([
            'BÁO CÁO LÀM THÊM GIỜ',
            'TỔNG QUAN',
            'Giờ OT đã duyệt',
            'XU HƯỚNG THEO THÁNG · ' . now()->year,
            'THEO PHÒNG BAN',
            'PHÂN BỔ TRẠNG THÁI',
            'QUY ĐỔI HỆ SỐ OT',
            'Ngày cuối tuần',
            'CHI TIẾT ĐƠN OT',
            'Công việc / Dự án',
            'Dự án Alpha',
            $ot->code,
        ] as $expected) {
            $this->assertContains($expected, $cells, "Workbook thiếu giá trị: {$expected}");
        }

        $this->assertSame(3, $this->countCharts($path));

        @unlink($path);
    }

    public function test_detail_export_contains_only_the_request_rows(): void
    {
        $approved = $this->makeLeave('approved', 3, now()->startOfYear()->addMonths(10)->addDays(9));
        $pending = $this->makeLeave('pending', 2, now()->startOfYear()->addMonths(10)->addDays(19));

        $response = $this->actingAs($this->admin)->get(route('reports.export.details', [
            'tab'  => 'leave',
            'year' => now()->year,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('danh-sach-nghi-phep-' . now()->year . '.xlsx', $response->headers->get('Content-Disposition'));

        [$spreadsheet, $path] = $this->loadWorkbook($response->streamedContent());

        $this->assertSame(['Chi tiết'], $spreadsheet->getSheetNames());
        $this->assertSame('Mã đơn', $spreadsheet->getSheetByName('Chi tiết')->getCell('A1')->getValue());

        $cells = $this->flatValues($spreadsheet, 'Chi tiết');

        foreach ([
            'Mã NV', 'Người bàn giao', 'Chức vụ', 'Số ngày',
            $approved->code, $pending->code, 'EVG-0001', 'Kế toán viên',
        ] as $expected) {
            $this->assertContains($expected, $cells, "File danh sách thiếu giá trị: {$expected}");
        }

        foreach (['TỔNG QUAN', 'XU HƯỚNG THEO THÁNG', 'PHÂN BỔ TRẠNG THÁI', 'CHI TIẾT ĐƠN NGHỈ PHÉP'] as $unexpected) {
            $this->assertNotContains($unexpected, $cells, "File danh sách không được chứa phần tổng hợp: {$unexpected}");
        }

        $this->assertSame(0, $this->countCharts($path));

        @unlink($path);
    }

    /**
     * @return array{0: Spreadsheet, 1: string}
     */
    private function loadWorkbook(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx') . '.xlsx';
        file_put_contents($path, $content);

        $spreadsheet = IOFactory::createReader('Xlsx')->load($path);

        return [$spreadsheet, $path];
    }

    /**
     * @return string[]
     */
    private function flatValues(Spreadsheet $spreadsheet, string $sheetName = 'Báo cáo'): array
    {
        $values = [];

        foreach ($spreadsheet->getSheetByName($sheetName)->toArray() as $row) {
            foreach ($row as $value) {
                if (is_scalar($value) && (string) $value !== '') {
                    $values[] = is_float($value) ? (string) round($value, 4) : (string) $value;
                }
            }
        }

        return $values;
    }

    private function countCharts(string $path): int
    {
        return count($this->chartXml($path));
    }

    /**
     * @return string[]
     */
    private function chartXml(string $path): array
    {
        $zip = new ZipArchive();
        $zip->open($path);

        $charts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/charts/chart\d+\.xml$#', $name)) {
                $charts[] = $zip->getFromName($name);
            }
        }

        $zip->close();

        return $charts;
    }

    private function makeLeave(string $status, float $days, $startDate): LeaveRequest
    {
        return LeaveRequest::create([
            'code'          => LeaveRequest::nextCode(),
            'user_id'       => $this->employee->id,
            'leave_type_id' => $this->annual->id,
            'start_date'    => $startDate->toDateString(),
            'end_date'      => $startDate->toDateString(),
            'day_type'      => 'full',
            'total_days'    => $days,
            'reason'        => 'Nghỉ phép kiểm tra xuất báo cáo',
            'status'        => $status,
            'current_step'  => 1,
            'total_steps'   => 2,
            'decided_at'    => in_array($status, ['approved', 'rejected'], true) ? now() : null,
        ]);
    }
}
