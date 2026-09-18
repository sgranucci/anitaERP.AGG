<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\RetencionIIBB;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Support\Caja\ChequePropioImputacionSupport;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use App\Support\Numerico\NumeroDecimalLocalSupport;
use RuntimeException;

/**
 * Preview/armado de asiento TES para orden de pago.
 *
 * Debe: proveedores MN/ME (según OC del comprobante); anticipos por lo pagado sin comprobante.
 * Haber: cuentas de caja, cheques, retenciones; NC restan de proveedores; OPA al Haber de anticipos
 * (o de proveedores si la empresa no tiene cuenta de anticipo).
 *
 * Moneda de la operación = moneda del pago. Todas las líneas del asiento van en esa moneda
 * (importes convertidos). Cotización única del pago en todas las líneas (también MN, para
 * poder expresar el movimiento en ME). La diferencia vs cotización de factura va a
 * diferencia de cambio (P&L), no a otra TC por línea.
 *
 * Cruzada (ej. factura DOL / pago PES): proveedor al valor libro en PES (monto × cot_factura);
 * DC = valor_libro − valor_liquidación asienta la pérdida/ganancia sin “anticipo” parche.
 *
 * Retenciones: se calculan en MN; en el asiento van en moneda del pago convertidas al
 * cambio del pago.
 *
 * Concepto de movimiento (pago.c):
 *   sprintf(concepto[0], "Pago: %s #%ld", proveedor15, nro);
 *   sprintf(concepto[1], "Pago ad.: %-11.11s #%ld", …);  // anticipo
 *   sprintf(concepto_chp, "%s Ch: %ld", proveedor14, nroCheque);
 */
final class PagoproveedorAsientoArmadoSupport
{
    /**
     * @param  list<object|array<string, mixed>>  $datosCaja
     * @param  list<object|array<string, mixed>>  $datosContables
     * @param  list<object|array<string, mixed>>  $datosChequesEmitidos
     * @param  list<object|array<string, mixed>>  $datosChequesRecibidos
     * @param  list<object|array<string, mixed>>  $datosComprobantes
     * @param  list<object|array<string, mixed>>  $datosRetenciones
     * @return list<array<string, mixed>>
     */
    public static function armar(
        array $datosCaja,
        array $datosContables,
        array $datosChequesEmitidos,
        array $datosChequesRecibidos,
        array $datosComprobantes,
        array $datosRetenciones,
        int $empresaId,
        int $proveedorId,
        string $fechaOperacion,
        CuentacajaRepositoryInterface $cuentacajaRepository,
        CuentacontableRepositoryInterface $cuentacontableRepository,
        string $proveedorNombre = '',
        string $numeroOp = '',
        int $monedaPagoId = 1,
        float $cotizacionPago = 1.0,
    ): array {
        $monedaLocal = self::monedaLocalId();
        if ($monedaPagoId <= 0) {
            $monedaPagoId = $monedaLocal;
        }
        $cotizacionPago = self::cotizacionUnicaDelPago($monedaPagoId, $cotizacionPago, $fechaOperacion);

        $asiento = [];
        $conceptoPago = self::conceptoPago($proveedorNombre, $numeroOp);
        $conceptoAnticipo = self::conceptoPagoAdelantado($proveedorNombre, $numeroOp);

        if ($datosContables !== []) {
            foreach ($datosContables as $linea) {
                $linea = self::asObject($linea);
                $cuentaId = (int) ($linea->cuentacontable_ids ?? 0);
                if ($cuentaId <= 0) {
                    continue;
                }
                $cuenta = $cuentacontableRepository->find($cuentaId);
                if ($cuenta === null) {
                    continue;
                }
                $obs = trim((string) ($linea->observacionasientos ?? ''));
                $monedaLin = (int) ($linea->monedaasiento_ids ?? $monedaPagoId);
                $cotLin = NumeroDecimalLocalSupport::aFloat($linea->cotizacionasientos ?? 0);
                if ($cotLin <= 0) {
                    $cotLin = self::cotizacionParaLinea($monedaLin, $cotizacionPago);
                }
                $debeLin = NumeroDecimalLocalSupport::aFloat($linea->debeasientos ?? 0);
                $haberLin = NumeroDecimalLocalSupport::aFloat($linea->haberasientos ?? 0);
                $asiento[] = [
                    'cuentacontable_id' => $cuentaId,
                    'codigo' => $cuenta->codigo,
                    'nombre' => $cuenta->nombre,
                    'moneda_id' => $monedaLin,
                    'cotizacion' => $cotLin,
                    'centrocosto_id' => (int) ($linea->centrocostoasiento_ids ?? 0),
                    'debe' => $debeLin > 0.0001 ? round($debeLin, 2) : '',
                    'haber' => $haberLin > 0.0001 ? round($haberLin, 2) : '',
                    'observacion' => $obs !== '' ? $obs : $conceptoPago,
                    'carga_cuentacontable_manual' => $linea->carga_cuentacontable_manuales ?? 'N',
                ];
            }

            return $asiento;
        }

        foreach ($datosCaja as $mov) {
            $mov = self::asObject($mov);
            $monto = abs((float) ($mov->montos ?? 0));
            if ($monto <= 0) {
                continue;
            }
            $cuentacaja = $cuentacajaRepository->find((int) ($mov->cuentacaja_ids ?? 0));
            if ($cuentacaja === null || empty($cuentacaja->cuentacontable_id)) {
                continue;
            }
            $obs = trim((string) ($mov->observaciones ?? ''));
            $monedaMedio = (int) ($mov->moneda_ids ?? $monedaPagoId);
            $montoLin = self::convertirImporteAMonedaPago(
                $monto,
                $monedaMedio,
                $monedaPagoId,
                $cotizacionPago
            );
            self::agregaCuenta(
                $asiento,
                (int) $cuentacaja->cuentacontable_id,
                $monedaPagoId,
                self::cotizacionParaLinea($monedaPagoId, $cotizacionPago),
                'H',
                $montoLin,
                $cuentacontableRepository,
                $obs !== '' ? $obs : $conceptoPago
            );
        }

        foreach ($datosChequesEmitidos as $cheque) {
            $cheque = self::asObject($cheque);
            $monto = abs((float) ($cheque->montos ?? 0));
            if ($monto <= 0) {
                continue;
            }
            $cuentacajaId = (int) ($cheque->cuentacaja_ids ?? 0);
            if ($cuentacajaId <= 0) {
                throw new RuntimeException(
                    'Cheque emitido sin cuenta de tesorería. Elija la cuenta/banco del cheque antes de armar el asiento.'
                );
            }
            $cuentaId = ChequePropioImputacionSupport::resolverCuentacontableIdEmitido(
                $empresaId,
                $cuentacajaId,
                $fechaOperacion,
                (string) ($cheque->fechapagos ?? $fechaOperacion),
                $cuentacajaRepository,
                $cuentacontableRepository
            );
            if ($cuentaId === null || $cuentaId <= 0) {
                throw new RuntimeException(
                    'No se pudo resolver la cuenta contable del cheque emitido (cuentacaja #'.$cuentacajaId
                    .'). Verifique que la cuenta de caja tenga cuentacontable_id o, si usa diferidos, Contable → Cuentas automáticas (caja.cheques_diferidos).'
                );
            }
            $monedaMedio = (int) ($cheque->moneda_ids ?? $monedaPagoId);
            $montoLin = self::convertirImporteAMonedaPago(
                $monto,
                $monedaMedio,
                $monedaPagoId,
                $cotizacionPago
            );
            $nroCh = (string) ($cheque->numerocheques ?? $cheque->numerocheque ?? '');
            self::agregaCuenta(
                $asiento,
                $cuentaId,
                $monedaPagoId,
                self::cotizacionParaLinea($monedaPagoId, $cotizacionPago),
                'H',
                $montoLin,
                $cuentacontableRepository,
                self::conceptoChequePropio($proveedorNombre, $nroCh)
            );
        }

        foreach ($datosChequesRecibidos as $cheque) {
            $cheque = self::asObject($cheque);
            $monto = abs((float) ($cheque->montos ?? 0));
            if ($monto <= 0) {
                continue;
            }
            $cuentaId = ChequePropioImputacionSupport::resolverCuentacontableIdValoresADepositar(
                $empresaId,
                $cuentacontableRepository
            );
            if ($cuentaId === null || $cuentaId <= 0) {
                throw new RuntimeException(
                    'No se pudo resolver la cuenta de valores a depositar para cheques de terceros. '
                    .'Configure Contable → Cuentas automáticas (caja.valores_a_depositar).'
                );
            }
            $monedaMedio = (int) ($cheque->moneda_ids ?? $monedaPagoId);
            $montoLin = self::convertirImporteAMonedaPago(
                $monto,
                $monedaMedio,
                $monedaPagoId,
                $cotizacionPago
            );
            self::agregaCuenta(
                $asiento,
                $cuentaId,
                $monedaPagoId,
                self::cotizacionParaLinea($monedaPagoId, $cotizacionPago),
                'H',
                $montoLin,
                $cuentacontableRepository,
                $conceptoPago
            );
        }

        // Retenciones: importe en MN → moneda del pago al cambio del pago.
        foreach ($datosRetenciones as $ret) {
            $ret = self::asObject($ret);
            $montoMn = abs((float) ($ret->montos ?? $ret->importe ?? 0));
            if ($montoMn <= 0) {
                continue;
            }
            $cuentaId = self::resolverCuentaRetencion($ret, $empresaId);
            if ($cuentaId <= 0) {
                $tipo = strtoupper(trim((string) ($ret->tiporetencion ?? '')));
                throw new RuntimeException(
                    'No hay cuenta contable para retención '.$tipo
                    .'. Configure Contable → Cuentas automáticas (pago.retencion_ganancias / iva / suss / iibb)'
                    .(in_array($tipo, ['B', 'IIBB', 'RTP'], true)
                        ? ' o Compras → Retención IIBB (cuenta por provincia).'
                        : '.')
                );
            }
            $montoLin = self::convertirMontoRetencionAMonedaPago($montoMn, $monedaPagoId, $cotizacionPago);
            self::agregaCuenta(
                $asiento,
                $cuentaId,
                $monedaPagoId,
                $cotizacionPago,
                'H',
                $montoLin,
                $cuentacontableRepository,
                $conceptoPago
            );
        }

        $proveedor = Proveedor::query()->find($proveedorId);
        if ($proveedorNombre === '' && $proveedor) {
            $proveedorNombre = (string) ($proveedor->nombre ?? '');
            $conceptoPago = self::conceptoPago($proveedorNombre, $numeroOp);
            $conceptoAnticipo = self::conceptoPagoAdelantado($proveedorNombre, $numeroOp);
        }

        $totalesPorCuenta = [];
        $dcTotal = 0.0;
        $cuentaApRef = 0;
        $dcSoloPnL = false; // cruzada a MN: proveedor a valor libro; DC sin contraasiento en AP
        foreach ($datosComprobantes as $comp) {
            $comp = self::asObject($comp);
            $monto = abs((float) ($comp->montos ?? 0));
            if ($monto <= 0) {
                continue;
            }
            $ccId = (int) ($comp->proveedor_cuentacorriente_ids ?? $comp->idcuentacorrientes ?? 0);
            $cuentaId = 0;
            $monedaDeudaId = (int) ($comp->moneda_ids ?? 1);
            $cc = null;
            if ($ccId > 0) {
                $cc = Proveedor_Cuentacorriente::query()
                    ->with(['comprobante_proveedores.ordencompras.ordencompra_articulos', 'comprobante_proveedores.proveedores'])
                    ->find($ccId);
            }
            $esOpa = $cc !== null && PagoproveedorAplicacionLadoSupport::esOpa($cc);
            $signo = $cc !== null ? PagoproveedorAplicacionLadoSupport::signo($cc) : 1;

            if ($esOpa) {
                $cuentaAnticipo = ProveedorAnticipoCuentaContableSupport::cuentaParaCreditoAplicado($cc);
                if ($cuentaAnticipo) {
                    $cuentaId = $cuentaAnticipo;
                    $monedaDeudaId = (int) ($cc->moneda_id ?: $monedaDeudaId);
                }
            }
            if ($cuentaId <= 0 && $cc?->comprobante_proveedores) {
                $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorDesdeComprobante(
                    $cc->comprobante_proveedores,
                    $proveedor
                );
                $monedaDeudaId = ProveedorCuentaContableMonedaSupport::monedaIdParaCuentaProveedor($cc->comprobante_proveedores)
                    ?: $monedaDeudaId;
            }
            if ($cuentaId <= 0) {
                $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorId($proveedor, $monedaDeudaId);
            }
            if ($cuentaId <= 0) {
                continue;
            }

            $cotDeuda = (float) ($comp->cotizaciones ?? $comp->cotizacion ?? 1);
            $cotApl = (float) ($comp->cotizacion_aplicadas ?? $comp->cotizacion_aplicada ?? 0);
            if ($cotApl <= 0) {
                $cotApl = $cotizacionPago;
            }
            $liq = PagoproveedorLiquidacionSupport::calcular(
                $monto,
                $monedaDeudaId,
                $cotDeuda,
                $monedaPagoId,
                $cotApl
            );

            // Preferir DC ya liquidada en el form / persistida; si no, la del support.
            $dcLinea = isset($comp->diferencias_cambio)
                ? (float) $comp->diferencias_cambio
                : (isset($comp->diferencia_cambio) ? (float) $comp->diferencia_cambio : $liq['dc']);

            // Moneda de la operación: importe en moneda del pago.
            // Cruzada hacia MN → valor libro (la DC completa hasta liquidación).
            // Misma moneda / cruzada hacia ME → monto o equivalente de liquidación.
            if ($liq['cruzada'] && $monedaPagoId <= $monedaLocal) {
                $montoEnMonedaPago = (float) $liq['valor_local_deuda'];
                $dcSoloPnL = true;
            } elseif ($liq['cruzada']) {
                $montoEnMonedaPago = (float) $liq['equivalente_pago'];
            } else {
                $montoEnMonedaPago = $monto;
            }

            $cotLinea = self::cotizacionParaLinea($monedaPagoId, $cotizacionPago);
            $key = $cuentaId.'|'.$monedaPagoId.'|'.$cotLinea;
            if (! isset($totalesPorCuenta[$key])) {
                $totalesPorCuenta[$key] = [
                    'cuentacontable_id' => $cuentaId,
                    'moneda_id' => $monedaPagoId,
                    'cotizacion' => $cotLinea,
                    'monto' => 0.0,
                ];
            }
            $totalesPorCuenta[$key]['monto'] += $signo * $montoEnMonedaPago;
            $dcTotal += $signo * $dcLinea;
            if ($signo > 0) {
                $cuentaApRef = $cuentaId;
            }
        }

        foreach ($totalesPorCuenta as $fila) {
            $montoNeto = round((float) $fila['monto'], 4);
            if (abs($montoNeto) < 0.0001) {
                continue;
            }
            self::agregaCuenta(
                $asiento,
                (int) $fila['cuentacontable_id'],
                (int) $fila['moneda_id'],
                (float) $fila['cotizacion'],
                $montoNeto >= 0 ? 'D' : 'H',
                abs($montoNeto),
                $cuentacontableRepository,
                $conceptoPago
            );
        }

        self::agregarDcSiCorresponde(
            $asiento,
            $dcTotal,
            $cuentaApRef,
            $proveedor,
            $cuentacontableRepository,
            $conceptoPago,
            $cotizacionPago,
            $dcSoloPnL,
            $empresaId
        );

        self::agregarAnticipoSiCorresponde(
            $asiento,
            $empresaId,
            $cuentacontableRepository,
            $conceptoAnticipo,
            $monedaPagoId,
            $cotizacionPago
        );

        return $asiento;
    }

    /**
     * Cotización única del pago (pago.c in_cotizacion). Se usa en todas las líneas.
     * Si el pago es MN y el header viene en 1, toma la venta DOL del día para poder
     * expresar el asiento en ME (mismo criterio que facturas PES con TC).
     */
    public static function cotizacionUnicaDelPago(
        int $monedaPagoId,
        float $cotizacionPago,
        ?string $fechaOperacion = null
    ): float {
        if ($cotizacionPago > 1.0001) {
            return $cotizacionPago;
        }

        if ($monedaPagoId <= self::monedaLocalId()) {
            $dolId = max(2, (int) config('cotizacion.monedaIdCommand', 2));
            $dia = CotizacionVigenteSupport::ventaValor($fechaOperacion, $dolId);
            if ($dia > 1.0001) {
                return $dia;
            }
        }

        return $cotizacionPago > 0 ? $cotizacionPago : 1.0;
    }

    /** TC a grabar en cada línea: siempre la del pago (también en MN). */
    public static function cotizacionParaLinea(int $monedaLineaId, float $cotizacionPago): float
    {
        unset($monedaLineaId);

        return $cotizacionPago > 0 ? $cotizacionPago : 1.0;
    }

    /**
     * Retención nace en MN; si el pago es ME se convierte al cambio del pago.
     */
    public static function convertirMontoRetencionAMonedaPago(
        float $montoMn,
        int $monedaPagoId,
        float $cotizacionPago
    ): float {
        return self::convertirImporteAMonedaPago($montoMn, self::monedaLocalId(), $monedaPagoId, $cotizacionPago);
    }

    /**
     * Lleva un importe a la moneda del pago (moneda de la operación).
     * MN→ME: ÷ TC. ME→MN: × TC. Misma moneda: sin cambio.
     */
    public static function convertirImporteAMonedaPago(
        float $monto,
        int $monedaOrigenId,
        int $monedaPagoId,
        float $cotizacionPago
    ): float {
        $monto = abs($monto);
        if ($monto < 0.0001) {
            return 0.0;
        }
        if ($monedaOrigenId === $monedaPagoId) {
            return round($monto, 4);
        }
        $cot = $cotizacionPago > 0 ? $cotizacionPago : 1.0;
        $local = self::monedaLocalId();
        if ($monedaOrigenId <= $local && $monedaPagoId > $local) {
            return round($monto / $cot, 4);
        }
        if ($monedaOrigenId > $local && $monedaPagoId <= $local) {
            return round($monto * $cot, 4);
        }

        return round($monto, 4);
    }

    private static function monedaLocalId(): int
    {
        return max(1, (int) config('cotizacion.ID_MONEDA_DEFAULT', 1));
    }

    /**
     * pago.c: sprintf(concepto[0], "Pago: %s #%ld", xstr, in_recibo);
     */
    public static function conceptoPago(string $proveedorNombre, string $numeroOp): string
    {
        $nombre = self::truncarNombre($proveedorNombre, 15);
        $nro = self::numeroParaConcepto($numeroOp);

        return sprintf('Pago: %s #%s', $nombre, $nro);
    }

    /**
     * pago.c: sprintf(concepto[1], "Pago ad.: %-11.11s #%ld", xstr, in_recibo);
     */
    public static function conceptoPagoAdelantado(string $proveedorNombre, string $numeroOp): string
    {
        $nombre = self::truncarNombre($proveedorNombre, 11);
        $nro = self::numeroParaConcepto($numeroOp);

        return sprintf('Pago ad.: %s #%s', $nombre, $nro);
    }

    /**
     * pago.c: sprintf(concepto_chp, "%s Ch: %ld", xstr, tchep[i].cheq);
     */
    public static function conceptoChequePropio(string $proveedorNombre, string $numeroCheque): string
    {
        $nombre = self::truncarNombre($proveedorNombre, 14);
        $nro = preg_replace('/\D+/', '', $numeroCheque) ?: $numeroCheque;
        if ($nro === '') {
            $nro = '0';
        }

        return sprintf('%s Ch: %s', $nombre, $nro);
    }

    private static function truncarNombre(string $nombre, int $max): string
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
        if ($nombre === '') {
            $nombre = 'Proveedor';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($nombre, 0, $max);
        }

        return substr($nombre, 0, $max);
    }

    private static function numeroParaConcepto(string $numeroOp): string
    {
        $nro = trim($numeroOp);
        if ($nro === '') {
            return '0';
        }

        return $nro;
    }

    /**
     * @param  list<array<string, mixed>>  $asiento
     */
    private static function agregarAnticipoSiCorresponde(
        array &$asiento,
        int $empresaId,
        CuentacontableRepositoryInterface $cuentacontableRepository,
        string $concepto,
        int $monedaPagoId = 1,
        float $cotizacionPago = 1.0,
    ): void {
        $linea = PagoproveedorAnticipoAsientoSupport::linea(
            $asiento,
            $empresaId,
            $monedaPagoId,
            $cotizacionPago
        );
        if ($linea === null) {
            return;
        }

        self::agregaCuenta(
            $asiento,
            $linea['cuentacontable_id'],
            $linea['moneda_id'],
            $linea['cotizacion'],
            'D',
            $linea['monto'],
            $cuentacontableRepository,
            $concepto
        );
    }

    /**
     * Cuenta de retención: Contable → Cuentas automáticas (pago.retencion_*).
     * IIBB: primero provincia (Compras → Retención IIBB); si no, fallback automático.
     */
    private static function resolverCuentaRetencion(object $ret, int $empresaId): int
    {
        $directo = (int) ($ret->cuentacontable_ids ?? $ret->cuentacontable_id ?? 0);
        if ($directo > 0) {
            return $directo;
        }

        $tipo = strtoupper(trim((string) ($ret->tiporetencion ?? '')));

        if (in_array($tipo, ['B', 'IIBB', 'RTP'], true)) {
            $provinciaId = (int) ($ret->provincia_ids ?? $ret->provincia_id ?? 0);
            if ($provinciaId > 0) {
                $reg = RetencionIIBB::query()->where('provincia_id', $provinciaId)->first();
                if ($reg && (int) ($reg->cuentacontable_id ?? 0) > 0) {
                    return (int) $reg->cuentacontable_id;
                }
            }
        }

        $clave = match ($tipo) {
            'G', 'GANANCIAS', 'RGP' => CuentaAutomaticaClaves::PAGO_RETENCION_GANANCIAS,
            'I', 'IVA', 'V', 'RIP', 'RIV' => CuentaAutomaticaClaves::PAGO_RETENCION_IVA,
            'S', 'SUSS', 'RSP' => CuentaAutomaticaClaves::PAGO_RETENCION_SUSS,
            'B', 'IIBB', 'RTP' => CuentaAutomaticaClaves::PAGO_RETENCION_IIBB,
            default => null,
        };

        if ($clave === null || $empresaId <= 0) {
            return 0;
        }

        return (int) (CuentaAutomaticaResolver::resolverId($empresaId, $clave) ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $asiento
     */
    private static function agregaCuenta(
        array &$asiento,
        int $cuentacontableId,
        int $monedaId,
        float $cotizacion,
        string $d_h,
        float $monto,
        CuentacontableRepositoryInterface $cuentacontableRepository,
        string $observacion = '',
    ): void {
        $debe = $d_h === 'D' ? $monto : '';
        $haber = $d_h === 'H' ? $monto : '';

        $idx = null;
        foreach ($asiento as $i => $linea) {
            if ((int) $linea['cuentacontable_id'] === $cuentacontableId
                && (int) $linea['moneda_id'] === $monedaId
                && (float) $linea['cotizacion'] === (float) $cotizacion
                && (string) ($linea['observacion'] ?? '') === $observacion) {
                $idx = $i;
                break;
            }
        }

        if ($idx === null) {
            $cuenta = $cuentacontableRepository->find($cuentacontableId);
            if ($cuenta === null) {
                return;
            }
            $asiento[] = [
                'cuentacontable_id' => $cuentacontableId,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'centrocosto_id' => 0,
                'debe' => $debe,
                'haber' => $haber,
                'observacion' => $observacion,
                'carga_cuentacontable_manual' => 'N',
            ];

            return;
        }

        if ($debe !== '') {
            $asiento[$idx]['debe'] = (float) ($asiento[$idx]['debe'] ?: 0) + $monto;
        }
        if ($haber !== '') {
            $asiento[$idx]['haber'] = (float) ($asiento[$idx]['haber'] ?: 0) + $monto;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $asiento
     */
    private static function agregarDcSiCorresponde(
        array &$asiento,
        float $dcTotal,
        int $cuentaApId,
        ?Proveedor $proveedor,
        CuentacontableRepositoryInterface $cuentacontableRepository,
        string $concepto,
        float $cotizacionPago = 1.0,
        bool $soloPnL = false,
        int $empresaId = 0,
    ): void {
        $dcTotal = round($dcTotal, 4);
        if (abs($dcTotal) < 0.01) {
            return;
        }

        $cuentaDcId = self::resolverCuentaDcId($cuentaApId, $proveedor, $cuentacontableRepository, $empresaId);
        if ($cuentaDcId <= 0) {
            throw new RuntimeException(
                'Hay diferencia de cambio ('.number_format(abs($dcTotal), 2, ',', '.').') pero falta la cuenta '
                .'de diferencia de cambio en Proveedores ME/MN. Configúrela en Contable → Cuentas (Dif. de cambio).'
            );
        }

        $monedaLocal = self::monedaLocalId();
        $cot = self::cotizacionParaLinea($monedaLocal, $cotizacionPago);
        $importe = abs($dcTotal);
        // dc = valor_libro − valor_liquidación. dc < 0 → pérdida (Debe DC).
        $perdida = $dcTotal < 0;

        self::agregaCuenta(
            $asiento,
            $cuentaDcId,
            $monedaLocal,
            $cot,
            $perdida ? 'D' : 'H',
            $importe,
            $cuentacontableRepository,
            $concepto
        );

        // Misma moneda ME: contraasiento en AP en MN. Cruzada a MN a valor libro: solo P&L.
        if (! $soloPnL && $cuentaApId > 0) {
            self::agregaCuenta(
                $asiento,
                $cuentaApId,
                $monedaLocal,
                $cot,
                $perdida ? 'H' : 'D',
                $importe,
                $cuentacontableRepository,
                $concepto
            );
        }
    }

    private static function resolverCuentaDcId(
        int $cuentaApId,
        ?Proveedor $proveedor,
        CuentacontableRepositoryInterface $cuentacontableRepository,
        int $empresaId = 0,
    ): int {
        $ids = array_filter([
            $cuentaApId,
            (int) ($proveedor->cuentacontable_id ?? 0),
            (int) ($proveedor->cuentacontableme_id ?? 0),
        ]);
        foreach ($ids as $id) {
            $cuenta = $cuentacontableRepository->find($id);
            $dcId = (int) ($cuenta->cuentacontable_difcambio_id ?? 0);
            if ($dcId > 0 && $dcId !== (int) $id) {
                return $dcId;
            }
        }

        if ($empresaId > 0) {
            $fallback = \App\Models\Contable\Cuentacontable::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', '532020001')
                ->value('id');
            if ($fallback) {
                return (int) $fallback;
            }
        }

        return 0;
    }

    /**
     * @param  object|array<string, mixed>  $value
     */
    private static function asObject(object|array $value): object
    {
        return is_array($value) ? (object) $value : $value;
    }
}
