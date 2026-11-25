@extends('layouts.app')

@section('title', 'Trung tâm thông báo')
@section('subtitle', $unreadCount > 0 ? $unreadCount . ' thông báo chưa đọc' : 'Tất cả thông báo đã được đọc')

@section('actions')
    @if ($unreadCount > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">
                <svg class="icon"><use href="#i-check"></use></svg>
                Đọc tất cả
            </button>
        </form>
    @endif
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-bell"></use></svg> Danh sách thông báo</div>
                <div class="card-sub">Cập nhật về đơn nghỉ phép, OT và quy trình duyệt</div>
            </div>
            <span class="badge badge-{{ $unreadCount > 0 ? 'warning' : 'success' }} no-dot">
                {{ $unreadCount > 0 ? $unreadCount . ' chưa đọc' : 'Đã đọc tất cả' }}
            </span>
        </div>
        <div class="card-body flush">
            @forelse ($notifications as $notification)
                <div class="flex gap-12" style="padding: 16px 22px; border-bottom: 1px solid var(--line-soft); align-items: flex-start; {{ $notification->is_read ? '' : 'background: #f7faff;' }}">
                    <span class="list-icon {{ $notification->is_read ? '' : 'orange' }}">
                        <svg class="icon"><use href="#i-bell"></use></svg>
                    </span>
                    <div style="flex: 1; min-width: 0">
                        <div class="flex items-center gap-8 flex-wrap">
                            <span style="font-size: 13.8px; font-weight: {{ $notification->is_read ? '650' : '800' }}; color: var(--navy-900)">
                                {{ $notification->title }}
                            </span>
                            @unless ($notification->is_read)
                                <span class="badge badge-warning no-dot" style="font-size: 9.5px; padding: 2px 6px">Mới</span>
                            @endunless
                            <span class="text-xs muted ml-auto nowrap">{{ $notification->created_at?->diffForHumans() }}</span>
                        </div>
                        @if ($notification->body)
                            <div class="text-sm" style="margin-top: 3px; color: var(--ink-soft)">{{ $notification->body }}</div>
                        @endif
                        <div class="text-xs muted" style="margin-top: 5px">
                            {{ vn_date($notification->created_at, 'd/m/Y H:i') }}
                            @if ($notification->type)
                                · <span class="strong">{{ $notification->type }}</span>
                            @endif
                        </div>
                    </div>
                    <div style="flex: none">
                        @if ($notification->link)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm">
                                    <svg class="icon"><use href="#i-arrow-right"></use></svg>
                                    Mở
                                </button>
                            </form>
                        @elseif (!$notification->is_read)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm">Đã đọc</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty" style="padding: 54px 20px">
                    <div class="empty-icon"><svg class="icon"><use href="#i-bell"></use></svg></div>
                    <strong>Chưa có thông báo nào</strong>
                    <p>Bạn sẽ nhận thông báo khi có đơn được gửi, duyệt hoặc từ chối.</p>
                </div>
            @endforelse
        </div>
        @if ($notifications->hasPages())
            {{ $notifications->links() }}
        @endif
    </div>
@endsection
