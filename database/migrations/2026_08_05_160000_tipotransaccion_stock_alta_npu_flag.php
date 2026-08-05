<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flag + tipo: ingreso de stock que genera NPU al confirmar (ajuste / lab / histórico).
 */
return new class extends Migration
{
    private const ABREVIATURA = 'NPUAL';

    private const NOMBRE = 'Alta NPU - Ajuste/Lab/Histórico';

    public function up(): void
    {
        Schema::table('tipotransaccion_stock', function (Blueprint $table) {
            if (! Schema::hasColumn('tipotransaccion_stock', 'alta_npu')) {
                $table->boolean('alta_npu')->default(false)->after('baja_npu');
            }
        });

        $existeId = (int) (DB::table('tipotransaccion_stock')
            ->where('abreviatura', self::ABREVIATURA)
            ->value('id') ?? 0);

        $payload = [
            'nombre' => self::NOMBRE,
            'operacion' => 'E',
            'signo' => 1,
            'estado' => 'A',
            'maneja_contabilidad' => 0,
            'requiere_aprobacion' => 0,
            'aviso_opcional' => 0,
            'destino_bien_uso' => 0,
            'origen_bien_uso' => 0,
            'baja_npu' => 0,
            'alta_npu' => 1,
            'deleted_at' => null,
            'updated_at' => now(),
        ];

        if ($existeId > 0) {
            DB::table('tipotransaccion_stock')->where('id', $existeId)->update($payload);
        } else {
            DB::table('tipotransaccion_stock')->insert(array_merge($payload, [
                'abreviatura' => self::ABREVIATURA,
                'created_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('tipotransaccion_stock')
            ->where('abreviatura', self::ABREVIATURA)
            ->update([
                'alta_npu' => 0,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        Schema::table('tipotransaccion_stock', function (Blueprint $table) {
            if (Schema::hasColumn('tipotransaccion_stock', 'alta_npu')) {
                $table->dropColumn('alta_npu');
            }
        });
    }
};
