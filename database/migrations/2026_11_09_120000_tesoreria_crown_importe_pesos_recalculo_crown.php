<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - tesoreria_crown_boveda.importe_pesos: retiro de dólares / euros valorizado en pesos
 *   (FIFO de Anita en los importados; cambio del día en los nativos del ERP).
 * - tesoreria_crown_jornada.recalculo_pendiente: un cambio en la jornada obliga a
 *   rehacer VIII y poder público desde ese día en adelante.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (Schema::hasTable('tesoreria_crown_boveda') && ! Schema::hasColumn('tesoreria_crown_boveda', 'importe_pesos')) {
            Schema::table('tesoreria_crown_boveda', function (Blueprint $table) {
                $table->decimal('importe_pesos', 18, 2)->nullable()->after('confirmado_at');
            });
        }
        if (Schema::hasTable('tesoreria_crown_jornada') && ! Schema::hasColumn('tesoreria_crown_jornada', 'recalculo_pendiente')) {
            Schema::table('tesoreria_crown_jornada', function (Blueprint $table) {
                $table->boolean('recalculo_pendiente')->default(false)->after('caja_final_viii');
                $table->index(['sala_id', 'recalculo_pendiente']);
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (Schema::hasTable('tesoreria_crown_jornada') && Schema::hasColumn('tesoreria_crown_jornada', 'recalculo_pendiente')) {
            Schema::table('tesoreria_crown_jornada', function (Blueprint $table) {
                $table->dropIndex(['sala_id', 'recalculo_pendiente']);
                $table->dropColumn('recalculo_pendiente');
            });
        }
        if (Schema::hasTable('tesoreria_crown_boveda') && Schema::hasColumn('tesoreria_crown_boveda', 'importe_pesos')) {
            Schema::table('tesoreria_crown_boveda', function (Blueprint $table) {
                $table->dropColumn('importe_pesos');
            });
        }
    }
};
