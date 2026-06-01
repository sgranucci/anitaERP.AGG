<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use App\Support\Ventas\GastronomiaDepositoConfigSupport;
use App\Support\Ventas\GastronomiaCuentacajaCanjeTarjeta;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaCuentacajaSoloAutomaticaSupport;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\Waitry\WaitryFacturacionDuplicadosSupport;
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
        bool $facturacionConDescuento = false,
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
                $resolucionPv = ArcaWsfeEmisionResiliencia::resolverPuntoventaEmision($pvCae, $pvCaea, false);
                $errores = array_merge(
                    $errores,
                    self::erroresPuntoventaEmisionInexistente($resolucionPv, $pvCae, $pvCaea),
                );
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

        $waitryOrderId = (int) ($cuenta->waitry_order_id ?? 0);
        if ($waitryOrderId > 0 && WaitryFacturacionDuplicadosSupport::waitryOrderIdYaFacturado($waitryOrderId, (int) $cuenta->id)) {
            $errores[] = WaitryFacturacionDuplicadosSupport::mensajeOrdenYaFacturada($waitryOrderId);
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

        $errores = array_merge(
            $errores,
            $this->erroresCanjePendienteRequiereFacturacionConDescuento(
                $cuenta,
                $facturacionConDescuento,
                $preview,
            ),
        );
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

            $empresaCobranzaId = $cfg ? (int) $cfg->empresa_id : (int) $cuenta->empresa_id;
            $errores = array_merge(
                $errores,
                $this->erroresCobranzaWaitryTotem($cuenta, $mediosPago, $empresaCobranzaId),
                $this->erroresCobranzaWaitryImpagaProhibeTotemManual($cuenta, $mediosPago, $empresaCobranzaId),
                $this->erroresCobranzaProhibeCuentacajaManual($cuenta, $mediosPago, $empresaCobranzaId),
            );
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

    /**
     * CTG sin ticket o cuenta TOTEM fuera del flujo automático Waitry correspondiente.
     *
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,ticket_id?:int,numeroticket?:int}>  $mediosPago
     * @return list<string>
     */
    private function erroresCobranzaProhibeCuentacajaManual(
        CuentaGastronomia $cuenta,
        array $mediosPago,
        int $empresaId,
    ): array {
        $ctg = GastronomiaCuentacajaCanjeTarjeta::cuentaParaEmpresa($empresaId);
        $ctgId = (int) ($ctg['id'] ?? 0);

        foreach ($mediosPago as $medio) {
            $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
            if ($cuentacajaId <= 0) {
                continue;
            }

            if ($ctgId > 0 && $cuentacajaId === $ctgId) {
                $ticketId = (int) ($medio['ticket_id'] ?? 0);
                $numeroTicket = (int) ($medio['numeroticket'] ?? 0);
                if ($ticketId > 0 && $numeroTicket > 0) {
                    continue;
                }

                return [GastronomiaCuentacajaSoloAutomaticaSupport::mensajeRechazoManual(GastronomiaCuentacajaCanjeTarjeta::codigo())];
            }

            if ($cuenta->waitry_cobro_totem) {
                continue;
            }

            $totemId = $this->idCuentacajaTotem($empresaId);
            if ($totemId > 0 && $cuentacajaId === $totemId) {
                return [GastronomiaCuentacajaSoloAutomaticaSupport::mensajeRechazoManual(
                    GastronomiaCuentacajaTotem::codigo(),
                )];
            }
        }

        return [];
    }

    /**
     * Cuenta Waitry impaga: cuenta TOTEM solo al importar orden ya cobrada en tótem.
     *
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null}>  $mediosPago
     * @return list<string>
     */
    private function erroresCobranzaWaitryImpagaProhibeTotemManual(
        CuentaGastronomia $cuenta,
        array $mediosPago,
        int $empresaId,
    ): array {
        $waitryOrderId = (int) ($cuenta->waitry_order_id ?? 0);
        if ($waitryOrderId <= 0 || $cuenta->waitry_cobro_totem) {
            return [];
        }

        $totemId = $this->idCuentacajaTotem($empresaId);
        if ($totemId <= 0) {
            return [];
        }

        foreach ($mediosPago as $medio) {
            if ((int) ($medio['cuentacaja_id'] ?? 0) === $totemId) {
                return [
                    'Esta cuenta Waitry está impaga: no puede usar la cuenta TOTEM manualmente. '
                    .'Se asigna automáticamente solo si la orden ya fue cobrada en el tótem Waitry.',
                ];
            }
        }

        return [];
    }

    /**
     * Cuenta Waitry cobrada en tótem: cobranza Anita siempre en cuenta puente TOTEM (único renglón).
     *
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null}>  $mediosPago
     * @return list<string>
     */
    private function erroresCobranzaWaitryTotem(CuentaGastronomia $cuenta, array $mediosPago, int $empresaId): array
    {
        if (! $cuenta->waitry_cobro_totem) {
            return [];
        }

        $totemErr = GastronomiaCuentacajaTotem::mensajeErrorResolucion($empresaId);
        if ($totemErr !== null) {
            return [$totemErr];
        }

        $esperada = WaitryMedioPagoCuentacajaSupport::cuentaPuenteTotemFacturacion(
            $empresaId,
            $cuenta->waitry_tipo_pago,
        );
        if ($esperada === null) {
            return ['No se pudo resolver la cuenta de caja TOTEM para la cobranza Waitry del tótem.'];
        }

        $medioWaitry = WaitryMedioPagoCuentacajaSupport::etiquetaTipo($cuenta->waitry_tipo_pago);

        if (count($mediosPago) !== 1) {
            return ['La cuenta Waitry del tótem debe cobrarse con un único medio TOTEM'
                .($medioWaitry !== '—' ? ' (pagada en tótem con «'.$medioWaitry.'»)' : '')
                .', sin agregar otros renglones.'];
        }

        $medio = $mediosPago[0];
        if ((int) ($medio['cuentacaja_id'] ?? 0) !== (int) $esperada['id']) {
            return ['La cobranza de esta cuenta Waitry debe usar únicamente la cuenta puente «'
                .$esperada['codigo'].' — '.$esperada['nombre'].'»'
                .($medioWaitry !== '—' ? ' (pagada en tótem con «'.$medioWaitry.'»)' : '')
                .'.'];
        }

        return [];
    }

    private function idCuentacajaTotem(int $empresaId): int
    {
        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);

        return (int) ($totem['id'] ?? 0);
    }

    /**
     * Canjes premio Wigos (cupón) y fidelidad (tarjeta): solo F8, descuento configurado y factura de cortesía.
     *
     * @param  array{total?:float,sin_cobranza?:bool,factura_cortesia?:bool,error?:string}  $preview
     * @return list<string>
     */
    private function erroresCanjePendienteRequiereFacturacionConDescuento(
        CuentaGastronomia $cuenta,
        bool $facturacionConDescuento,
        array $preview,
    ): array {
        if (! $this->cuentaTieneCanjePendienteFacturacionConDescuento($cuenta)) {
            return [];
        }

        if (! $facturacionConDescuento) {
            return [
                'Esta cuenta tiene un canje de premio o fidelidad pendiente: use F8 «Facturar con descuento». '
                .'No puede facturar con F5 ni sin el flujo de descuento.',
            ];
        }

        $errores = [];
        $descuentoId = (int) ($cuenta->descuento_gastronomia_id ?? 0);
        if ($descuentoId <= 0) {
            $errores[] = 'Debe aplicar el descuento gastronomía del canje (F8) antes de emitir.';

            return $errores;
        }

        $descuento = $cuenta->descuentoGastronomia;
        if (! $descuento) {
            $errores[] = 'Descuento gastronomía del canje no encontrado.';

            return $errores;
        }

        $codigoDesc = trim((string) $descuento->codigo);
        if ($this->tieneCanjePremioPendiente($cuenta)) {
            $codigoEsperado = trim((string) config('gastronomia.canje_premio_descuento_codigo', '10'));
            if ($codigoDesc !== $codigoEsperado) {
                $errores[] = 'El descuento debe ser el configurado para canje de premios Wigos (código '.$codigoEsperado.').';
            }
        }
        if ($this->tieneCanjeFidelidadPendiente($cuenta)) {
            $codigoEsperado = trim((string) config('gastronomia.canje_fidelidad_descuento_codigo', '10'));
            if ($codigoDesc !== $codigoEsperado) {
                $errores[] = 'El descuento debe ser el configurado para canje de fidelidad (código '.$codigoEsperado.').';
            }
        }

        if (empty($preview['sin_cobranza'])) {
            $errores[] = 'Los canjes deben facturarse como cortesía ($0,01) con el descuento del 100 % aplicado.';
        }

        return $errores;
    }

    private function cuentaTieneCanjePendienteFacturacionConDescuento(CuentaGastronomia $cuenta): bool
    {
        return $cuenta->tieneCanjePendienteRequiereFacturacionConDescuento();
    }

    private function tieneCanjePremioPendiente(CuentaGastronomia $cuenta): bool
    {
        return $cuenta->tieneCanjePremioPendiente();
    }

    private function tieneCanjeFidelidadPendiente(CuentaGastronomia $cuenta): bool
    {
        return $cuenta->tieneCanjeFidelidadPendiente();
    }

    /**
     * @param  array{puntoventa_id:int,usa_caea:bool}  $resolucionPv
     * @return list<string>
     */
    private static function erroresPuntoventaEmisionInexistente(
        array $resolucionPv,
        int $pvCae,
        int $pvCaea,
    ): array {
        $puntoventaId = (int) ($resolucionPv['puntoventa_id'] ?? 0);
        if ($puntoventaId <= 0) {
            return ['Configure un punto de venta CAE o CAEA válido en la configuración gastronomía de esta terminal.'];
        }

        if (Puntoventa::query()->whereKey($puntoventaId)->exists()) {
            return [];
        }

        $usaCaea = ! empty($resolucionPv['usa_caea']);
        $configId = $usaCaea ? $pvCaea : $pvCae;
        $tipo = $usaCaea ? 'CAEA' : 'CAE';
        $modo = $usaCaea && ArcaWsfeEmisionResiliencia::forzarModoCaea()
            ? ' (modo CAEA forzado por ARCA_WSFE_FORZAR_MODO_CAEA)'
            : '';

        return [
            'El punto de venta '.$tipo.' configurado (id '.$configId.') no existe o fue eliminado'.$modo
            .'. Actualícelo en Ventas → Configuración punto de venta gastronomía.',
        ];
    }
}
