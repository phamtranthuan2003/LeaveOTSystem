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
@endphp

@section('content')
    <script type="application/json" id="holiday-data">@json($holidayDates)</script>

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
                            <label for="ot-type">Loại ngày OT <span class="req">*</span></label>
                            <select id="ot-day-type" name="ot_type" class="select @error('ot_type') is-invalid @enderror" required>
                                <option value="weekday" data-rate="{{ $rates['weekday'] }}" @selected(old('ot_type', 'weekday') === 'weekday')>Ngày làm việc bình thường (x{{ rtrim(rtrim(number_format($rates['weekday'], 1, ',', '.'), '0'), ',') }})</option>
                                <option value="weekend" data-rate="{{ $rates['weekend'] }}" @selected(old('ot_type') === 'weekend')>Ngày cuối tuần (x{{ rtrim(rtrim(number_format($rates['weekend'], 1, ',', '.'), '0'), ',') }})</option>
                                <option value="holiday" data-rate="{{ $rates['holiday'] }}" @selected(old('ot_type') === 'holiday')>Ngày lễ (x{{ rtrim(rtrim(number_format($rates['holiday'], 1, ',', '.'), '0'), ',') }})</option>
                            </select>
                            @error('ot_type')<span class="error-text">{{ $message }}</span>@enderror
                            <span class="help" id="ot-type-hint">Hệ số OT được xác định khi chọn ngày.</span>
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
                                    <div class="strong" style="color: var(--orange-600); font-size: 18px" id="ot-summary-rate">x{{ rtrim(rtrim(number_format($rates['weekday'], 1, ',', '.'), '0'), ',') }}</div>
                                </div>
                            </div>
                            <div class="divider" style="margin: 12px 0"></div>
                            <div class="flex justify-between text-sm">
                                <span class="muted">Loại ngày</span>
                                <span class="strong" id="ot-summary-type">Ngày làm việc</span>
                            </div>
                        </div>

                        <div class="ot-summary">
                            <div class="os-item">Ngày thường<b>x{{ rtrim(rtrim(number_format($rates['weekday'], 1, ',', '.'), '0'), ',') }}</b></div>
                            <div class="os-item">Cuối tuần<b>x{{ rtrim(rtrim(number_format($rates['weekend'], 1, ',', '.'), '0'), ',') }}</b></div>
                            <div class="os-item">Ngày lễ<b>x{{ rtrim(rtrim(number_format($rates['holiday'], 1, ',', '.'), '0'), ',') }}</b></div>
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
                                    <div class="timeline-title">{{ $approver->name }}</div>
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

@push('scripts')
<script>
    (function () {
        var hoursInput = document.getElementById('ot-hours');
        var typeSelect = document.getElementById('ot-day-type');
        var summaryHours = document.getElementById('ot-summary-hours');
        var summaryRate = document.getElementById('ot-summary-rate');
        var summaryType = document.getElementById('ot-summary-type');
        var typeHint = document.getElementById('ot-type-hint');

        function multi(value) {
            return (Math.round(parseFloat(value || 0) * 100) / 100).toString().replace('.', ',');
        }

        function sync() {
            var hours = parseFloat(hoursInput.value || 0);
            summaryHours.textContent = hours > 0 ? multi(hours) : '—';

            var option = typeSelect.options[typeSelect.selectedIndex];
            var rate = option ? parseFloat(option.dataset.rate || 0) : 0;
            summaryRate.textContent = 'x' + multi(rate);
            summaryType.textContent = option ? option.textContent.replace(/\s*\(x.*\)\s*$/, '') : '—';

            if (hours > 16) {
                summaryHours.style.color = 'var(--red-600)';
                typeHint.textContent = 'Một đơn OT không được vượt quá 16 giờ.';
                typeHint.style.color = 'var(--red-600)';
            } else {
                summaryHours.style.color = '';
                typeHint.style.color = '';
            }
        }

        ['change', 'input'].forEach(function (event) {
            hoursInput.addEventListener(event, sync);
            typeSelect.addEventListener(event, sync);
        });
        document.getElementById('ot-date').addEventListener('change', function () {
            setTimeout(sync, 10);
        });

        // app.js tự động phát hiện loại ngày; đồng bộ tóm tắt sau khi nó chạy.
        setTimeout(sync, 30);
    })();
</script>
@endpush
