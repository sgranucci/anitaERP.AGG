<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use App\Support\Ventas\GastronomiaDepositoConfigSupport;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use InvalidArgumentException;

/**
 * Validaciones previas a emitir factura + cobranza (evita comprobantes a medio grabar).
 */
final class GastronomiaPreflightEmisionService
{
    public function __construct(
        private readonly GastronomiaReceptorFacturacionService $receptorFacturacionService,
        private readonly GastronomiaFacturacionService $facturacionGastronomiaService,
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly GastronomiaTurnoOperativoService $turnoOperativoService,
    ) {
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null}>  $mediosPago
     * @return list<string>
     */
    public function erroresAntesDeEmitir(
        CuentaGastronomia $cuenta,
        ?ConfiguracionPuntoventaGastronomia $cfg,
        int $monedaId,
        array $mediosPago,
        array $payloadFactura,
    ): array {
        $errores = [];

        if (! $cfg) {
            $errores[] = 'No hay configuración de punto de venta gastronomía para este equipo ('
                .GastronomiaIdentificadorPc::resolver().').';

            return $errores;
        }

        if (config('gastronomia.jornada_obligatoria', true)) {
            try {
                $this->jornadaService->exigirJornadaAbierta((int) $cfg->empresa_id);
            } catch (InvalidArgumentException $e) {
                $errores[] = $e->getMessage();
            }
        }

        if (GastronomiaTurnoOperativoService::requiereHabilitacionTurno()) {
            try {
                $this->turnoOperativoService->exigirTurnoHabilitadoSiConfigurado(
                    GastronomiaIdentificadorPc::resolver(),
                    (int) $cfg->empresa_id,
                );
            } catch (InvalidArgumentException $e) {
                $errores[] = $e->getMessage();
            }
        }

        $tipoFacturaId = (int) ($cfg->tipotransaccion_id ?? 0);
        if ($tipoFacturaId <= 0) {
            $tipoFacturaId = (int) config('gastronomia.tipotransaccion_factura_id', 0);
        }
        if ($tipoFacturaId <= 0) {
            $errores[] = 'Configure el tipo de transacción (factura) en la configuración del punto de venta gastronomía.';
        }

        if ((int) ($cfg->listaprecio_id ?? 0) <= 0) {
            $errores[] = 'Configure la lista de precios en la configuración del punto de venta gastronomía.';
        }

        $errores = array_merge($errores, GastronomiaDepositoConfigSupport::erroresDepositosFaltantes($cfg));

        $pvCae = (int) ($cfg->puntoventa_cae_id ?? 0);
        $pvCaea = (int) ($cfg->puntoventa_caea_id ?? 0);
        if ($pvCae <= 0 && $pvCaea <= 0) {
            $errores[] = 'Configure punto de venta CAE y/o CAEA en la configuración gastronomía de esta terminal.';
        } else {
            try {
                ArcaWsfeEmisionResiliencia::resolverPuntoventaEmision($pvCae, $pvCaea, false);
            } catch (InvalidArgumentException $e) {
                $errores[] = $e->getMessage();
            }
        }

        if ($cuenta->lineas->isEmpty()) {
            $errores[] = 'La cuenta no tiene consumos cargados.';
        }

        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            $errores[] = 'La cuenta no está abierta.';
        }

        $cuenta->loadMissing('descuentoGastronomia');
        if ((int) ($cuenta->descuento_gastronomia_id ?? 0) > 0 && (int) ($cuenta->cliente_interno_descuento_id ?? 0) <= 0) {
            $errores[] = 'Indique el cliente interno del descuento (quien invita o centro de costos). '
                .'Es independiente del cliente de la factura.';
        }

        try {
            $this->receptorFacturacionService->resolverParaFacturar($cuenta);
        } catch (InvalidArgumentException $e) {
            $errores[] = $e->getMessage();
        }

        $preview = $this->facturacionGastronomiaService->previewTotalesEmision($payloadFactura, $cuenta);
        if (! empty($preview['error'])) {
            $errores[] = (string) $preview['error'];
        }

        $sinCobranza = ! empty($preview['sin_cobranza']);
        if (! $sinCobranza) {
            $msgCaja = GastronomiaCobranzaService::mensajeConfigCobranzaFaltante($cfg);
            if ($msgCaja) {
                $errores[] = $msgCaja;
            }

            $errMedios = GastronomiaCobranzaService::validarMediosContraTotalEsperado(
                $mediosPago,
                (float) ($preview['total'] ?? 0),
                $cfg->empresa_id ? (int) $cfg->empresa_id : (int) $cuenta->empresa_id,
            );
            if ($errMedios) {
                $errores[] = $errMedios;
            }
        }

        return $errores;
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null}>  $mediosPago
     */
    public function exigirListoParaEmitir(
        CuentaGastronomia $cuenta,
        ?ConfiguracionPuntoventaGastronomia $cfg,
        int $monedaId,
        array $mediosPago,
        array $payloadFactura,
    ): void {
        $errores = $this->erroresAntesDeEmitir($cuenta, $cfg, $monedaId, $mediosPago, $payloadFactura);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }
    }

    /**
     * Validación extra para F5: cuenta de caja de efectivo configurada en .env.
     *
     * @return list<string>
     */
    public function erroresCuentacajaEfectivo(CuentaGastronomia $cuenta, ?ConfiguracionPuntoventaGastronomia $cfg): array
    {
        $empresaId = $cfg ? (int) $cfg->empresa_id : (int) $cuenta->empresa_id;
        $ccErr = GastronomiaCuentacajaEfectivo::mensajeErrorResolucion($empresaId);

        return $ccErr ? [$ccErr] : [];
    }
}
