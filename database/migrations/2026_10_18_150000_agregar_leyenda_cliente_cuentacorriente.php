<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leyenda en CC clientes: renglón de ajuste deuda/ficha (sin venta ni cobranza).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cliente_cuentacorriente')) {
            return;
        }
        if (Schema::hasColumn('cliente_cuentacorriente', 'leyenda')) {
            return;
        }

        Schema::table('cliente_cuentacorriente', function (Blueprint $table) {
            $table->string('leyenda', 120)->nullable()->after('empresa_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cliente_cuentacorriente')) {
            return;
        }
        if (! Schema::hasColumn('cliente_cuentacorriente', 'leyenda')) {
            return;
        }

        Schema::table('cliente_cuentacorriente', function (Blueprint $table) {
            $table->dropColumn('leyenda');
        });
    }
};
