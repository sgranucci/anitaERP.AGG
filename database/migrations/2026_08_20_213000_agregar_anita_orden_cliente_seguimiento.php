<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `movscli` de Anita se identifica por (movsc_cliente, movsc_orden). Sin guardar ese orden,
 * una nota editada en Anita entra como renglón nuevo y la versión vieja queda para siempre
 * (9 casos detectados el 20/08/2026). Con el orden la sincronización actualiza en su lugar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cliente_seguimiento', 'anita_orden')) {
            return;
        }

        Schema::table('cliente_seguimiento', function (Blueprint $table) {
            $table->unsignedInteger('anita_orden')->nullable()->after('cliente_id');
            $table->index(['cliente_id', 'anita_orden'], 'idx_cliente_seguimiento_anita_orden');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cliente_seguimiento', 'anita_orden')) {
            return;
        }

        Schema::table('cliente_seguimiento', function (Blueprint $table) {
            $table->dropIndex('idx_cliente_seguimiento_anita_orden');
            $table->dropColumn('anita_orden');
        });
    }
};
