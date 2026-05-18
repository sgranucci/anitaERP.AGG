<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un mismo código de punto de venta no puede repetirse por empresa (índice único en BD).
     * Los duplicados se eliminan de forma permanente conservando el id menor.
     */
    public function up(): void
    {
        if (! Schema::hasTable('puntoventa')) {
            return;
        }

        $seen = [];
        foreach (DB::table('puntoventa')->orderBy('id')->get() as $row) {
            $key = $row->empresa_id.'|'.trim((string) $row->codigo);
            if (isset($seen[$key])) {
                DB::table('puntoventa')->where('id', $row->id)->delete();
            } else {
                $seen[$key] = true;
            }
        }

        Schema::table('puntoventa', function (Blueprint $table) {
            $table->unique(['empresa_id', 'codigo'], 'uq_puntoventa_empresa_codigo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('puntoventa')) {
            return;
        }

        Schema::table('puntoventa', function (Blueprint $table) {
            $table->dropUnique('uq_puntoventa_empresa_codigo');
        });
    }
};
