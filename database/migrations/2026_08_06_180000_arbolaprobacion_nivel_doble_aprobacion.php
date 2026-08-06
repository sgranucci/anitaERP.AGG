<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        if (! Schema::hasColumn('arbolaprobacion_nivel', 'doble_aprobacion')) {
            Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
                $table->char('doble_aprobacion', 1)->default('N')->after('documento_estado_al_aprobar');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('arbolaprobacion_nivel')
            && Schema::hasColumn('arbolaprobacion_nivel', 'doble_aprobacion')) {
            Schema::table('arbolaprobacion_nivel', function (Blueprint $table) {
                $table->dropColumn('doble_aprobacion');
            });
        }
    }
};
