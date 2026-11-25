<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->buildQuery($filters, $request->user());

        $all = $query->get();
        $summary = $this->summarize($all, $filters['tab']);
        $monthly = $this->monthlyBreakdown($all, $filters['tab']);
        $byDepartment = $this->departmentBreakdown($all, $filters['tab']);
        $byType = $filters['tab'] === 'leave' ? $this->typeBreakdown($all) : [];

        $items = (clone $query)->latest()->paginate(12)->withQueryString();

        $departments = Department::orderBy('name')->get();
        $leaveTypes = LeaveType::orderBy('name')->get();
        $years = range(now()->year + 1, now()->year - 3);

        return view('reports.index', compact(
            'filters', 'items', 'summary', 'monthly', 'byDepartment', 'byType',
            'departments', 'leaveTypes', 'years'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->buildQuery($filters, $request->user())->latest()->get();

        $isLeave = $filters['tab'] === 'leave';
        $summary = $this->summarize($rows, $filters['tab']);
        $monthly = $this->monthlyBreakdown($rows, $filters['tab']);
        $byDepartment = $this->departmentBreakdown($rows, $filters['tab']);
        $byType = $isLeave ? $this->typeBreakdown($rows) : [];

        $statusLabels = ['approved' => 'Đã duyệt', 'pending' => 'Chờ duyệt', 'rejected' => 'Từ chối', 'cancelled' => 'Đã huỷ'];
        $cancelled = max(0, $summary['total'] - $summary['approved'] - $summary['pending'] - $summary['rejected']);
        $statusCounts = [
            'approved'  => $summary['approved'],
            'pending'   => $summary['pending'],
            'rejected'  => $summary['rejected'],
            'cancelled' => $cancelled,
        ];

        $departmentName = $filters['department_id']
            ? (Department::find($filters['department_id'])?->name ?? '—')
            : 'Tất cả';
        $leaveTypeName = ($isLeave && $filters['leave_type_id'])
            ? (LeaveType::find($filters['leave_type_id'])?->name ?? '—')
            : 'Tất cả';
        $statusName = $filters['status'] ? ($statusLabels[$filters['status']] ?? '—') : 'Tất cả';
        $period = $filters['month'] ? 'Tháng ' . $filters['month'] . '/' . $filters['year'] : 'Năm ' . $filters['year'];
        $typeTotal = collect($byType)->sum('value');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Everland Leave & OT')
            ->setTitle('Báo cáo ' . ($isLeave ? 'nghỉ phép' : 'làm thêm giờ') . ' năm ' . $filters['year']);

        $sheet = $spreadsheet->getActiveSheet();
        $sheetName = 'Báo cáo';
        $sheet->setTitle($sheetName);

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', $isLeave ? 'BÁO CÁO NGHỈ PHÉP' : 'BÁO CÁO LÀM THÊM GIỜ');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FF1D4ED8');

        $row = 3;
        $infoRows = [
            ['Kỳ báo cáo', $period],
            ['Phòng ban', $departmentName],
            ['Trạng thái', $statusName],
        ];
        if ($isLeave) {
            $infoRows[] = ['Loại phép', $leaveTypeName];
        }
        $infoRows[] = ['Từ khoá', $filters['q'] !== '' ? $filters['q'] : '—'];
        $infoRows[] = ['Xuất lúc', now()->format('d/m/Y H:i')];

        foreach ($infoRows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }

        $sectionTitle = function (int $at, string $text) use ($sheet): void {
            $sheet->mergeCells("A{$at}:F{$at}");
            $sheet->setCellValue("A{$at}", $text);
            $sheet->getStyle("A{$at}")->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle("A{$at}")->getFont()->getColor()->setARGB('FF1D4ED8');
            $sheet->getStyle("A{$at}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE7EEFF');
        };

        $tableHeader = function (int $at, array $values) use ($sheet): void {
            foreach (array_values($values) as $index => $value) {
                $cell = chr(65 + $index) . $at;
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F5FB');
            }
        };

        $row += 1;
        $sectionTitle($row, 'TỔNG QUAN');
        $row++;
        $tableHeader($row, ['Chỉ tiêu', 'Giá trị']);
        $row++;

        $metrics = [
            ['Tổng số đơn', $summary['total']],
            ['Chờ duyệt', $summary['pending']],
            ['Đã duyệt', $summary['approved']],
            ['Từ chối', $summary['rejected']],
            ['Đã huỷ', $statusCounts['cancelled']],
            [$isLeave ? 'Ngày nghỉ đã duyệt' : 'Giờ OT đã duyệt', round((float) $summary['value'], 2)],
        ];
        foreach ($metrics as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }

        $row += 1;
        $sectionTitle($row, 'XU HƯỚNG THEO THÁNG · ' . $filters['year']);
        $row++;
        $tableHeader($row, ['Tháng', $isLeave ? 'Số ngày đã duyệt' : 'Số giờ đã duyệt']);
        $row++;

        $monthlyStart = $row;
        foreach ($monthly['labels'] as $index => $label) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $monthly['values'][$index] ?? 0);
            $row++;
        }
        $monthlyEnd = $row - 1;

        $sheet->setCellValue("A{$row}", 'Tổng cộng');
        $sheet->setCellValue("B{$row}", round(array_sum($monthly['values']), 2));
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
        $row++;

        $row += 1;
        $sectionTitle($row, 'THEO PHÒNG BAN');
        $row++;
        $tableHeader($row, ['Phòng ban', $isLeave ? 'Số ngày' : 'Số giờ', 'Số đơn']);
        $row++;

        $departmentStart = $row;
        foreach ($byDepartment as $item) {
            $sheet->setCellValue("A{$row}", $item['name']);
            $sheet->setCellValue("B{$row}", $item['value']);
            $sheet->setCellValue("C{$row}", $item['count']);
            $row++;
        }
        $departmentEnd = $row - 1;
        if (!$byDepartment) {
            $sheet->setCellValue("A{$row}", 'Chưa có dữ liệu');
            $row++;
        }

        $row += 1;
        $sectionTitle($row, 'PHÂN BỔ TRẠNG THÁI');
        $row++;
        $tableHeader($row, ['Trạng thái', 'Số đơn', 'Tỷ lệ']);
        $row++;

        $statusStart = $row;
        foreach ($statusLabels as $key => $label) {
            $count = $statusCounts[$key];
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $count);
            $sheet->setCellValue("C{$row}", $summary['total'] > 0 ? $count / $summary['total'] : 0);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('0%');
            $row++;
        }
        $statusEnd = $row - 1;

        $row += 1;
        $typeStart = null;
        $typeEnd = null;
        if ($isLeave) {
            $sectionTitle($row, 'THEO LOẠI NGHỈ PHÉP');
            $row++;
            $tableHeader($row, ['Loại nghỉ phép', 'Số ngày', 'Tỷ lệ']);
            $row++;

            $typeStart = $row;
            foreach ($byType as $item) {
                $sheet->setCellValue("A{$row}", $item['name']);
                $sheet->setCellValue("B{$row}", $item['value']);
                $sheet->setCellValue("C{$row}", $typeTotal > 0 ? $item['value'] / $typeTotal : 0);
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('0%');
                $row++;
            }
            $typeEnd = $row - 1;
            if (!$byType) {
                $sheet->setCellValue("A{$row}", 'Chưa có dữ liệu');
                $row++;
            }
        } else {
            $sectionTitle($row, 'QUY ĐỔI HỆ SỐ OT');
            $row++;
            $tableHeader($row, ['Loại ngày', 'Hệ số']);
            $row++;

            foreach ([
                ['Ngày làm việc bình thường', (float) setting('ot_rate_weekday', 1.5)],
                ['Ngày cuối tuần', (float) setting('ot_rate_weekend', 2)],
                ['Ngày lễ', (float) setting('ot_rate_holiday', 3)],
            ] as [$label, $rate]) {
                $sheet->setCellValue("A{$row}", $label);
                $sheet->setCellValue("B{$row}", $rate);
                $row++;
            }
        }

        $row += 1;
        $sectionTitle($row, $isLeave ? 'CHI TIẾT ĐƠN NGHỈ PHÉP' : 'CHI TIẾT ĐƠN OT');
        $row++;

        $headers = $isLeave
            ? ['Mã đơn', 'Mã NV', 'Nhân viên', 'Email', 'Chức vụ', 'Phòng ban', 'Loại nghỉ', 'Hình thức', 'Từ ngày', 'Đến ngày', 'Số ngày', 'Trạng thái', 'Bước duyệt', 'Người bàn giao', 'Lý do', 'Ngày tạo', 'Ngày quyết định']
            : ['Mã đơn', 'Mã NV', 'Nhân viên', 'Email', 'Chức vụ', 'Phòng ban', 'Ngày OT', 'Bắt đầu', 'Kết thúc', 'Số giờ', 'Loại OT', 'Hệ số', 'Công việc / Dự án', 'Lý do', 'Trạng thái', 'Bước duyệt', 'Ngày tạo', 'Ngày quyết định'];

        $tableHeader($row, $headers);
        $detailHeaderRow = $row;
        $row++;

        foreach ($rows as $item) {
            $values = $isLeave
                ? [
                    $item->code,
                    $item->user?->employee_code,
                    $item->user?->name,
                    $item->user?->email,
                    $item->user?->position,
                    $item->user?->department?->name,
                    $item->leaveType?->name,
                    working_day_label($item->day_type),
                    vn_date($item->start_date),
                    vn_date($item->end_date),
                    round((float) $item->total_days, 2),
                    status_meta($item->status)['label'],
                    ($item->status === 'pending' ? $item->current_step : $item->total_steps) . '/' . $item->total_steps,
                    $item->handover?->name ?? '—',
                    $item->reason,
                    $item->created_at?->format('d/m/Y H:i'),
                    $item->decided_at?->format('d/m/Y H:i') ?? '—',
                ]
                : [
                    $item->code,
                    $item->user?->employee_code,
                    $item->user?->name,
                    $item->user?->email,
                    $item->user?->position,
                    $item->user?->department?->name,
                    vn_date($item->ot_date),
                    substr($item->start_time, 0, 5),
                    substr($item->end_time, 0, 5),
                    round((float) $item->hours, 2),
                    ot_type_meta($item->ot_type)['label'],
                    round((float) $item->multiplier, 2),
                    $item->project,
                    $item->reason,
                    status_meta($item->status)['label'],
                    ($item->status === 'pending' ? $item->current_step : $item->total_steps) . '/' . $item->total_steps,
                    $item->created_at?->format('d/m/Y H:i'),
                    $item->decided_at?->format('d/m/Y H:i') ?? '—',
                ];

            foreach (array_values($values) as $index => $value) {
                $sheet->setCellValue(chr(65 + $index) . $row, $value);
            }
            $row++;
        }

        $lastRow = $row - 1;
        if ($lastRow > $detailHeaderRow) {
            $lastColumn = chr(64 + count($headers));
            $sheet->setAutoFilter("A{$detailHeaderRow}:{$lastColumn}{$lastRow}");
        }

        foreach (range('A', 'R') as $column) {
            $sheet->getColumnDimension($column)->setWidth(18);
        }
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(20);

        $range = fn (string $column, int $from, int $to): string => "'{$sheetName}'!{$column}{$from}:{$column}{$to}";

        $this->addBarChart(
            $sheet,
            'monthly',
            'Xu hướng theo tháng · ' . $filters['year'],
            $range('A', $monthlyStart, $monthlyEnd),
            $range('B', $monthlyStart, $monthlyEnd),
            $monthlyEnd - $monthlyStart + 1,
            false,
            '2F6BFF',
            1
        );

        if ($byDepartment) {
            $this->addBarChart(
                $sheet,
                'department',
                'Theo phòng ban',
                $range('A', $departmentStart, $departmentEnd),
                $range('B', $departmentStart, $departmentEnd),
                $departmentEnd - $departmentStart + 1,
                true,
                '2F6BFF',
                2
            );
        }

        $this->addBarChart(
            $sheet,
            'status',
            'Phân bổ trạng thái',
            $range('A', $statusStart, $statusEnd),
            $range('B', $statusStart, $statusEnd),
            $statusEnd - $statusStart + 1,
            true,
            'F0A020',
            3
        );

        if ($isLeave && $typeStart !== null && $typeEnd !== null && $typeEnd >= $typeStart) {
            $this->addBarChart(
                $sheet,
                'leave-type',
                'Theo loại nghỉ phép',
                $range('A', $typeStart, $typeEnd),
                $range('B', $typeStart, $typeEnd),
                $typeEnd - $typeStart + 1,
                true,
                '0F9D68',
                4
            );
        }

        $filename = 'bao-cao-' . $filters['tab'] . '-' . $filters['year'] . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    protected function addBarChart(
        Worksheet $sheet,
        string $name,
        string $title,
        string $categoryRange,
        string $valueRange,
        int $pointCount,
        bool $horizontal,
        string $color,
        int $position
    ): void {
        if ($pointCount < 1) {
            return;
        }

        $categories = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $categoryRange, null, $pointCount);
        $values = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valueRange, '#,##0.0', $pointCount, [], null, $color);

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            [],
            [$categories],
            [$values]
        );
        $series->setPlotDirection($horizontal ? DataSeries::DIRECTION_BAR : DataSeries::DIRECTION_COL);

        $chart = new Chart($name, new Title($title), null, new PlotArea(null, [$series]));

        $columns = [['H', 'L'], ['M', 'Q']];
        $index = $position - 1;
        $topRow = 10 + intdiv($index, 2) * 19;
        [$left, $right] = $columns[$index % 2];
        $chart->setTopLeftPosition($left . $topRow);
        $chart->setBottomRightPosition($right . ($topRow + 17));

        $sheet->addChart($chart);
    }

    protected function filters(Request $request): array
    {
        $tab = $request->input('tab') === 'overtime' ? 'overtime' : 'leave';
        $status = $request->input('status');
        $status = in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true) ? $status : null;

        return [
            'tab'           => $tab,
            'year'          => (int) $request->input('year', now()->year),
            'month'         => $request->filled('month') ? (int) $request->input('month') : null,
            'department_id' => $request->filled('department_id') ? (int) $request->input('department_id') : null,
            'status'        => $status,
            'leave_type_id' => $request->filled('leave_type_id') ? (int) $request->input('leave_type_id') : null,
            'q'             => trim((string) $request->input('q', '')),
        ];
    }

    protected function buildQuery(array $filters, User $user): Builder
    {
        $scopedIds = null;

        if (!$user->hasPermission('leave.view.all') && !$user->hasPermission('ot.view.all')) {
            $scopedIds = array_values(array_unique(array_merge([$user->id], $user->teamIds())));
        }

        if ($filters['tab'] === 'leave') {
            return LeaveRequest::query()
                ->with(['user.department', 'leaveType', 'handover'])
                ->whereYear('start_date', $filters['year'])
                ->when($filters['month'], fn ($q) => $q->whereMonth('start_date', $filters['month']))
                ->when($filters['department_id'], fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('department_id', $filters['department_id'])))
                ->when($filters['status'], fn ($q) => $q->where('status', $filters['status']))
                ->when($filters['leave_type_id'], fn ($q) => $q->where('leave_type_id', $filters['leave_type_id']))
                ->when($filters['q'] !== '', fn ($q) => $q->where(function ($w) use ($filters) {
                    $w->where('code', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', '%' . $filters['q'] . '%')
                            ->orWhere('employee_code', 'like', '%' . $filters['q'] . '%'));
                }))
                ->when($scopedIds, fn ($q) => $q->whereIn('user_id', $scopedIds));
        }

        return OvertimeRequest::query()
            ->with(['user.department'])
            ->whereYear('ot_date', $filters['year'])
            ->when($filters['month'], fn ($q) => $q->whereMonth('ot_date', $filters['month']))
            ->when($filters['department_id'], fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('department_id', $filters['department_id'])))
            ->when($filters['status'], fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['q'] !== '', fn ($q) => $q->where(function ($w) use ($filters) {
                $w->where('code', 'like', '%' . $filters['q'] . '%')
                    ->orWhere('project', 'like', '%' . $filters['q'] . '%')
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('employee_code', 'like', '%' . $filters['q'] . '%'));
            }))
            ->when($scopedIds, fn ($q) => $q->whereIn('user_id', $scopedIds));
    }

    protected function summarize($rows, string $tab): array
    {
        $approved = $rows->where('status', 'approved');

        return [
            'total'    => $rows->count(),
            'pending'  => $rows->where('status', 'pending')->count(),
            'approved' => $approved->count(),
            'rejected' => $rows->where('status', 'rejected')->count(),
            'value'    => $tab === 'leave' ? (float) $approved->sum('total_days') : (float) $approved->sum('hours'),
            'unit'     => $tab === 'leave' ? 'ngày' : 'giờ',
        ];
    }

    protected function monthlyBreakdown($rows, string $tab): array
    {
        $values = array_fill(1, 12, 0.0);

        foreach ($rows->where('status', 'approved') as $row) {
            $date = $tab === 'leave' ? $row->start_date : $row->ot_date;
            if (!$date) {
                continue;
            }
            $month = (int) $date->format('n');
            $values[$month] += $tab === 'leave' ? (float) $row->total_days : (float) $row->hours;
        }

        return [
            'labels' => ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'],
            'values' => array_values(array_map(fn ($v) => round($v, 1), $values)),
        ];
    }

    protected function departmentBreakdown($rows, string $tab): array
    {
        return $rows->where('status', 'approved')
            ->groupBy(fn ($row) => $row->user?->department?->name ?? 'Chưa xếp phòng ban')
            ->map(fn ($group, $name) => [
                'name'  => $name,
                'value' => round((float) ($tab === 'leave' ? $group->sum('total_days') : $group->sum('hours')), 1),
                'count' => $group->count(),
            ])
            ->sortByDesc('value')
            ->values()
            ->take(8)
            ->all();
    }

    protected function typeBreakdown($rows): array
    {
        return $rows->where('status', 'approved')
            ->groupBy(fn ($row) => $row->leaveType?->name ?? 'Khác')
            ->map(fn ($group, $name) => [
                'name'  => $name,
                'value' => round((float) $group->sum('total_days'), 1),
                'color' => $group->first()->leaveType?->color ?? '#2f6bff',
            ])
            ->sortByDesc('value')
            ->values()
            ->all();
    }
}
