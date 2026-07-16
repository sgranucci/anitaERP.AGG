<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acelera UPDATE de tasaretencion al cargar padrón ARBA Ret (cuit + vigencia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('padron_iibb_arba', function (Blueprint $table) {
            $table->index(['cuit', 'desdefecha', 'hastafecha'], 'padron_iibb_arba_cuit_vigencia_index');
        });
    }

    public function down(): void
    {
        Schema::table('padron_iibb_arba', function (Blueprint $table) {
            $table->dropIndex('padron_iibb_arba_cuit_vigencia_index');
        });
    }
};
