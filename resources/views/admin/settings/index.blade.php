@extends('layouts.app')

@section('title', 'Cấu hình hệ thống')
@section('subtitle', 'Thông tin công ty, chính sách nghỉ phép và hệ số làm thêm giờ')

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        @foreach ($groups as $group => $fields)
            <div class="card {{ $loop->first ? '' : 'mt-20' }}">
                <div class="card-head">
                    <div>
                        <div class="card-title">
                            <svg class="icon"><use href="#{{ $loop->index === 0 ? 'i-briefcase' : ($loop->index === 1 ? 'i-sun' : 'i-clock') }}"></use></svg>
                            {{ $group }}
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        @foreach ($fields as $key => $config)
                            <div class="field">
                                <label for="{{ $key }}">{{ $config['label'] }}</label>
                                <input id="{{ $key }}" name="{{ $key }}" type="{{ $config['type'] }}"
                                       class="input @error($key) is-invalid @enderror"
                                       value="{{ old($key, $settings[$key]->value ?? '') }}"
                                       @if ($config['type'] === 'number') step="0.1" @endif
                                       @if ($config['type'] === 'time') placeholder="HH:MM" @endif
                                       @cannot('setting.update') disabled @endcannot>
                                @error($key)<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        <div class="card mt-20">
            <div class="card-body">
                <div class="flex items-center justify-between gap-12 flex-wrap">
                    <div class="text-sm muted">
                        <svg class="icon icon-sm"><use href="#i-info"></use></svg>
                        Thay đổi sẽ áp dụng cho các đơn tạo mới và cách tính ngày công.
                    </div>
                    @can('setting.update')
                        <button type="submit" class="btn btn-primary">
                            <svg class="icon"><use href="#i-check"></use></svg>
                            Lưu cấu hình
                        </button>
                    @endcan
                </div>
            </div>
        </div>
    </form>
@endsection
