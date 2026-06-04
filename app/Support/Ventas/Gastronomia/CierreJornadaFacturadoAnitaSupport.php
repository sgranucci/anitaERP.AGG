<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Illuminate\Database\Eloquent\Collection;

/**
 * Total facturado Anita en jornada (fechajornada), alineado a Facturas del día / cierre de turno.
 *
 * - Neto: SUM(venta.total) con NC incluidas una sola vez (signo negativo en ERP).
 * - Cuadro de cierre: fila jornada (medios reales) + fila TOTEM (puente, medio real Waitry).
 */
final class CierreJornadaFacturadoAnitaSupport
{
    /**
     * @param  Collection<int, VentaGastronomiaEmision>  $emisiones
     * @return array{
     *   anita_jornada: array<string, mixed>,
     *   anita_totem: array<string, mixed>,
     *   qr: float,
     *   mp: float,
     *   efectivo: float,
     *   otros: float,
     *   total: float,
     *   total_facturas: float,
     *   total_notas_credito: float,
     *   cantidad_facturas: int,
     *   cantidad_notas_credito: int,
     *   cantidad_facturas_totem: int,
     *   etiqueta: string,
     *   tipo: string
     * }
     */
    public static function totalesDesdeEmisiones(Collection $emisiones, int $empresaId): array
    {
        $filaJornada = CierreJornadaProcesoGrillaSupport::filaVacia('Facturado Anita (jornada)', 'anita_jornada');
        $filaTotem = CierreJornadaProcesoGrillaSupport::filaVacia(
            'Facturado Anita — cobro TOTEM (medio real Waitry)',
            'anita_totem',
        );
        $totalNeto = 0.0;
        $totalFacturas = 0.0;
        $totalNotasCredito = 0.0;
        $cantidadFacturas = 0;
        $cantidadNotasCredito = 0;
        $cantidadFacturasTotem = 0;

        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);

        foreach ($emisiones as $emision) {
            $venta = $emision->venta;
            if ($venta === null) {
                continue;
            }

            $monto = round((float) ($venta->total ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }

            $esNotaCredito = ($emision->venta_factura_origen_id ?? null) !== null;
            $totalNeto = round($totalNeto + $monto, 2);

            if ($esNotaCredito) {
                $totalNotasCredito = round($totalNotasCredito + $monto, 2);
                $cantidadNotasCredito++;

                continue;
            }

            $totalFacturas = round($totalFacturas + $monto, 2);
            $cantidadFacturas++;

            $esTotem = self::esFacturaCobroTotem($emision, $empresaId, $totemId);
            if ($esTotem) {
                $cantidadFacturasTotem++;
            }

            $col = self::columnaMedioParaFactura($emision, $empresaId, $totemId, $esTotem);
            if ($esTotem) {
                $filaTotem[$col] = round($filaTotem[$col] + $monto, 2);
            } else {
                $filaJornada[$col] = round($filaJornada[$col] + $monto, 2);
            }
        }

        $filaJornada = self::cerrarFilaFacturado($filaJornada);
        $filaTotem = self::cerrarFilaFacturado($filaTotem);

        return [
            'anita_jornada' => $filaJornada,
            'anita_totem' => $filaTotem,
            'qr' => $filaJornada['qr'],
            'mp' => $filaJornada['mp'],
            'efectivo' => $filaJornada['efectivo'],
            'otros' => $filaJornada['otros'],
            'total' => $totalNeto,
            'total_facturas' => $totalFacturas,
            'total_notas_credito' => $totalNotasCredito,
            'cantidad_facturas' => $cantidadFacturas,
            'cantidad_notas_credito' => $cantidadNotasCredito,
            'cantidad_facturas_totem' => $cantidadFacturasTotem,
            'etiqueta' => $filaJornada['etiqueta'],
            'tipo' => $filaJornada['tipo'],
        ];
    }

    /**
     * @return array{
     *   anita_jornada: array<string, mixed>,
     *   anita_totem: array<string, mixed>,
     *   qr: float,
     *   mp: float,
     *   efectivo: float,
     *   otros: float,
     *   total: float,
     *   total_facturas: float,
     *   total_notas_credito: float,
     *   cantidad_facturas: int,
     *   cantidad_notas_credito: int,
     *   cantidad_facturas_totem: int,
     *   etiqueta: string,
     *   tipo: string
     * }
     */
    public static function totalesJornadaEmpresa(int $empresaId, string $fechaJornada): array
    {
        if ($empresaId <= 0 || $fechaJornada === '') {
            return self::totalesDesdeEmisiones(new Collection, $empresaId);
        }

        $emisiones = VentaGastronomiaEmision::query()
            ->with(['venta.cobranzasDirectas', 'venta.caja_movimientos.cobranzas', 'cuenta'])
            ->whereHas('venta', function ($q) use ($empresaId, $fechaJornada) {
                $q->whereDate('fechajornada', $fechaJornada)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->get();

        return self::totalesDesdeEmisiones($emisiones, $empresaId);
    }

    public static function totalNetoJornadaEmpresa(int $empresaId, string $fechaJornada): float
    {
        return self::totalesJornadaEmpresa($empresaId, $fechaJornada)['total'];
    }

    public static function esFacturaCobroTotemPublico(
        VentaGastronomiaEmision $emision,
        int $empresaId,
        int $totemId,
    ): bool {
        return self::esFacturaCobroTotem($emision, $empresaId, $totemId);
    }

    public static function columnaMedioParaEmisionPublico(
        VentaGastronomiaEmision $emision,
        int $empresaId,
        int $totemId,
        bool $esTotem,
    ): string {
        return self::columnaMedioParaFactura($emision, $empresaId, $totemId, $esTotem);
    }

    private static function esFacturaCobroTotem(
        VentaGastronomiaEmision $emision,
        int $empresaId,
        int $totemId,
    ): bool {
        $medio = self::primerMedioCobranza($emision, $empresaId);
        if ($medio !== null) {
            return $totemId > 0 && (int) $medio['cuentacaja_id'] === $totemId;
        }

        return (bool) ($emision->cuenta?->waitry_cobro_totem ?? false);
    }

    private static function columnaMedioParaFactura(
        VentaGastronomiaEmision $emision,
        int $empresaId,
        int $totemId,
        bool $esTotem,
    ): string {
        $waitryTipo = $emision->cuenta?->waitry_tipo_pago;
        $medio = self::primerMedioCobranza($emision, $empresaId);

        if ($esTotem && $waitryTipo !== null && $waitryTipo !== '') {
            $clave = CierreJornadaProcesoMedioSupport::claveDesdeWaitryTipo($waitryTipo);
        } elseif ($medio !== null) {
            $clave = CierreJornadaProcesoMedioSupport::claveDesdeCuentacaja(
                ['id' => (int) $medio['cuentacaja_id']],
                $empresaId,
            );
        } else {
            $clave = CierreJornadaProcesoMedioSupport::CLAVE_OTRO;
        }

        return match ($clave) {
            CierreJornadaProcesoMedioSupport::CLAVE_QR => 'qr',
            CierreJornadaProcesoMedioSupport::CLAVE_MP => 'mp',
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO => 'efectivo',
            default => 'otros',
        };
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private static function cerrarFilaFacturado(array $fila): array
    {
        foreach (['qr', 'mp', 'efectivo', 'otros'] as $k) {
            $fila[$k] = round((float) ($fila[$k] ?? 0), 2);
        }
        $fila['total'] = round($fila['qr'] + $fila['mp'] + $fila['efectivo'] + $fila['otros'], 2);

        return $fila;
    }

    /**
     * @return array{cuentacaja_id:int,label:string}|null
     */
    private static function primerMedioCobranza(VentaGastronomiaEmision $emision, int $empresaId): ?array
    {
        $venta = $emision->venta;
        if ($venta === null) {
            return null;
        }

        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        foreach ($medios as $lineas) {
            foreach ($lineas as $medio) {
                $ccId = (int) ($medio->cuentacaja_id ?? 0);
                if ($ccId <= 0) {
                    continue;
                }
                $codigo = trim((string) ($medio->codigo ?? ''));
                $nombre = trim((string) ($medio->nombre ?? ''));
                $label = $codigo !== '' && $nombre !== ''
                    ? $codigo.' — '.$nombre
                    : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$ccId));

                return ['cuentacaja_id' => $ccId, 'label' => $label];
            }
        }

        return null;
    }
}
