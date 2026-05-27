<?php
// database/seeders/SpecializationSeeder.php

namespace Database\Seeders;

use App\Models\Specialization;
use Illuminate\Database\Seeder;

class SpecializationSeeder extends Seeder
{
    public function run(): void
    {
        $specializations = [
            [ 'name' => 'حلاقة +لحية '],
            ['name' => 'حلاقة لحية'],
            [ 'name' => 'حلاقة شعر', ],
            ['name' => 'صبغ شعر'],
            [ 'name' => 'صبغ لحية'],
            [ 'name' => ' تصفيف شعر(سيشوار)'],
            [ 'name' => ' تركيب شعر'],
            ['name' => 'كيراتين شعر'],
            [ 'name' => 'حلاقة كلاسيكية'],
            ['name' => 'حلاقة قص شعر'],

        ];

        foreach ($specializations as $spec) {
            Specialization::updateOrCreate(
                ['name' => $spec['name']],
            );
        }
    }
}
