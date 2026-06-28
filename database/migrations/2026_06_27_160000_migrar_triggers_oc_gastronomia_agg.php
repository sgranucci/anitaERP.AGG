<?php

use App\Support\Configuracion\OcArbolTriggerCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TIPO_ARBOL = 'Ordenes de compra';

    /** @var array<string, string> */
    private const EMPRESAS = [
        '1' => 'BIYEMAS',
        '2' => 'KANDIKO',
        '3' => 'REBISCO',
    ];

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        if (! Schema::hasTable('arbolaprobacion_oc_trigger')) {
            return;
        }

        $sectorComprasId = (int) DB::table('sector_legajocompra')
            ->whereRaw('UPPER(TRIM(nombre)) = ?', ['COMPRAS'])
            ->value('id');
        $sectorGastroId = (int) DB::table('sector_legajocompra')
            ->whereRaw('UPPER(TRIM(nombre)) = ?', ['GASTRONOMIA'])
            ->value('id');
        $sectorDestinoId = (int) DB::table('sector_legajocompra')
            ->whereRaw('UPPER(TRIM(nombre)) = ?', ['CUENTAS A PAGAR'])
            ->value('id');
        $centrocostoId = (int) DB::table('centrocosto')->where('codigo', '85')->value('id');

        if ($sectorGastroId <= 0 || $centrocostoId <= 0) {
            return;
        }

        $now = now()->toDateTimeString();

        foreach (self::EMPRESAS as $codigoEmpresa => $nombreEmpresa) {
            $empresaId = (int) DB::table('empresa')->where('codigo', $codigoEmpresa)->value('id');
            if ($empresaId <= 0) {
                continue;
            }

            $arbolId = (int) DB::table('arbolaprobacion')
                ->where('tipoarbol', self::TIPO_ARBOL)
                ->where('empresa_id', $empresaId)
                ->whereNull('deleted_at')
                ->value('id');

            if ($arbolId <= 0) {
                continue;
            }

            $existe = DB::table('arbolaprobacion_oc_trigger')
                ->where('arbolaprobacion_id', $arbolId)
                ->where('evento', OcArbolTriggerCatalog::EVENTO_CAMBIO_SECTOR)
                ->whereNull('deleted_at')
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('arbolaprobacion_oc_trigger')->insert([
                'arbolaprobacion_id' => $arbolId,
                'nombre' => 'Gastronomía — cambio de sector ('.$nombreEmpresa.')',
                'tipo' => OcArbolTriggerCatalog::TIPO_EVENTO,
                'evento' => OcArbolTriggerCatalog::EVENTO_CAMBIO_SECTOR,
                'evaluador' => null,
                'sector_origen_id' => $sectorComprasId > 0 ? $sectorComprasId : null,
                'sector_destino_id' => $sectorGastroId,
                'centrocosto_circuito_id' => $centrocostoId,
                'documento_estado_al_aprobar' => 'APROBADA',
                'accion_final' => OcArbolTriggerCatalog::ACCION_CAMBIAR_SECTOR,
                'accion_final_sector_id' => $sectorDestinoId > 0 ? $sectorDestinoId : null,
                'accion_final_estado' => null,
                'prioridad' => 50,
                'anula_auto_aprobacion' => 'N',
                'reevaluar_en_actualizacion' => 'N',
                'activo' => 'S',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        if (! Schema::hasTable('arbolaprobacion_oc_trigger')) {
            return;
        }

        DB::table('arbolaprobacion_oc_trigger')
            ->where('evento', OcArbolTriggerCatalog::EVENTO_CAMBIO_SECTOR)
            ->where('nombre', 'like', 'Gastronomía — cambio de sector%')
            ->update(['deleted_at' => now()->toDateTimeString()]);
    }
};
