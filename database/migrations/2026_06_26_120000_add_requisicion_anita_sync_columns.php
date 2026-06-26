<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicion', function (Blueprint $table) {
            if (! Schema::hasColumn('requisicion', 'anita_sync_estado')) {
                $table->string('anita_sync_estado', 30)->nullable()->after('estado');
            }
            if (! Schema::hasColumn('requisicion', 'anita_sync_error')) {
                $table->text('anita_sync_error')->nullable()->after('anita_sync_estado');
            }
            if (! Schema::hasColumn('requisicion', 'anita_sync_at')) {
                $table->timestamp('anita_sync_at')->nullable()->after('anita_sync_error');
            }
        });

        Schema::table('requisicion_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('requisicion_articulo', 'anita_nro_interno')) {
                $table->unsignedInteger('anita_nro_interno')->nullable()->after('capex_id');
            }
            if (! Schema::hasColumn('requisicion_articulo', 'anita_nro_orden')) {
                $table->unsignedSmallInteger('anita_nro_orden')->nullable()->after('anita_nro_interno');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisicion', function (Blueprint $table) {
            if (Schema::hasColumn('requisicion', 'anita_sync_at')) {
                $table->dropColumn('anita_sync_at');
            }
            if (Schema::hasColumn('requisicion', 'anita_sync_error')) {
                $table->dropColumn('anita_sync_error');
            }
            if (Schema::hasColumn('requisicion', 'anita_sync_estado')) {
                $table->dropColumn('anita_sync_estado');
            }
        });

        Schema::table('requisicion_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('requisicion_articulo', 'anita_nro_orden')) {
                $table->dropColumn('anita_nro_orden');
            }
            if (Schema::hasColumn('requisicion_articulo', 'anita_nro_interno')) {
                $table->dropColumn('anita_nro_interno');
            }
        });
    }
};
