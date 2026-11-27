@extends('layouts.app')

@section('title', 'Loại nghỉ phép')
@section('subtitle', 'Danh mục loại nghỉ, số ngày mặc định và quy tắc trừ quỹ phép')

@section('content')
    <div class="grid grid-2-1">
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-sun"></use></svg> Danh mục loại nghỉ</div>
                    <div class="card-sub">{{ $leaveTypes->count() }} loại · {{ $leaveTypes->where('active', true)->count() }} đang áp dụng</div>
                </div>
            </div>
            <div class="card-body flush">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Loại nghỉ</th>
                                <th>Mã</th>
                                <th>Mặc định</th>
                                <th>Trừ quỹ</th>
                                <th>Yêu cầu tệp</th>
                                <th>Có lương</th>
                                <th>Đơn</th>
                                <th>Trạng thái</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($leaveTypes as $type)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-12" style="min-width: 230px">
                                            <span style="width: 12px; height: 12px; border-radius: 4px; background: {{ $type->color }}; flex: none"></span>
                                            <div style="min-width: 0">
                                                <div class="cell-main">{{ $type->name }}</div>
                                                @if ($type->description)
                                                    <div class="text-xs muted">{{ \Illuminate\Support\Str::limit($type->description, 60) }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="chip">{{ $type->code }}</span></td>
                                    <td class="num">{{ rtrim(rtrim(number_format($type->default_days, 1, ',', '.'), '0'), ',') }}</td>
                                    <td>
                                        <span class="badge badge-{{ $type->deduct_balance ? 'primary' : 'muted' }} no-dot">{{ $type->deduct_balance ? 'Có' : 'Không' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $type->requires_attachment ? 'warning' : 'muted' }} no-dot">{{ $type->requires_attachment ? 'Bắt buộc' : 'Không' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $type->is_paid ? 'success' : 'muted' }} no-dot">{{ $type->is_paid ? 'Có' : 'Không' }}</span>
                                    </td>
                                    <td class="num">{{ $type->leave_requests_count }}</td>
                                    <td>
                                        <span class="badge badge-{{ $type->active ? 'success' : 'muted' }}">
                                            {{ $type->active ? 'Đang dùng' : 'Ngưng' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="cell-actions">
                                            @can('leavetype.update')
                                                <a href="{{ route('admin.leave-types.index', ['edit' => $type->id]) }}" class="btn btn-outline btn-sm">Sửa</a>
                                            @endcan
                                            @can('leavetype.toggle')
                                                <form method="POST" action="{{ route('admin.leave-types.toggle', $type) }}"
                                                      data-confirm="{{ $type->active ? 'Ngưng áp dụng loại nghỉ ' . $type->name . '?' : 'Kích hoạt loại nghỉ ' . $type->name . '?' }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm {{ $type->active ? 'btn-warning-soft' : 'btn-success' }}">
                                                        {{ $type->active ? 'Ngưng' : 'Bật' }}
                                                    </button>
                                                </form>
                                            @endcan
                                            @can('leavetype.delete')
                                                <form method="POST" action="{{ route('admin.leave-types.destroy', $type) }}"
                                                      data-confirm="Xoá loại nghỉ {{ $type->name }}? Quỹ phép của loại nghỉ này ở tất cả nhân sự sẽ bị xoá theo và không thể hoàn tác.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger-soft btn-sm">Xoá</button>
                                                </form>
                                            @endcan
                                            @if (!auth()->user()->hasPermission('leavetype.update') && !auth()->user()->hasPermission('leavetype.toggle') && !auth()->user()->hasPermission('leavetype.delete'))
                                                <span class="text-xs muted">Chỉ xem</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="empty" style="padding: 30px">
                                            <strong>Chưa có loại nghỉ nào</strong>
                                            <p>Thêm loại nghỉ đầu tiên ở biểu mẫu bên phải.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if (auth()->user()->hasPermission($editing ? 'leavetype.update' : 'leavetype.create'))
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <svg class="icon"><use href="#{{ $editing ? 'i-edit' : 'i-plus' }}"></use></svg>
                    {{ $editing ? 'Sửa loại nghỉ' : 'Thêm loại nghỉ' }}
                </div>
                @if ($editing)
                    <a href="{{ route('admin.leave-types.index') }}" class="btn btn-ghost btn-sm">Huỷ sửa</a>
                @endif
            </div>
            <div class="card-body">
                <form method="POST" action="{{ $editing ? route('admin.leave-types.update', $editing) : route('admin.leave-types.store') }}">
                    @csrf
                    @if ($editing)
                        @method('PUT')
                    @endif
                    <div class="form-grid">
                        <div class="field">
                            <label for="code">Mã loại nghỉ <span class="req">*</span></label>
                            <input id="code" name="code" type="text" class="input @error('code') is-invalid @enderror" value="{{ old('code', $editing?->code) }}" placeholder="VD: AL" required>
                            @error('code')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="name">Tên loại nghỉ <span class="req">*</span></label>
                            <input id="name" name="name" type="text" class="input @error('name') is-invalid @enderror" value="{{ old('name', $editing?->name) }}" required>
                            @error('name')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="color">Màu nhận diện <span class="req">*</span></label>
                            <div class="flex items-center gap-8">
                                <input id="color" name="color" type="color" value="{{ old('color', $editing?->color ?? '#2f6bff') }}" style="width: 46px; height: 42px; border: 1px solid var(--line); border-radius: 10px; padding: 3px; background: #fff">
                                <input type="text" class="input" value="{{ old('color', $editing?->color ?? '#2f6bff') }}" readonly style="flex: 1">
                            </div>
                            @error('color')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="default_days">Số ngày mặc định <span class="req">*</span></label>
                            <input id="default_days" name="default_days" type="number" step="0.5" min="0" max="365" class="input @error('default_days') is-invalid @enderror" value="{{ old('default_days', $editing?->default_days ?? 0) }}" required>
                            @error('default_days')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field" style="grid-column: 1 / -1">
                            <label for="description">Mô tả / quy định</label>
                            <textarea id="description" name="description" rows="2" class="textarea">{{ old('description', $editing?->description) }}</textarea>
                        </div>
                    </div>

                    <div class="mt-16" style="display: grid; gap: 10px">
                        <label class="checkbox-row">
                            <input type="hidden" name="deduct_balance" value="0">
                            <input type="checkbox" name="deduct_balance" value="1" @checked(old('deduct_balance', $editing?->deduct_balance ?? true))>
                            Trừ vào quỹ phép năm
                        </label>
                        <label class="checkbox-row">
                            <input type="hidden" name="requires_attachment" value="0">
                            <input type="checkbox" name="requires_attachment" value="1" @checked(old('requires_attachment', $editing?->requires_attachment ?? false))>
                            Bắt buộc đính kèm minh chứng
                        </label>
                        <label class="checkbox-row">
                            <input type="hidden" name="is_paid" value="0">
                            <input type="checkbox" name="is_paid" value="1" @checked(old('is_paid', $editing?->is_paid ?? true))>
                            Nghỉ có hưởng lương
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary w-full">
                            <svg class="icon"><use href="#i-check"></use></svg>
                            {{ $editing ? 'Lưu thay đổi' : 'Thêm loại nghỉ' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
@endsection
