<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isRole('admin');
        $canLeave = $user->hasPermission('leave.approve');
        $canOvertime = $user->hasPermission('ot.approve');

        abort_if(! $canLeave && ! $canOvertime, 403, 'Bạn không có quyền duyệt đơn.');

        $typeFilter = $request->input('type', 'all');

        $pendingQuery = Approval::with(['approver.role', 'approvable.user.department', 'approvable.user.role', 'approvable.approvals.approver.role'])
            ->where('status', 'pending')
            ->latest();

        if (! $isAdmin) {
            $pendingQuery->where(function ($q) use ($user) {
                $q->where('approver_id', $user->id);

                if ($user->isRole('hr')) {
                    $q->orWhere('approver_role', 'hr');
                }
            });
        }

        $pending = $pendingQuery->get()
            ->filter(fn (Approval $approval) => $approval->approvable
                && $approval->approvable->status === 'pending'
                && (int) $approval->approvable->current_step === (int) $approval->step
                && ($approval->approvable instanceof LeaveRequest ? $canLeave : $canOvertime))
            ->values();

        $counts = [
            'all' => $pending->count(),
            'leave' => $pending->filter(fn ($a) => $a->approvable instanceof LeaveRequest)->count(),
            'overtime' => $pending->filter(fn ($a) => $a->approvable instanceof OvertimeRequest)->count(),
        ];

        $filtered = $pending->filter(function ($approval) use ($typeFilter) {
            return match ($typeFilter) {
                'leave' => $approval->approvable instanceof LeaveRequest,
                'overtime' => $approval->approvable instanceof OvertimeRequest,
                default => true,
            };
        })->values();

        $perPage = 8;
        $page = Paginator::resolveCurrentPage();
        $items = $filtered->forPage($page, $perPage)->values();

        $approvals = new LengthAwarePaginator($items, $filtered->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);

        $approvedToday = Approval::where('status', 'approved')
            ->whereDate('acted_at', today())
            ->where(fn ($q) => $q->where('approver_id', $user->id)->orWhere('acted_by', $user->id))
            ->count();

        $rejectedToday = Approval::where('status', 'rejected')
            ->whereDate('acted_at', today())
            ->where(fn ($q) => $q->where('approver_id', $user->id)->orWhere('acted_by', $user->id))
            ->count();

        $history = Approval::with(['approvable.user'])
            ->whereIn('status', ['approved', 'rejected'])
            ->where(fn ($q) => $q->where('approver_id', $user->id)->orWhere('acted_by', $user->id))
            ->orderByDesc('acted_at')
            ->limit(8)
            ->get();

        return view('approvals.index', compact(
            'user', 'isAdmin', 'typeFilter', 'counts', 'approvals', 'approvedToday', 'rejectedToday', 'history'
        ));
    }

    public function approve(Request $request, Approval $approval, ApprovalService $service): RedirectResponse
    {
        $approval->loadMissing('approvable');
        $requestable = $approval->approvable;

        if (! $requestable) {
            abort(404);
        }

        $this->authorizeAction($request->user(), $requestable);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $service->approve($approval, $request->user(), $validated['note'] ?? null);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function reject(Request $request, Approval $approval, ApprovalService $service): RedirectResponse
    {
        $approval->loadMissing('approvable');
        $requestable = $approval->approvable;

        if (! $requestable) {
            abort(404);
        }

        $this->authorizeAction($request->user(), $requestable);

        $validated = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'note.required' => 'Vui lòng nhập lý do từ chối để nhân viên được biết.',
            'note.min' => 'Lý do từ chối cần tối thiểu 5 ký tự.',
        ]);

        $result = $service->reject($approval, $request->user(), $validated['note']);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function approveAll(Request $request, Approval $approval, ApprovalService $service): RedirectResponse
    {
        $approval->loadMissing('approvable');
        $requestable = $approval->approvable;

        if (! $requestable) {
            abort(404);
        }

        if (! $service->canApproveAll($requestable, $request->user())) {
            abort(403, 'Chỉ quản lý cấp cao nhất hoặc quản trị viên mới có quyền duyệt toàn bộ đơn.');
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $service->approveAll($requestable, $request->user(), $validated['note'] ?? null);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    protected function authorizeAction($user, $requestable): void
    {
        $permission = $requestable instanceof LeaveRequest ? 'leave.approve' : 'ot.approve';

        if (! $user->hasPermission($permission)) {
            abort(403, 'Bạn không có quyền duyệt loại đơn này.');
        }
    }
}
