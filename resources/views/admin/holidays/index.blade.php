@extends('layouts.app')

@section('title', 'Ngày lễ')
@section('subtitle', 'Khai báo ngày lễ để tính ngày làm việc và hệ số OT ngày lễ')

@section('content')
    <div class="grid grid-2-1">
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-calendar"></use></svg> Ngày lễ năm {{ $year }}</div>
                    <div class="card-sub">{{ $holidays->count() }} ngày đã khai báo</div>
                </div>
                <form method="GET" action="{{ route('admin.holidays.index') }}">
                    <select name="year" class="select" data-auto-submit style="min-width: 110px">
                        @foreach ($years as $option)
                            <option value="{{ $option }}" @selected($year === $option)>Năm {{ $option }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="card-body flush">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Tên ngày lễ</th>
                                <th>Ghi chú</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($holidays as $holiday)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-12">
                                            <div class="date-badge">
                                                <b>{{ $holiday->holiday_date->format('d') }}</b>
                                                <span>T{{ $holiday->holiday_date->format('n') }}</span>
                                            </div>
                                            <div>
                                                <div class="cell-main">{{ $holiday->holiday_date->format('d/m/Y') }}</div>
                                                <div class="text-xs muted">{{ ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][$holiday->holiday_date->dayOfWeek] }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $holiday->name }}</td>
                                    <td class="text-sm muted">{{ $holiday->description ?: '—' }}</td>
                                    <td>
                                        <div class="cell-actions">
                                            @can('holiday.update')
                                                <a href="{{ route('admin.holidays.index', ['year' => $year, 'edit' => $holiday->id]) }}" class="btn btn-outline btn-sm">Sửa</a>
                                            @endcan
                                            @can('holiday.delete')
                                                <form method="POST" action="{{ route('admin.holidays.destroy', $holiday) }}"
                                                      data-confirm="Xoá ngày lễ {{ $holiday->name }}?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger-soft btn-sm">Xoá</button>
                                                </form>
                                            @endcan
                                            @if (!auth()->user()->hasPermission('holiday.update') && !auth()->user()->hasPermission('holiday.delete'))
                                                <span class="text-xs muted">Chỉ xem</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="empty" style="padding: 30px">
                                            <strong>Chưa có ngày lễ trong năm {{ $year }}</strong>
                                            <p>Thêm ngày lễ ở biểu mẫu bên phải để hệ thống tính đúng ngày công.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if (auth()->user()->hasPermission($editing ? 'holiday.update' : 'holiday.create'))
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <svg class="icon"><use href="#{{ $editing ? 'i-edit' : 'i-plus' }}"></use></svg>
                    {{ $editing ? 'Sửa ngày lễ' : 'Thêm ngày lễ' }}
                </div>
                @if ($editing)
                    <a href="{{ route('admin.holidays.index', ['year' => $year]) }}" class="btn btn-ghost btn-sm">Huỷ sửa</a>
                @endif
            </div>
            <div class="card-body">
                <form method="POST" action="{{ $editing ? route('admin.holidays.update', $editing) : route('admin.holidays.store') }}">
                    @csrf
                    @if ($editing)
                        @method('PUT')
                    @endif
                    <div class="field">
                        <label for="name">Tên ngày lễ <span class="req">*</span></label>
                        <input id="name" name="name" type="text" class="input @error('name') is-invalid @enderror" value="{{ old('name', $editing?->name) }}" placeholder="VD: Quốc khánh 2/9" required>
                        @error('name')<span class="error-text">{{ $message }}</span>@enderror
                    </div>
                    <div class="field mt-12">
                        <label for="holiday_date">Ngày <span class="req">*</span></label>
                        <input id="holiday_date" name="holiday_date" type="date" class="input @error('holiday_date') is-invalid @enderror" value="{{ old('holiday_date', $editing?->holiday_date?->format('Y-m-d')) }}" required>
                        @error('holiday_date')<span class="error-text">{{ $message }}</span>@enderror
                    </div>
                    <div class="field mt-12">
                        <label for="description">Ghi chú</label>
                        <input id="description" name="description" type="text" class="input" value="{{ old('description', $editing?->description) }}">
                        @error('description')<span class="error-text">{{ $message }}</span>@enderror
                    </div>

                    <div class="note-box mt-16">
                        Ngày lễ được dùng để: tính số ngày phép (bỏ qua ngày lễ), tự động nhận diện loại OT
                        <b>ngày lễ</b> với hệ số x{{ rtrim(rtrim(number_format((float) setting('ot_rate_holiday', 3), 1, ',', '.'), '0'), ',') }}.
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary w-full">
                            <svg class="icon"><use href="#i-check"></use></svg>
                            {{ $editing ? 'Lưu thay đổi' : 'Thêm ngày lễ' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
@endsection
