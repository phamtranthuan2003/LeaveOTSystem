@extends('layouts.app')

@section('title', 'Chi tiết đơn nghỉ phép')
@section('subtitle', $leave->code . ' · ' . $leave->leaveType?->name)

@section('actions')
    <a href="{{ route('leave.index') }}" class="btn btn-outline btn-sm">
        <svg class="icon"><use href="#i-arrow-left"></use></svg>
        Danh sách
    </a>
    @if ($canCancel)
        <form method="POST" action="{{ route('leave.cancel', $leave) }}" data-confirm="Bạn chắc chắn muốn huỷ đơn {{ $leave->code }}?">
            @csrf
            <button type="submit" class="btn btn-danger-soft btn-sm">
                <svg class="icon"><use href="#i-x"></use></svg>
                Huỷ đơn
            </button>
        </form>
    @endif
    @can('records.purge')
        <form method="POST" action="{{ route('leave.destroy', $leave) }}" data-confirm="Xoá vĩnh viễn đơn {{ $leave->code }}? Quỹ phép đã trừ (nếu có) sẽ được hoàn lại.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">
                <svg class="icon"><use href="#i-trash"></use></svg>
                Xoá đơn
            </button>
        </form>
    @endcan
@endsection

@section('content')
    @php
        $meta = status_meta($leave->status);
        $rejection = $leave->approvals->firstWhere('status', 'rejected');
        $sortedApprovals = $leave->approvals->sortBy('step');
        $isOwner = auth()->id() === $leave->user_id;
    @endphp

    @if ($leave->status === 'rejected')
        <div class="alert alert-error">
            <svg class="icon"><use href="#i-alert"></use></svg>
            <div>
                <strong>Đơn đã bị từ chối{{ $rejection?->approver ? ' bởi ' . $rejection->approver->name : '' }}.</strong>
                @if ($rejection?->note)<div style="margin-top:4px">Lý do: {{ $rejection->note }}</div>@endif
            </div>
        </div>
    @elseif ($leave->status === 'cancelled')
        <div class="alert alert-info">
            <svg class="icon"><use href="#i-info"></use></svg>
            <div>Đơn đã được huỷ. Quỹ phép (nếu đã trừ) đã được hoàn lại.</div>
        </div>
    @elseif ($canApprove)
        <div class="alert alert-warning">
            <svg class="icon"><use href="#i-clock"></use></svg>
            <div>
                <strong>Đơn đang chờ bạn phê duyệt ở bước {{ $leave->current_step }}/{{ $leave->total_steps }}.</strong>
                Vui lòng kiểm tra thông tin và quyết định bên dưới.
            </div>
        </div>
    @endif

    <div class="grid grid-2-1" style="align-items: start">
        <div class="grid" style="align-content: start">
            <div class="card">
                <div class="card-head">
                    <div class="cell-user">
                        <span class="avatar avatar-lg">
                            @if ($leave->user?->avatarUrl())
                                <img src="{{ $leave->user->avatarUrl() }}" alt="{{ $leave->user->name }}">
                            @else
                                {{ initials($leave->user?->name) }}
                            @endif
                        </span>
                        <div>
                            <div class="card-title">{{ $leave->user?->name }}</div>
                            <div class="card-sub">{{ $leave->user?->position }} · {{ $leave->user?->department?->name }}</div>
                            <div class="cell-sub">{{ $leave->user?->email }} · {{ $leave->user?->employee_code }}</div>
                        </div>
                    </div>
                    <span class="badge badge-{{ $meta['tone'] }} badge-lg">{{ $meta['label'] }}</span>
                </div>
                <div class="card-body">
                    <div class="kv-grid">
                        <div class="kv-item">
                            <div class="k">Mã đơn</div>
                            <div class="v">{{ $leave->code }}</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Loại nghỉ phép</div>
                            <div class="v">
                                <span class="legend-dot" style="display:inline-block;margin-right:6px;background: {{ $leave->leaveType?->color ?? '#2f6bff' }}"></span>
                                {{ $leave->leaveType?->name }}
                            </div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Thời gian nghỉ</div>
                            <div class="v">{{ vn_date($leave->start_date, 'd/m/Y') }} – {{ vn_date($leave->end_date, 'd/m/Y') }}</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Hình thức</div>
                            <div class="v">{{ working_day_label($leave->day_type) }}</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Tổng số ngày</div>
                            <div class="v" style="color: var(--blue-700)">{{ days_label($leave->total_days) }}</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Người bàn giao</div>
                            <div class="v">{{ $leave->handover?->name ?? 'Không có' }}</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Ngày tạo đơn</div>
                            <div class="v">{{ vn_date($leave->created_at, 'd/m/Y H:i') }}</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Cập nhật gần nhất</div>
                            <div class="v">{{ $leave->updated_at?->diffForHumans() }}</div>
                        </div>
                    </div>

                    <hr class="divider">

                    <div class="section-title">Lý do nghỉ</div>
                    <div class="note-box">{{ $leave->reason }}</div>

                    @if ($leave->attachment)
                        <div class="mt-16">
                            <div class="section-title">Tài liệu đính kèm</div>
                            <a href="{{ asset($leave->attachment) }}" target="_blank" rel="noopener" class="chip" style="padding: 9px 12px">
                                <svg class="icon"><use href="#i-clip"></use></svg>
                                {{ basename($leave->attachment) }}
                                <svg class="icon icon-sm"><use href="#i-download"></use></svg>
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-layers"></use></svg> Quy trình duyệt thác nước</div>
                        <div class="card-sub">{{ $leave->current_step ?? 0 }}/{{ $leave->total_steps }} bước · {{ status_meta($leave->status)['label'] }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <ol class="timeline">
                        <li class="timeline-item done">
                            <span class="timeline-dot"><svg class="icon"><use href="#i-user"></use></svg></span>
                            <div class="timeline-title">{{ $leave->user?->name }}</div>
                            <div class="timeline-meta">Tạo đơn · {{ vn_date($leave->created_at, 'd/m/Y H:i') }}</div>
                        </li>
                        @foreach ($sortedApprovals as $approval)
                            @php
                                $isCurrent = $leave->status === 'pending' && (int) $approval->step === (int) $leave->current_step && $approval->status === 'pending';
                                $itemClass = $approval->status === 'approved' ? 'done' : ($approval->status === 'rejected' ? 'rejected' : ($isCurrent ? 'current' : 'skipped'));
                            @endphp
                            <li class="timeline-item {{ $itemClass }}">
                                <span class="timeline-dot">
                                    @if ($approval->status === 'approved')
                                        <svg class="icon"><use href="#i-check"></use></svg>
                                    @elseif ($approval->status === 'rejected')
                                        <svg class="icon"><use href="#i-x"></use></svg>
                                    @else
                                        {{ $approval->step }}
                                    @endif
                                </span>
                                <div class="timeline-title">
                                    {{ $approval->approver && $approval->approver->isRole('hr') ? 'Bộ phận HR' : ($approval->approver?->name ?? 'Người duyệt') }}
                                    @if ($isCurrent)
                                        <span class="timeline-wait">Đang chờ duyệt</span>
                                    @elseif ($approval->status === 'approved')
                                        <span class="badge badge-success no-dot" style="margin-left: 6px">Đã duyệt</span>
                                    @elseif ($approval->status === 'rejected')
                                        <span class="badge badge-danger no-dot" style="margin-left: 6px">Từ chối</span>
                                    @elseif ($approval->status === 'skipped')
                                        <span class="badge badge-muted no-dot" style="margin-left: 6px">Bỏ qua</span>
                                    @else
                                        <span class="badge badge-muted no-dot" style="margin-left: 6px">Chờ bước trước</span>
                                    @endif
                                </div>
                                <div class="timeline-meta">
                                    Bước {{ $approval->step }}/{{ $leave->total_steps }}
                                    · {{ $approval->approver?->role?->name ?? ucfirst($approval->approver_role ?? '') }}
                                    @if ($approval->acted_at) · {{ vn_date($approval->acted_at, 'd/m/Y H:i') }} @endif
                                </div>
                                @if ($approval->note)
                                    <div class="timeline-note">{{ $approval->note }}</div>
                                @endif
                                @if ($approval->acted_by && (int) $approval->acted_by !== (int) $approval->approver_id)
                                    <div class="timeline-note">Duyệt thay bởi {{ $approval->actor?->name ?? 'Quản trị viên' }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>

        <div class="grid" style="align-content: start">
            @if ($canApprove)
                <div class="card" style="border-color: #ffd9a3">
                    <div class="card-head" style="background: #fffaf2">
                        <div>
                            <div class="card-title"><svg class="icon"><use href="#i-check"></use></svg> Phê duyệt đơn</div>
                            <div class="card-sub">Bước {{ $leave->current_step }}/{{ $leave->total_steps }} · {{ $leave->user?->name }}</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('approvals.approve', $currentApproval) }}">
                            @csrf
                            <div class="field">
                                <label for="approve-note">Ghi chú duyệt (không bắt buộc)</label>
                                <textarea id="approve-note" name="note" class="textarea" style="min-height: 76px" maxlength="500" placeholder="Ví dụ: Đồng ý, bàn giao đầy đủ..."></textarea>
                            </div>
                            <div class="form-actions" style="margin-top: 14px; justify-content: stretch">
                                <button type="submit" class="btn btn-success w-full">
                                    <svg class="icon"><use href="#i-check"></use></svg>
                                    Duyệt đơn nghỉ phép
                                </button>
                            </div>
                        </form>
                        @if ($canApproveAll)
                            <form method="POST" action="{{ route('approvals.approve-all', $currentApproval) }}" class="mt-8"
                                  data-confirm="{{ auth()->user()?->isRole('admin')
                                      ? 'Duyệt toàn bộ các bước còn lại của đơn ' . $leave->code . ' (kể cả HR)?'
                                      : 'Duyệt toàn bộ các cấp quản lý của đơn ' . $leave->code . '? HR vẫn sẽ duyệt chốt.' }}">
                                @csrf
                                <button type="submit" class="btn btn-navy btn-block">
                                    <svg class="icon"><use href="#i-layers"></use></svg>
                                    {{ auth()->user()?->isRole('admin') ? 'Duyệt toàn bộ chuỗi' : 'Duyệt toàn bộ các cấp' }}
                                </button>
                            </form>
                        @endif
                        <button type="button" class="btn btn-danger-soft btn-block mt-8" data-modal-open="#reject-modal">
                            <svg class="icon"><use href="#i-x"></use></svg>
                            Từ chối đơn
                        </button>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-wallet"></use></svg> Ảnh hưởng quỹ phép</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="flex items-center justify-between mb-12">
                        <span class="text-sm muted">Số ngày trừ quỹ</span>
                        <span class="strong">{{ days_label($leave->total_days) }}</span>
                    </div>
                    <div class="flex items-center justify-between mb-12">
                        <span class="text-sm muted">Loại phép trừ quỹ</span>
                        <span class="badge badge-{{ $leave->leaveType?->deduct_balance ? 'primary' : 'muted' }} no-dot">
                            {{ $leave->leaveType?->deduct_balance ? 'Có' : 'Không' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between mb-12">
                        <span class="text-sm muted">Đã trừ quỹ</span>
                        <span class="badge badge-{{ $leave->balance_deducted ? 'success' : 'warning' }} no-dot">
                            {{ $leave->balance_deducted ? 'Đã trừ' : 'Chưa trừ' }}
                        </span>
                    </div>
                    <div class="note-box mt-8">
                        Quỹ phép được trừ ngay khi gửi đơn; đơn bị từ chối hoặc huỷ sẽ được hoàn lại.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-user"></use></svg> Thông tin nhân sự</div>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="btn btn-ghost btn-sm" style="display:none">Hồ sơ</a>
                </div>
                <div class="card-body">
                    <div class="meta-row" style="gap: 8px; flex-direction: column">
                        <span>Cấp trên trực tiếp: <b>{{ $leave->user?->manager?->name ?? 'Không có' }}</b></span>
                        <span>Phòng ban: <b>{{ $leave->user?->department?->name ?? '—' }}</b></span>
                        <span>Ngày vào làm: <b>{{ vn_date($leave->user?->join_date, 'd/m/Y') }}</b></span>
                        <span>Người bàn giao: <b>{{ $leave->handover?->name ?? 'Không chỉ định' }}</b></span>
                    </div>
                    <div class="mt-16">
                        <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
                            <svg class="icon"><use href="#i-file"></use></svg>
                            In phiếu nghỉ phép
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($canApprove)
        <div class="modal" id="reject-modal">
            <div class="modal-card">
                <form method="POST" action="{{ route('approvals.reject', $currentApproval) }}">
                    @csrf
                    <div class="modal-head">
                        <div>
                            <h3>Từ chối đơn {{ $leave->code }}</h3>
                            <p>Lý do từ chối sẽ được gửi tới {{ $leave->user?->name }}.</p>
                        </div>
                        <button type="button" class="modal-close" data-modal-close>
                            <svg class="icon"><use href="#i-x"></use></svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="field">
                            <label for="reject-note">Lý do từ chối <span class="req">*</span></label>
                            <textarea id="reject-note" name="note" class="textarea" required minlength="5" maxlength="500" placeholder="Vui lòng nêu rõ lý do từ chối (tối thiểu 5 ký tự)..."></textarea>
                        </div>
                    </div>
                    <div class="modal-foot">
                        <button type="button" class="btn btn-outline" data-modal-close>Đóng</button>
                        <button type="submit" class="btn btn-danger">Xác nhận từ chối</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
