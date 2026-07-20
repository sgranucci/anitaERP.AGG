<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Stock\Articulo;
use App\Models\Stock\Tipoarticulo;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Services\Configuracion\ImpuestoService;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Illuminate\Support\Facades\DB;

/**
 * Importe de líneas de menú con cigarrillos (imp. interno) y desglose contable en facturas mixtas.
 */
final class CierreJornadaVentasCigarrillosSupport
{
    private const TASA_IVA_DEFAULT = 21.0;

    /** @var array<int, bool> */
    private static array $formulaContieneCigarrilloCache = [];

    private static ?int $tipoArticuloCigarrilloIdCache = null;

    private static bool $tipoArticuloCigarrilloIdResolved = false;

    /**
     * Suma importes firmados de renglones de menú cigarrillos (p. ej. V0950 @ precio > 0).
     */
    public static function importeLineasMenuCigarrillos(Venta $venta, int $empresaId): float
    {
        if ($empresaId <= 0) {
            return 0.0;
        }

        $venta->loadMissing(['venta_emisiones.articulos', 'tipotransacciones']);
        $signo = $venta->tipotransacciones->signo ?? GastronomiaVentaComprobanteSignoSupport::SIGNO_SUMA;
        $tipoCigarrilloId = self::tipoArticuloCigarrilloId();

        $precioMenuUnitario = 0.0;
        $packsInsumo = 0.0;

        foreach ($venta->venta_emisiones ?? [] as $emision) {
            if (! $emision instanceof Venta_Emision) {
                continue;
            }

            $articulo = $emision->articulos;
            if (! $articulo instanceof Articulo) {
                continue;
            }

            $cantidad = GastronomiaVentaComprobanteSignoSupport::cantidadLineaVenta(
                (float) ($emision->cantidad ?? 0),
                $signo,
            );
            $precio = (float) ($emision->precio ?? 0);

            if ($tipoCigarrilloId !== null
                && (int) $articulo->tipoarticulo_id === $tipoCigarrilloId) {
                $packsInsumo = round($packsInsumo + $cantidad, 4);
            }

            if ($precio > 0.0001 && self::articuloEsLineaMenuCigarrillos($articulo)) {
                $precioMenuUnitario = $precio;
            }
        }

        if (abs($packsInsumo) > 0.0001 && $precioMenuUnitario > 0.0001) {
            return round($packsInsumo * $precioMenuUnitario, 2);
        }

        $total = 0.0;
        foreach ($venta->venta_emisiones ?? [] as $emision) {
            if (! $emision instanceof Venta_Emision) {
                continue;
            }

            $precio = (float) ($emision->precio ?? 0);
            if ($precio <= 0.0001) {
                continue;
            }

            $articulo = $emision->articulos;
            if (! $articulo instanceof Articulo || ! self::articuloEsLineaMenuCigarrillos($articulo)) {
                continue;
            }

            $cantidad = GastronomiaVentaComprobanteSignoSupport::cantidadLineaVenta(
                (float) ($emision->cantidad ?? 0),
                $signo,
            );
            $total = round($total + round($cantidad * $precio, 2), 2);
        }

        return $total;
    }

    public static function importeLineasMenuCigarrillosPorVentaId(int $ventaId, int $empresaId): float
    {
        if ($ventaId <= 0 || $empresaId <= 0) {
            return 0.0;
        }

        $venta = Venta::query()->find($ventaId);

        return $venta !== null ? self::importeLineasMenuCigarrillos($venta, $empresaId) : 0.0;
    }

    /**
     * Imp. interno de cabecera o, si falta (p. ej. NC cigarrillos), inferido desde insumos tipo CIGARRILLO.
     */
    public static function resolverImpuestoInternoVenta(Venta $venta, int $empresaId, float $importeCigarrillos): float
    {
        $impuestoInterno = self::sumarImpuestoInternoCabecera($venta);
        if (abs($impuestoInterno) > 0.0001) {
            return self::firmarImpuestoInternoSegunComprobante($venta, $impuestoInterno);
        }

        if (abs($importeCigarrillos) <= 0.0001) {
            return 0.0;
        }

        return self::firmarImpuestoInternoSegunComprobante(
            $venta,
            self::inferirImpuestoInternoDesdeInsumos($venta, $empresaId, $importeCigarrillos),
        );
    }

    /**
     * Separa ventas gravadas / kiosco (cigarrillos) e IVA cuando la factura mezcla rubros.
     *
     * @return array{
     *   ventas_gravadas: float,
     *   ventas_kiosco: float,
     *   iva_normal: float,
     *   iva_cigarrillos: float
     * }
     */
    public static function desglosarImportesContables(
        float $totalFactura,
        float $impuestoInterno,
        float $importeCigarrillos,
        float $exento = 0.0,
    ): array {
        if (abs($totalFactura) <= 0.0001) {
            return self::importesVacios();
        }

        if (abs($impuestoInterno) <= 0.0001) {
            $base = self::desglosarBaseIvaConSigno($totalFactura, 0.0, $exento);

            return [
                'ventas_gravadas' => $base['gravado'],
                'ventas_kiosco' => 0.0,
                'iva_normal' => $base['iva'],
                'iva_cigarrillos' => 0.0,
            ];
        }

        $importeCig = self::normalizarImporteCigarrillos($totalFactura, $importeCigarrillos);
        if (abs($importeCig) <= 0.0001 && abs($impuestoInterno) > 0.0001) {
            $importeCig = $totalFactura;
        }

        $importeResto = round($totalFactura - $importeCig, 2);

        // El exento (venta sin IVA) se atribuye al tramo no-cigarrillos; si la factura es
        // toda cigarrillos, al tramo cigarrillos. Nunca se le calcula IVA.
        $restoTieneImporte = abs($importeResto) > 0.0001;
        $exentoResto = $restoTieneImporte ? $exento : 0.0;
        $exentoCig = $restoTieneImporte ? 0.0 : $exento;

        $baseCig = self::desglosarBaseIvaConSigno($importeCig, $impuestoInterno, $exentoCig);
        $baseResto = $restoTieneImporte
            ? self::desglosarBaseIvaConSigno($importeResto, 0.0, $exentoResto)
            : ['gravado' => 0.0, 'iva' => 0.0, 'neto_venta' => 0.0];

        return [
            'ventas_kiosco' => round($baseCig['gravado'] + $impuestoInterno, 2),
            'ventas_gravadas' => round($baseResto['gravado'], 2),
            'iva_cigarrillos' => round($baseCig['iva'], 2),
            'iva_normal' => round($baseResto['iva'], 2),
        ];
    }

    /**
     * @return array{gravado:float,iva:float,neto_venta:float}
     */
    public static function desglosarBaseIvaConSigno(float $total, float $impuestoInterno, float $exento = 0.0): array
    {
        $sign = $total >= 0 ? 1.0 : -1.0;
        $absTotal = abs($total);
        $absImpuestoInterno = abs($impuestoInterno);
        $netoVentas = round(max(0.0, $absTotal - $absImpuestoInterno), 2);
        // El exento es parte de la venta que NO lleva IVA (a ARCA el IVA va sobre el neto
        // gravado, no sobre el exento): se excluye de la base gravable y se imputa igual a
        // ventas. La base gravable = neto - impuesto interno - exento.
        $absExento = min($netoVentas, abs($exento));
        $baseGravable = round(max(0.0, $netoVentas - $absExento), 2);
        $gravadoIva = round($baseGravable / (1.0 + self::TASA_IVA_DEFAULT / 100.0), 2);
        $iva = round($baseGravable - $gravadoIva, 2);
        // Ventas (gravado + exento) = neto menos el IVA (el exento no genera IVA).
        $gravado = round($netoVentas - $iva, 2);

        return [
            'gravado' => round($sign * $gravado, 2),
            'iva' => round($sign * $iva, 2),
            'neto_venta' => round($sign * $netoVentas, 2),
        ];
    }

    /**
     * Exento de cabecera (venta_impuestos concepto "Exento"), con signo según el comprobante.
     */
    public static function resolverExentoVenta(Venta $venta): float
    {
        $exento = self::sumarExentoCabecera($venta);
        if (abs($exento) <= 0.0001) {
            return 0.0;
        }

        return self::firmarImpuestoInternoSegunComprobante($venta, $exento);
    }

    public static function resolverExentoVentaPorVentaId(int $ventaId): float
    {
        if ($ventaId <= 0) {
            return 0.0;
        }

        $venta = Venta::query()->find($ventaId);

        return $venta !== null ? self::resolverExentoVenta($venta) : 0.0;
    }

    public static function articuloEsLineaMenuCigarrillos(Articulo $articulo): bool
    {
        $descripcion = mb_strtoupper(trim((string) ($articulo->descripcion ?? '')));
        if ($descripcion !== '' && str_contains($descripcion, 'CIGARRILLO')) {
            return true;
        }

        $formulaId = (int) ($articulo->formula ?? 0);

        return $formulaId > 0 && self::formulaContieneInsumoCigarrillo($formulaId);
    }

    private static function formulaContieneInsumoCigarrillo(int $formulaId): bool
    {
        if (array_key_exists($formulaId, self::$formulaContieneCigarrilloCache)) {
            return self::$formulaContieneCigarrilloCache[$formulaId];
        }

        $tipoCigarrilloId = self::tipoArticuloCigarrilloId();
        if ($tipoCigarrilloId === null) {
            return self::$formulaContieneCigarrilloCache[$formulaId] = false;
        }

        $contiene = DB::table('formula_articulo_hijo as fh')
            ->join('articulo as a', 'a.id', '=', 'fh.articulo_id')
            ->where('fh.formula_articulo_id', $formulaId)
            ->where('a.tipoarticulo_id', $tipoCigarrilloId)
            ->exists();

        if (! $contiene) {
            $contiene = DB::table('formula_articulo_hijo as fh')
                ->where('fh.formula_articulo_id', $formulaId)
                ->whereNotNull('fh.formula_hija_id')
                ->whereExists(function ($q) use ($tipoCigarrilloId) {
                    $q->select(DB::raw(1))
                        ->from('formula_articulo_hijo as fh2')
                        ->join('articulo as a2', 'a2.id', '=', 'fh2.articulo_id')
                        ->whereColumn('fh2.formula_articulo_id', 'fh.formula_hija_id')
                        ->where('a2.tipoarticulo_id', $tipoCigarrilloId);
                })
                ->exists();
        }

        return self::$formulaContieneCigarrilloCache[$formulaId] = $contiene;
    }

    private static function sumarImpuestoInternoCabecera(Venta $venta): float
    {
        $venta->loadMissing('venta_impuestos');
        $total = 0.0;
        foreach ($venta->venta_impuestos ?? [] as $vi) {
            $concepto = mb_strtolower((string) ($vi->concepto ?? ''));
            if (str_contains($concepto, 'intern')) {
                $total += (float) ($vi->importe ?? 0);
            }
        }

        return round($total, 2);
    }

    private static function sumarExentoCabecera(Venta $venta): float
    {
        $venta->loadMissing('venta_impuestos');
        $total = 0.0;
        foreach ($venta->venta_impuestos ?? [] as $vi) {
            $concepto = mb_strtolower((string) ($vi->concepto ?? ''));
            if (str_contains($concepto, 'exent')) {
                $total += (float) ($vi->importe ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * venta_impuestos persiste imp. interno en valor absoluto; alinear signo con NC (tipotransaccion Resta).
     */
    private static function firmarImpuestoInternoSegunComprobante(Venta $venta, float $impuestoInterno): float
    {
        if (abs($impuestoInterno) <= 0.0001) {
            return 0.0;
        }

        $venta->loadMissing('tipotransacciones');
        $signo = $venta->tipotransacciones->signo ?? null;
        $abs = abs($impuestoInterno);

        return GastronomiaVentaComprobanteSignoSupport::esNotaCreditoSigno($signo) ? -$abs : $abs;
    }

    private static function inferirImpuestoInternoDesdeInsumos(Venta $venta, int $empresaId, float $importeCigarrillos): float
    {
        $venta->loadMissing(['venta_emisiones.articulos']);
        $tipoCigarrilloId = self::tipoArticuloCigarrilloId();
        if ($tipoCigarrilloId === null) {
            return 0.0;
        }

        /** @var ImpuestoService $impuestoService */
        $impuestoService = app(ImpuestoService::class);
        $fechaFactura = self::fechaFacturaVenta($venta);
        $coef = 0.0;

        foreach ($venta->venta_emisiones ?? [] as $emision) {
            if (! $emision instanceof Venta_Emision) {
                continue;
            }

            $articulo = $emision->articulos;
            if (! $articulo instanceof Articulo || (int) $articulo->tipoarticulo_id !== $tipoCigarrilloId) {
                continue;
            }

            $coefInsumo = $impuestoService->coeficienteImpuestoInternoArticulo(
                (int) $articulo->id,
                [],
                $empresaId,
                $fechaFactura,
            );
            if ($coefInsumo > $coef) {
                $coef = $coefInsumo;
            }
        }

        if ($coef <= 0.0001) {
            return 0.0;
        }

        return round($importeCigarrillos * $coef, 2);
    }

    private static function fechaFacturaVenta(Venta $venta): string
    {
        foreach (['fechafactura', 'fecha', 'fechajornada'] as $campo) {
            $valor = trim((string) ($venta->{$campo} ?? ''));
            if ($valor !== '') {
                return substr($valor, 0, 10);
            }
        }

        return date('Y-m-d');
    }

    private static function tipoArticuloCigarrilloId(): ?int
    {
        if (self::$tipoArticuloCigarrilloIdResolved) {
            return self::$tipoArticuloCigarrilloIdCache;
        }

        self::$tipoArticuloCigarrilloIdResolved = true;
        $nombre = mb_strtoupper(trim((string) config('facturacion.IMPUESTO_INTERNO_TIPOARTICULO_NOMBRE', 'CIGARRILLO')));
        if ($nombre === '') {
            return self::$tipoArticuloCigarrilloIdCache = null;
        }

        $id = Tipoarticulo::query()->whereRaw('UPPER(nombre) = ?', [$nombre])->value('id');

        return self::$tipoArticuloCigarrilloIdCache = $id !== null ? (int) $id : null;
    }

    private static function normalizarImporteCigarrillos(float $totalFactura, float $importeCigarrillos): float
    {
        if (abs($importeCigarrillos) <= 0.0001) {
            return 0.0;
        }

        $sign = $totalFactura >= 0 ? 1.0 : -1.0;
        $importeCig = $importeCigarrillos;
        if ($sign < 0.0 && $importeCig > 0.0) {
            $importeCig = -abs($importeCig);
        } elseif ($sign > 0.0 && $importeCig < 0.0) {
            $importeCig = abs($importeCig);
        }

        if (abs($importeCig) > abs($totalFactura) + 0.02) {
            return round($totalFactura, 2);
        }

        return round($importeCig, 2);
    }

    /**
     * @return array{
     *   ventas_gravadas: float,
     *   ventas_kiosco: float,
     *   iva_normal: float,
     *   iva_cigarrillos: float
     * }
     */
    private static function importesVacios(): array
    {
        return [
            'ventas_gravadas' => 0.0,
            'ventas_kiosco' => 0.0,
            'iva_normal' => 0.0,
            'iva_cigarrillos' => 0.0,
        ];
    }
}
