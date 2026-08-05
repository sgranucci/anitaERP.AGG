<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Caja\Cuentacaja;
use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use InvalidArgumentException;

/**
 * Convierte líneas del preview de asientos del proceso en payload para AsientoRepository (bridge ctamov).
 */
final class CierreJornadaProcesoAsientosGrabacionSupport
{
    public const DESCRIPCION_ASIENTO = 'Venta gastronomia';

    public const DESCRIPCION_ASIENTO_COMPENSACION_FONDO_FIJO = 'Reducion de Fondo fijo';

    public const CODIGO_ASIENTO_COMPENSACION_FONDO_FIJO = 'compensacion_efectivo_no_facturado';

    private const TOLERANCIA_CUADRE = 0.02;

    /**
     * @param  list<array<string, mixed>>  $asientosPreview
     * @return list<array{
     *   codigo:string,
     *   titulo:string,
     *   payload:array<string,mixed>,
     *   resumen_debe:float,
     *   resumen_haber:float
     * }>
     */
    public static function armarPayloadsAsientos(
        array $asientosPreview,
        int $empresaId,
        array $configContable,
        string $fecha,
        string $fechaJornada,
    ): array {
        if ($asientosPreview === []) {
            throw new InvalidArgumentException('No hay asientos para grabar.');
        }

        /** @var array<int, int> $cacheCuentas */
        $cacheCuentas = [];
        $out = [];

        foreach ($asientosPreview as $asiento) {
            $codigo = (string) ($asiento['codigo'] ?? '');
            $titulo = (string) ($asiento['titulo'] ?? $codigo);
            $lineas = $asiento['lineas'] ?? [];
            if (! is_array($lineas) || $lineas === []) {
                continue;
            }

            $observacion = $codigo === self::CODIGO_ASIENTO_COMPENSACION_FONDO_FIJO
                ? self::DESCRIPCION_ASIENTO_COMPENSACION_FONDO_FIJO
                : self::DESCRIPCION_ASIENTO;

            $payloadLineas = self::payloadDesdeLineasPreview(
                $lineas,
                $empresaId,
                $configContable,
                $cacheCuentas,
                $observacion,
            );

            if ($payloadLineas['cuentacontable_ids'] === []) {
                continue;
            }

            $debe = round(array_sum(array_map(
                static fn ($d) => is_numeric($d) ? (float) $d : 0.,
                $payloadLineas['debes'],
            )), 2);
            $haber = round(array_sum(array_map(
                static fn ($h) => is_numeric($h) ? (float) $h : 0.,
                $payloadLineas['haberes'],
            )), 2);

            if (abs($debe - $haber) > self::TOLERANCIA_CUADRE) {
                throw new InvalidArgumentException(
                    'El asiento «'.$titulo.'» no cuadra (debe '.$debe.' vs haber '.$haber.').',
                );
            }

            $out[] = [
                'codigo' => $codigo,
                'titulo' => $titulo,
                'resumen_debe' => $debe,
                'resumen_haber' => $haber,
                'payload' => array_merge($payloadLineas, [
                    'empresa_id' => $empresaId,
                    'fecha' => $fecha,
                    'observacion' => $observacion,
                ]),
            ];
        }

        if ($out === []) {
            throw new InvalidArgumentException('Ningún asiento del preview tiene líneas grabables.');
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  array<int, int>  $cacheCuentas
     * @return array{
     *   cuentacontable_ids:list<int|string>,
     *   debes:list<float|string>,
     *   haberes:list<float|string>,
     *   moneda_ids:list<int>,
     *   centrocosto_ids:list<int|null>,
     *   cotizaciones:list<float>,
     *   observaciones:list<string>
     * }
     */
    public static function payloadDesdeLineasPreview(
        array $lineas,
        int $empresaId,
        array $configContable,
        array &$cacheCuentas,
        string $descripcionLinea = self::DESCRIPCION_ASIENTO,
    ): array {
        $cuentacontableIds = [];
        $debes = [];
        $haberes = [];
        $monedaIds = [];
        $centrocostoIds = [];
        $cotizaciones = [];
        $observaciones = [];

        foreach ($lineas as $ln) {
            if (($ln['tipo'] ?? '') === 'info') {
                continue;
            }

            $debe = round((float) ($ln['debe'] ?? 0), 2);
            $haber = round((float) ($ln['haber'] ?? 0), 2);
            if (abs($debe) <= 0.0001 && abs($haber) <= 0.0001) {
                continue;
            }

            $cuentaRefId = (int) ($ln['cuenta_id'] ?? 0);
            if ($cuentaRefId <= 0) {
                throw new InvalidArgumentException(
                    'Línea sin cuenta: '.trim((string) ($ln['concepto'] ?? '')),
                );
            }

            $cuentacontableId = self::resolverCuentacontableId(
                $cuentaRefId,
                $empresaId,
                $configContable,
                $cacheCuentas,
            );

            $cuentacontableIds[] = $cuentacontableId;
            $debes[] = $debe > 0.0001 ? $debe : '';
            $haberes[] = $haber > 0.0001 ? $haber : '';
            $monedaIds[] = 1;
            $centrocostoIds[] = CierreJornadaProcesoAsientosCentrocostoSupport::resolverCentrocostoIdParaCuentacontableOError(
                $cuentacontableId,
            );
            $cotizaciones[] = 1.;
            $observaciones[] = $descripcionLinea;
        }

        return [
            'cuentacontable_ids' => $cuentacontableIds,
            'debes' => $debes,
            'haberes' => $haberes,
            'moneda_ids' => $monedaIds,
            'centrocosto_ids' => $centrocostoIds,
            'cotizaciones' => $cotizaciones,
            'observaciones' => $observaciones,
        ];
    }

    /**
     * @param  array<int, int>  $cache
     */
    public static function resolverCuentacontableId(
        int $cuentaRefId,
        int $empresaId,
        array $configContable,
        array &$cache,
    ): int {
        if (isset($cache[$cuentaRefId])) {
            return $cache[$cuentaRefId];
        }

        foreach (['cuenta_ventas', 'cuenta_iva', 'cuenta_ventas_kiosco', 'cuenta_fondo_fijo_maquinas', 'cuenta_diferencia_caja'] as $base) {
            $cfgId = (int) ($configContable[$base.'_id'] ?? 0);
            if ($cfgId > 0 && $cfgId === $cuentaRefId) {
                $cache[$cuentaRefId] = $cfgId;

                return $cfgId;
            }
        }

        // Medios de cobro del preview usan id de cuentacaja; puede coincidir numéricamente con cuentacontable.id.
        $caja = Cuentacaja::query()
            ->with('cuentacontables:id,codigo,nombre,empresa_id')
            ->find($cuentaRefId);

        if ($caja !== null) {
            $cuentacontableId = (int) ($caja->cuentacontables?->id ?? $caja->cuentacontable_id ?? 0);
            if ($cuentacontableId <= 0) {
                throw new InvalidArgumentException(
                    'No se pudo resolver cuenta contable para cuenta caja id '.$cuentaRefId.'.',
                );
            }
            $cuentacontableId = CierreJornadaProcesoAsientosCuentaSupport::aplicarRemapCuentacontableMedioCobro(
                $cuentacontableId,
                $empresaId,
                (int) $caja->id,
            );
            $cache[$cuentaRefId] = $cuentacontableId;

            return $cuentacontableId;
        }

        $contable = Cuentacontable::query()
            ->where('id', $cuentaRefId)
            ->where('empresa_id', $empresaId)
            ->value('id');

        if ($contable !== null) {
            $cache[$cuentaRefId] = (int) $contable;

            return (int) $contable;
        }

        throw new InvalidArgumentException(
            'No se pudo resolver cuenta contable para cuenta caja/contable id '.$cuentaRefId.'.',
        );
    }

    /**
     * Metadatos de asientos grabados en el snapshot del proceso (codigo/titulo por asiento_id).
     *
     * @return array<int, array{codigo: string, titulo: string}>
     */
    public static function mapaAsientosGrabadosPorEmpresaJornada(int $empresaId, string $fechaJornada): array
    {
        $jornadaId = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->value('id');

        if ($jornadaId === null) {
            return [];
        }

        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();

        if ($snapshot === null) {
            return [];
        }

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $asientos = $payload['asientos_proceso_grabacion']['asientos'] ?? [];
        if (! is_array($asientos)) {
            return [];
        }

        $mapa = [];
        foreach ($asientos as $item) {
            if (! is_array($item)) {
                continue;
            }
            $asientoId = (int) ($item['asiento_id'] ?? 0);
            if ($asientoId <= 0) {
                continue;
            }
            $mapa[$asientoId] = [
                'codigo' => trim((string) ($item['codigo'] ?? '')),
                'titulo' => trim((string) ($item['titulo'] ?? '')),
            ];
        }

        return $mapa;
    }
}
