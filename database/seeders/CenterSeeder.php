<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CenterSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $ownerRole = Role::firstOrCreate([
                'name' => 'salon_owner',
                'guard_name' => 'api',
            ]);

            $centers = [
                [
                    'name' => 'أحمد محمد',
                    'phone' => '0994050583',
                    'salon_name' => 'صالون الجمال',
                    'address' => 'الرياض، حي النور',
                ],
                [
                    'name' => 'خالد عبدالله',
                    'phone' => '0994050580',
                    'salon_name' => 'صالون الأناقة',
                    'address' => 'جدة، شارع التحلية',
                ],
                [
                    'name' => 'محمد علي',
                    'phone' => '0994050505',
                    'salon_name' => 'صالون الفخامة',
                    'address' => 'الدمام، حي الشاطئ',
                ],
            ];

            foreach ($centers as $centerData) {
                $user = User::updateOrCreate(
                    ['phone' => $centerData['phone']],
                    [
                        'name' => $centerData['name'],
                        'password' => Hash::make('password'),
                        'role' => 'salon_owner',
                    ]
                );

                $user->syncRoles([$ownerRole]);

                Salon::updateOrCreate(
                    ['owner_id' => $user->id],
                    [
                        'name' => $centerData['salon_name'],
                        'address' => $centerData['address'],
                        'phone' => $centerData['phone'],
                        'is_active' => true,
                    ]
                );


            }
        });
    }
}
