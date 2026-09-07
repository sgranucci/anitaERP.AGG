<?php

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_CuentaExcepcion;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CC 85: allowlist + bebidas/tabaco; restaurar Rama B en árbol empresa 1 si faltaba.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ccId = (int) (Centrocosto::query()->where('codigo', '85')->value('id') ?: 0);
        if ($ccId <= 0) {
            return;
        }

        $hdattilo = (int) (DB::table('usuario')->where('usuario', 'hdattilo')->value('id') ?: 0);
        $mbmendez = (int) (DB::table('usuario')->where('usuario', 'mbmendez')->value('id') ?: 0);

        $arboles = Arbolaprobacion::query()
            ->where('tipoarbol', 'Requisiciones')
            ->whereIn('empresa_id', [1, 2, 3])
            ->get(['id', 'empresa_id']);

        foreach ($arboles as $arbol) {
            $empresaId = (int) $arbol->empresa_id;
            $arbolId = (int) $arbol->id;

            $cuentas = Cuentacontable::query()
                ->where('empresa_id', $empresaId)
                ->whereIn('codigo', ['115010001', '115010002', '115010003'])
                ->pluck('id');

            foreach ($cuentas as $cuentaId) {
                Arbolaprobacion_CuentaExcepcion::query()->updateOrCreate(
                    [
                        'arbolaprobacion_id' => $arbolId,
                        'centrocosto_id' => $ccId,
                        'empresa_id' => $empresaId,
                        'cuentacontable_id' => (int) $cuentaId,
                    ],
                    ['activo' => 'S']
                );
            }

            // Rama A auto APROBADA (nivel 1)
            $ramaA = Arbolaprobacion_Nivel::query()
                ->where('arbolaprobacion_id', $arbolId)
                ->where('centrocosto_id', $ccId)
                ->where('rama', 'A')
                ->first();
            if ($ramaA) {
                $ramaA->update([
                    'nivel' => 1,
                    'usuario_id' => null,
                    'documento_estado_al_aprobar' => 'APROBADA',
                    'desdemonto' => 0,
                    'hastamonto' => '999999999999999999.9999',
                    'moneda_id' => (int) ($ramaA->moneda_id ?: 1),
                ]);
            }

            $tieneB = Arbolaprobacion_Nivel::query()
                ->where('arbolaprobacion_id', $arbolId)
                ->where('centrocosto_id', $ccId)
                ->where('rama', 'B')
                ->exists();

            if (! $tieneB && $hdattilo > 0 && $mbmendez > 0) {
                Arbolaprobacion_Nivel::query()->create([
                    'arbolaprobacion_id' => $arbolId,
                    'centrocosto_id' => $ccId,
                    'nivel' => 1,
                    'usuario_id' => null,
                    'desdemonto' => 0,
                    'hastamonto' => '999999999999999999.9999',
                    'moneda_id' => 1,
                    'documento_estado_al_aprobar' => 'EN COMPRAS',
                    'doble_aprobacion' => 'N',
                    'rama' => 'B',
                ]);
                Arbolaprobacion_Nivel::query()->create([
                    'arbolaprobacion_id' => $arbolId,
                    'centrocosto_id' => $ccId,
                    'nivel' => 2,
                    'usuario_id' => $hdattilo,
                    'desdemonto' => 0,
                    'hastamonto' => 5000000,
                    'moneda_id' => 1,
                    'documento_estado_al_aprobar' => 'APROBADA',
                    'doble_aprobacion' => 'N',
                    'rama' => 'B',
                ]);
                Arbolaprobacion_Nivel::query()->create([
                    'arbolaprobacion_id' => $arbolId,
                    'centrocosto_id' => $ccId,
                    'nivel' => 2,
                    'usuario_id' => $mbmendez,
                    'desdemonto' => 5000000.01,
                    'hastamonto' => '999999999999999999.9999',
                    'moneda_id' => 1,
                    'documento_estado_al_aprobar' => 'APROBADA',
                    'doble_aprobacion' => 'N',
                    'rama' => 'B',
                ]);
            }
        }
    }

    public function down(): void
    {
        // No borra allowlist ni niveles: corrección operativa.
    }
};
