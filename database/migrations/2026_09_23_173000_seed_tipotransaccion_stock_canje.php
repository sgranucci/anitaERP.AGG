<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo CANJE (operación C): canje color/talle u otros ajustes con líneas + y − en el mismo comprobante.
 * Aplica a todas las instalaciones (AGG, Ferli, Bierzo, Interforming, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tipotransaccion_stock')) {
            return;
        }

        $now = now();
        $payload = [
            'nombre' => 'Canje de stock',
            'abreviatura' => 'CANJE',
            'operacion' => 'C',
            'signo' => 1,
            'estado' => 'A',
            'requiere_aprobacion' => 0,
            'aviso_opcional' => 0,
            'maneja_contabilidad' => 0,
            'updated_at' => $now,
        ];

        $existeId = (int) (DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'CANJE')
            ->value('id') ?? 0);

        if ($existeId > 0) {
            $update = $payload;
            if (Schema::hasColumn('tipotransaccion_stock', 'deleted_at')) {
                $update['deleted_at'] = null;
            }
            DB::table('tipotransaccion_stock')->where('id', $existeId)->update($update);

            return;
        }

        DB::table('tipotransaccion_stock')->insert(array_merge($payload, [
            'created_at' => $now,
        ]));
    }

    public function down(): void
    {
        if (! Schema::hasTable('tipotransaccion_stock')) {
            return;
        }

        $q = DB::table('tipotransaccion_stock')->where('abreviatura', 'CANJE');
        if (Schema::hasColumn('tipotransaccion_stock', 'deleted_at')) {
            $q->update(['deleted_at' => now(), 'updated_at' => now()]);
        } else {
            $q->delete();
        }
    }
};
