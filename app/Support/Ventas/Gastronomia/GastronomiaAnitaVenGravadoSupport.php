<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Ventas\Venta;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;

/**
 * Neto gravado Anita (ven_gravado) alineado con impuestos ERP en gastronomía modo mínimo.
 */
final class GastronomiaAnitaVenGravadoSupport
{
    public const IMPORTE_CORTESIA_MINIMA = 0.01;

    public static function esCortesiaMinima(float $totalAbs): bool
    {
        $totalAbs = round(abs($totalAbs), 2);

        return abs($totalAbs - self::IMPORTE_CORTESIA_MINIMA) <= 0.005;
    }

    /**
     * @return array{monto: float, exento: float, gravado: float, iva: float}
     */
    public static function montosCabeceraCortesiaMinima(): array
    {
        return [
            'monto' => self::IMPORTE_CORTESIA_MINIMA,
            'exento' => self::IMPORTE_CORTESIA_MINIMA,
            'gravado' => 0.0,
            'iva' => 0.0,
        ];
    }

    /**
     * Normaliza cabecera Anita al importar: cortesía $0,01 → exento (sin gravado/IVA).
     *
     * @return array{total: float, gravado: float, exento: float, iva: float}
     */
    public static function montosCabeceraImportDesdeAnita(
        float $venMonto,
        float $venGravado,
        float $venExento,
        float $venIva,
    ): array {
        $total = round(abs($venMonto), 2);
        if (! self::esCortesiaMinima($total)) {
            return [
                'total' => $total,
                'gravado' => round($venGravado, 2),
                'exento' => round($venExento, 2),
                'iva' => round($venIva, 2),
            ];
        }

        $montos = self::montosCabeceraCortesiaMinima();

        return [
            'total' => $montos['monto'],
            'gravado' => $montos['gravado'],
            'exento' => $montos['exento'],
            'iva' => $montos['iva'],
        ];
    }

    /**
     * Impuestos ERP para importación de invitación/cortesía $0,01 (sin Gravado → obs ARCA 1427).
     *
     * @return list<array{concepto: string, baseimponible: float, tasa: float, importe: float, impuesto_id: int|null}>
     */
    public static function filasVentaImpuestoImportCortesiaMinima(): array
    {
        $monto = self::IMPORTE_CORTESIA_MINIMA;

        return [
            ['concepto' => 'Exento', 'baseimponible' => 0., 'tasa' => 0., 'importe' => $monto, 'impuesto_id' => 1],
            ['concepto' => 'Total', 'baseimponible' => 0., 'tasa' => 0., 'importe' => $monto, 'impuesto_id' => null],
        ];
    }

    /**
     * Alinea venta + data_cae antes de grabar en Anita (factura cortesía $0,01).
     *
     * @param  array<string, mixed>  $venta
     * @param  array<string, mixed>  $dataCAE
     */
    public static function aplicarCortesiaMinimaEnPayloadAnita(array &$venta, array &$dataCAE, bool $forzar = false): void
    {
        if (! $forzar && ! self::esCortesiaMinima((float) ($venta['total'] ?? 0))) {
            return;
        }

        $montos = self::montosCabeceraCortesiaMinima();
        $venta['total'] = $montos['monto'];
        $dataCAE['total'] = $montos['monto'];
        $dataCAE['exento'] = $montos['exento'];
        $dataCAE['nogravado'] = (float) ($dataCAE['nogravado'] ?? 0);
        $dataCAE['gravado'] = $montos['gravado'];
        $dataCAE['iva'] = $montos['iva'];
    }

    /**
     * @param  list<array{concepto?: string, importe?: float|int|string, baseimponible?: float|int|string}>  $conceptosTotales
     */
    public static function gravadoDesdeConceptosTotales(array $conceptosTotales, float $totalAbs): float
    {
        $gravadoAl = 0.0;
        $subtotalConcepto = 0.0;
        $iva = 0.0;
        $baseIva = 0.0;

        foreach ($conceptosTotales as $concepto) {
            $nombre = trim((string) ($concepto['concepto'] ?? ''));
            $importe = round((float) ($concepto['importe'] ?? 0), 2);
            $base = round((float) ($concepto['baseimponible'] ?? 0), 2);

            if (preg_match('/^Gravado/i', $nombre)) {
                $gravadoAl += $importe;
            } elseif (strcasecmp($nombre, 'Total Logistica') === 0) {
                // Bierzo: la logística grava e integra ven_gravado / importeGravado MTXCA.
                $gravadoAl += $importe;
            } elseif ($nombre === 'Subtotal') {
                $subtotalConcepto += $importe;
            } elseif (preg_match('/^Iva/i', $nombre)) {
                $iva += $importe;
                $baseIva += $base;
            }
        }

        $totalAbs = round(abs($totalAbs), 2);
        $esCortesiaMinima = abs($totalAbs - 0.01) <= 0.02;

        if ($gravadoAl > 0.) {
            return round($gravadoAl, 2);
        }

        if ($esCortesiaMinima) {
            return 0.0;
        }

        if ($baseIva > 0.) {
            return round($baseIva, 2);
        }

        if ($subtotalConcepto > 0. && $iva > 0.) {
            return round($subtotalConcepto, 2);
        }

        return 0.0;
    }

    /**
     * Actualiza ven_monto, ven_gravado, ven_impuesto1 y ven_exento en cabecera venta Anita (sin tocar vengrav).
     */
    public static function actualizarMontosCabeceraAnita(
        string $tipoAnita,
        string $letra,
        int $sucursal,
        int $numero,
        float $gravado,
        float $iva,
        float $exento,
        ?float $monto = null,
    ): void {
        if ($sucursal <= 0 || $numero <= 0 || trim($tipoAnita) === '') {
            throw new \InvalidArgumentException('Clave de comprobante Anita inválida.');
        }

        $api = new ApiAnita;
        $gravadoStr = number_format(round($gravado, 2), 2, '.', '');
        $ivaStr = number_format(round($iva, 2), 2, '.', '');
        $exentoStr = number_format(round($exento, 2), 2, '.', '');
        $valores = " ven_gravado = '".$gravadoStr."', ven_impuesto1 = '".$ivaStr."', ven_exento = '".$exentoStr."' ";
        if ($monto !== null) {
            $montoStr = number_format(round($monto, 2), 2, '.', '');
            $valores = " ven_monto = '".$montoStr."', ".$valores;
        }
        $where = " WHERE ven_sucursal = '".$sucursal."'"
            ." AND ven_tipo = '".addslashes($tipoAnita)."'"
            ." AND ven_nro = '".$numero."'"
            ." AND ven_letra = '".addslashes($letra)."'";

        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => 'venta',
            'sistema' => 'ventas',
            'valores' => $valores,
            'whereArmado' => $where,
        ], 'venta montos cabecera update', 'gastronomia.anita_ven_gravado.update');
    }

    /**
     * Actualiza solo ven_gravado (compatibilidad con llamadas puntuales).
     */
    public static function actualizarVenGravadoCabeceraAnita(
        string $tipoAnita,
        string $letra,
        int $sucursal,
        int $numero,
        float $gravado,
    ): void {
        if ($sucursal <= 0 || $numero <= 0 || trim($tipoAnita) === '') {
            throw new \InvalidArgumentException('Clave de comprobante Anita inválida.');
        }

        $api = new ApiAnita;
        $gravadoStr = number_format(round($gravado, 2), 2, '.', '');
        $where = " WHERE ven_sucursal = '".$sucursal."'"
            ." AND ven_tipo = '".addslashes($tipoAnita)."'"
            ." AND ven_nro = '".$numero."'"
            ." AND ven_letra = '".addslashes($letra)."'";

        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => 'venta',
            'sistema' => 'ventas',
            'valores' => " ven_gravado = '".$gravadoStr."' ",
            'whereArmado' => $where,
        ], 'venta ven_gravado update', 'gastronomia.anita_ven_gravado.update');
    }

    /**
     * Sincroniza gravado / IVA / exento de cabecera Anita desde montos ERP.
     *
     * @param  array{gravado?: float, iva?: float, exento?: float}  $montosErp
     * @return bool true si se actualizó Anita
     */
    public static function sincronizarMontosCabeceraDesdeVentaErp(
        Venta $venta,
        object $cabeceraAnita,
        array $montosErp,
        float $tolerancia = 0.02,
    ): bool {
        $totalErp = round(abs((float) ($montosErp['total'] ?? $venta->total ?? 0)), 2);
        $gravadoErp = round((float) ($montosErp['gravado'] ?? 0), 2);
        $ivaErp = round((float) ($montosErp['iva'] ?? 0), 2);
        $exentoErp = round((float) ($montosErp['exento'] ?? 0), 2);

        if (self::esCortesiaMinima($totalErp)) {
            $montosCortesia = self::montosCabeceraCortesiaMinima();
            $totalErp = $montosCortesia['monto'];
            $gravadoErp = $montosCortesia['gravado'];
            $ivaErp = $montosCortesia['iva'];
            $exentoErp = $montosCortesia['exento'];
        }

        $montoAnita = round((float) ($cabeceraAnita->ven_monto ?? 0), 2);
        $gravadoAnita = round((float) ($cabeceraAnita->ven_gravado ?? 0), 2);
        $ivaAnita = round((float) ($cabeceraAnita->ven_impuesto1 ?? 0), 2);
        $exentoAnita = round((float) ($cabeceraAnita->ven_exento ?? 0), 2);

        if (
            self::coincideMonetario($totalErp, $montoAnita, self::esCortesiaMinima($totalErp) ? 0.001 : $tolerancia)
            && self::coincideMonetario($gravadoErp, $gravadoAnita, $tolerancia)
            && self::coincideMonetario($ivaErp, $ivaAnita, $tolerancia)
            && self::coincideMonetario($exentoErp, $exentoAnita, self::esCortesiaMinima($totalErp) ? 0.001 : $tolerancia)
        ) {
            return false;
        }

        $venta->loadMissing('puntoventas.empresas');
        $puntoventa = $venta->puntoventas;
        if (! $puntoventa) {
            throw new \RuntimeException('Punto de venta no encontrado para venta #'.$venta->id);
        }

        [$tipoAnita, $letra, $sucursal, $numero] = self::resolverClaveAnitaDesdeVenta($venta, $cabeceraAnita);

        self::actualizarMontosCabeceraAnita($tipoAnita, $letra, $sucursal, $numero, $gravadoErp, $ivaErp, $exentoErp, $totalErp);

        return true;
    }

    /**
     * Sincroniza solo ven_gravado (compatibilidad).
     *
     * @return bool true si se actualizó Anita
     */
    public static function sincronizarVenGravadoDesdeVentaErp(
        Venta $venta,
        object $cabeceraAnita,
        float $gravadoErp,
        float $tolerancia = 0.02,
    ): bool {
        $gravadoAnita = round((float) ($cabeceraAnita->ven_gravado ?? 0), 2);
        $gravadoErp = round($gravadoErp, 2);

        if (self::coincideMonetario($gravadoErp, $gravadoAnita, $tolerancia)) {
            return false;
        }

        [$tipoAnita, $letra, $sucursal, $numero] = self::resolverClaveAnitaDesdeVenta($venta, $cabeceraAnita);
        self::actualizarVenGravadoCabeceraAnita($tipoAnita, $letra, $sucursal, $numero, $gravadoErp);

        return true;
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: int}
     */
    private static function resolverClaveAnitaDesdeVenta(Venta $venta, object $cabeceraAnita): array
    {
        $venta->loadMissing('puntoventas.empresas');
        $puntoventa = $venta->puntoventas;
        if (! $puntoventa) {
            throw new \RuntimeException('Punto de venta no encontrado para venta #'.$venta->id);
        }

        $empresaCodigo = $puntoventa->empresas?->codigo ?? $puntoventa->empresa_id;
        $tipoAnita = strtoupper(trim((string) ($cabeceraAnita->ven_tipo ?? '')));
        if ($tipoAnita === '') {
            $tipoAnita = KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge(
                'FAC',
                (string) $puntoventa->codigo,
                $empresaCodigo,
                $puntoventa->modofacturacion ?? null,
            );
        }

        $sucursal = self::sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
        $numero = (int) ($cabeceraAnita->ven_nro ?? $venta->numerocomprobante ?? 0);
        $letra = 'B';
        if (preg_match('/\s+([A-Z])-/', (string) ($venta->codigo ?? ''), $m)) {
            $letra = $m[1];
        }

        return [$tipoAnita, $letra, $sucursal, $numero];
    }

    private static function coincideMonetario(float $erp, float $anita, float $tolerancia): bool
    {
        if (abs($erp - $anita) <= $tolerancia) {
            return true;
        }

        return abs(abs($erp) - abs($anita)) <= $tolerancia;
    }

    public static function sucursalDesdeCodigoPuntoventa(string $codigo): int
    {
        return (int) preg_replace('/\D+/', '', trim($codigo));
    }

    /**
     * Repara gravado / IVA / exento de cabecera Anita desde montos ERP emparejados.
     *
     * @return array{revisadas:int, actualizadas:int, errores:list<array<string, mixed>>}
     */
    public static function repararGravadoDesdeVentasErp(
        iterable $ventas,
        callable $montosErp,
        callable $cabeceraAnita,
        float $tolerancia = 0.02,
    ): array {
        $resultado = ['revisadas' => 0, 'actualizadas' => 0, 'errores' => []];

        foreach ($ventas as $venta) {
            if (! $venta instanceof Venta) {
                continue;
            }

            $resultado['revisadas']++;
            try {
                $erp = $montosErp($venta);
                $cab = $cabeceraAnita($venta);
                if ($cab === null) {
                    continue;
                }

                if (self::sincronizarMontosCabeceraDesdeVentaErp($venta, $cab, $erp, $tolerancia)) {
                    $resultado['actualizadas']++;
                }
            } catch (\Throwable $e) {
                $resultado['errores'][] = [
                    'venta_id' => (int) $venta->id,
                    'codigo' => (string) ($venta->codigo ?? ''),
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        return $resultado;
    }
}
