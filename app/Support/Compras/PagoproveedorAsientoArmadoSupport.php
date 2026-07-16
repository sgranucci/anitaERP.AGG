<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\RetencionIIBB;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Support\Caja\ChequePropioImputacionSupport;

/**
 * Preview/armado de asiento TES para orden de pago.
 *
 * Debe: proveedores MN/ME (según OC del comprobante).
 * Haber: cuentas de caja, cheques propios (banco/diferidos), cheques entregados (valores a depositar), retenciones.
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
    ): array {
        $asiento = [];

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
                $asiento[] = [
                    'cuentacontable_id' => $cuentaId,
                    'codigo' => $cuenta->codigo,
                    'nombre' => $cuenta->nombre,
                    'moneda_id' => (int) ($linea->monedaasiento_ids ?? 1),
                    'cotizacion' => (float) ($linea->cotizacionasientos ?? 1),
                    'centrocosto_id' => (int) ($linea->centrocostoasiento_ids ?? 0),
                    'debe' => $linea->debeasientos ?? '',
                    'haber' => $linea->haberasientos ?? '',
                    'observacion' => (string) ($linea->observacionasientos ?? ''),
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
            self::agregaCuenta(
                $asiento,
                (int) $cuentacaja->cuentacontable_id,
                (int) ($mov->moneda_ids ?? 1),
                (float) ($mov->cotizaciones ?? 1),
                'H',
                $monto,
                $cuentacontableRepository,
                (string) ($mov->observaciones ?? '')
            );
        }

        foreach ($datosChequesEmitidos as $cheque) {
            $cheque = self::asObject($cheque);
            $monto = abs((float) ($cheque->montos ?? 0));
            if ($monto <= 0) {
                continue;
            }
            $cuentaId = ChequePropioImputacionSupport::resolverCuentacontableIdEmitido(
                $empresaId,
                (int) ($cheque->cuentacaja_ids ?? 0),
                $fechaOperacion,
                (string) ($cheque->fechapagos ?? $fechaOperacion),
                $cuentacajaRepository,
                $cuentacontableRepository
            );
            if ($cuentaId === null) {
                continue;
            }
            self::agregaCuenta(
                $asiento,
                $cuentaId,
                (int) ($cheque->moneda_ids ?? 1),
                (float) ($cheque->cotizaciones ?? 1),
                'H',
                $monto,
                $cuentacontableRepository,
                'Cheque propio emitido'
            );
        }

        $valoresId = ChequePropioImputacionSupport::resolverCuentacontableIdValoresADepositar(
            $empresaId,
            $cuentacontableRepository
        );
        if ($valoresId !== null) {
            foreach ($datosChequesRecibidos as $cheque) {
                $cheque = self::asObject($cheque);
                $monto = abs((float) ($cheque->montos ?? 0));
                if ($monto <= 0) {
                    continue;
                }
                self::agregaCuenta(
                    $asiento,
                    $valoresId,
                    (int) ($cheque->moneda_ids ?? 1),
                    (float) ($cheque->cotizaciones ?? 1),
                    'H',
                    $monto,
                    $cuentacontableRepository,
                    'Cheque de terceros entregado'
                );
            }
        }

        foreach ($datosRetenciones as $ret) {
            $ret = self::asObject($ret);
            $monto = abs((float) ($ret->montos ?? $ret->importe ?? 0));
            if ($monto <= 0) {
                continue;
            }
            $cuentaId = self::resolverCuentaRetencion($ret);
            if ($cuentaId <= 0) {
                continue;
            }
            self::agregaCuenta(
                $asiento,
                $cuentaId,
                (int) ($ret->moneda_ids ?? 1),
                (float) ($ret->cotizaciones ?? 1),
                'H',
                $monto,
                $cuentacontableRepository,
                'Retención '.(string) ($ret->tiporetencion ?? '')
            );
        }

        $proveedor = Proveedor::query()->find($proveedorId);
        $totalesPorCuenta = [];
        foreach ($datosComprobantes as $comp) {
            $comp = self::asObject($comp);
            $monto = abs((float) ($comp->montos ?? 0));
            if ($monto <= 0) {
                continue;
            }
            $ccId = (int) ($comp->proveedor_cuentacorriente_ids ?? $comp->idcuentacorrientes ?? 0);
            $cuentaId = 0;
            $monedaId = (int) ($comp->moneda_ids ?? 1);
            if ($ccId > 0) {
                $cc = Proveedor_Cuentacorriente::query()
                    ->with(['comprobante_proveedores.ordencompras.ordencompra_articulos', 'comprobante_proveedores.proveedores'])
                    ->find($ccId);
                if ($cc?->comprobante_proveedores) {
                    $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorDesdeComprobante(
                        $cc->comprobante_proveedores,
                        $proveedor
                    );
                    $monedaId = ProveedorCuentaContableMonedaSupport::monedaIdParaCuentaProveedor($cc->comprobante_proveedores)
                        ?: $monedaId;
                }
            }
            if ($cuentaId <= 0) {
                $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorId($proveedor, $monedaId);
            }
            if ($cuentaId <= 0) {
                continue;
            }
            $key = $cuentaId.'|'.$monedaId.'|'.(float) ($comp->cotizaciones ?? 1);
            if (! isset($totalesPorCuenta[$key])) {
                $totalesPorCuenta[$key] = [
                    'cuentacontable_id' => $cuentaId,
                    'moneda_id' => $monedaId,
                    'cotizacion' => (float) ($comp->cotizaciones ?? 1),
                    'monto' => 0.0,
                ];
            }
            $totalesPorCuenta[$key]['monto'] += $monto;
        }

        foreach ($totalesPorCuenta as $fila) {
            self::agregaCuenta(
                $asiento,
                (int) $fila['cuentacontable_id'],
                (int) $fila['moneda_id'],
                (float) $fila['cotizacion'],
                'D',
                (float) $fila['monto'],
                $cuentacontableRepository,
                'Cancelación proveedores'
            );
        }

        return $asiento;
    }

    private static function resolverCuentaRetencion(object $ret): int
    {
        $tipo = strtoupper((string) ($ret->tiporetencion ?? ''));
        if (in_array($tipo, ['B', 'IIBB'], true)) {
            $provinciaId = (int) ($ret->provincia_ids ?? $ret->provincia_id ?? 0);
            if ($provinciaId > 0) {
                $reg = RetencionIIBB::query()->where('provincia_id', $provinciaId)->first();
                if ($reg && (int) $reg->cuentacontable_id > 0) {
                    return (int) $reg->cuentacontable_id;
                }
            }
            $directo = (int) ($ret->cuentacontable_ids ?? 0);
            if ($directo > 0) {
                return $directo;
            }
        }

        return (int) ($ret->cuentacontable_ids ?? 0);
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
     * @param  object|array<string, mixed>  $value
     */
    private static function asObject(object|array $value): object
    {
        return is_array($value) ? (object) $value : $value;
    }
}
