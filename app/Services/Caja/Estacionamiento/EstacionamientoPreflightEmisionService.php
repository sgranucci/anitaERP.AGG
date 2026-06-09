<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\CuentaEstacionamiento;
use App\Support\Caja\Estacionamiento\EstacionamientoCuentacajaEfectivo;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use InvalidArgumentException;

/**
 * Validaciones previas a emitir factura + cobranza (estacionamiento POS).
 */
final class EstacionamientoPreflightEmisionService
{
    public function __construct(
        private readonly EstacionamientoReceptorFacturacionService $receptorFacturacionService,
        private readonly EstacionamientoFacturacionService $facturacionService,
        private readonly JornadaEstacionamientoService $jornadaService,
        private readonly EstacionamientoTurnoOperativoService $turnoOperativoService,
    ) {
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null}>  $mediosPago
     * @return list<string>
     */
    public function erroresAntesDeEmitir(
        CuentaEstacionamiento $cuenta,
        ?ConfiguracionPuntoventaEstacionamiento $cfg,
        int $monedaId,
        array $mediosPago,
        array $payloadFactura,
        bool $facturacionConDescuento = false,
    ): array {
        $errores = [];

        if (! $cfg) {
            $errores[] = 'No hay configuración de punto de venta estacionamiento para este equipo ('
                .EstacionamientoIdentificadorPc::resolver().').';

            return $errores;
        }

        if (config('estacionamiento.jornada_obligatoria', true)) {
            try {
                $this->jornadaService->exigirJornadaAbierta((int) $cfg->empresa_id);
            } catch (InvalidArgumentException $e) {
                $errores[] = $e->getMessage();
            }
        }

        if (EstacionamientoTurnoOperativoService::requiereHabilitacionTurno()) {
            try {
                $this->turnoOperativoService->exigirTurnoHabilitadoSiConfigurado(
                    EstacionamientoIdentificadorPc::resolver(),
                    (int) $cfg->empresa_id,
                );
            } catch (InvalidArgumentException $e) {
                $errores[] = $e->getMessage();
            }
        }

        $tipoFacturaId = (int) ($cfg->tipotransaccion_id ?? 0);
        if ($tipoFacturaId <= 0) {
            $tipoFacturaId = (int) config('estacionamiento.tipotransaccion_factura_id', 0);
        }
        if ($tipoFacturaId <= 0) {
            $errores[] = 'Configure el tipo de transacción (factura) en la configuración del punto de venta estacionamiento.';
        }

        $pvCae = (int) ($cfg->puntoventa_cae_id ?? 0);
        $pvCaea = (int) ($cfg->puntoventa_caea_id ?? 0);
        if ($pvCae <= 0 && $pvCaea <= 0) {
            $errores[] = 'Configure punto de venta CAE y/o CAEA en la configuración estacionamiento de esta terminal.';
        } else {
            try {
                $resolucionPv = ArcaWsfeEmisionResiliencia::resolverPuntoventaEmision($pvCae, $pvCaea, false);
                $errores = array_merge(
                    $errores,
                    self::erroresPuntoventaEmisionInexistente($resolucionPv, $pvCae, $pvCaea),
                );
            } catch (InvalidArgumentException $e) {
                $errores[] = $e->getMessage();
            }
        }

        if ((int) ($cuenta->categoria_automovil_estacionamiento_id ?? 0) <= 0) {
            $errores[] = 'Debe indicar la categoría del vehículo en la cuenta.';
        }

        if ($cuenta->lineas->isEmpty()) {
            $errores[] = 'La cuenta no tiene ítems cargados.';
        }

        if ($cuenta->estado !== CuentaEstacionamiento::ESTADO_ABIERTA) {
            $errores[] = 'La cuenta no está abierta.';
        }

        $cuenta->loadMissing('descuentoEstacionamiento');
        if ($facturacionConDescuento && (int) ($cuenta->descuento_estacionamiento_id ?? 0) <= 0) {
            $errores[] = 'Debe indicar el código de descuento antes de facturar con F8.';
        }
        if ((int) ($cuenta->descuento_estacionamiento_id ?? 0) > 0 && (int) ($cuenta->cliente_interno_descuento_id ?? 0) <= 0) {
            $errores[] = 'Indique el cliente interno del descuento (quien invita o centro de costos). '
                .'Es independiente del cliente de la factura.';
        }

        try {
            $this->receptorFacturacionService->resolverParaFacturar($cuenta);
        } catch (InvalidArgumentException $e) {
            $errores[] = $e->getMessage();
        }

        $preview = $this->facturacionService->previewTotalesEmision($payloadFactura, $cuenta);
        if (! empty($preview['error'])) {
            $errores[] = (string) $preview['error'];
        }

        $sinCobranza = ! empty($preview['sin_cobranza']);
        if (! $sinCobranza) {
            $msgCaja = EstacionamientoCobranzaService::mensajeConfigCobranzaFaltante($cfg);
            if ($msgCaja) {
                $errores[] = $msgCaja;
            }

            $errMedios = EstacionamientoCobranzaService::validarMediosContraTotalEsperado(
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
        CuentaEstacionamiento $cuenta,
        ?ConfiguracionPuntoventaEstacionamiento $cfg,
        int $monedaId,
        array $mediosPago,
        array $payloadFactura,
        bool $facturacionConDescuento = false,
    ): void {
        $errores = $this->erroresAntesDeEmitir(
            $cuenta,
            $cfg,
            $monedaId,
            $mediosPago,
            $payloadFactura,
            $facturacionConDescuento,
        );
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }
    }

    /**
     * @return list<string>
     */
    public function erroresCuentacajaEfectivo(CuentaEstacionamiento $cuenta, ?ConfiguracionPuntoventaEstacionamiento $cfg): array
    {
        $empresaId = $cfg ? (int) $cfg->empresa_id : (int) $cuenta->empresa_id;
        $ccErr = EstacionamientoCuentacajaEfectivo::mensajeErrorResolucion($empresaId);

        return $ccErr ? [$ccErr] : [];
    }

    /**
     * @param  array{puntoventa_id:int,usa_caea:bool}  $resolucionPv
     * @return list<string>
     */
    private static function erroresPuntoventaEmisionInexistente(array $resolucionPv, int $pvCae, int $pvCaea): array
    {
        if ((int) ($resolucionPv['puntoventa_id'] ?? 0) > 0) {
            return [];
        }

        $partes = [];
        if ($pvCae > 0) {
            $partes[] = 'CAE id '.$pvCae;
        }
        if ($pvCaea > 0) {
            $partes[] = 'CAEA id '.$pvCaea;
        }

        return ['El punto de venta configurado ('.implode(' / ', $partes).') no existe o no está habilitado.'];
    }
}
