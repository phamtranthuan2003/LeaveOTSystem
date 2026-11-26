@extends('layouts.app')

@section('title', 'Tạo đơn nghỉ phép')
@section('subtitle', 'Điền thông tin nghỉ phép và gửi qua quy trình duyệt thác nước')

@section('actions')
    <a href="{{ route('leave.index') }}" class="btn btn-outline btn-sm">
        <svg class="icon"><use href="#i-arrow-left"></use></svg>
        Danh sách đơn
    </a>
@endsection

@php
    $holidayDates = $holidays->pluck('holiday_date')->map(fn ($date) => $date->format('Y-m-d'))->values();
@endphp

@section('content')
    <script type="application/json" id="holiday-data">@json($holidayDates)</script>

    <form method="POST" action="{{ route('leave.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="grid grid-2-1" style="align-items: start">
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-sun"></use></svg> Thông tin đơn nghỉ phép</div>
                        <div class="card-sub">Các trường có dấu <span class="req">*</span> là bắt buộc</div>
                    </div>
                    <span class="badge badge-primary no-dot">Năm {{ $year }}</span>
                </div>
                <div class="card-body form-card-body">
                    <div class="form-grid">
                        <div class="field span-2">
                            <label for="leave_type_id">Loại nghỉ phép <span class="req">*</span></label>
                            <select id="leave_type_id" name="leave_type_id" class="select @error('leave_type_id') is-invalid @enderror" required>
                                <option value="">— Chọn loại nghỉ phép —</option>
                                @foreach ($leaveTypes as $type)
                                    @php
                                        $balance = $balances->get($type->id);
                                        $entitled = $balance ? $balance->totalEntitled() : (float) $type->default_days;
                                        $remaining = $balance ? $balance->remaining() : (float) $type->default_days;
                                    @endphp
                                    <option value="{{ $type->id }}"
                                            data-name="{{ $type->name }}"
                                            data-color="{{ $type->color }}"
                                            data-deduct="{{ $type->deduct_balance ? '1' : '0' }}"
                                            data-requires="{{ $type->requires_attachment ? '1' : '0' }}"
                                            data-remaining="{{ $remaining }}"
                                            data-entitled="{{ $entitled }}"
                                            data-used="{{ $balance?->used_days ?? 0 }}"
                                            @selected(old('leave_type_id') == $type->id)>
                                        {{ $type->name }}@if ($type->deduct_balance) — còn {{ rtrim(rtrim(number_format($remaining, 1, ',', '.'), '0'), ',') }}/{{ rtrim(rtrim(number_format($entitled, 1, ',', '.'), '0'), ',') }} ngày @else — không trừ quỹ @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('leave_type_id')<span class="error-text">{{ $message }}</span>@enderror
                            <span class="help" id="type-help">Chọn loại nghỉ để xem quỹ phép khả dụng.</span>
                        </div>

                        <div class="field">
                            <label for="start_date">Từ ngày <span class="req">*</span></label>
                            <input id="leave-start" type="date" name="start_date" class="input @error('start_date') is-invalid @enderror"
                                   value="{{ old('start_date', now()->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                            @error('start_date')<span class="error-text">{{ $message }}</span>@enderror
                        </div>

                        <div class="field">
                            <label for="end_date">Đến ngày <span class="req">*</span></label>
                            <input id="leave-end" type="date" name="end_date" class="input @error('end_date') is-invalid @enderror"
                                   value="{{ old('end_date', now()->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                            @error('end_date')<span class="error-text">{{ $message }}</span>@enderror
                        </div>

                        <div class="field span-2">
                            <label>Hình thức nghỉ <span class="req">*</span></label>
                            <div class="inline-options">
                                <label class="checkbox-row">
                                    <input type="radio" name="day_type" value="full" id="leave-day-type" @checked(old('day_type', 'full') === 'full')>
                                    Cả ngày
                                </label>
                                <label class="checkbox-row">
                                    <input type="radio" name="day_type" value="morning" @checked(old('day_type') === 'morning')>
                                    Buổi sáng (0,5 ngày)
                                </label>
                                <label class="checkbox-row">
                                    <input type="radio" name="day_type" value="afternoon" @checked(old('day_type') === 'afternoon')>
                                    Buổi chiều (0,5 ngày)
                                </label>
                            </div>
                            <span class="help" id="leave-day-hint">Chọn ngày để tính số ngày nghỉ</span>
                            @error('day_type')<span class="error-text">{{ $message }}</span>@enderror
                        </div>

                        <div class="field">
                            <label for="total_days_preview">Số ngày nghỉ (dự kiến)</label>
                            <div class="input-group">
                                <svg class="icon"><use href="#i-calendar"></use></svg>
                                <input id="leave-total-days" type="text" class="input" value="" readonly tabindex="-1" placeholder="Tự động tính">
                            </div>
                            <span class="help">Tính theo ngày làm việc: T2–T6 cả ngày, T7 buổi sáng (0,5 ngày), trừ Chủ nhật và ngày lễ.</span>
                        </div>

                        <div class="field">
                            <label for="handover_to">Người bàn giao công việc</label>
                            <select id="handover_to" name="handover_to" class="select">
                                <option value="">— Không chọn —</option>
                                @foreach ($handovers as $colleague)
                                    <option value="{{ $colleague->id }}" @selected((string) old('handover_to') === (string) $colleague->id)>
                                        {{ $colleague->name }} · {{ $colleague->position ?: $colleague->department?->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('handover_to')<span class="error-text">{{ $message }}</span>@enderror
                            <span class="help">
                                @if ($handovers->isEmpty())
                                    Chưa có đồng nghiệp cùng phòng ban để chọn bàn giao.
                                @else
                                    Chỉ chọn được đồng nghiệp cùng phòng ban với bạn.
                                @endif
                            </span>
                        </div>

                        <div class="field span-2">
                            <label for="reason">Lý do nghỉ <span class="req">*</span></label>
                            <textarea id="reason" name="reason" class="textarea @error('reason') is-invalid @enderror" required
                                      placeholder="Mô tả lý do nghỉ, kế hoạch bàn giao công việc...">{{ old('reason') }}</textarea>
                            @error('reason')<span class="error-text">{{ $message }}</span>@enderror
                            <span class="help">Tối thiểu 10 ký tự, tối đa 1.000 ký tự.</span>
                        </div>

                        <div class="field span-2">
                            <label for="attachment">Tài liệu đính kèm</label>
                            <div class="upload-zone">
                                <svg class="icon icon-lg"><use href="#i-clip"></use></svg>
                                <div style="flex:1; min-width:0">
                                    <input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="w-full">
                                    <div class="help" style="margin-top:4px">PDF, JPG, PNG, DOC, DOCX · tối đa 5MB.</div>
                                </div>
                            </div>
                            <span class="help" id="attachment-hint" style="display:none; color: var(--red-600); font-weight:600">
                                Loại nghỉ này yêu cầu đính kèm giấy tờ chứng minh.
                            </span>
                            @error('attachment')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('leave.index') }}" class="btn btn-outline">Huỷ bỏ</a>
                        <button type="submit" class="btn btn-primary">
                            <svg class="icon"><use href="#i-send"></use></svg>
                            Gửi đơn duyệt
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid" style="align-content: start">
                <div class="card">
                    <div class="card-head">
                        <div>
                            <div class="card-title"><svg class="icon"><use href="#i-wallet"></use></svg> Quỹ phép khả dụng</div>
                            <div class="card-sub" id="balance-type-label">Chọn loại nghỉ phép</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="highlight-box">
                            <div class="flex items-end justify-between">
                                <div>
                                    <div class="text-xs muted strong" style="text-transform: uppercase; letter-spacing: .07em">Còn lại</div>
                                    <div style="font-size: 28px; font-weight: 800; color: var(--navy-900); line-height: 1.2" id="bp-remaining">—</div>
                                </div>
                                <div class="text-right text-sm muted">
                                    <div>Được hưởng</div>
                                    <div class="strong" style="color: var(--ink)" id="bp-entitled">—</div>
                                </div>
                            </div>
                            <div class="progress mt-12"><span id="bp-bar" style="width: 0%"></span></div>
                            <div class="flex justify-between mt-8 text-xs muted">
                                <span>Đã dùng <b id="bp-used" style="color: var(--ink)">0</b> ngày</span>
                                <span id="bp-note">—</span>
                            </div>
                        </div>
                        <div class="note-box mt-16">
                            <svg class="icon icon-sm" style="color: var(--blue-600)"><use href="#i-info"></use></svg>
                            Quỹ phép được trừ ngay khi gửi đơn. Đơn bị từ chối hoặc huỷ sẽ được hoàn lại số ngày đã trừ.
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
                                    <div class="timeline-title">
                                        {{ $approver->isRole('hr') ? 'Bộ phận HR' : $approver->name }}
                                        @if ($approver->isRole('hr'))
                                            <span class="badge badge-info no-dot" style="margin-left: 4px">HR chốt</span>
                                        @endif
                                    </div>
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
                            <div class="card-title"><svg class="icon"><use href="#i-info"></use></svg> Ngày lễ trong năm {{ $year }}</div>
                        </div>
                    </div>
                    <div class="card-body">
                        @forelse ($holidays->take(5) as $holiday)
                            <div class="flex items-center gap-10" style="padding: 7px 0; border-bottom: 1px dashed var(--line-soft)">
                                <span class="legend-dot" style="background: var(--orange-500)"></span>
                                <span class="text-sm strong">{{ $holiday->name }}</span>
                                <span class="text-xs muted ml-auto">{{ vn_date($holiday->holiday_date, 'd/m/Y') }}</span>
                            </div>
                        @empty
                            <p class="text-sm muted mb-0">Chưa cập nhật ngày lễ cho năm nay.</p>
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
        var typeSelect = document.getElementById('leave_type_id');
        var typeLabel = document.getElementById('balance-type-label');
        var remainingEl = document.getElementById('bp-remaining');
        var entitledEl = document.getElementById('bp-entitled');
        var usedEl = document.getElementById('bp-used');
        var barEl = document.getElementById('bp-bar');
        var noteEl = document.getElementById('bp-note');
        var attachmentHint = document.getElementById('attachment-hint');
        var typeHelp = document.getElementById('type-help');

        function fmt(value) {
            return (Math.round(parseFloat(value || 0) * 100) / 100).toString().replace('.', ',');
        }

        function updateBalance() {
            var option = typeSelect.options[typeSelect.selectedIndex];
            if (!option || !option.value) {
                remainingEl.textContent = '—';
                entitledEl.textContent = '—';
                usedEl.textContent = '0';
                barEl.style.width = '0%';
                noteEl.textContent = '—';
                typeLabel.textContent = 'Chọn loại nghỉ phép';
                attachmentHint.style.display = 'none';
                return;
            }

            var deduct = option.dataset.deduct === '1';
            var remaining = parseFloat(option.dataset.remaining || 0);
            var entitled = parseFloat(option.dataset.entitled || 0);
            var used = parseFloat(option.dataset.used || 0);
            var color = option.dataset.color || '#2f6bff';

            typeLabel.textContent = option.dataset.name;
            usedEl.textContent = fmt(used);
            barEl.style.background = color;

            if (deduct) {
                remainingEl.textContent = fmt(remaining) + ' ngày';
                entitledEl.textContent = fmt(entitled) + ' ngày';
                noteEl.textContent = 'Quỹ phép năm {{ $year }}';
                barEl.style.width = (entitled > 0 ? Math.min(100, (used / entitled) * 100) : 0) + '%';
            } else {
                remainingEl.textContent = 'Không trừ quỹ';
                entitledEl.textContent = 'Không giới hạn';
                noteEl.textContent = 'Loại nghỉ không trừ quỹ phép';
                barEl.style.width = '0%';
                usedEl.textContent = '0';
            }

            attachmentHint.style.display = option.dataset.requires === '1' ? 'inline-flex' : 'none';
            typeHelp.textContent = option.dataset.requires === '1'
                ? 'Loại nghỉ này yêu cầu giấy tờ chứng minh khi gửi đơn.'
                : 'Đơn sẽ được tính theo ngày làm việc: T2–T6 cả ngày, T7 buổi sáng (0,5 ngày), trừ Chủ nhật và ngày lễ.';
        }

        typeSelect.addEventListener('change', updateBalance);
        updateBalance();

        var start = document.getElementById('leave-start');
        var end = document.getElementById('leave-end');
        if (start && end) {
            start.addEventListener('change', function () {
                end.min = start.value || '';
                if (end.value && end.value < start.value) end.value = start.value;
            });
        }
    })();
</script>
@endpush
