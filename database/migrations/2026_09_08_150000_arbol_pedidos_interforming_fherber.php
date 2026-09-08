<?php

use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Árbol de aprobación de Pedidos (PE) para INTERFORMING: nivel 1 → fherber.
 */
return new class extends Migration
{
    private const TIPO_ARBOL = 'Pedidos';

    private const NOMBRE = 'Pedidos Interforming — aprobación F. Herber';

    private const USUARIO = 'fherber';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        if (! Schema::hasTable('arbolaprobacion') || ! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        $usuarioId = (int) DB::table('usuario')->where('usuario', self::USUARIO)->value('id');
        if ($usuarioId <= 0) {
            throw new RuntimeException('No existe el usuario '.self::USUARIO.' para el árbol de Pedidos.');
        }

        $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?? 0);
        if ($empresaId <= 0) {
            throw new RuntimeException('No hay empresa para asociar el árbol de Pedidos.');
        }

        $centrocostoId = (int) (DB::table('centrocosto')->orderBy('id')->value('id') ?? 0);
        if ($centrocostoId <= 0) {
            throw new RuntimeException('No hay centro de costo para el nivel del árbol de Pedidos.');
        }

        $monedaId = (int) (DB::table('moneda')->orderBy('id')->value('id') ?? 1);

        $now = now()->toDateTimeString();

        $arbol = DB::table('arbolaprobacion')
            ->where('tipoarbol', self::TIPO_ARBOL)
            ->orderBy('id')
            ->first();

        if ($arbol) {
            $arbolId = (int) $arbol->id;
            DB::table('arbolaprobacion')->where('id', $arbolId)->update([
                'nombre' => self::NOMBRE,
                'empresa_id' => $empresaId,
                'recordatorio' => 'N',
                'diasinrespuesta' => 0,
                'diavencimientorecordatorio' => 0,
                'estado' => 'ACTIVO',
                'updated_at' => $now,
            ]);
        } else {
            $arbolId = (int) DB::table('arbolaprobacion')->insertGetId([
                'nombre' => self::NOMBRE,
                'tipoarbol' => self::TIPO_ARBOL,
                'empresa_id' => $empresaId,
                'recordatorio' => 'N',
                'diasinrespuesta' => 0,
                'diavencimientorecordatorio' => 0,
                'estado' => 'ACTIVO',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $nivelesExistentes = DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', $arbolId)
            ->pluck('id');

        foreach ($nivelesExistentes as $nivelId) {
            $modelo = Arbolaprobacion_Nivel::query()->find($nivelId);
            if ($modelo) {
                $modelo->delete();
            }
        }

        Arbolaprobacion_Nivel::create([
            'arbolaprobacion_id' => $arbolId,
            'centrocosto_id' => $centrocostoId,
            'nivel' => 1,
            'usuario_id' => $usuarioId,
            'desdemonto' => 0,
            'hastamonto' => 0,
            'moneda_id' => $monedaId,
            'documento_estado_al_aprobar' => null,
            'doble_aprobacion' => 'N',
        ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        if (! Schema::hasTable('arbolaprobacion')) {
            return;
        }

        $arbolIds = DB::table('arbolaprobacion')
            ->where('tipoarbol', self::TIPO_ARBOL)
            ->where('nombre', self::NOMBRE)
            ->pluck('id');

        foreach ($arbolIds as $arbolId) {
            $nivelIds = DB::table('arbolaprobacion_nivel')
                ->where('arbolaprobacion_id', $arbolId)
                ->pluck('id');
            foreach ($nivelIds as $nivelId) {
                $modelo = Arbolaprobacion_Nivel::query()->find($nivelId);
                if ($modelo) {
                    $modelo->delete();
                }
            }
            DB::table('arbolaprobacion')->where('id', $arbolId)->delete();
        }
    }
};
