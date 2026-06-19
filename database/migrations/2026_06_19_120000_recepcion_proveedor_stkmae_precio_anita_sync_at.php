<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor', 'stkmae_precio_anita_sync_at')) {
                $table->timestamp('stkmae_precio_anita_sync_at')->nullable()->after('origen_carga');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor', 'stkmae_precio_anita_sync_at')) {
                $table->dropColumn('stkmae_precio_anita_sync_at');
            }
        });
    }
};
