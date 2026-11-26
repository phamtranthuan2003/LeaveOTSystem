@extends('layouts.app')

@section('title', 'Duyệt đơn')
@section('subtitle', $isAdmin
    ? 'Quản trị viên: xem và phê duyệt bất kỳ đơn nào đang chờ trong toàn công ty'
    : 'Phê duyệt nghỉ phép và OT theo đúng bước trong quy trình thác nước')

@section('content')
    @php
        $tabs = [
            'all'      => ['label' => 'Tất cả', 'count' => $counts['all']],
            'leave'    => ['label' => 'Nghỉ phép', 'count' => $counts['leave']],
            'overtime' => ['label' => 'Làm thêm giờ', 'count' => $counts['overtime']],
        ];
    @endphp

    <div class="info-strip mb-20">
        <div class="info-tile">
            <span class="stat-icon amber"><svg class="icon"><use href="#i-clock"></use></svg></span>
            <div>
                <div class="it-value">{{ $counts['all'] }}</div>
                <div class="it-label">{{ $isAdmin ? 'Đơn chờ duyệt toàn công ty' : 'Đơn đang chờ bạn duyệt' }}</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon green"><svg class="icon"><use href="#i-check"></use></svg></span>
            <div>
                <div class="it-value">{{ $approvedToday }}</div>
                <div class="it-label">Đã duyệt hôm nay</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon red"><svg class="icon"><use href="#i-x"></use></svg></span>
            <div>
                <div class="it-value">{{ $rejectedToday }}</div>
                <div class="it-label">Đã từ chối hôm nay</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon info"><svg class="icon"><use href="#i-calendar"></use></svg></span>
            <div>
                <div class="it-value">{{ $counts['leave'] }}</div>
                <div class="it-label">Đơn nghỉ phép chờ duyệt</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon purple"><svg class="icon"><use href="#i-briefcase"></use></svg></span>
            <div>
                <div class="it-value">{{ $counts['overtime'] }}</div>
                <div class="it-label">Đơn OT chờ duyệt</div>
            </div>
        </div>
    </div>

    <div class="tab-link-row">
        <div class="tabs">
            @foreach ($tabs as $key => $tab)
                <a href="{{ request()->fullUrlWithQuery(['type' => $key, 'page' => null]) }}"
                   class="tab {{ $typeFilter === $key ? 'active' : '' }}">
                    {{ $tab['label'] }}
                    <span class="count-pill {{ $key === 'all' && $tab['count'] > 0 ? 'orange' : '' }}">{{ $tab['count'] }}</span>
                </a>
            @endforeach
        </div>
        <span class="text-sm muted">{{ $isAdmin ? 'Hiển thị toàn bộ đơn đang chờ duyệt trong công ty' : 'Hiển thị theo bước duyệt hiện tại của bạn' }}</span>
    </div>

    @forelse ($approvals as $approval)
        @php
            $doc = $approval->approvable;
            $isLeaveDoc = $doc instanceof \App\Models\LeaveRequest;
            $docMeta = status_meta($doc->status);
            $docApprovals = $doc->approvals->sortBy('step');
        @endphp
        <div class="card mb-20">
            <div class="card-body">
                <div class="flex items-start gap-16 flex-wrap">
                    <div class="cell-user" style="min-width: 0">
                        <span class="avatar {{ $isLeaveDoc ? '' : 'orange' }}">
                            @if ($doc->user?->avatarUrl())
                                <img src="{{ $doc->user->avatarUrl() }}" alt="">
                            @else
                                {{ initials($doc->user?->name) }}
                            @endif
                        </span>
                        <div style="min-width: 0">
                            <div style="font-size: 15px; font-weight: 800; color: var(--navy-900)">{{ $doc->user?->name }}</div>
                            <div class="text-xs muted">{{ $doc->user?->position }} · {{ $doc->user?->department?->name }}</div>
                        </div>
                    </div>
                    <div class="flex gap-8 items-center ml-auto flex-wrap">
                        <span class="badge badge-{{ $isLeaveDoc ? 'info' : 'orange' }} no-dot">{{ $isLeaveDoc ? 'Đơn nghỉ phép' : 'Đơn OT' }}</span>
                        <span class="badge badge-warning">Bước {{ $doc->current_step }}/{{ $doc->total_steps }}</span>
                        @if ($isAdmin && $approval->approver_id !== $user->id)
                            <span class="badge badge-muted no-dot">Người duyệt: {{ $approval->approver?->isRole('hr') ? 'Bộ phận HR' : ($approval->approver?->name ?? '—') }}</span>
                        @endif
                        <span class="chip">{{ $doc->code }}</span>
                    </div>
                </div>

                <div class="highlight-box mt-16">
                    @if ($isLeaveDoc)
                        <div class="grid grid-4" style="gap: 12px">
                            <div class="kv-item">
                                <div class="k">Loại nghỉ</div>
                                <div class="v">{{ $doc->leaveType?->name }}</div>
                            </div>
                            <div class="kv-item">
                                <div class="k">Thời gian nghỉ</div>
                                <div class="v">{{ vn_date($doc->start_date, 'd/m') }} – {{ vn_date($doc->end_date, 'd/m/Y') }}</div>
                            </div>
                            <div class="kv-item">
                                <div class="k">Số ngày</div>
                                <div class="v">{{ days_label($doc->total_days) }} · {{ working_day_label($doc->day_type) }}</div>
                            </div>
                            <div class="kv-item">
                                <div class="k">Bàn giao cho</div>
                                <div class="v">{{ $doc->handover?->name ?? 'Không chỉ định' }}</div>
                            </div>
                        </div>
                    @else
                        <div class="grid grid-4" style="gap: 12px">
                            <div class="kv-item">
                                <div class="k">Ngày OT</div>
                                <div class="v">{{ vn_date($doc->ot_date, 'd/m/Y') }}</div>
                            </div>
                            <div class="kv-item">
                                <div class="k">Khung giờ</div>
                                <div class="v">{{ substr($doc->start_time, 0, 5) }} – {{ substr($doc->end_time, 0, 5) }}</div>
                            </div>
                            <div class="kv-item">
                                <div class="k">Số giờ</div>
                                <div class="v">{{ hours_label($doc->hours) }} · x{{ rtrim(rtrim(number_format($doc->multiplier, 1, ',', '.'), '0'), ',') }}</div>
                            </div>
                            <div class="kv-item">
                                <div class="k">Dự án</div>
                                <div class="v">{{ $doc->project ?: 'Không chỉ định' }}</div>
                            </div>
                        </div>
                    @endif
                    <div class="mt-12">
                        <span class="text-xs muted strong" style="text-transform: uppercase; letter-spacing: .06em">Lý do / Nội dung</span>
                        <div class="text-sm" style="margin-top: 3px">{{ $doc->reason }}</div>
                    </div>
                </div>

                <div class="flex items-center gap-12 flex-wrap mt-16">
                    <span class="text-xs muted strong" style="text-transform: uppercase; letter-spacing: .06em">Chuỗi duyệt</span>
                    <div class="chain">
                        @foreach ($docApprovals as $step)
                            @php
                                $isCurrent = (int) $step->step === (int) $doc->current_step && $step->status === 'pending';
                                $stepClass = $step->status === 'approved' ? 'done' : ($step->status === 'rejected' ? 'rejected' : ($isCurrent ? 'current' : ''));
                            @endphp
                            <span class="chain-step {{ $stepClass }}">
                                <span class="avatar" style="width:22px;height:22px;font-size:8px">{{ initials($step->approver?->isRole('hr') ? 'HR' : $step->approver?->name) }}</span>
                                {{ $step->approver?->isRole('hr') ? 'Bộ phận HR' : $step->approver?->name }}
                                @if ($step->status === 'approved') <svg class="icon icon-sm"><use href="#i-check"></use></svg> @endif
                            </span>
                            @if (!$loop->last)
                                <span class="chain-arrow"><svg class="icon"><use href="#i-chevron-right"></use></svg></span>
                            @endif
                        @endforeach
                    </div>
                    <span class="text-xs muted ml-auto">Gửi lúc {{ vn_date($doc->created_at, 'd/m/Y H:i') }}</span>
                </div>
            </div>
            <div class="card-foot flex gap-10 justify-between flex-wrap">
                <div class="flex gap-8">
                    @if ($isLeaveDoc)
                        <a href="{{ route('leave.show', $doc) }}" class="btn btn-outline btn-sm">
                            <svg class="icon"><use href="#i-eye"></use></svg>
                            Xem chi tiết
                        </a>
                    @else
                        <a href="{{ route('overtime.show', $doc) }}" class="btn btn-outline btn-sm">
                            <svg class="icon"><use href="#i-eye"></use></svg>
                            Xem chi tiết
                        </a>
                    @endif
                </div>
                <div class="flex gap-8">
                    @if ($isAdmin || $approval->isFinalManagementStep())
                        <form method="POST" action="{{ route('approvals.approve-all', $approval) }}"
                              data-confirm="{{ $isAdmin
                                  ? 'Duyệt toàn bộ các bước còn lại của ' . $doc->code . ' (kể cả HR)?'
                                  : ($isLeaveDoc
                                      ? 'Duyệt toàn bộ các cấp quản lý của ' . $doc->code . '? HR vẫn sẽ duyệt chốt.'
                                      : 'Duyệt toàn bộ các cấp còn lại và hoàn tất ' . $doc->code . '?') }}">
                            @csrf
                            <button type="submit" class="btn btn-navy btn-sm">
                                <svg class="icon"><use href="#i-layers"></use></svg>
                                {{ $isAdmin ? 'Duyệt toàn bộ' : 'Duyệt toàn bộ các cấp' }}
                            </button>
                        </form>
                    @endif
                    <button type="button" class="btn btn-danger-soft btn-sm" data-modal-open="#reject-{{ $approval->id }}">
                        <svg class="icon"><use href="#i-x"></use></svg>
                        Từ chối
                    </button>
                    <form method="POST" action="{{ route('approvals.approve', $approval) }}"
                          data-confirm="Duyệt {{ $isLeaveDoc ? 'đơn nghỉ phép' : 'đơn OT' }} {{ $doc->code }}?">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <svg class="icon"><use href="#i-check"></use></svg>
                            Phê duyệt
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal" id="reject-{{ $approval->id }}">
            <div class="modal-card">
                <form method="POST" action="{{ route('approvals.reject', $approval) }}">
                    @csrf
                    <div class="modal-head">
                        <div>
                            <h3>Từ chối {{ $doc->code }}</h3>
                            <p>Đơn của {{ $doc->user?->name }} · Bước {{ $doc->current_step }}/{{ $doc->total_steps }}</p>
                        </div>
                        <button type="button" class="modal-close" data-modal-close>
                            <svg class="icon"><use href="#i-x"></use></svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning" style="margin-bottom: 14px">
                            <svg class="icon"><use href="#i-alert"></use></svg>
                            <div>Đơn sẽ bị từ chối và toàn bộ các bước còn lại sẽ dừng. Nhân viên sẽ nhận thông báo kèm lý do.</div>
                        </div>
                        <div class="field">
                            <label for="reject-note-{{ $approval->id }}">Lý do từ chối <span class="req">*</span></label>
                            <textarea id="reject-note-{{ $approval->id }}" name="note" class="textarea" required minlength="5" maxlength="500"
                                      placeholder="Nêu rõ lý do từ chối (tối thiểu 5 ký tự)..."></textarea>
                        </div>
                    </div>
                    <div class="modal-foot">
                        <button type="button" class="btn btn-outline" data-modal-close>Đóng</button>
                        <button type="submit" class="btn btn-danger">Xác nhận từ chối</button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body">
                <div class="empty" style="padding: 54px 20px">
                    <div class="empty-icon"><svg class="icon"><use href="#i-check"></use></svg></div>
                    <strong>{{ $isAdmin ? 'Không có đơn nào chờ duyệt trong công ty' : 'Không có đơn nào chờ bạn duyệt' }}</strong>
                    <p>{{ $isAdmin ? 'Toàn bộ đơn nghỉ phép và OT trong công ty đã được xử lý xong.' : 'Bạn đã xử lý hết đơn trong hàng chờ. Đơn mới sẽ xuất hiện tại đây.' }}</p>
                    <div class="mt-16">
                        <a href="{{ route('calendar.index') }}" class="btn btn-outline btn-sm">
                            <svg class="icon"><use href="#i-calendar"></use></svg>
                            Xem lịch nghỉ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endforelse

    @if ($approvals->hasPages())
        <div class="card">{{ $approvals->links() }}</div>
    @endif

    <div class="card mt-20">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-briefcase"></use></svg> Lịch sử duyệt gần đây</div>
                <div class="card-sub">8 quyết định phê duyệt / từ chối mới nhất của bạn</div>
            </div>
        </div>
        <div class="card-body flush">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Thời điểm</th>
                            <th>Đơn</th>
                            <th>Nhân sự</th>
                            <th>Loại</th>
                            <th>Quyết định</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $item)
                            @php
                                $historyDoc = $item->approvable;
                                $historyIsLeave = $historyDoc instanceof \App\Models\LeaveRequest;
                            @endphp
                            <tr>
                                <td class="text-sm">{{ vn_date($item->acted_at, 'd/m/Y H:i') }}</td>
                                <td>
                                    @if ($historyDoc)
                                        <a href="{{ $historyIsLeave ? route('leave.show', $historyDoc) : route('overtime.show', $historyDoc) }}" class="cell-main">{{ $historyDoc->code }}</a>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $historyDoc?->user?->name ?? '—' }}</td>
                                <td><span class="badge badge-{{ $historyIsLeave ? 'info' : 'orange' }} no-dot">{{ $historyIsLeave ? 'Nghỉ phép' : 'OT' }}</span></td>
                                <td>
                                    <span class="badge badge-{{ $item->status === 'approved' ? 'success' : 'danger' }}">
                                        {{ $item->status === 'approved' ? 'Đã duyệt' : 'Từ chối' }}
                                    </span>
                                </td>
                                <td class="text-sm muted">{{ Str::limit($item->note, 60) ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty" style="padding: 24px">
                                        <strong>Chưa có lịch sử duyệt</strong>
                                        <p>Các quyết định duyệt của bạn sẽ được lưu tại đây.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
