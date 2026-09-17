<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Services\Ventas\FacturaelectronicaService;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\VentaNumeracionEmpresaSupport;
use App\Support\Ventas\VentaNumeradorFiscalSupport;
use Throwable;

/**
 * Contexto del POS Local: PV, depósito, próxima factura.
 * Si el PV tiene webservice, el próximo número sale de ARCA (FECompUltimoAutorizado).
 * Sin webservice: ERP / numerador fiscal.
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
     *   usa_webservice:bool,
     *   fuente_numero:string,
     *   aviso:?string
     * }
     */
    public static function paraLocal(?LocalVenta $local, bool $consultarArca = true): array
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
            'usa_webservice' => false,
            'fuente_numero' => '',
            'aviso' => null,
        ];

        if (! $local) {
            return $vacio;
        }

        $local->loadMissing([
            'puntoventa:id,codigo,nombre,webservice,modofacturacion,empresa_id',
            'deposito:id,codigo,nombre',
            'listaprecio:id,codigo,nombre',
            'tipotransaccionFac:id,abreviatura,codigo,nombre',
        ]);

        $pvIdTmp = (int) ($local->puntoventaDefaultId() ?? $local->puntoventa_id ?? 0);
        $pv = $pvIdTmp > 0
            ? Puntoventa::query()->find($pvIdTmp)
            : ($local->puntoventaDefault() ?? $local->puntoventa);

        $dep = $local->deposito;
        $lista = $local->listaprecio;
        $tipoFac = $local->tipotransaccionFac
            ?? Tipotransaccion::query()->find($local->tipoFacId());

        $abrev = strtoupper(substr(trim((string) ($tipoFac->abreviatura ?? 'FAC')), 0, 3)) ?: 'FAC';
        $letra = 'B'; // preview consumidor final; Factura A cambia con el cliente
        $pvCodigo = str_pad(trim((string) ($pv->codigo ?? '')), 5, '0', STR_PAD_LEFT);
        $empresaId = (int) ($local->empresa_id ?? 0);
        $pvId = (int) ($pv->id ?? $local->puntoventaDefaultId() ?? 0);
        $usaWebservice = self::pvUsaWebservice($pv);

        $proximo = 0;
        $aviso = null;
        $fuente = '';

        if ($pvId <= 0 || ! $tipoFac) {
            $aviso = 'Configure punto de venta y tipo FAC del local.';
        } else {
            $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
                (string) ($tipoFac->codigo ?? ''),
                $letra
            );
            if ($codigoAfip <= 0) {
                $aviso = 'Sin código AFIP del tipo FAC; el número se confirma al emitir.';
            } elseif ($usaWebservice) {
                if (! $consultarArca) {
                    $fuente = 'arca_pendiente';
                    $proximo = 0;
                } else {
                    [$proximo, $aviso, $fuente] = self::proximoDesdeArca(
                        $pv,
                        $codigoAfip,
                        $empresaId
                    );
                }
            } elseif (VentaNumeradorFiscalSupport::estaEnUso()) {
                $row = VentaNumeradorFiscalSupport::consultar($pvId, $codigoAfip);
                $proximo = VentaNumeradorFiscalSupport::proximoNumero(
                    (int) ($row->ultimo_numero ?? 0),
                    (int) ($row->piso ?? 0)
                );
                $fuente = 'numerador_fiscal';
            } else {
                $ultimo = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpPorCodigoAfip(
                    $pvId,
                    $codigoAfip,
                    $empresaId > 0 ? $empresaId : null,
                    $letra
                );
                $proximo = $ultimo + 1;
                $fuente = 'erp';
            }
        }

        if ($usaWebservice && ! $consultarArca) {
            $etiqueta = '…';
        } elseif ($proximo > 0) {
            $etiqueta = sprintf('%s %s %s-%08d', $abrev, $letra, $pvCodigo, $proximo);
        } else {
            $etiqueta = '—';
        }

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
            'usa_webservice' => $usaWebservice,
            'fuente_numero' => $fuente,
            'aviso' => $aviso,
        ];
    }

    public static function pvUsaWebservice(?Puntoventa $pv): bool
    {
        if ($pv === null) {
            return false;
        }

        return trim((string) ($pv->webservice ?? '')) !== '';
    }

    /**
     * @return array{0:int,1:?string,2:string} proximo, aviso, fuente
     */
    private static function proximoDesdeArca(Puntoventa $pv, int $codigoAfip, int $empresaId): array
    {
        $empresa = Empresa::query()->find((int) ($pv->empresa_id ?? $empresaId ?: 0));
        $nroinscripcion = $empresa !== null ? (string) ($empresa->nroinscripcion ?? '') : '';
        $timeout = max(5, (int) config('facturacion_local.preview_arca_soap_timeout', 10));
        $opciones = [
            'emision_pos_arca' => true,
            'aplicar_timeout_pos_arca' => true,
            'soap_timeout_arca_pos' => $timeout,
            'notificar_failover_transporte_en_capa_superior' => true,
            'origen_facturacion_local' => true,
        ];

        try {
            $raw = app(FacturaelectronicaService::class)->traeUltimoNumeroComprobante(
                $nroinscripcion,
                $codigoAfip,
                $pv,
                $opciones,
            );
        } catch (Throwable $e) {
            return [0, 'ARCA: '.$e->getMessage(), 'arca_error'];
        }

        if ($raw === -1 || $raw === '-1') {
            // PV nuevo en ARCA (último 0) o fallo: si no hay ventas ERP, próximo = 1.
            $tieneVentas = \App\Models\Ventas\Venta::query()
                ->where('puntoventa_id', (int) $pv->id)
                ->exists();
            if (! $tieneVentas) {
                return [1, null, 'arca'];
            }

            return [0, 'ARCA no devolvió el último número autorizado.', 'arca_error'];
        }

        $ultimo = (int) $raw;

        return [$ultimo > 0 ? $ultimo + 1 : 1, null, 'arca'];
    }
}
