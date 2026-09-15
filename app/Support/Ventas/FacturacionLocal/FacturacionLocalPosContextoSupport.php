<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\VentaNumeracionEmpresaSupport;
use App\Support\Ventas\VentaNumeradorFiscalSupport;

/**
 * Contexto informativo del POS Local: PV, depósito, próxima factura (sin reservar número).
 */
final class FacturacionLocalPosContextoSupport
{
    /**
     * @return array{
     *   puntoventa_id:int,
     *   puntoventa_codigo:string,
     *   puntoventa_nombre:string,
     *   deposito_id:int,
     *   deposito_codigo:string,
     *   deposito_nombre:string,
     *   listaprecio_id:int,
     *   listaprecio_codigo:string,
     *   listaprecio_nombre:string,
     *   tipo_fac_abrev:string,
     *   proxima_etiqueta:string,
     *   proxima_numero:int,
     *   letra_sugerida:string,
     *   aviso:?string
     * }
     */
    public static function paraLocal(?LocalVenta $local): array
    {
        $vacio = [
            'puntoventa_id' => 0,
            'puntoventa_codigo' => '',
            'puntoventa_nombre' => '',
            'deposito_id' => 0,
            'deposito_codigo' => '',
            'deposito_nombre' => '',
            'listaprecio_id' => 0,
            'listaprecio_codigo' => '',
            'listaprecio_nombre' => '',
            'tipo_fac_abrev' => 'FAC',
            'proxima_etiqueta' => '—',
            'proxima_numero' => 0,
            'letra_sugerida' => 'B',
            'aviso' => null,
        ];

        if (! $local) {
            return $vacio;
        }

        $local->loadMissing([
            'puntoventa:id,codigo,nombre',
            'deposito:id,codigo,nombre',
            'listaprecio:id,codigo,nombre',
            'tipotransaccionFac:id,abreviatura,codigo,nombre',
        ]);

        $pv = $local->puntoventaDefault() ?? $local->puntoventa;
        $dep = $local->deposito;
        $lista = $local->listaprecio;
        $tipoFac = $local->tipotransaccionFac
            ?? Tipotransaccion::query()->find($local->tipoFacId());

        $abrev = strtoupper(substr(trim((string) ($tipoFac->abreviatura ?? 'FAC')), 0, 3)) ?: 'FAC';
        $letra = 'B'; // preview consumidor final; Factura A cambia con el cliente
        $pvCodigo = str_pad(trim((string) ($pv->codigo ?? '')), 5, '0', STR_PAD_LEFT);
        $empresaId = (int) ($local->empresa_id ?? 0);
        $pvId = (int) ($pv->id ?? $local->puntoventaDefaultId() ?? 0);

        $proximo = 0;
        $aviso = null;
        if ($pvId > 0 && $tipoFac) {
            $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
                (string) ($tipoFac->codigo ?? ''),
                $letra
            );
            if ($codigoAfip > 0) {
                if (VentaNumeradorFiscalSupport::estaEnUso()) {
                    $row = VentaNumeradorFiscalSupport::consultar($pvId, $codigoAfip);
                    $proximo = VentaNumeradorFiscalSupport::proximoNumero(
                        (int) ($row->ultimo_numero ?? 0),
                        (int) ($row->piso ?? 0)
                    );
                } else {
                    $ultimo = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpPorCodigoAfip(
                        $pvId,
                        $codigoAfip,
                        $empresaId > 0 ? $empresaId : null,
                        $letra
                    );
                    $proximo = $ultimo + 1;
                }
            } else {
                $aviso = 'Sin código AFIP del tipo FAC; el número se confirma al emitir.';
            }
        } else {
            $aviso = 'Configure punto de venta y tipo FAC del local.';
        }

        $etiqueta = $proximo > 0
            ? sprintf('%s %s %s-%08d', $abrev, $letra, $pvCodigo, $proximo)
            : '—';

        return [
            'puntoventa_id' => $pvId,
            'puntoventa_codigo' => (string) ($pv->codigo ?? ''),
            'puntoventa_nombre' => (string) ($pv->nombre ?? ''),
            'deposito_id' => (int) ($dep->id ?? $local->deposito_id ?? 0),
            'deposito_codigo' => (string) ($dep->codigo ?? ''),
            'deposito_nombre' => (string) ($dep->nombre ?? ''),
            'listaprecio_id' => (int) ($lista->id ?? $local->listaprecio_id ?? 0),
            'listaprecio_codigo' => (string) ($lista->codigo ?? ''),
            'listaprecio_nombre' => (string) ($lista->nombre ?? ''),
            'tipo_fac_abrev' => $abrev,
            'proxima_etiqueta' => $etiqueta,
            'proxima_numero' => $proximo,
            'letra_sugerida' => $letra,
            'aviso' => $aviso,
        ];
    }
}
