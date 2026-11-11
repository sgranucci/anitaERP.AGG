<?php

use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temporal AGG: OV de Gastronomía (CC 85) sin nivel en el árbol.
 * Habilita aprobación desde Impuestos: Griselda Dominick (Gri) o Guillermo Surace.
 */
return new class extends Migration
{
    private const TIPO_ARBOL = 'Ordenes de venta';

    private const CC_GASTRONOMIA = '85';

    /** @var list<string> */
    private const USUARIOS = ['gdominick', 'gsurace'];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (! Schema::hasTable('arbolaprobacion') || ! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        $arbol = DB::table('arbolaprobacion')
            ->where('tipoarbol', self::TIPO_ARBOL)
            ->whereRaw('UPPER(TRIM(estado)) = ?', ['ACTIVO'])
            ->orderBy('id')
            ->first();

        if (! $arbol) {
            throw new \RuntimeException('Árbol OV CC 85 impuestos: no hay árbol de órdenes de venta activo.');
        }

        $centrocostoId = (int) DB::table('centrocosto')->where('codigo', self::CC_GASTRONOMIA)->value('id');
        if ($centrocostoId <= 0) {
            throw new \RuntimeException('Árbol OV CC 85 impuestos: no existe centro de costo código '.self::CC_GASTRONOMIA.'.');
        }

        $monedaId = (int) (DB::table('moneda')->whereRaw('UPPER(TRIM(nombre)) = ?', ['PESOS'])->value('id') ?? 0);
        if ($monedaId <= 0) {
            throw new \RuntimeException('Árbol OV CC 85 impuestos: no se encontró la moneda PESOS.');
        }

        $now = now()->toDateTimeString();

        foreach (self::USUARIOS as $usuario) {
            $usuarioId = (int) DB::table('usuario')->where('usuario', $usuario)->value('id');
            if ($usuarioId <= 0) {
                throw new \RuntimeException('Árbol OV CC 85 impuestos: usuario '.$usuario.' inexistente.');
            }

            $ya = DB::table('arbolaprobacion_nivel')
                ->where('arbolaprobacion_id', $arbol->id)
                ->where('centrocosto_id', $centrocostoId)
                ->where('nivel', 1)
                ->where('usuario_id', $usuarioId)
                ->where('moneda_id', $monedaId)
                ->exists();

            if ($ya) {
                continue;
            }

            Arbolaprobacion_Nivel::create([
                'arbolaprobacion_id' => (int) $arbol->id,
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

        DB::table('arbolaprobacion')->where('id', $arbol->id)->update(['updated_at' => $now]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        $arbolId = (int) (DB::table('arbolaprobacion')
            ->where('tipoarbol', self::TIPO_ARBOL)
            ->whereRaw('UPPER(TRIM(estado)) = ?', ['ACTIVO'])
            ->orderBy('id')
            ->value('id') ?? 0);

        $centrocostoId = (int) (DB::table('centrocosto')->where('codigo', self::CC_GASTRONOMIA)->value('id') ?? 0);
        if ($arbolId <= 0 || $centrocostoId <= 0) {
            return;
        }

        $usuarioIds = DB::table('usuario')
            ->whereIn('usuario', self::USUARIOS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($usuarioIds === []) {
            return;
        }

        $nivelIds = DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', $arbolId)
            ->where('centrocosto_id', $centrocostoId)
            ->where('nivel', 1)
            ->whereIn('usuario_id', $usuarioIds)
            ->pluck('id');

        foreach ($nivelIds as $nivelId) {
            $modelo = Arbolaprobacion_Nivel::query()->find($nivelId);
            if ($modelo) {
                $modelo->delete();
            }
        }
    }
};
