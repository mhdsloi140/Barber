<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {

     
        $this->call([
            RoleSeeder::class,      // 1. أولاً: إنشاء الأدوار
            CenterSeeder::class,     // 2. ثانياً: إنشاء أصحاب الصالونات
            // BarberSeeder::class,  // 3. ثالثاً: إنشاء الحلاقين (اختياري)
            ClientSeeder::class,     // 4. رابعاً: إنشاء الزبائن
        ]);


    }
}
