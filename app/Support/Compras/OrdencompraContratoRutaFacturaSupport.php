<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Stock\Articulo;
use Carbon\Carbon;
use RuntimeException;

/**
 * Ruta de facturación e imputación contable de un contrato / OC abierta.
 *
 * Define si las facturas del contrato exigen recepción COM y, si van sin recepción,
 * de dónde sale la cuenta DEBE del neto (artículos de la OC o cuenta del contrato).
 */
final class OrdencompraContratoRutaFacturaSupport
{
    public const IMPUTACION_ARTICULOS = 'articulos';

    public const IMPUTACION_MANUAL = 'manual';

    /** @return list<string> */
    public static function imputaciones(): array
    {
        return [
            self::IMPUTACION_ARTICULOS,
            self::IMPUTACION_MANUAL,
        ];
    }

    public static function etiquetaImputacion(string $imputacion): string
    {
        return match ($imputacion) {
            self::IMPUTACION_ARTICULOS => 'Cuenta de los artículos de la OC',
            self::IMPUTACION_MANUAL => 'Cuenta indicada en el contrato',
            default => $imputacion,
        };
    }

    /**
     * @return array{
     *     es_contrato: bool,
     *     vigente: bool,
     *     aplica: bool,
     *     requiere_recepcion: bool,
     *     imputacion: string|null,
     *     cuentacontable_id: int,
     *     vigencia_desde: string|null,
     *     vigencia_hasta: string|null
     * }
     */
    public static function resolver(?Ordencompra $oc, ?string $fechaYmd = null): array
    {
        $vacio = [
            'es_contrato' => false,
            'vigente' => false,
            'aplica' => false,
            'requiere_recepcion' => true,
            'imputacion' => null,
            'cuentacontable_id' => 0,
            'vigencia_desde' => null,
            'vigencia_hasta' => null,
        ];

        if (! $oc || ! (bool) ($oc->es_contrato ?? false)) {
            return $vacio;
        }

        $fecha = self::aCarbon($fechaYmd) ?? Carbon::today();
        $desde = self::aCarbon($oc->contrato_vigencia_desde ?? null);
        $hasta = self::aCarbon($oc->contrato_vigencia_hasta ?? null);
        $vigente = true;
        if ($desde instanceof Carbon && $fecha->lt($desde->copy()->startOfDay())) {
            $vigente = false;
        }
        if ($hasta instanceof Carbon && $fecha->gt($hasta->copy()->endOfDay())) {
            $vigente = false;
        }

        $requiere = (bool) ($oc->contrato_requiere_recepcion ?? true);
        $imputacion = self::normalizarImputacion($oc->contrato_imputacion_contable ?? null);
        $cuentaId = 0;
        if ($requiere) {
            $imputacion = null;
        } elseif ($imputacion === self::IMPUTACION_MANUAL) {
            $cuentaId = (int) ($oc->contrato_cuentacontable_id ?? 0);
        }

        return [
            'es_contrato' => true,
            'vigente' => $vigente,
            'aplica' => $vigente,
            'requiere_recepcion' => $requiere,
            'imputacion' => $imputacion,
            'cuentacontable_id' => $cuentaId,
            'vigencia_desde' => $desde?->format('Y-m-d'),
            'vigencia_hasta' => $hasta?->format('Y-m-d'),
        ];
    }

    public static function aplicaSinRecepcion(?Ordencompra $oc, ?string $fechaYmd = null): bool
    {
        $ruta = self::resolver($oc, $fechaYmd);

        return $ruta['aplica'] && ! $ruta['requiere_recepcion'];
    }

    public static function aplicaConRecepcion(?Ordencompra $oc, ?string $fechaYmd = null): bool
    {
        $ruta = self::resolver($oc, $fechaYmd);

        return $ruta['aplica'] && $ruta['requiere_recepcion'];
    }

    public static function imputacionManual(?Ordencompra $oc, ?string $fechaYmd = null): bool
    {
        $ruta = self::resolver($oc, $fechaYmd);

        return $ruta['aplica']
            && ! $ruta['requiere_recepcion']
            && $ruta['imputacion'] === self::IMPUTACION_MANUAL;
    }

    public static function imputacionArticulos(?Ordencompra $oc, ?string $fechaYmd = null): bool
    {
        $ruta = self::resolver($oc, $fechaYmd);

        return $ruta['aplica']
            && ! $ruta['requiere_recepcion']
            && $ruta['imputacion'] === self::IMPUTACION_ARTICULOS;
    }

    public static function cuentaManualId(?Ordencompra $oc, ?string $fechaYmd = null): int
    {
        $ruta = self::resolver($oc, $fechaYmd);
        if (! $ruta['aplica'] || $ruta['requiere_recepcion'] || $ruta['imputacion'] !== self::IMPUTACION_MANUAL) {
            return 0;
        }

        return (int) ($ruta['cuentacontable_id'] ?? 0);
    }

    public static function cuentaDebeNetoManual(?Ordencompra $oc, int $cuentaLineaId, ?string $fechaYmd = null): int
    {
        if ($cuentaLineaId > 0) {
            return $cuentaLineaId;
        }

        return self::cuentaManualId($oc, $fechaYmd);
    }

    /**
     * Completa la cuenta DEBE del neto con la del contrato si el renglón viene vacío.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function rellenarCuentaManualEnLineas(?Ordencompra $oc, array $lineas, ?string $fechaYmd = null): array
    {
        $cuentaId = self::cuentaManualId($oc, $fechaYmd);
        if ($cuentaId <= 0) {
            return $lineas;
        }

        foreach ($lineas as $i => $linea) {
            if ((int) ($linea['cuentacontabledebe_id'] ?? 0) <= 0) {
                $lineas[$i]['cuentacontabledebe_id'] = $cuentaId;
            }
        }

        return $lineas;
    }

    public static function normalizarImputacion(mixed $valor): string
    {
        $v = strtolower(trim((string) $valor));
        if (in_array($v, self::imputaciones(), true)) {
            return $v;
        }

        return self::IMPUTACION_ARTICULOS;
    }

    /**
     * Prorratea el neto de la factura sobre las cuentas de compra/gasto de los artículos de la OC.
     *
     * @return list<array{cuentacontable_id:int, importe:float, centrocosto_id:int, observacion:string}>
     */
    public static function lineasDebeNetoDesdeArticulosOc(
        Ordencompra $oc,
        float $importeNeto,
        int $empresaId,
        int $centrocostoDefault,
        string $observacion,
    ): array {
        $importeNeto = round($importeNeto, 2);
        if ($importeNeto <= 0) {
            return [];
        }

        $oc->loadMissing(['ordencompra_articulos.articulos.articulo_cuentacontables']);
        $lineasOc = $oc->ordencompra_articulos;
        if ($lineasOc->isEmpty()) {
            throw new RuntimeException(
                'El contrato imputa el neto con las cuentas de los artículos de la OC, pero la orden no tiene renglones. '
                .'Cargue artículos en la OC o indique una cuenta a imputar en el contrato.'
            );
        }

        $monedaRef = (int) ($lineasOc->first()->moneda_id ?: 1);
        /** @var array<string, array{cuentacontable_id:int, centrocosto_id:int, importe:float}> $agrupado */
        $agrupado = [];

        foreach ($lineasOc as $linea) {
            if (! $linea instanceof Ordencompra_Articulo) {
                continue;
            }
            $cant = (float) ($linea->cantidad ?? 0);
            if ($cant <= 0) {
                continue;
            }
            $base = OrdencompraTotalesCabecera::importeLineaEnMonedaReferencia(
                $monedaRef,
                (int) ($linea->moneda_id ?: $monedaRef),
                $cant,
                (float) ($linea->precio ?? 0),
                (float) ($linea->cotizacion ?? 1),
            );
            if ($base <= 0) {
                continue;
            }

            $articulo = $linea->articulos;
            $ctaId = self::cuentaCompraOGastoArticulo($articulo, $empresaId);
            if ($ctaId <= 0) {
                $etiqueta = trim((string) ($articulo->sku ?? '').' '.(string) ($articulo->descripcion ?? ''));
                throw new RuntimeException(
                    'El artículo '.($etiqueta !== '' ? '«'.$etiqueta.'»' : 'id '.$linea->articulo_id)
                    .' de la OC no tiene cuenta contable de compras/gastos para imputar la factura del contrato.'
                );
            }

            $ccId = (int) ($linea->centrocostodestino_id ?: $oc->centrocosto_id ?: $centrocostoDefault);
            $clave = $ctaId.'|'.$ccId;
            if (! isset($agrupado[$clave])) {
                $agrupado[$clave] = [
                    'cuentacontable_id' => $ctaId,
                    'centrocosto_id' => $ccId,
                    'importe' => 0.0,
                ];
            }
            $agrupado[$clave]['importe'] += $base;
        }

        if ($agrupado === []) {
            throw new RuntimeException(
                'No se pudo armar la imputación del neto desde los artículos de la OC (sin importes). '
                .'Revise los renglones o indique una cuenta a imputar en el contrato.'
            );
        }

        $totalBase = round(array_sum(array_column($agrupado, 'importe')), 2);
        if ($totalBase <= 0) {
            throw new RuntimeException(
                'Los artículos de la OC no tienen importe para prorratear el neto de la factura del contrato.'
            );
        }

        $lineas = [];
        $asignado = 0.0;
        $items = array_values($agrupado);
        $ultimo = count($items) - 1;
        foreach ($items as $i => $row) {
            if ($i === $ultimo) {
                $importe = round($importeNeto - $asignado, 2);
            } else {
                $importe = round($importeNeto * ($row['importe'] / $totalBase), 2);
                $asignado += $importe;
            }
            if ($importe <= 0) {
                continue;
            }
            $lineas[] = [
                'cuentacontable_id' => $row['cuentacontable_id'],
                'importe' => $importe,
                'centrocosto_id' => $row['centrocosto_id'] > 0 ? $row['centrocosto_id'] : $centrocostoDefault,
                'observacion' => $observacion,
            ];
        }

        return $lineas;
    }

    public static function cuentaCompraOGastoArticulo(?Articulo $articulo, int $empresaId): int
    {
        if (! $articulo) {
            return 0;
        }

        $articulo->loadMissing('articulo_cuentacontables');
        foreach (['COMPRAS', 'GASTOS'] as $tipo) {
            $cuentaGrid = $articulo->articulo_cuentacontables
                ?->first(fn ($row) => (int) $row->empresa_id === $empresaId
                    && strtoupper((string) $row->tipoimputacion) === $tipo);
            if ($cuentaGrid && (int) $cuentaGrid->cuentacontable_id > 0) {
                return (int) $cuentaGrid->cuentacontable_id;
            }
        }

        return (int) ($articulo->cuentacontablecompra_id ?? 0);
    }

    private static function aCarbon(mixed $valor): ?Carbon
    {
        if ($valor instanceof Carbon) {
            return $valor->copy()->startOfDay();
        }
        if ($valor instanceof \DateTimeInterface) {
            return Carbon::instance($valor)->startOfDay();
        }
        $txt = trim((string) $valor);
        if ($txt === '') {
            return null;
        }

        try {
            return Carbon::parse($txt)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
