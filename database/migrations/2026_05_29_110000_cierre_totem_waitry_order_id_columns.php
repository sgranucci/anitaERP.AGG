<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cierre_totem_jornada_gastronomia')) {
            return;
        }

        Schema::table('cierre_totem_jornada_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'ticket_movimiento_id_anterior')) {
                $table->renameColumn('ticket_movimiento_id_anterior', 'waitry_order_id_anterior');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'ticket_movimiento_id_desde')) {
                $table->renameColumn('ticket_movimiento_id_desde', 'waitry_order_id_desde');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'ticket_movimiento_id_hasta')) {
                $table->renameColumn('ticket_movimiento_id_hasta', 'waitry_order_id_hasta');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'cantidad_pendiente_anita')) {
                $table->renameColumn('cantidad_pendiente_anita', 'cantidad_impagas_waitry');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'cantidad_canjeado_anita')) {
                $table->renameColumn('cantidad_canjeado_anita', 'cantidad_pagadas_waitry');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'cantidad_canjeado_erp')) {
                $table->renameColumn('cantidad_canjeado_erp', 'cantidad_facturadas_erp');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'total_montoticket')) {
                $table->renameColumn('total_montoticket', 'total_monto');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cierre_totem_jornada_gastronomia')) {
            return;
        }

        Schema::table('cierre_totem_jornada_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'waitry_order_id_anterior')) {
                $table->renameColumn('waitry_order_id_anterior', 'ticket_movimiento_id_anterior');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'waitry_order_id_desde')) {
                $table->renameColumn('waitry_order_id_desde', 'ticket_movimiento_id_desde');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'waitry_order_id_hasta')) {
                $table->renameColumn('waitry_order_id_hasta', 'ticket_movimiento_id_hasta');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'cantidad_impagas_waitry')) {
                $table->renameColumn('cantidad_impagas_waitry', 'cantidad_pendiente_anita');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'cantidad_pagadas_waitry')) {
                $table->renameColumn('cantidad_pagadas_waitry', 'cantidad_canjeado_anita');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'cantidad_facturadas_erp')) {
                $table->renameColumn('cantidad_facturadas_erp', 'cantidad_canjeado_erp');
            }
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'total_monto')) {
                $table->renameColumn('total_monto', 'total_montoticket');
            }
        });
    }
};
