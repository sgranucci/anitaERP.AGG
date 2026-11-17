<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\Cliente;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use RuntimeException;

/**
 * Arma las piernas del asiento. Si hay subdiario de la sala, manda ese.
 * Si no, usa las cuentas de Configuración → Cuentas automáticas (las mismas claves que impcont).
 */
final class TransferenciaCrownAsientoBuilder
{
    /** @var array<string, array<string, mixed>> empresa|clave => línea base de cuenta */
    private array $cuentaPorClave = [];

    public function __construct(
        private readonly TransferenciaCrownCuentas $cuentas,
    ) {
    }

    /**
     * @param  list<object>  $subdiario
     * @param  array<string, mixed>  $fila
     * @return array{origen: string, nro_operacion: int, lineas: list<array<string, mixed>>}
     */
    public function desdeSubdiario(int $empresaId, array $subdiario, array $fila): array
    {
        $descripcion = TransferenciaCrownComprobante::codigoVisible($fila);
        $porCuenta = [];
        $nroOperacion = 0;

        foreach ($subdiario as $linea) {
            $nro = (int) ($linea->subd_nro_operacion ?? 0);
            if ($nro > 0 && $nroOperacion === 0) {
                $nroOperacion = $nro;
            }
            foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($linea) as $imp) {
                $codigo = (int) ($imp['cuenta'] ?? 0);
                if ($codigo <= 0) {
                    continue;
                }
                $cuenta = $this->cuentas->resolverErpPorCodigo($empresaId, $codigo, 'subdiario');
                $dh = (string) $imp['dh'];
                $key = $cuenta['cuentacontable_id'].'|'.$dh;
                if (! isset($porCuenta[$key])) {
                    $porCuenta[$key] = [
                        'cuentacontable_id' => $cuenta['cuentacontable_id'],
                        'codigo' => $cuenta['codigo'],
                        'nombre' => $cuenta['nombre'],
                        'dh' => $dh,
                        'importe' => 0.0,
                        'descripcion' => $descripcion,
                    ];
                }
                $porCuenta[$key]['importe'] = round($porCuenta[$key]['importe'] + (float) $imp['importe'], 2);
            }
        }

        return [
            'origen' => 'subdiario',
            'nro_operacion' => $nroOperacion,
            'lineas' => array_values($porCuenta),
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{origen: string, nro_operacion: int, lineas: list<array<string, mixed>>}
     */
    public function generar(int $empresaId, array $fila, string $perfil): array
    {
        $sucursal = (int) ($fila['sucursal'] ?? 0);
        $credito = TransferenciaCrownComprobante::esCredito($fila);
        $dhIngreso = $credito ? 'D' : 'H';
        $dhPago = $credito ? 'H' : 'D';
        $descripcion = TransferenciaCrownComprobante::codigoVisible($fila);
        $proceso = $perfil === 'pxsol' ? 'arca' : 'gastro';
        $claveVenta = TransferenciaCrownCuentaClaves::clavePv($proceso, 'venta', $sucursal);
        $claveEfectivo = TransferenciaCrownCuentaClaves::clavePv($proceso, 'efectivo', $sucursal);
        $esCc = ! empty($fila['es_cuenta_corriente']);

        $lineas = [];
        $total = round((float) ($fila['total'] ?? 0), 2);
        if ($total <= 0.01) {
            $lineas[] = $this->linea($empresaId, $claveVenta, $dhIngreso, 0.01, $descripcion);
            $lineas[] = $this->lineaPago($empresaId, $fila, $esCc, $claveEfectivo, $dhPago, 0.01, $descripcion);

            return ['origen' => 'generado', 'nro_operacion' => 0, 'lineas' => $lineas];
        }

        $neto = TransferenciaCrownComprobante::neto($fila);
        $iva = TransferenciaCrownComprobante::iva($fila);
        $noi = round((float) ($fila['noi'] ?? 0), 2);
        $percIva = round((float) ($fila['percepcion_iva'] ?? 0), 2);
        $otros = round((float) ($fila['otros'] ?? 0), 2);

        if ($neto > 0) {
            $lineas[] = $this->linea($empresaId, $claveVenta, $dhIngreso, $neto, $descripcion);
        }
        if ($iva > 0) {
            $lineas[] = $this->linea(
                $empresaId,
                $credito ? CuentaAutomaticaClaves::VENTAS_IVA_CREDITO_FISCAL : CuentaAutomaticaClaves::VENTAS_IVA_DEBITO_FISCAL,
                $dhIngreso,
                $iva,
                $descripcion,
            );
        }
        if ($noi > 0) {
            $lineas[] = $this->linea(
                $empresaId,
                $credito ? TransferenciaCrownCuentaClaves::IVA_NOI_CREDITO : TransferenciaCrownCuentaClaves::IVA_NOI_DEBITO,
                $dhIngreso,
                $noi,
                $descripcion,
            );
        }
        if ($percIva > 0) {
            $lineas[] = $this->linea(
                $empresaId,
                $credito ? TransferenciaCrownCuentaClaves::PERCEPCION_IVA_CREDITO : TransferenciaCrownCuentaClaves::PERCEPCION_IVA_DEBITO,
                $dhIngreso,
                $percIva,
                $descripcion,
            );
        }
        if ($otros > 0 && $perfil === 'pxsol') {
            $lineas[] = $this->linea($empresaId, TransferenciaCrownCuentaClaves::PERCEPCION_IIBB, $dhIngreso, $otros, $descripcion);
        }

        $pago = round($neto + $iva + $noi + $percIva + ($perfil === 'pxsol' ? $otros : 0), 2);
        if ($pago <= 0) {
            $pago = $total;
        }
        $lineas[] = $this->lineaPago($empresaId, $fila, $esCc, $claveEfectivo, $dhPago, $pago, $descripcion);

        return ['origen' => 'generado', 'nro_operacion' => 0, 'lineas' => $lineas];
    }

    /**
     * CC: usa cliente.cuentacontable_id (en Crown casi todos tienen 130110000).
     * Si falta, cae a la cuenta automática puente. Contado: efectivo del PV.
     *
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function lineaPago(
        int $empresaId,
        array $fila,
        bool $esCc,
        string $claveEfectivo,
        string $dh,
        float $importe,
        string $descripcion,
    ): array {
        if (! $esCc) {
            return $this->linea($empresaId, $claveEfectivo, $dh, $importe, $descripcion);
        }

        $cuentaClienteId = $this->cuentaDeudoresClienteId($empresaId, $fila);
        if ($cuentaClienteId > 0) {
            return $this->lineaPorId($empresaId, $cuentaClienteId, $dh, $importe, $descripcion);
        }

        return $this->linea($empresaId, TransferenciaCrownCuentaClaves::PUENTE, $dh, $importe, $descripcion);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function cuentaDeudoresClienteId(int $empresaId, array $fila): int
    {
        $clienteId = (int) ($fila['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            return 0;
        }
        $cliente = Cliente::query()->find($clienteId, ['id', 'cuentacontable_id']);
        $cuentaId = (int) ($cliente->cuentacontable_id ?? 0);
        if ($cuentaId <= 0) {
            return 0;
        }
        $cuenta = Cuentacontable::query()
            ->whereKey($cuentaId)
            ->where('empresa_id', $empresaId)
            ->first(['id']);

        return $cuenta !== null ? (int) $cuenta->id : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function lineaPorId(int $empresaId, int $cuentaId, string $dh, float $importe, string $descripcion): array
    {
        $cacheKey = $empresaId.'|id:'.$cuentaId;
        if (! isset($this->cuentaPorClave[$cacheKey])) {
            $cuenta = Cuentacontable::query()
                ->whereKey($cuentaId)
                ->where('empresa_id', $empresaId)
                ->first(['id', 'codigo', 'nombre']);
            if ($cuenta === null) {
                throw new RuntimeException('No está la cuenta contable id '.$cuentaId.' para la empresa '.$empresaId.'.');
            }
            $this->cuentaPorClave[$cacheKey] = [
                'cuentacontable_id' => (int) $cuenta->id,
                'codigo' => (string) $cuenta->codigo,
                'nombre' => (string) $cuenta->nombre,
            ];
        }

        return array_merge($this->cuentaPorClave[$cacheKey], [
            'dh' => $dh,
            'importe' => round($importe, 2),
            'descripcion' => $descripcion,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function linea(int $empresaId, string $clave, string $dh, float $importe, string $descripcion): array
    {
        $cacheKey = $empresaId.'|'.$clave;
        if (! isset($this->cuentaPorClave[$cacheKey])) {
            $id = (int) (CuentaAutomaticaResolver::resolverId($empresaId, $clave) ?? 0);
            $cuenta = $id > 0
                ? Cuentacontable::query()->whereKey($id)->where('empresa_id', $empresaId)->first(['id', 'codigo', 'nombre'])
                : null;
            if ($cuenta === null) {
                throw new RuntimeException(TransferenciaCrownCuentaClaves::mensajeFalta($clave));
            }
            $this->cuentaPorClave[$cacheKey] = [
                'cuentacontable_id' => (int) $cuenta->id,
                'codigo' => (string) $cuenta->codigo,
                'nombre' => (string) $cuenta->nombre,
            ];
        }

        return array_merge($this->cuentaPorClave[$cacheKey], [
            'dh' => $dh,
            'importe' => round($importe, 2),
            'descripcion' => $descripcion,
        ]);
    }
}
