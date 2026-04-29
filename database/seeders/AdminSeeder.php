<?php

namespace Database\Seeders;

use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
      $user=User::create([
        'name'=>'admin',
        'phone'=>'0930641701',
        'password'=>Hash::make('12345678'),
        'role'=>'admin',
      ]);
        $user->assignRole('admin');
    }
}
