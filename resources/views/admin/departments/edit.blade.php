@extends('layouts.app')

@section('title', 'Sửa phòng ban · ' . $department->name)
@section('subtitle', 'Cập nhật thông tin đơn vị, quản lý và cấp bậc trong cây tổ chức')

@section('actions')
    <a href="{{ route('admin.departments.index') }}" class="btn btn-outline btn-sm">
        <svg class="icon"><use href="#i-arrow-left"></use></svg>
        Quay lại
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.departments.update', $department) }}">
        @csrf
        @method('PUT')
        <div class="grid grid-1-2">
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><svg class="icon"><use href="#i-branch"></use></svg> Thông tin hiện tại</div>
                </div>
                <div class="card-body">
                    <div class="kv-grid">
                        <div class="kv-item"><div class="k">Mã đơn vị</div><div class="v">{{ $department->code }}</div></div>
                        <div class="kv-item"><div class="k">Cấp bậc</div><div class="v">{{ $department->levelLabel() }}</div></div>
                        <div class="kv-item"><div class="k">Đơn vị cấp trên</div><div class="v">{{ $department->parent?->name ?? 'Cấp cao nhất' }}</div></div>
                        <div class="kv-item"><div class="k">Số nhân sự</div><div class="v">{{ $department->users()->count() }}</div></div>
                    </div>
                    <div class="note-box mt-16">
                        Khi thay đổi đơn vị cấp trên, cấp bậc của phòng ban và toàn bộ đơn vị trực thuộc sẽ được tính lại tự động.
                    </div>
                    <div class="alert alert-warning mt-16">
                        <svg class="icon"><use href="#i-alert"></use></svg>
                        <div><b>Lưu ý:</b> Không thể chọn chính nó hoặc phòng ban con làm cấp trên.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div class="card-title"><svg class="icon"><use href="#i-edit"></use></svg> Chỉnh sửa thông tin</div>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="field">
                            <label for="code">Mã đơn vị <span class="req">*</span></label>
                            <input id="code" name="code" type="text" class="input @error('code') is-invalid @enderror" value="{{ old('code', $department->code) }}" required>
                            @error('code')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="name">Tên đơn vị <span class="req">*</span></label>
                            <input id="name" name="name" type="text" class="input @error('name') is-invalid @enderror" value="{{ old('name', $department->name) }}" required>
                            @error('name')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="parent_id">Đơn vị cấp trên</label>
                            <select id="parent_id" name="parent_id" class="select @error('parent_id') is-invalid @enderror">
                                <option value="">— Cấp cao nhất (Tập đoàn) —</option>
                                @foreach ($parents as $parent)
                                    <option value="{{ $parent->id }}" @selected((string) old('parent_id', $department->parent_id) === (string) $parent->id)>
                                        {{ str_repeat('— ', max(0, $parent->level - 1)) }}{{ $parent->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="manager_id">Quản lý đơn vị</label>
                            <select id="manager_id" name="manager_id" class="select">
                                <option value="">— Chưa chỉ định —</option>
                                @foreach ($managers as $manager)
                                    <option value="{{ $manager->id }}" @selected((string) old('manager_id', $department->manager_id) === (string) $manager->id)>{{ $manager->name }}{{ $manager->position ? ' · ' . $manager->position : '' }}</option>
                                @endforeach
                            </select>
                            @error('manager_id')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="sort_order">Thứ tự hiển thị</label>
                            <input id="sort_order" name="sort_order" type="number" min="0" max="9999" class="input" value="{{ old('sort_order', $department->sort_order) }}">
                        </div>
                        <div class="field">
                            <label>Trạng thái</label>
                            <label class="checkbox-row" style="height: 42px">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" name="active" value="1" @checked(old('active', $department->active))>
                                Đang hoạt động
                            </label>
                        </div>
                        <div class="field" style="grid-column: 1 / -1">
                            <label for="description">Mô tả</label>
                            <textarea id="description" name="description" rows="3" class="textarea">{{ old('description', $department->description) }}</textarea>
                            @error('description')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('admin.departments.index') }}" class="btn btn-outline">Huỷ</a>
                        <button type="submit" class="btn btn-primary">
                            <svg class="icon"><use href="#i-check"></use></svg>
                            Lưu thay đổi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card mt-20">
        <div class="card-head">
            <div class="card-title"><svg class="icon"><use href="#i-shield"></use></svg> Trạng thái đơn vị</div>
        </div>
        <div class="card-body">
            <div class="flex items-center justify-between gap-12 flex-wrap">
                <div class="text-sm muted">
                    Hiện tại: <b style="color: var(--ink)">{{ $department->active ? 'Đang hoạt động' : 'Ngưng hoạt động' }}</b>
                    · Không thể ngưng khi còn phòng ban con hoặc nhân sự đang làm việc.
                </div>
                <form method="POST" action="{{ route('admin.departments.toggle', $department) }}"
                      data-confirm="{{ $department->active ? 'Ngưng hoạt động ' . $department->name . '?' : 'Kích hoạt lại ' . $department->name . '?' }}">
                    @csrf
                    <button type="submit" class="btn {{ $department->active ? 'btn-warning-soft' : 'btn-success' }}">
                        {{ $department->active ? 'Ngưng hoạt động' : 'Kích hoạt lại' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
