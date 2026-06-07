<?php
// app/Http/Resources/UserResource.php (نسخة when)

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // البيانات الأساسية
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'role' => $this->getRoleNames()->first(),
            'is_active' => $this->is_active,
            'avatar' => $this->getFirstMediaUrl('avatar'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'token' => $this->token,
            'notifications_enabled' => (bool) $this->notifications_enabled,
        ];
    }
}
