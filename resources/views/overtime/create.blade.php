@extends('layouts.app')

@section('title', 'Đăng ký làm thêm giờ')
@section('subtitle', 'Ghi nhận thời gian OT và gửi qua quy trình duyệt thác nước')

@section('actions')
    <a href="{{ route('overtime.index') }}" class="btn btn-outline btn-sm">
        <svg class="icon"><use href="#i-arrow-left"></use></svg>
        Danh sách đơn
    </a>
@endsection

@php
    $holidayDates = $holidays->pluck('holiday_date')->map(fn ($date) => $date->format('Y-m-d'))->values();
    $typeLabels = [
        'weekday' => 'Ngày làm việc bình thường',
        'weekend' => 'Ngày cuối tuần',
        'holiday' => 'Ngày lễ',
    ];
    $formatRate = fn ($rate) => rtrim(rtrim(number_format($rate, 1, ',', '.'), '0'), ',');
@endphp

@section('content')
    <script type="application/json" id="holiday-data">@json($holidayDates)</script>
    <script type="application/json" id="ot-rate-data">@json($rates)</script>

    <form method="POST" action="{{ route('overtime.store') }}">
        @csrf
        <div class="grid grid-2-1" style="align-items: start">
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-clock"></use></svg> Thông tin đăng ký OT</div>
                        <div class="card-sub">Các trường có dấu <span class="req">*</span> là bắt buộc</div>
                    </div>
                    <span class="badge badge-orange no-dot">Tối đa 16 giờ/đơn</span>
                </div>
                <div class="card-body form-card-body">
                    <div class="form-grid">
                        <div class="field">
                            <label for="ot-date">Ngày làm thêm <span class="req">*</span></label>
                            <input id="ot-date" type="date" name="ot_date" class="input @error('ot_date') is-invalid @enderror"
                                   value="{{ old('ot_date', now()->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                            @error('ot_date')<span class="error-text">{{ $message }}</span>@enderror
                        </div>

                        <div class="field">
                            <label for="ot-type-display">Loại ngày OT</label>
                            <div class="input-group">
                                <svg class="icon"><use href="#i-calendar"></use></svg>
                                <input id="ot-type-display" type="text" class="input" readonly tabindex="-1"
                                       value="{{ $typeLabels[$initialType] }} · x{{ $formatRate($initialRate) }}">
                            </div>
                            <span class="help">Tự động xác định theo ngày đã chọn: ngày lễ → cuối tuần → ngày làm việc.</span>
                        </div>

                        <div class="field">
                            <label for="ot-start">Giờ bắt đầu <span class="req">*</span></label>
                            <input id="ot-start" type="time" name="start_time" class="input @error('start_time') is-invalid @enderror"
                                   value="{{ old('start_time', '17:30') }}" required>
                            @error('start_time')<span class="error-text">{{ $message }}</span>@enderror
                        </div>

                        <div class="field">
                            <label for="ot-end">Giờ kết thúc <span class="req">*</span></label>
                            <input id="ot-end" type="time" name="end_time" class="input @error('end_time') is-invalid @enderror"
                                   value="{{ old('end_time', '19:30') }}" required>
                            @error('end_time')<span class="error-text">{{ $message }}</span>@enderror
                        </div>

                        <div class="field">
                            <label for="ot-hours">Tổng giờ OT (dự kiến)</label>
                            <div class="input-group">
                                <svg class="icon"><use href="#i-clock"></use></svg>
                                <input id="ot-hours" type="text" class="input" placeholder="Tự động tính" readonly tabindex="-1">
                            </div>
                            <span class="help">Nếu giờ kết thúc nhỏ hơn giờ bắt đầu, đơn được tính là OT qua đêm (kết thúc vào hôm sau). Tối đa 16 giờ/đơn.</span>
                        </div>

                        <div class="field">
                            <label for="project">Dự án / Bộ phận liên quan</label>
                            <input id="project" name="project" type="text" class="input" maxlength="150"
                                   value="{{ old('project') }}" placeholder="Ví dụ: Công trình Everland Central">
                            @error('project')<span class="error-text">{{ $message }}</span>@enderror
                        </div>

                        <div class="field span-2">
                            <label for="reason">Nội dung công việc <span class="req">*</span></label>
                            <textarea id="reason" name="reason" class="textarea @error('reason') is-invalid @enderror" required
                                      placeholder="Mô tả công việc làm thêm giờ, kết quả dự kiến...">{{ old('reason') }}</textarea>
                            @error('reason')<span class="error-text">{{ $message }}</span>@enderror
                            <span class="help">Tối thiểu 10 ký tự, tối đa 1.000 ký tự.</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('overtime.index') }}" class="btn btn-outline">Huỷ bỏ</a>
                        <button type="submit" class="btn btn-orange">
                            <svg class="icon"><use href="#i-send"></use></svg>
                            Gửi đơn OT
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid" style="align-content: start">
                <div class="card">
                    <div class="card-head">
                        <div>
                            <div class="card-title"><svg class="icon"><use href="#i-trend"></use></svg> Tóm tắt OT</div>
                            <div class="card-sub">Hệ số theo cấu hình HR</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="highlight-box">
                            <div class="flex justify-between items-end">
                                <div>
                                    <div class="text-xs muted strong" style="text-transform: uppercase; letter-spacing: .07em">Thời gian OT</div>
                                    <div style="font-size: 28px; font-weight: 800; color: var(--navy-900); line-height: 1.2">
                                        <span id="ot-summary-hours">—</span>
                                        <span class="text-sm muted strong" style="font-size: 13px">giờ</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs muted">Hệ số</div>
                                    <div class="strong" style="color: var(--orange-600); font-size: 18px" id="ot-summary-rate">x{{ $formatRate($initialRate) }}</div>
                                </div>
                            </div>
                            <div class="divider" style="margin: 12px 0"></div>
                            <div class="flex justify-between text-sm">
                                <span class="muted">Loại ngày</span>
                                <span class="strong" id="ot-summary-type">{{ $typeLabels[$initialType] }}</span>
                            </div>
                        </div>

                        <div class="ot-summary">
                            <div class="os-item">Ngày thường<b>x{{ $formatRate($rates['weekday']) }}</b></div>
                            <div class="os-item">Cuối tuần<b>x{{ $formatRate($rates['weekend']) }}</b></div>
                            <div class="os-item">Ngày lễ<b>x{{ $formatRate($rates['holiday']) }}</b></div>
                        </div>

                        <div class="note-box mt-16">
                            <svg class="icon icon-sm" style="color: var(--blue-600)"><use href="#i-info"></use></svg>
                            OT chỉ được ghi nhận khi đơn được duyệt hoàn tất. Hệ số áp dụng theo loại ngày và cấu hình của công ty.
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <div>
                            <div class="card-title"><svg class="icon"><use href="#i-layers"></use></svg> Quy trình duyệt dự kiến</div>
                            <div class="card-sub">Theo cấu trúc quản lý thác nước</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <ol class="timeline">
                            <li class="timeline-item done">
                                <span class="timeline-dot"><svg class="icon"><use href="#i-user"></use></svg></span>
                                <div class="timeline-title">{{ $user->name }}</div>
                                <div class="timeline-meta">Người tạo đơn · {{ $user->position ?: 'Nhân viên' }}</div>
                            </li>
                            @forelse ($approvalChain as $index => $approver)
                                <li class="timeline-item current">
                                    <span class="timeline-dot">{{ $index + 1 }}</span>
                                    <div class="timeline-title">{{ $approver->isRole('hr') ? 'Bộ phận HR' : $approver->name }}</div>
                                    <div class="timeline-meta">{{ $approver->role?->name }} · Bước {{ $index + 1 }}</div>
                                </li>
                            @empty
                                <li class="timeline-item rejected">
                                    <span class="timeline-dot"><svg class="icon"><use href="#i-alert"></use></svg></span>
                                    <div class="timeline-title">Chưa tìm thấy người duyệt</div>
                                    <div class="timeline-meta">Liên hệ HR trước khi gửi đơn.</div>
                                </li>
                            @endforelse
                        </ol>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <div>
                            <div class="card-title"><svg class="icon"><use href="#i-calendar"></use></svg> Ngày lễ gần nhất</div>
                        </div>
                    </div>
                    <div class="card-body">
                        @php $upcoming = $holidays->filter(fn ($h) => $h->holiday_date->gte(today()))->take(5); @endphp
                        @forelse ($upcoming as $holiday)
                            <div class="flex items-center gap-10" style="padding: 7px 0; border-bottom: 1px dashed var(--line-soft)">
                                <span class="legend-dot" style="background: var(--orange-500)"></span>
                                <span class="text-sm strong">{{ $holiday->name }}</span>
                                <span class="text-xs muted ml-auto">{{ vn_date($holiday->holiday_date, 'd/m/Y') }}</span>
                            </div>
                        @empty
                            <p class="text-sm muted mb-0">Không có ngày lễ sắp tới.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
