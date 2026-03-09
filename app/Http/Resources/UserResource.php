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

            // التوكن إذا وجد
            $this->when(isset($this->token), [
                'token' => $this->token,
                'token_type' => 'Bearer',
            ]),

            // بيانات المدير
            // $this->when($this->hasRole('admin'), [
            //     'permissions' => $this->getAllPermissions()->pluck('name'),
            //     'users_count' => \App\Models\User::count(),
            // ]),

            // بيانات صاحب الصالون
            $this->when($this->hasRole('salon_owner') && $this->relationLoaded('ownedSalon'), [
                'salon' => [
                    'id' => $this->ownedSalon?->id,
                    'name' => $this->ownedSalon?->name,
                    'address' => $this->ownedSalon?->address,
                    'phone' => $this->ownedSalon?->phone,
                    'description' => $this->ownedSalon?->description,
                    'latitude' => $this->ownedSalon?->latitude,
                    'longitude' => $this->ownedSalon?->longitude,
                    'is_active' => $this->ownedSalon?->is_active,
                    'barbers_count' => $this->ownedSalon?->barbers()->count(),
                    'services_count' => $this->ownedSalon?->services()->count(),
                ],
            ]),

            // بيانات الحلاق
            $this->when($this->hasRole('barber') && $this->relationLoaded('salons'), [
                'salons' => $this->salons->map(fn($salon) => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'phone' => $salon->phone,
                ]),
                'appointments_today' => $this->barberAppointments()
                    ->whereDate('appointment_date', today())
                    ->count(),
            ]),

            // بيانات الزبون
            $this->when($this->hasRole('customer'), [
                'appointments_count' => $this->customerAppointments()->count(),
                'upcoming_count' => $this->customerAppointments()
                    ->where('appointment_date', '>=', today())
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->count(),
            ]),
        ];
    }
}
