@extends('layouts.app')

@section('title', 'Quỹ phép toàn công ty')
@section('subtitle', 'Theo dõi, khởi tạo và điều chỉnh số ngày phép của từng nhân sự')

@section('content')
    <form method="GET" action="{{ route('admin.balances.index') }}" class="filter-bar">
        <div class="field search">
            <label for="q">Tìm nhân sự</label>
            <div class="input-group">
                <svg class="icon"><use href="#i-search"></use></svg>
                <input id="q" name="q" type="text" class="input" value="{{ request('q') }}" placeholder="Tên hoặc mã nhân viên...">
            </div>
        </div>
        <div class="field">
            <label for="year">Năm</label>
            <select id="year" name="year" class="select">
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected($year === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="leave_type_id">Loại phép</label>
            <select id="leave_type_id" name="leave_type_id" class="select">
                @foreach ($leaveTypes as $type)
                    <option value="{{ $type->id }}" @selected($selectedType?->id === $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="department_id">Phòng ban</label>
            <select id="department_id" name="department_id" class="select">
                <option value="">Tất cả</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">
                <svg class="icon"><use href="#i-filter"></use></svg>
                Lọc
            </button>
            <a href="{{ route('admin.balances.index') }}" class="btn btn-outline btn-sm">Xoá lọc</a>
        </div>
    </form>

    <div class="info-strip mb-20">
        <div class="info-tile">
            <span class="stat-icon info"><svg class="icon"><use href="#i-calendar"></use></svg></span>
            <div>
                <div class="it-value">{{ rtrim(rtrim(number_format($summary['entitled'], 1, ',', '.'), '0'), ',') }}</div>
                <div class="it-label">Ngày được hưởng · {{ $selectedType?->name ?? '—' }}</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon orange"><svg class="icon"><use href="#i-trend"></use></svg></span>
            <div>
                <div class="it-value">{{ rtrim(rtrim(number_format($summary['used'], 1, ',', '.'), '0'), ',') }}</div>
                <div class="it-label">Đã sử dụng</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon green"><svg class="icon"><use href="#i-wallet"></use></svg></span>
            <div>
                <div class="it-value">{{ rtrim(rtrim(number_format($summary['remaining'], 1, ',', '.'), '0'), ',') }}</div>
                <div class="it-label">Còn lại</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon purple"><svg class="icon"><use href="#i-users"></use></svg></span>
            <div>
                <div class="it-value">{{ $users->total() }}</div>
                <div class="it-label">Nhân sự đang hoạt động</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-wallet"></use></svg> Quỹ phép năm {{ $year }}</div>
                <div class="card-sub">Cộng/trừ nhanh hoặc chỉnh sửa trực tiếp ngày được hưởng, chuyển năm và đã dùng</div>
            </div>
            <a href="{{ route('admin.balances.export', request()->only(['year', 'leave_type_id', 'department_id', 'q'])) }}"
               class="btn btn-outline btn-sm">
                <svg class="icon"><use href="#i-download"></use></svg>
                Xuất Excel
            </a>
        </div>
        <div class="card-body flush">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nhân sự</th>
                            <th>Phòng ban</th>
                            <th>Được hưởng</th>
                            <th>Chuyển năm</th>
                            <th>Đã dùng</th>
                            <th>Còn lại</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $employee)
                            @php
                                $balance = $pageBalances->get($employee->id);
                                $entitled = $balance ? $balance->totalEntitled() : (float) ($selectedType?->default_days ?? 0);
                                $used = $balance?->used_days ?? 0;
                                $remaining = $balance ? $balance->remaining() : $entitled;
                                $usedPct = $entitled > 0 ? min(100, ($used / $entitled) * 100) : 0;
                            @endphp
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $employee->name }}</div>
                                    <div class="text-xs muted">{{ $employee->employee_code ?: $employee->email }}</div>
                                </td>
                                <td class="text-sm">{{ $employee->department?->name ?? '—' }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format($entitled, 1, ',', '.'), '0'), ',') }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format($balance?->carried_over ?? 0, 1, ',', '.'), '0'), ',') }}</td>
                                <td>
                                    <div class="flex items-center gap-8">
                                        <span class="num">{{ rtrim(rtrim(number_format($used, 1, ',', '.'), '0'), ',') }}</span>
                                        <div class="progress" style="width: 70px">
                                            <span style="width: {{ $usedPct }}%; background: {{ $usedPct >= 100 ? '#d83b4b' : 'linear-gradient(90deg,#ffb955,#ef8a08)' }}"></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $remaining <= 0 ? 'danger' : ($remaining <= 2 ? 'warning' : 'success') }} no-dot">
                                        {{ rtrim(rtrim(number_format($remaining, 1, ',', '.'), '0'), ',') }} ngày
                                    </span>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        @can('balance.adjust')
                                            <button type="button" class="btn btn-success btn-sm" data-modal-open="#grant-{{ $employee->id }}">
                                                <svg class="icon"><use href="#i-plus"></use></svg>
                                                Cộng / Trừ
                                            </button>
                                        @endcan
                                        @can('balance.update')
                                            <button type="button" class="btn btn-outline btn-sm" data-modal-open="#adjust-{{ $employee->id }}">Điều chỉnh</button>
                                        @endcan
                                        @if (!auth()->user()->hasPermission('balance.adjust') && !auth()->user()->hasPermission('balance.update'))
                                            <span class="text-xs muted">Chỉ xem</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty" style="padding: 30px">
                                        <strong>Không có nhân sự phù hợp</strong>
                                        <p>Thử thay đổi bộ lọc hoặc từ khoá tìm kiếm.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($users->hasPages())
            {{ $users->links() }}
        @endif
    </div>

    @foreach ($users as $employee)
        @php
            $balance = $pageBalances->get($employee->id);
            $employeeTypeBalances = $pageTypeBalances->get($employee->id, collect())->keyBy('leave_type_id');
        @endphp
        @can('balance.adjust')
        <div class="modal" id="grant-{{ $employee->id }}">
            <div class="modal-card">
                <form method="POST" action="{{ route('admin.balances.adjust', $employee) }}">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <div class="modal-head">
                        <div>
                            <h3>Cộng / Trừ phép · {{ $employee->name }}</h3>
                            <p>Chọn loại phép cần cộng/trừ · năm {{ $year }}</p>
                        </div>
                        <button type="button" class="modal-close" data-modal-close>
                            <svg class="icon"><use href="#i-x"></use></svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-grid">
                            <div class="field span-2">
                                <label for="grant-type-{{ $employee->id }}">Loại phép <span class="req">*</span></label>
                                <select id="grant-type-{{ $employee->id }}" name="leave_type_id" class="select" data-grant-type required>
                                    @foreach ($leaveTypes as $type)
                                        @php
                                            $typeBalance = $employeeTypeBalances->get($type->id);
                                            $typeEntitled = $typeBalance ? $typeBalance->totalEntitled() : (float) $type->default_days;
                                            $typeUsed = $typeBalance?->used_days ?? 0;
                                            $typeRemaining = $typeBalance ? $typeBalance->remaining() : $typeEntitled;
                                        @endphp
                                        <option value="{{ $type->id }}"
                                                @selected($selectedType?->id === $type->id)
                                                data-entitled="{{ $typeEntitled }}"
                                                data-used="{{ $typeUsed }}"
                                                data-remaining="{{ $typeRemaining }}">
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="text-xs muted">Áp dụng cho bất kỳ loại phép nào đang theo dõi quỹ.</span>
                            </div>
                            <div class="field">
                                <label for="grant-days-{{ $employee->id }}">Số ngày <span class="req">*</span></label>
                                <input id="grant-days-{{ $employee->id }}" name="days" type="number" step="0.5" min="-60" max="60" class="input" required placeholder="VD: 2 hoặc -1.5">
                                <span class="text-xs muted">Số dương để cộng, số âm để trừ.</span>
                            </div>
                            <div class="field">
                                <label for="grant-note-{{ $employee->id }}">Lý do <span class="req">*</span></label>
                                <input id="grant-note-{{ $employee->id }}" name="note" type="text" class="input" required minlength="3" maxlength="255" placeholder="VD: Thưởng phép thâm niên">
                            </div>
                        </div>
                        <div class="note-box mt-16" data-grant-current>
                            Hiện tại (<span data-grant-type-name>{{ $selectedType?->name }}</span>):
                            được hưởng <b data-grant-entitled>{{ rtrim(rtrim(number_format($balance?->totalEntitled() ?? ($selectedType?->default_days ?? 0), 1, ',', '.'), '0'), ',') }}</b>
                            · đã dùng <b data-grant-used>{{ rtrim(rtrim(number_format($balance?->used_days ?? 0, 1, ',', '.'), '0'), ',') }}</b>
                            · còn lại <b data-grant-remaining>{{ rtrim(rtrim(number_format($balance?->remaining() ?? ($selectedType?->default_days ?? 0), 1, ',', '.'), '0'), ',') }}</b> ngày
                        </div>
                    </div>
                    <div class="modal-foot">
                        <button type="button" class="btn btn-outline" data-modal-close>Đóng</button>
                        <button type="submit" class="btn btn-success">Xác nhận cộng / trừ</button>
                    </div>
                </form>
            </div>
        </div>
        @endcan

        @can('balance.update')
        <div class="modal" id="adjust-{{ $employee->id }}">
            <div class="modal-card">
                <form method="POST" action="{{ route('admin.balances.update', $employee) }}">
                    @csrf
                    <input type="hidden" name="leave_type_id" value="{{ $selectedType?->id }}">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <div class="modal-head">
                        <div>
                            <h3>Điều chỉnh quỹ phép · {{ $employee->name }}</h3>
                            <p>{{ $selectedType?->name }} · năm {{ $year }}</p>
                        </div>
                        <button type="button" class="modal-close" data-modal-close>
                            <svg class="icon"><use href="#i-x"></use></svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-grid">
                            <div class="field">
                                <label for="entitled-{{ $employee->id }}">Ngày được hưởng <span class="req">*</span></label>
                                <input id="entitled-{{ $employee->id }}" name="entitled_days" type="number" step="0.5" min="0" max="60" class="input" value="{{ old('entitled_days', $balance?->entitled_days ?? $selectedType?->default_days ?? 0) }}" required>
                            </div>
                            <div class="field">
                                <label for="carried-{{ $employee->id }}">Ngày chuyển năm <span class="req">*</span></label>
                                <input id="carried-{{ $employee->id }}" name="carried_over" type="number" step="0.5" min="0" max="60" class="input" value="{{ old('carried_over', $balance?->carried_over ?? 0) }}" required>
                            </div>
                            <div class="field">
                                <label for="used-{{ $employee->id }}">Ngày đã dùng <span class="req">*</span></label>
                                <input id="used-{{ $employee->id }}" name="used_days" type="number" step="0.5" min="0" max="120" class="input" value="{{ old('used_days', $balance?->used_days ?? 0) }}" required>
                            </div>
                            <div class="field">
                                <label for="note-{{ $employee->id }}">Ghi chú</label>
                                <input id="note-{{ $employee->id }}" name="note" type="text" class="input" value="{{ old('note', $balance?->note) }}" placeholder="Lý do điều chỉnh...">
                            </div>
                        </div>
                        <div class="note-box mt-16">
                            Hồ sơ hiện tại: <b>{{ $balance ? 'đã khởi tạo' : 'chưa khởi tạo (đang dùng mặc định)' }}</b>.
                            Công thức còn lại = Được hưởng − Đã dùng.
                        </div>
                    </div>
                    <div class="modal-foot">
                        <button type="button" class="btn btn-outline" data-modal-close>Đóng</button>
                        <button type="submit" class="btn btn-primary">Lưu điều chỉnh</button>
                    </div>
                </form>
            </div>
        </div>
        @endcan
    @endforeach
@endsection
