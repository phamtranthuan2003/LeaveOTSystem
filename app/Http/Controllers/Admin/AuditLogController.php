<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->input('module')))
            ->when($request->filled('actor'), fn ($q) => $q->where('user_id', $request->input('actor')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = trim((string) $request->input('q'));
                $q->where(function ($w) use ($keyword) {
                    $w->where('description', 'like', "%{$keyword}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$keyword}%"));
                });
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $modules = AuditLog::query()->distinct()->orderBy('module')->pluck('module');

        $actors = User::query()
            ->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.audit.index', compact('logs', 'modules', 'actors'));
    }

    public function destroy(AuditLog $auditLog): RedirectResponse
    {
        $auditLog->delete();

        return back()->with('success', 'Đã xoá bản ghi nhật ký hoạt động.');
    }

    public function clear(): RedirectResponse
    {
        $deleted = AuditLog::query()->delete();

        return back()->with('success', "Đã xoá toàn bộ {$deleted} bản ghi nhật ký hoạt động.");
    }
}
