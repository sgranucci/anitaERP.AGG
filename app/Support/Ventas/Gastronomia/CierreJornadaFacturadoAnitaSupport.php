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
 * - Columnas QR/MP/Efectivo/Otros: solo facturas (sin NC), como GastronomiaTurnoOperativoTotalesSupport.
 */
final class CierreJornadaFacturadoAnitaSupport
{
    /**
     * @param  Collection<int, VentaGastronomiaEmision>  $emisiones
     * @return array{
     *   qr:float,
     *   mp:float,
     *   efectivo:float,
     *   otros:float,
     *   total:float,
     *   total_facturas:float,
     *   total_notas_credito:float,
     *   cantidad_facturas:int,
     *   cantidad_notas_credito:int,
     *   etiqueta:string,
     *   tipo:string
     * }
     */
    public static function totalesDesdeEmisiones(Collection $emisiones, int $empresaId): array
    {
        $fila = CierreJornadaProcesoGrillaSupport::filaVacia('Facturado Anita (jornada)', 'anita_jornada');
        $totalNeto = 0.0;
        $totalFacturas = 0.0;
        $totalNotasCredito = 0.0;
        $cantidadFacturas = 0;
        $cantidadNotasCredito = 0;

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

            $col = self::columnaMedioParaFactura($emision, $empresaId, $totemId);
            $fila[$col] = round($fila[$col] + $monto, 2);
        }

        $fila['total'] = $totalNeto;
        $fila['total_facturas'] = $totalFacturas;
        $fila['total_notas_credito'] = $totalNotasCredito;
        $fila['cantidad_facturas'] = $cantidadFacturas;
        $fila['cantidad_notas_credito'] = $cantidadNotasCredito;

        return $fila;
    }

    /**
     * @return array{
     *   qr:float,
     *   mp:float,
     *   efectivo:float,
     *   otros:float,
     *   total:float,
     *   total_facturas:float,
     *   total_notas_credito:float,
     *   cantidad_facturas:int,
     *   cantidad_notas_credito:int,
     *   etiqueta:string,
     *   tipo:string
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

    private static function columnaMedioParaFactura(
        VentaGastronomiaEmision $emision,
        int $empresaId,
        int $totemId,
    ): string {
        $waitryTipo = $emision->cuenta?->waitry_tipo_pago;
        $medio = self::primerMedioCobranza($emision, $empresaId);
        $anitaEsTotem = $medio !== null
            ? ($totemId > 0 && (int) $medio['cuentacaja_id'] === $totemId)
            : (bool) ($emision->cuenta?->waitry_cobro_totem ?? false);

        if ($anitaEsTotem && $waitryTipo !== null && $waitryTipo !== '') {
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
