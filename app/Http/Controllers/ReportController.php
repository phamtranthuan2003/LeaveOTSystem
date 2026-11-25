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

        $filename = 'bao-cao-' . $filters['tab'] . '-' . $filters['year'] . '.csv';

        if ($filters['tab'] === 'leave') {
            $headers = ['Mã đơn', 'Nhân viên', 'Mã NV', 'Phòng ban', 'Loại nghỉ', 'Từ ngày', 'Đến ngày', 'Số ngày', 'Trạng thái', 'Ngày tạo'];
        } else {
            $headers = ['Mã đơn', 'Nhân viên', 'Mã NV', 'Phòng ban', 'Ngày OT', 'Bắt đầu', 'Kết thúc', 'Số giờ', 'Loại OT', 'Hệ số', 'Công việc', 'Trạng thái', 'Ngày tạo'];
        }

        return response()->streamDownload(function () use ($rows, $headers, $filters) {
            echo "\xEF\xBB\xBF";

            $out = fopen('php://output', 'w');

            fputcsv($out, $headers);

            foreach ($rows as $row) {
                if ($filters['tab'] === 'leave') {
                    fputcsv($out, [
                        $row->code,
                        $row->user?->name,
                        $row->user?->employee_code,
                        $row->user?->department?->name,
                        $row->leaveType?->name,
                        vn_date($row->start_date),
                        vn_date($row->end_date),
                        $row->total_days,
                        status_meta($row->status)['label'],
                        $row->created_at?->format('d/m/Y H:i'),
                    ]);
                } else {
                    fputcsv($out, [
                        $row->code,
                        $row->user?->name,
                        $row->user?->employee_code,
                        $row->user?->department?->name,
                        vn_date($row->ot_date),
                        substr($row->start_time, 0, 5),
                        substr($row->end_time, 0, 5),
                        $row->hours,
                        ot_type_meta($row->ot_type)['label'],
                        $row->multiplier,
                        $row->project,
                        status_meta($row->status)['label'],
                        $row->created_at?->format('d/m/Y H:i'),
                    ]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
                ->with(['user.department', 'leaveType'])
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
