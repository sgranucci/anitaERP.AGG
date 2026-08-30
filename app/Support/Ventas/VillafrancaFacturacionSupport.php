<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numeración y vencimiento de facturas Villafranca (El Bierzo).
 *
 * - No Reparto 101 (factura dividida): copia el número de la FAC de Bierzo (PV 15).
 * - Reparto 101 (huérfana: remito Bierzo): avanza compemis FAC A sucursal 1 en
 *   /usr2/villafranca y emite en el mismo PV 00001. En pendmae del remito graba
 *   penm_ref_* = FAC / letra / sucursal 1 / número de esa factura.
 * - Vencimiento: siempre la fecha de la factura.
 * - Pedido impreso: Anita `enca_impre_pedido()` muestra neto / recargo / total
 *   solo si hay factura Villafranca (reparto 101, coef 1.10).
 */
final class VillafrancaFacturacionSupport
{
    public const TIPOEXPRESO_REPARTO_101 = '4';

    public static function esReparto101($pedido): bool
    {
        $tipo = is_object($pedido)
            ? ($pedido->transportes->tipoexpreso ?? '')
            : '';

        return (string) $tipo === self::TIPOEXPRESO_REPARTO_101;
    }

    public static function coeficienteReparto101(): float
    {
        $coef = (float) config('facturacion.COEFICIENTE_EXTRA_REPARTO_101', 1.10);

        return $coef > 0 ? $coef : 1.10;
    }

    /**
     * Anita lista_pedido.fc / enca_impre_pedido: monto1 = ven_monto/coef,
     * monto2 = diferencia, monto3 = ven_monto. Solo factura Villafranca 101.
     *
     * @return array{neto: float, recargo: float, total: float, coeficiente: float}|null
     */
    public static function montosPedidoDesdeFactura($pedido): ?array
    {
        if (! self::esReparto101($pedido)) {
            return null;
        }

        $factura = self::facturaVillafrancaDelPedido($pedido);
        $total = (float) ($factura->total ?? 0);
        if ($factura === null || $total <= 0) {
            return null;
        }

        return self::partirMontoFactura($total);
    }

    /**
     * @return array{neto: float, recargo: float, total: float, coeficiente: float}
     */
    public static function partirMontoFactura(float $totalFactura, ?float $coeficiente = null): array
    {
        $coef = $coeficiente ?? self::coeficienteReparto101();
        if ($coef <= 0) {
            $coef = 1.10;
        }
        $total = round($totalFactura, 2);
        $neto = round($total / $coef, 2);

        return [
            'neto' => $neto,
            'recargo' => round($total - $neto, 2),
            'total' => $total,
            'coeficiente' => $coef,
        ];
    }

    public static function facturaVillafrancaDelPedido($pedido): ?object
    {
        $pedidoId = (int) ($pedido->id ?? 0);
        if ($pedidoId <= 0) {
            return null;
        }

        $ventas = $pedido->relationLoaded('ventas')
            ? Collection::make($pedido->ventas)
            : Venta::query()->where('pedido_id', $pedidoId)->orderByDesc('id')->get();

        return $ventas->first(static function ($venta) {
            return PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision((int) ($venta->puntoventa_id ?? 0));
        });
    }

    public static function sucursalNumeradorPropio(): string
    {
        $sucursal = trim((string) config('facturacion.VILLAFRANCA_NUMERADOR_SUCURSAL', '1'));

        return $sucursal !== '' ? $sucursal : '1';
    }

    /**
     * PV ERP de emisión del 101 (Villafranca sucursal 1). Coincide con el numerador Anita.
     */
    public static function idPuntoVentaReparto101(): int
    {
        $id = (int) config('facturacion.PUNTOVENTA_DIVISION_REPARTO_101_ID', 0);
        if ($id > 0) {
            return $id;
        }

        $codigo = self::codigoPuntoVentaReparto101();
        $empresaId = (int) DB::table('puntoventa')
            ->where('id', (int) config('facturacion.PUNTOVENTA_DIVISION_ID', 0))
            ->value('empresa_id');
        if ($empresaId <= 0 || $codigo === '') {
            return 0;
        }

        return (int) DB::table('puntoventa')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id');
    }

    public static function codigoPuntoVentaReparto101(): string
    {
        $codigo = trim((string) config('facturacion.PUNTOVENTA_DIVISION_REPARTO_101_CODIGO', ''));
        if ($codigo !== '') {
            $soloDigitos = preg_replace('/\D+/', '', $codigo);

            return $soloDigitos !== ''
                ? str_pad($soloDigitos, 5, '0', STR_PAD_LEFT)
                : $codigo;
        }

        $sucursal = (int) self::sucursalNumeradorPropio();

        return $sucursal > 0 ? str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT) : '00001';
    }

    public static function esPuntoVentaReparto101(int $puntoventaId): bool
    {
        $id = self::idPuntoVentaReparto101();

        return $id > 0 && $puntoventaId === $id;
    }

    /**
     * Referencia de la factura Villafranca para pendmae.penm_ref_* del remito.
     * Sucursal = PV de emisión (00001), igual al numerador Anita.
     *
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    public static function referenciaPendmaeDesdeFactura(
        string $tipo,
        string $letra,
        $sucursalEmision,
        int $numero
    ): array {
        $tipo = strtoupper(substr(trim($tipo), 0, 3));
        $letra = strtoupper(substr(trim($letra), 0, 1));

        return [
            'tipo' => $tipo !== '' ? $tipo : 'FAC',
            'letra' => $letra !== '' ? $letra : 'A',
            'sucursal' => (int) $sucursalEmision,
            'nro' => $numero,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $ref
     * @return array<string, mixed>
     */
    public static function aplicarReferenciaPendmae(array $data, array $ref): array
    {
        $data['penm_ref_tipo'] = $ref['tipo'];
        $data['penm_ref_letra'] = $ref['letra'];
        $data['penm_ref_sucursal'] = $ref['sucursal'];
        $data['penm_ref_nro'] = $ref['nro'];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    public static function referenciaPendmaeDesdeRequest(array $request): array
    {
        $tipo = strtoupper(substr(trim((string) ($request['penm_ref_tipo'] ?? '')), 0, 3));
        $letra = strtoupper(substr(trim((string) ($request['penm_ref_letra'] ?? '')), 0, 1));

        return [
            'tipo' => $tipo !== '' ? $tipo : ' ',
            'letra' => $letra !== '' ? $letra : ' ',
            'sucursal' => (int) ($request['penm_ref_sucursal'] ?? 0),
            'nro' => (int) ($request['penm_ref_nro'] ?? 0),
        ];
    }

    public static function pathSistema(): string
    {
        return PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA;
    }

    public static function debeForzarVencimientoFechaFactura(bool $grabaComprobanteDividido, $puntoventa = null): bool
    {
        if ($grabaComprobanteDividido) {
            return true;
        }

        $puntoventaId = is_object($puntoventa)
            ? (int) ($puntoventa->id ?? 0)
            : (int) $puntoventa;

        return PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision($puntoventaId);
    }

    /**
     * @param  list<array{fechavencimiento:mixed,total:mixed}>  $cuotas
     * @return list<array{fechavencimiento:mixed,total:mixed}>
     */
    public static function aplicarVencimientoFechaFactura(array $cuotas, $fechaFactura): array
    {
        foreach ($cuotas as $i => $cuota) {
            $cuotas[$i]['fechavencimiento'] = $fechaFactura;
        }

        return $cuotas;
    }

    /**
     * FAC Villafranca de una división (no 101): id de la FAC de Bierzo a grabar.
     */
    public static function ventaOrigenIdParaGrabar(int $puntoventaId, int $ventaOrigenIdDivision): ?int
    {
        if ($ventaOrigenIdDivision <= 0) {
            return null;
        }
        if (! PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision($puntoventaId)) {
            return null;
        }

        return $ventaOrigenIdDivision;
    }

    /**
     * NC Villafranca: hereda la FAC de Bierzo de la factura aplicada, o la aplica si es de Bierzo.
     */
    public static function heredarOrigenIdDesdeVenta(?object $ventaAplicada): ?int
    {
        if ($ventaAplicada === null) {
            return null;
        }

        $heredado = (int) ($ventaAplicada->venta_origen_id ?? 0);
        if ($heredado > 0) {
            return $heredado;
        }

        $aplicadaId = (int) ($ventaAplicada->id ?? 0);
        if ($aplicadaId <= 0) {
            return null;
        }
        if (PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision((int) ($ventaAplicada->puntoventa_id ?? 0))) {
            return null;
        }

        return $aplicadaId;
    }

    /**
     * Pares VF → FAC Bierzo ya inferidos (pedido / número). No persiste.
     *
     * @return list<array{venta_id:int,venta_origen_id:int,comprobante:string,origen_comprobante:string}>
     */
    public static function paresOrigenParaBackfill(): array
    {
        $pares = [];
        foreach (VillafrancaPruebaVsRealSupport::listarErp() as $f) {
            $ventaId = (int) ($f['venta_id'] ?? 0);
            $origenId = (int) ($f['origen_id'] ?? 0);
            if ($ventaId <= 0 || $origenId <= 0 || $ventaId === $origenId) {
                continue;
            }
            $pares[] = [
                'venta_id' => $ventaId,
                'venta_origen_id' => $origenId,
                'comprobante' => (string) ($f['comprobante'] ?? ''),
                'origen_comprobante' => (string) ($f['origen_comprobante'] ?? ''),
            ];
        }

        return $pares;
    }

    /**
     * @param  list<array{venta_id:int,venta_origen_id:int}>  $pares
     */
    public static function aplicarBackfillVentaOrigen(array $pares): int
    {
        $n = 0;
        foreach ($pares as $par) {
            $ventaId = (int) ($par['venta_id'] ?? 0);
            $origenId = (int) ($par['venta_origen_id'] ?? 0);
            if ($ventaId <= 0 || $origenId <= 0 || $ventaId === $origenId) {
                continue;
            }
            $n += (int) DB::table('venta')
                ->where('id', $ventaId)
                ->whereNull('venta_origen_id')
                ->update(['venta_origen_id' => $origenId]);
        }

        return $n;
    }
}
