<div class="notifications-dropdown">
    <div class="dropdown">
        <button class="btn btn-link dropdown-toggle" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-bell"></i>
            @if(auth()->user()->unreadNotifications->count() > 0)
                <span class="notification-badge">{{ auth()->user()->unreadNotifications->count() }}</span>
            @endif
        </button>
        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="width: 350px; max-height: 500px; overflow-y: auto;">
            <div class="dropdown-header">
                <strong>الإشعارات</strong>
                @if(auth()->user()->unreadNotifications->count() > 0)
                    <a href="#" id="markAllRead" class="float-left small">تحديد الكل كمقروء</a>
                @endif
            </div>
            <div class="dropdown-divider"></div>
            @forelse(auth()->user()->notifications->take(10) as $notification)
                <a href="#" class="dropdown-item notification-item {{ $notification->read_at ? 'read' : 'unread' }}" data-id="{{ $notification->id }}">
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                            <i class="fas {{ $notification->data['icon'] ?? 'fa-bell' }} mt-1"></i>
                        </div>
                        <div class="flex-grow-1 me-2">
                            <div class="notification-title">{{ $notification->data['title'] }}</div>
                            <div class="notification-message">{{ $notification->data['message'] }}</div>
                            <div class="notification-time">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                        @if(!$notification->read_at)
                            <div class="unread-dot"></div>
                        @endif
                    </div>
                </a>
                @if(!$loop->last)
                    <div class="dropdown-divider"></div>
                @endif
            @empty
                <div class="dropdown-item text-center text-muted">
                    <i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>
                    لا توجد إشعارات
                </div>
            @endforelse
            @if(auth()->user()->notifications->count() > 10)
                <div class="dropdown-divider"></div>
                <div class="dropdown-item text-center">
                    <a href="{{ route('admin.notifications') }}">عرض جميع الإشعارات</a>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #dc2626;
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notification-item {
        padding: 12px 15px;
        transition: background 0.2s;
    }

    .notification-item:hover {
        background: #f8f9fa;
    }

    .notification-item.unread {
        background: #f0f7ff;
    }

    .notification-title {
        font-weight: 600;
        font-size: 14px;
        color: #1f2937;
    }

    .notification-message {
        font-size: 12px;
        color: #6b7280;
        margin-top: 2px;
    }

    .notification-time {
        font-size: 10px;
        color: #9ca3af;
        margin-top: 4px;
    }

    .unread-dot {
        width: 8px;
        height: 8px;
        background: #3b82f6;
        border-radius: 50%;
        margin-top: 8px;
    }

    .dropdown-header {
        padding: 10px 15px;
        font-weight: 600;
    }

    .dropdown-menu {
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // تحديد إشعار كمقروء عند النقر عليه
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const notificationId = this.dataset.id;
                if (notificationId) {
                    fetch(`/admin/notifications/${notificationId}/mark-as-read`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json'
                        }
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        });

        // تحديد الكل كمقروء
        const markAllRead = document.getElementById('markAllRead');
        if (markAllRead) {
            markAllRead.addEventListener('click', function(e) {
                e.preventDefault();
                fetch('/admin/notifications/mark-all-read', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                }).then(() => {
                    location.reload();
                });
            });
        }
    });
</script>
