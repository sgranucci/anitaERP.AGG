<?php

use App\Models\Configuracion\Arbolaprobacion_Nivel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TIPO_ARBOL = 'Ordenes de compra';

    private const CC_GASTRONOMIA = '85';

    private const ESTADO_ARBOL = 'Activo';

    private const ESTADO_OC_AL_APROBAR = 'APROBADA';

    /** @var array<string, array{empresa_codigo: string, usuario: string, nombre_arbol: string}> */
    private const EMPRESAS = [
        'BIYEMAS' => [
            'empresa_codigo' => '1',
            'usuario' => 'ddominguez',
            'nombre_arbol' => 'Ordenes de compra — GASTRONOMIA (BIYEMAS)',
        ],
        'KANDIKO' => [
            'empresa_codigo' => '2',
            'usuario' => 'mmoskaluc',
            'nombre_arbol' => 'Ordenes de compra — GASTRONOMIA (KANDIKO)',
        ],
        'REBISCO' => [
            'empresa_codigo' => '3',
            'usuario' => 'wchavez',
            'nombre_arbol' => 'Ordenes de compra — GASTRONOMIA (REBISCO)',
        ],
    ];

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $centrocostoId = (int) DB::table('centrocosto')->where('codigo', self::CC_GASTRONOMIA)->value('id');
        if ($centrocostoId <= 0) {
            throw new \RuntimeException('Árbol OC GASTRONOMIA: no existe centro de costo código '.self::CC_GASTRONOMIA.'.');
        }

        $sectorGastroId = (int) DB::table('sector_legajocompra')
            ->whereRaw('UPPER(TRIM(nombre)) = ?', ['GASTRONOMIA'])
            ->value('id');
        if ($sectorGastroId <= 0) {
            throw new \RuntimeException('Árbol OC GASTRONOMIA: no existe sector GASTRONOMIA.');
        }

        $sectorDestinoId = (int) DB::table('sector_legajocompra')
            ->whereRaw('UPPER(TRIM(nombre)) = ?', ['CUENTAS A PAGAR'])
            ->value('id');
        if ($sectorDestinoId <= 0) {
            throw new \RuntimeException('Árbol OC GASTRONOMIA: no existe sector CUENTAS A PAGAR.');
        }

        $monedaPesosId = (int) DB::table('moneda')->where('nombre', 'PESOS')->value('id');
        if ($monedaPesosId <= 0) {
            throw new \RuntimeException('Árbol OC GASTRONOMIA: no existe moneda PESOS.');
        }

        $now = now()->toDateTimeString();

        foreach (self::EMPRESAS as $clave => $cfg) {
            $empresaId = (int) DB::table('empresa')->where('codigo', $cfg['empresa_codigo'])->value('id');
            if ($empresaId <= 0) {
                throw new \RuntimeException('Árbol OC GASTRONOMIA: no se encontró empresa '.$clave.' (código '.$cfg['empresa_codigo'].').');
            }

            $usuarioId = (int) DB::table('usuario')->where('usuario', $cfg['usuario'])->value('id');
            if ($usuarioId <= 0) {
                throw new \RuntimeException('Árbol OC GASTRONOMIA '.$clave.': usuario '.$cfg['usuario'].' inexistente.');
            }

            $arbol = DB::table('arbolaprobacion')
                ->where('tipoarbol', self::TIPO_ARBOL)
                ->where('empresa_id', $empresaId)
                ->whereNull('deleted_at')
                ->first();

            $payloadCabecera = [
                'nombre' => $cfg['nombre_arbol'],
                'tipoarbol' => self::TIPO_ARBOL,
                'empresa_id' => $empresaId,
                'recordatorio' => 'N',
                'diasinrespuesta' => 0,
                'diavencimientorecordatorio' => 0,
                'estado' => self::ESTADO_ARBOL,
                'oc_disparar_arbol_al_alta' => 'N',
                'oc_sector_cambio_centrocosto_id' => $centrocostoId,
                'oc_sector_disparo_aprobacion_id' => $sectorGastroId,
                'oc_sector_destino_aprobacion_id' => $sectorDestinoId,
                'updated_at' => $now,
            ];

            if ($arbol) {
                $arbolId = (int) $arbol->id;
                DB::table('arbolaprobacion')->where('id', $arbolId)->update($payloadCabecera);
            } else {
                $arbolId = (int) DB::table('arbolaprobacion')->insertGetId(array_merge($payloadCabecera, [
                    'created_at' => $now,
                ]));
            }

            DB::table('arbolaprobacion_nivel')
                ->where('arbolaprobacion_id', $arbolId)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            Arbolaprobacion_Nivel::create([
                'arbolaprobacion_id' => $arbolId,
                'nivel' => 1,
                'centrocosto_id' => $centrocostoId,
                'usuario_id' => $usuarioId,
                'desdemonto' => 0,
                'hastamonto' => 0,
                'moneda_id' => $monedaPesosId,
                'documento_estado_al_aprobar' => self::ESTADO_OC_AL_APROBAR,
            ]);
        }
    }

    public function down(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $now = now()->toDateTimeString();

        foreach (self::EMPRESAS as $cfg) {
            $empresaId = (int) DB::table('empresa')->where('codigo', $cfg['empresa_codigo'])->value('id');
            if ($empresaId <= 0) {
                continue;
            }

            $arbol = DB::table('arbolaprobacion')
                ->where('tipoarbol', self::TIPO_ARBOL)
                ->where('empresa_id', $empresaId)
                ->whereNull('deleted_at')
                ->first();

            if (! $arbol) {
                continue;
            }

            DB::table('arbolaprobacion_nivel')
                ->where('arbolaprobacion_id', $arbol->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            DB::table('arbolaprobacion')
                ->where('id', $arbol->id)
                ->update(['deleted_at' => $now, 'updated_at' => $now]);
        }
    }
};
