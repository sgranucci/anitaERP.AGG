<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta_anita_replica')) {
            return;
        }
        if (Schema::hasColumn('venta_anita_replica', 'archivos_estado')) {
            return;
        }

        Schema::table('venta_anita_replica', function (Blueprint $table) {
            $table->json('archivos_estado')->nullable()->after('payload_vencae');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta_anita_replica')) {
            return;
        }
        if (! Schema::hasColumn('venta_anita_replica', 'archivos_estado')) {
            return;
        }

        Schema::table('venta_anita_replica', function (Blueprint $table) {
            $table->dropColumn('archivos_estado');
        });
    }
};
