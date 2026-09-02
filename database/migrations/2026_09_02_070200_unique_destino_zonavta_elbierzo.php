<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1:1 zona de venta ↔ destino SENASA (El Bierzo).
 * destino.zonavta_id ya existía; se refuerza unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasTable('destino')) {
            return;
        }

        $existe = DB::select('SHOW INDEX FROM destino WHERE Key_name = ?', ['uq_destino_zonavta']);
        if ($existe !== []) {
            return;
        }

        Schema::table('destino', function (Blueprint $table) {
            $table->unique('zonavta_id', 'uq_destino_zonavta');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasTable('destino')) {
            return;
        }

        $existe = DB::select('SHOW INDEX FROM destino WHERE Key_name = ?', ['uq_destino_zonavta']);
        if ($existe === []) {
            return;
        }

        Schema::table('destino', function (Blueprint $table) {
            $table->dropUnique('uq_destino_zonavta');
        });
    }
};
