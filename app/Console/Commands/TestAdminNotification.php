<?php
// app/Console/Commands/TestAdminNotification.php

namespace App\Console\Commands;

use App\Services\Notification\FirebaseNotificationService;
use Illuminate\Console\Command;

class TestAdminNotification extends Command
{
    protected $signature = 'notify:admin {title?} {body?}';
    protected $description = 'إرسال إشعار تجريبي للمديرين';

    public function handle(FirebaseNotificationService $firebaseService)
    {
        $title = $this->argument('title') ?? ' إشعار تجريبي';
        $body = $this->argument('body') ?? 'هذا إشعار تجريبي من النظام';

        $result = $firebaseService->sendToAdmins($title, $body);

        $this->info("تم إرسال الإشعار إلى {$result['sent']} من أصل {$result['total']} مدير");
    }
}
