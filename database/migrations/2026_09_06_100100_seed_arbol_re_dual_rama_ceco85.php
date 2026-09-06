<?php

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_CuentaExcepcion;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Piloto gastronomía CC 85: dual-rama en árboles RE de BIYEMAS / KANDIKO / REBISCO.
 * Rama A = nivel auto actual. Rama B = hdattilo ≤5M, mbmendez ≥5M+0.01.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion_nivel')
            || ! Schema::hasColumn('arbolaprobacion_nivel', 'rama')
            || ! Schema::hasTable('arbolaprobacion_cuenta_excepcion')) {
            return;
        }

        $cc = Centrocosto::query()->where('codigo', '85')->first();
        if (! $cc) {
            return;
        }
        $ccId = (int) $cc->id;

        $hdattilo = Usuario::query()->where('usuario', 'hdattilo')->value('id');
        $mbmendez = Usuario::query()->where('usuario', 'mbmendez')->value('id');
        if (! $hdattilo || ! $mbmendez) {
            return;
        }

        $arbolIds = Arbolaprobacion::query()
            ->where('tipoarbol', 'Requisiciones')
            ->whereIn('empresa_id', [1, 2, 3])
            ->pluck('id', 'empresa_id');

        if ($arbolIds->isEmpty()) {
            return;
        }

        $monedaId = (int) (DB::table('arbolaprobacion_nivel')
            ->whereIn('arbolaprobacion_id', $arbolIds->values()->all())
            ->where('centrocosto_id', $ccId)
            ->value('moneda_id') ?: 1);

        foreach ($arbolIds as $empresaId => $arbolId) {
            $arbolId = (int) $arbolId;
            $empresaId = (int) $empresaId;

            // Marcar niveles actuales del CC 85 como Rama A (si aún no tienen rama).
            Arbolaprobacion_Nivel::query()
                ->where('arbolaprobacion_id', $arbolId)
                ->where('centrocosto_id', $ccId)
                ->whereNull('rama')
                ->update(['rama' => 'A']);

            // Si no hay ningún nivel A (caso raro), crear auto APROBADA.
            $tieneA = Arbolaprobacion_Nivel::query()
                ->where('arbolaprobacion_id', $arbolId)
                ->where('centrocosto_id', $ccId)
                ->where('rama', 'A')
                ->exists();
            if (! $tieneA) {
                Arbolaprobacion_Nivel::query()->create([
                    'arbolaprobacion_id' => $arbolId,
                    'centrocosto_id' => $ccId,
                    'nivel' => 1,
                    'usuario_id' => null,
                    'desdemonto' => 0,
                    'hastamonto' => '999999999999999999.9999',
                    'moneda_id' => $monedaId,
                    'documento_estado_al_aprobar' => 'APROBADA',
                    'doble_aprobacion' => 'N',
                    'rama' => 'A',
                ]);
            } else {
                // Ordenamiento: Rama A auto queda siempre como nivel 1.
                Arbolaprobacion_Nivel::query()
                    ->where('arbolaprobacion_id', $arbolId)
                    ->where('centrocosto_id', $ccId)
                    ->where('rama', 'A')
                    ->update(['nivel' => 1]);
            }

            // Rama B: limpiar previas del piloto e insertar EN COMPRAS + firmantes.
            Arbolaprobacion_Nivel::query()
                ->where('arbolaprobacion_id', $arbolId)
                ->where('centrocosto_id', $ccId)
                ->where('rama', 'B')
                ->delete();

            Arbolaprobacion_Nivel::query()->create([
                'arbolaprobacion_id' => $arbolId,
                'centrocosto_id' => $ccId,
                'nivel' => 1,
                'usuario_id' => null,
                'desdemonto' => 0,
                'hastamonto' => '999999999999999999.9999',
                'moneda_id' => $monedaId,
                'documento_estado_al_aprobar' => 'EN COMPRAS',
                'doble_aprobacion' => 'N',
                'rama' => 'B',
            ]);

            Arbolaprobacion_Nivel::query()->create([
                'arbolaprobacion_id' => $arbolId,
                'centrocosto_id' => $ccId,
                'nivel' => 2,
                'usuario_id' => (int) $hdattilo,
                'desdemonto' => 0,
                'hastamonto' => 5000000,
                'moneda_id' => $monedaId,
                'documento_estado_al_aprobar' => 'APROBADA',
                'doble_aprobacion' => 'N',
                'rama' => 'B',
            ]);

            Arbolaprobacion_Nivel::query()->create([
                'arbolaprobacion_id' => $arbolId,
                'centrocosto_id' => $ccId,
                'nivel' => 2,
                'usuario_id' => (int) $mbmendez,
                'desdemonto' => 5000000.01,
                'hastamonto' => '999999999999999999.9999',
                'moneda_id' => $monedaId,
                'documento_estado_al_aprobar' => 'APROBADA',
                'doble_aprobacion' => 'N',
                'rama' => 'B',
            ]);

            $cuentaId = Cuentacontable::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', '115010001')
                ->value('id');
            if (! $cuentaId) {
                continue;
            }

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
    }

    public function down(): void
    {
        if (! Schema::hasTable('arbolaprobacion_nivel')
            || ! Schema::hasColumn('arbolaprobacion_nivel', 'rama')) {
            return;
        }

        $ccId = (int) (Centrocosto::query()->where('codigo', '85')->value('id') ?: 0);
        if ($ccId <= 0) {
            return;
        }

        $arbolIds = Arbolaprobacion::query()
            ->where('tipoarbol', 'Requisiciones')
            ->whereIn('empresa_id', [1, 2, 3])
            ->pluck('id');

        Arbolaprobacion_Nivel::query()
            ->whereIn('arbolaprobacion_id', $arbolIds)
            ->where('centrocosto_id', $ccId)
            ->where('rama', 'B')
            ->delete();

        Arbolaprobacion_Nivel::query()
            ->whereIn('arbolaprobacion_id', $arbolIds)
            ->where('centrocosto_id', $ccId)
            ->where('rama', 'A')
            ->update(['rama' => null]);

        if (Schema::hasTable('arbolaprobacion_cuenta_excepcion')) {
            Arbolaprobacion_CuentaExcepcion::query()
                ->whereIn('arbolaprobacion_id', $arbolIds)
                ->where('centrocosto_id', $ccId)
                ->delete();
        }
    }
};
