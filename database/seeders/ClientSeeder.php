<?php
// database/seeders/ClientSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $clients = [
                [
                    'name' => 'أحمد محمود',
                    'phone' => '0994050123',
                ],
                [
                    'name' => 'عبدالله سعد',
                    'phone' => '0994050183',
                ],
                [
                    'name' => 'علي أحمد',
                    'phone' => '0994050823',
                ],
                [
                    'name' => 'محمد عمر',
                    'phone' => '0994050888',
                ],
                [
                    'name' => 'فهد عبدالعزيز',
                    'phone' => '0994050999',
                ]
            ];

            foreach ($clients as $client) {
                // إنشاء المستخدم (زبون)
                $user = User::create([
                    'name' => $client['name'],
                    'phone' => $client['phone'],
                    'password' => Hash::make('password'),
                    'role' => 'customer', // تخزين الدور في حقل role
                ]);

                // تعيين الدور باستخدام Spatie
                $user->assignRole('customer');

               
            }
        });
    }
}
