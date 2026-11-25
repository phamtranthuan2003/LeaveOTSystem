@extends('layouts.app')

@section('title', 'Chi tiết đơn làm thêm giờ')
@section('subtitle', $overtime->code . ' · ' . vn_date($overtime->ot_date, 'd/m/Y'))

@section('actions')
    <a href="{{ route('overtime.index') }}" class="btn btn-outline btn-sm">
        <svg class="icon"><use href="#i-arrow-left"></use></svg>
        Danh sách
    </a>
    @if ($canCancel)
        <form method="POST" action="{{ route('overtime.cancel', $overtime) }}" data-confirm="Bạn chắc chắn muốn huỷ đơn OT {{ $overtime->code }}?">
            @csrf
            <button type="submit" class="btn btn-danger-soft btn-sm">
                <svg class="icon"><use href="#i-x"></use></svg>
                Huỷ đơn
            </button>
        </form>
    @endif
@endsection

@section('content')
    @php
        $meta = status_meta($overtime->status);
        $typeMeta = ot_type_meta($overtime->ot_type);
        $rejection = $overtime->approvals->firstWhere('status', 'rejected');
        $sortedApprovals = $overtime->approvals->sortBy('step');
    @endphp

    @if ($overtime->status === 'rejected')
        <div class="alert alert-error">
            <svg class="icon"><use href="#i-alert"></use></svg>
            <div>
                <strong>Đơn OT đã bị từ chối{{ $rejection?->approver ? ' bởi ' . $rejection->approver->name : '' }}.</strong>
                @if ($rejection?->note)<div style="margin-top:4px">Lý do: {{ $rejection->note }}</div>@endif
            </div>
        </div>
    @elseif ($overtime->status === 'cancelled')
        <div class="alert alert-info">
            <svg class="icon"><use href="#i-info"></use></svg>
            <div>Đơn OT đã được huỷ.</div>
        </div>
    @elseif ($canApprove)
        <div class="alert alert-warning">
            <svg class="icon"><use href="#i-clock"></use></svg>
            <div>
                <strong>Đơn OT đang chờ bạn phê duyệt ở bước {{ $overtime->current_step }}/{{ $overtime->total_steps }}.</strong>
                Vui lòng kiểm tra khung giờ và nội dung công việc.
            </div>
        </div>
    @endif

    <div class="grid grid-2-1" style="align-items: start">
        <div class="grid" style="align-content: start">
            <div class="card">
                <div class="card-head">
                    <div class="cell-user">
                        <span class="avatar avatar-lg orange">
                            @if ($overtime->user?->avatarUrl())
                                <img src="{{ $overtime->user->avatarUrl() }}" alt="{{ $overtime->user->name }}">
                            @else
                                {{ initials($overtime->user?->name) }}
                            @endif
                        </span>
                        <div>
                            <div class="card-title">{{ $overtime->user?->name }}</div>
                            <div class="card-sub">{{ $overtime->user?->position }} · {{ $overtime->user?->department?->name }}</div>
                            <div class="cell-sub">{{ $overtime->user?->email }} · {{ $overtime->user?->employee_code }}</div>
                        </div>
                    </div>
                    <span class="badge badge-{{ $meta['tone'] }} badge-lg">{{ $meta['label'] }}</span>
                </div>
                <div class="card-body">
                    <div class="kv-grid">
                        <div class="kv-item">
                            <div class="k">Mã đơn</div>
                            <div class="v">{{ $overtime->code }}</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Ngày làm thêm</div>
                            <div class="v">{{ vn_date($overtime->ot_date, 'd/m/Y') }} · {{ $overtime->ot_date->translatedFormat('l') }}</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Khung giờ</div>
                            <div class="v">{{ substr($overtime->start_time, 0, 5) }} – {{ substr($overtime->end_time, 0, 5) }}@if ($overtime->end_time <= $overtime->start_time) <span class="text-xs muted">(+1 ngày)</span>@endif</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Tổng thời gian</div>
                            <div class="v" style="color: var(--orange-600)">{{ hours_label($overtime->hours) }}</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Loại ngày OT</div>
                            <div class="v"><span class="badge badge-{{ $typeMeta['tone'] }}">{{ $typeMeta['label'] }}</span></div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Hệ số áp dụng</div>
                            <div class="v">x{{ rtrim(rtrim(number_format($overtime->multiplier, 1, ',', '.'), '0'), ',') }}</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Dự án / Bộ phận</div>
                            <div class="v">{{ $overtime->project ?: 'Không chỉ định' }}</div>
                        </div>
                        <div class="kv-item">
                            <div class="k">Ngày tạo đơn</div>
                            <div class="v">{{ vn_date($overtime->created_at, 'd/m/Y H:i') }}</div>
                        </div>
                    </div>

                    <hr class="divider">

                    <div class="section-title">Nội dung công việc</div>
                    <div class="note-box">{{ $overtime->reason }}</div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-layers"></use></svg> Quy trình duyệt thác nước</div>
                        <div class="card-sub">{{ $overtime->current_step ?? 0 }}/{{ $overtime->total_steps }} bước · {{ $meta['label'] }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <ol class="timeline">
                        <li class="timeline-item done">
                            <span class="timeline-dot"><svg class="icon"><use href="#i-user"></use></svg></span>
                            <div class="timeline-title">{{ $overtime->user?->name }}</div>
                            <div class="timeline-meta">Tạo đơn · {{ vn_date($overtime->created_at, 'd/m/Y H:i') }}</div>
                        </li>
                        @foreach ($sortedApprovals as $approval)
                            @php
                                $isCurrent = $overtime->status === 'pending' && (int) $approval->step === (int) $overtime->current_step && $approval->status === 'pending';
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
                                    {{ $approval->approver?->name ?? 'Người duyệt' }}
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
                                    Bước {{ $approval->step }}/{{ $overtime->total_steps }}
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
                            <div class="card-title"><svg class="icon"><use href="#i-check"></use></svg> Phê duyệt đơn OT</div>
                            <div class="card-sub">Bước {{ $overtime->current_step }}/{{ $overtime->total_steps }} · {{ $overtime->user?->name }}</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('approvals.approve', $currentApproval) }}">
                            @csrf
                            <div class="field">
                                <label for="approve-note">Ghi chú duyệt (không bắt buộc)</label>
                                <textarea id="approve-note" name="note" class="textarea" style="min-height: 76px" maxlength="500" placeholder="Ví dụ: Đồng ý, đã xác nhận khối lượng công việc..."></textarea>
                            </div>
                            <div class="form-actions" style="margin-top: 14px; justify-content: stretch">
                                <button type="submit" class="btn btn-success w-full">
                                    <svg class="icon"><use href="#i-check"></use></svg>
                                    Duyệt đơn OT
                                </button>
                            </div>
                        </form>
                        @if ($canApproveAll)
                            <form method="POST" action="{{ route('approvals.approve-all', $currentApproval) }}" class="mt-8"
                                  data-confirm="Duyệt toàn bộ các bước còn lại của đơn {{ $overtime->code }}?">
                                @csrf
                                <button type="submit" class="btn btn-navy btn-block">
                                    <svg class="icon"><use href="#i-layers"></use></svg>
                                    Duyệt toàn bộ chuỗi
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
                        <div class="card-title"><svg class="icon"><use href="#i-trend"></use></svg> Thông tin ghi nhận</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="flex items-center justify-between mb-12">
                        <span class="text-sm muted">Số giờ OT</span>
                        <span class="strong">{{ hours_label($overtime->hours) }}</span>
                    </div>
                    <div class="flex items-center justify-between mb-12">
                        <span class="text-sm muted">Hệ số</span>
                        <span class="badge badge-orange no-dot">x{{ rtrim(rtrim(number_format($overtime->multiplier, 1, ',', '.'), '0'), ',') }} · {{ $typeMeta['label'] }}</span>
                    </div>
                    <div class="flex items-center justify-between mb-12">
                        <span class="text-sm muted">Quy đổi giờ hệ số</span>
                        <span class="strong">{{ rtrim(rtrim(number_format($overtime->hours * $overtime->multiplier, 2, ',', '.'), '0'), ',') }} giờ</span>
                    </div>
                    <div class="note-box mt-8">
                        {{ $overtime->ot_type === 'holiday' ? 'Ngày lễ được áp dụng hệ số cao nhất theo quy chế.' : ($overtime->ot_type === 'weekend' ? 'Ngày cuối tuần áp dụng hệ số theo quy chế OT.' : 'Ngày làm việc bình thường áp dụng hệ số cơ bản.') }}
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-user"></use></svg> Thông tin nhân sự</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="meta-row" style="gap: 8px; flex-direction: column">
                        <span>Cấp trên trực tiếp: <b>{{ $overtime->user?->manager?->name ?? 'Không có' }}</b></span>
                        <span>Phòng ban: <b>{{ $overtime->user?->department?->name ?? '—' }}</b></span>
                        <span>Ngày vào làm: <b>{{ vn_date($overtime->user?->join_date, 'd/m/Y') }}</b></span>
                        <span>Mã nhân sự: <b>{{ $overtime->user?->employee_code }}</b></span>
                    </div>
                    <div class="mt-16">
                        <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
                            <svg class="icon"><use href="#i-file"></use></svg>
                            In phiếu OT
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
                            <h3>Từ chối đơn OT {{ $overtime->code }}</h3>
                            <p>Lý do từ chối sẽ được gửi tới {{ $overtime->user?->name }}.</p>
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
