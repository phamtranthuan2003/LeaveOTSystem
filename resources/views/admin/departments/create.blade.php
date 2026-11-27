@extends('layouts.app')

@section('title', 'Thêm phòng ban')
@section('subtitle', 'Tạo đơn vị tổ chức mới trong cây phòng ban')

@section('actions')
    <a href="{{ route('admin.departments.index') }}" class="btn btn-outline btn-sm" data-back>
        <svg class="icon"><use href="#i-arrow-left"></use></svg>
        Quay lại
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.departments.store') }}">
        @csrf
        <div class="grid grid-1-2">
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><svg class="icon"><use href="#i-info"></use></svg> Gợi ý cấu trúc</div>
                </div>
                <div class="card-body">
                    <div class="note-box">
                        Hệ thống hỗ trợ 4 cấp: <b>Tập đoàn → Khối/Ban → Phòng → Nhóm</b>. Cấp bậc được tính tự động từ đơn vị cấp trên.
                    </div>
                    <div class="note-box mt-12">
                        Trưởng phòng được chọn làm <b>Quản lý</b> sẽ là người duyệt cấp phòng trong chuỗi thác nước của nhân sự trực thuộc.
                    </div>
                    <div class="note-box mt-12">
                        Không thể ngưng hoạt động đơn vị còn phòng ban con hoặc nhân sự đang làm việc.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div class="card-title"><svg class="icon"><use href="#i-branch"></use></svg> Thông tin phòng ban</div>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="field">
                            <label for="code">Mã đơn vị <span class="req">*</span></label>
                            <input id="code" name="code" type="text" class="input @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="VD: PH-HR" required autofocus>
                            @error('code')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="name">Tên đơn vị <span class="req">*</span></label>
                            <input id="name" name="name" type="text" class="input @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="parent_id">Đơn vị cấp trên</label>
                            <select id="parent_id" name="parent_id" class="select">
                                <option value="">— Cấp cao nhất (Tập đoàn) —</option>
                                @foreach ($parents as $parent)
                                    <option value="{{ $parent->id }}" @selected((string) old('parent_id') === (string) $parent->id)>
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
                                    <option value="{{ $manager->id }}" @selected((string) old('manager_id') === (string) $manager->id)>{{ $manager->name }}{{ $manager->position ? ' · ' . $manager->position : '' }}</option>
                                @endforeach
                            </select>
                            @error('manager_id')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="sort_order">Thứ tự hiển thị</label>
                            <input id="sort_order" name="sort_order" type="number" min="0" max="9999" class="input" value="{{ old('sort_order', 0) }}">
                        </div>
                        <div class="field">
                            <label>Trạng thái</label>
                            <label class="checkbox-row" style="height: 42px">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" name="active" value="1" @checked(old('active', true))>
                                Đang hoạt động
                            </label>
                        </div>
                        <div class="field" style="grid-column: 1 / -1">
                            <label for="description">Mô tả</label>
                            <textarea id="description" name="description" rows="3" class="textarea">{{ old('description') }}</textarea>
                            @error('description')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('admin.departments.index') }}" class="btn btn-outline" data-back>Huỷ</a>
                        <button type="submit" class="btn btn-primary">
                            <svg class="icon"><use href="#i-check"></use></svg>
                            Tạo phòng ban
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
