<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flag de comportamiento: tipo de transferencia igual a "con aviso/aprobación",
 * pero el usuario decide al grabar (modal) si envía o no el aviso.
 * Si elige No: la transferencia queda directa (confirmada, sin aviso ni aprobación).
 * Si elige Sí: usa el canal de aviso de aprobación del depósito destino.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tipotransaccion_stock')) {
            return;
        }

        Schema::table('tipotransaccion_stock', function (Blueprint $table) {
            if (! Schema::hasColumn('tipotransaccion_stock', 'aviso_opcional')) {
                $table->boolean('aviso_opcional')->default(false)->after('requiere_aprobacion');
            }
        });

        $existe = DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'TRAOP')
            ->whereNull('deleted_at')
            ->exists();

        if (! $existe) {
            DB::table('tipotransaccion_stock')->insert([
                'nombre' => 'Transferencia con aviso opcional',
                'abreviatura' => 'TRAOP',
                'operacion' => 'T',
                'signo' => 1,
                'estado' => 'A',
                'requiere_aprobacion' => false,
                'aviso_opcional' => true,
                'maneja_contabilidad' => false,
                'destino_bien_uso' => false,
                'origen_bien_uso' => false,
                'baja_npu' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'TRAOP')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        Schema::table('tipotransaccion_stock', function (Blueprint $table) {
            if (Schema::hasColumn('tipotransaccion_stock', 'aviso_opcional')) {
                $table->dropColumn('aviso_opcional');
            }
        });
    }
};
