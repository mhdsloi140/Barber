<?php
// database/migrations/xxxx_add_services_columns_to_appointments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'services')) {
                $table->json('services')->nullable()->after('service_id');
            }
            if (!Schema::hasColumn('appointments', 'services_details')) {
                $table->json('services_details')->nullable()->after('services');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['services', 'services_details']);
        });
    }
};
