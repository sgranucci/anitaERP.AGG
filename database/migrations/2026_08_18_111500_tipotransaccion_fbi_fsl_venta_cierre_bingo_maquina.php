<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FBI (p-vtabingo) / FSL (p-vtamaquina): comprobantes internos exentos en ventas ERP
 * al cierre contable. + venta_id en rendiciones bingo/máquinas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->asegurarTipo('FBI', 'Factura bingo interna');
        $this->asegurarTipo('FSL', 'Factura sala maquinas');

        if (Schema::hasTable('rendicion_bingo_caja')
            && ! Schema::hasColumn('rendicion_bingo_caja', 'venta_id')) {
            Schema::table('rendicion_bingo_caja', function (Blueprint $table) {
                $table->unsignedBigInteger('venta_id')->nullable()->after('asiento_id');
                $table->foreign('venta_id')
                    ->references('id')
                    ->on('venta')
                    ->nullOnDelete();
                $table->index('venta_id');
            });
        }

        if (Schema::hasTable('rendicion_maquina')
            && ! Schema::hasColumn('rendicion_maquina', 'venta_id')) {
            Schema::table('rendicion_maquina', function (Blueprint $table) {
                $table->unsignedBigInteger('venta_id')->nullable()->after('asiento_id');
                $table->foreign('venta_id')
                    ->references('id')
                    ->on('venta')
                    ->nullOnDelete();
                $table->index('venta_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rendicion_bingo_caja')
            && Schema::hasColumn('rendicion_bingo_caja', 'venta_id')) {
            Schema::table('rendicion_bingo_caja', function (Blueprint $table) {
                $table->dropForeign(['venta_id']);
                $table->dropIndex(['venta_id']);
                $table->dropColumn('venta_id');
            });
        }

        if (Schema::hasTable('rendicion_maquina')
            && Schema::hasColumn('rendicion_maquina', 'venta_id')) {
            Schema::table('rendicion_maquina', function (Blueprint $table) {
                $table->dropForeign(['venta_id']);
                $table->dropIndex(['venta_id']);
                $table->dropColumn('venta_id');
            });
        }

        DB::table('tipotransaccion')
            ->whereIn('abreviatura', ['FBI', 'FSL'])
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    private function asegurarTipo(string $abrev, string $nombre): void
    {
        $existe = DB::table('tipotransaccion')
            ->where('abreviatura', $abrev)
            ->whereNull('deleted_at')
            ->exists();

        if ($existe) {
            return;
        }

        DB::table('tipotransaccion')->insert([
            'nombre' => $nombre,
            'operacion' => 'V',
            'operacionstock' => 'O',
            'abreviatura' => $abrev,
            'codigo' => $abrev,
            'signo' => 1,
            'estado' => 'A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
