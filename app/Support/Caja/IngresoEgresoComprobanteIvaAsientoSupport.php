<?php

namespace App\Support\Caja;

use App\Models\Compras\Concepto_Ivacompra;
use App\Support\Compras\ComprobanteProveedorAsientoCuadreSupport;
use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use App\Support\Compras\ComprobanteProveedorDebeGastoSupport;
use App\Support\Contable\CuentaCentrocostoAsignadosSupport;

/**
 * Arma líneas DEBE de asiento desde conceptos IVA compra (ingreso/egreso).
 *
 * Impuestos y percepciones van 1:1 a la cuenta del concepto.
 * El neto (sin COM) es gasto abierto: una línea por concepto, o el reparto
 * `debitos_gasto` si el usuario agregó más débitos en la vista previa.
 * El haber sigue siendo las cuentas de caja del movimiento.
 */
final class IngresoEgresoComprobanteIvaAsientoSupport
{
    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @param  list<array<string, mixed>>  $debitosGasto
     * @return list<array{cuentacontable_id: int, importe: float, observacion: string, concepto_ivacompra_id: int|null, centrocosto_id: int}>
     */
    public static function lineasDebeDesdeConceptos(
        array $conceptos,
        int $centrocostoId = 1,
        ?int $empresaId = null,
        array $debitosGasto = [],
    ): array {
        $armado = self::armar($conceptos, $debitosGasto, $centrocostoId, $empresaId, true);

        return array_map(static function (array $linea): array {
            return [
                'cuentacontable_id' => (int) $linea['cuentacontable_id'],
                'importe' => (float) $linea['importe'],
                'centrocosto_id' => (int) $linea['centrocosto_id'],
                'observacion' => (string) $linea['observacion'],
                'concepto_ivacompra_id' => $linea['concepto_ivacompra_id'],
            ];
        }, $armado['lineas']);
    }

    /**
     * @param  list<array<string, mixed>>  $comprobantes
     * @return list<array{cuentacontable_id: int, importe: float, observacion: string, concepto_ivacompra_id: int|null}>
     */
    public static function lineasDebeDesdeComprobantes(array $comprobantes, int $centrocostoId = 1, ?int $empresaId = null): array
    {
        $lineas = [];

        foreach ($comprobantes as $comprobante) {
            $conceptos = $comprobante['conceptos'] ?? [];
            if (! is_array($conceptos)) {
                continue;
            }
            $debitos = $comprobante['debitos_gasto'] ?? [];
            if (! is_array($debitos)) {
                $debitos = [];
            }
            $empresaComp = (int) ($comprobante['empresa_id'] ?? $empresaId ?? 0) ?: null;

            foreach (self::lineasDebeDesdeConceptos($conceptos, $centrocostoId, $empresaComp, $debitos) as $linea) {
                $lineas[] = $linea;
            }
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @param  list<array<string, mixed>>  $debitosGasto
     * @return array{
     *     lineas: list<array<string, mixed>>,
     *     avisos: list<array{tipo: string, mensaje: string, concepto_ivacompra_id?: int, nombre?: string}>,
     *     neto_imputable: float,
     *     permite_reparto_gasto: bool,
     *     error: string|null
     * }
     */
    public static function armar(
        array $conceptos,
        array $debitosGasto,
        int $centrocostoId,
        ?int $empresaId,
        bool $exigirCuentas,
    ): array {
        $modelos = self::modelosPorId($conceptos);
        $impuestos = [];
        $netos = [];
        $neto = 0.0;
        $avisos = [];

        foreach ($conceptos as $concepto) {
            $conceptoId = (int) ($concepto['concepto_ivacompra_id'] ?? 0);
            $montoRaw = round((float) ($concepto['monto'] ?? 0), 2);
            if ($conceptoId <= 0 || abs($montoRaw) < 0.0001) {
                continue;
            }

            $modelo = $modelos->get($conceptoId);
            if (! $modelo instanceof Concepto_Ivacompra) {
                throw new \RuntimeException('Concepto IVA compra id «'.$conceptoId.'» inexistente.');
            }

            $tipo = (string) ($modelo->tipoconcepto ?? '');
            $codigo = (string) ($modelo->codigo ?? '');
            $permiteNegativo = ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo($tipo, $codigo);
            $monto = $permiteNegativo ? $montoRaw : round(abs($montoRaw), 2);
            $empresaLinea = (int) ($concepto['empresa_id'] ?? $empresaId ?? 0);
            $esImpuestoInterno = ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno($tipo, $codigo);
            $cuentaId = self::cuentaDebe($concepto, $modelo, $empresaLinea);

            if (ComprobanteProveedorConceptoIvaTipos::esImpuesto($tipo) && ! $esImpuestoInterno) {
                if ($monto < 0) {
                    continue;
                }
                if ($cuentaId <= 0) {
                    $avisos[] = self::avisoSinCuenta($conceptoId, (string) $modelo->nombre, true);
                    if ($exigirCuentas) {
                        throw new \RuntimeException(
                            'Falta cuenta contable DEBE en concepto IVA «'.$modelo->nombre.'». '
                            .'Configúrela en el maestro Conceptos IVA compra.'
                        );
                    }
                }
                $impuestos[] = self::linea(
                    $cuentaId,
                    $monto,
                    $centrocostoId,
                    (string) $modelo->nombre,
                    $conceptoId,
                    'impuesto',
                    false,
                    false,
                );

                continue;
            }

            $esGasto = ComprobanteProveedorConceptoIvaTipos::esNetoDeGasto($tipo, $codigo)
                || ComprobanteProveedorConceptoIvaTipos::esExento($tipo, $codigo)
                || $tipo === '';
            if (! $esGasto) {
                if ($exigirCuentas) {
                    throw new \RuntimeException(
                        'Concepto IVA «'.$modelo->nombre.'» con tipo «'.$tipo.'» no admite tesorería.'
                    );
                }
                $esGasto = true;
            }

            if ($monto < 0) {
                $neto = round($neto + $monto, 2);

                continue;
            }

            $neto = round($neto + $monto, 2);
            $netos[] = [
                'cuenta_id' => $cuentaId,
                'monto' => $monto,
                'nombre' => (string) $modelo->nombre,
                'concepto_id' => $conceptoId,
                'centrocosto_id' => (int) ($concepto['centrocosto_id'] ?? 0),
                'es_impuesto_interno' => $esImpuestoInterno,
            ];
        }

        $cuentaGasto = 0;
        foreach ($netos as $netoLinea) {
            if (! empty($netoLinea['es_impuesto_interno'])) {
                continue;
            }
            if ((int) $netoLinea['cuenta_id'] > 0) {
                $cuentaGasto = (int) $netoLinea['cuenta_id'];
                break;
            }
        }
        if ($cuentaGasto > 0) {
            foreach ($netos as &$netoLinea) {
                if (! empty($netoLinea['es_impuesto_interno']) && (int) $netoLinea['cuenta_id'] <= 0) {
                    $netoLinea['cuenta_id'] = $cuentaGasto;
                }
            }
            unset($netoLinea);
        }

        $debitos = self::normalizarDebitos($debitosGasto);
        $hayReparto = $debitos !== [];
        $lineas = $impuestos;
        $error = null;

        if ($hayReparto) {
            $suma = ComprobanteProveedorDebeGastoSupport::sumaImportes($debitos);
            $diff = round($suma - $neto, 2);
            if (abs($diff) > ComprobanteProveedorAsientoCuadreSupport::TOLERANCIA) {
                $error = 'El reparto de cuentas de gasto ('.number_format($suma, 2, ',', '.')
                    .') no coincide con el neto ('.number_format($neto, 2, ',', '.')
                    .'). Diferencia: '.number_format($diff, 2, ',', '.').'.';
                if ($exigirCuentas) {
                    throw new \RuntimeException($error);
                }
            }
            foreach ($debitos as $i => $debito) {
                $cuentaId = (int) $debito['cuentacontable_id'];
                if ($cuentaId <= 0) {
                    $msg = 'Falta la cuenta contable en el débito de gasto #'.($i + 1).'.';
                    $avisos[] = [
                        'tipo' => 'gasto_sin_cuenta',
                        'mensaje' => $msg,
                    ];
                    if ($exigirCuentas) {
                        throw new \RuntimeException($msg);
                    }
                    $error = $error ?? $msg;
                }
                $lineas[] = self::linea(
                    $cuentaId,
                    (float) $debito['importe'],
                    CuentaCentrocostoAsignadosSupport::resolver(
                        $cuentaId,
                        (int) ($debito['centrocosto_id'] ?? 0),
                        $centrocostoId
                    ),
                    'Gasto',
                    null,
                    'debe_gasto',
                    true,
                    true,
                );
            }
        } else {
            foreach ($netos as $netoLinea) {
                $cuentaId = (int) $netoLinea['cuenta_id'];
                if ($cuentaId <= 0) {
                    $avisos[] = self::avisoSinCuenta((int) $netoLinea['concepto_id'], (string) $netoLinea['nombre'], false);
                    if ($exigirCuentas) {
                        $msgCuenta = ! empty($netoLinea['es_impuesto_interno'])
                            ? 'El impuesto interno «'.$netoLinea['nombre'].'» es neto y va a la cuenta de gasto. '
                                .'Indíquela en la vista previa del asiento.'
                            : 'Falta cuenta de gasto para «'.$netoLinea['nombre'].'». '
                                .'Indíquela en la vista previa del asiento.';
                        throw new \RuntimeException($msgCuenta);
                    }
                }
                $lineas[] = self::linea(
                    $cuentaId,
                    (float) $netoLinea['monto'],
                    CuentaCentrocostoAsignadosSupport::resolver(
                        $cuentaId,
                        (int) ($netoLinea['centrocosto_id'] ?? 0),
                        $centrocostoId
                    ),
                    (string) $netoLinea['nombre'],
                    (int) $netoLinea['concepto_id'],
                    'neto_manual',
                    true,
                    false,
                );
            }
        }

        return [
            'lineas' => $lineas,
            'avisos' => $avisos,
            'neto_imputable' => round($neto, 2),
            'permite_reparto_gasto' => $neto > 0.0001,
            'error' => $error,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @return list<array{tipo: string, mensaje: string, concepto_ivacompra_id?: int, nombre?: string}>
     */
    public static function avisosCuentasFaltantes(array $conceptos, ?int $empresaId = null): array
    {
        return self::armar($conceptos, [], 1, $empresaId, false)['avisos'];
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @return \Illuminate\Support\Collection<int, Concepto_Ivacompra>
     */
    private static function modelosPorId(array $conceptos)
    {
        $ids = [];
        foreach ($conceptos as $concepto) {
            $id = (int) ($concepto['concepto_ivacompra_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return collect();
        }

        return Concepto_Ivacompra::query()
            ->with('concepto_ivacompra_empresas')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $concepto
     */
    private static function cuentaDebe(array $concepto, Concepto_Ivacompra $modelo, int $empresaId): int
    {
        $cuentaId = (int) ($concepto['cuentacontabledebe_id'] ?? 0);
        if ($cuentaId > 0) {
            return $cuentaId;
        }

        return $modelo->cuentacontableDebeIdParaEmpresa($empresaId > 0 ? $empresaId : null);
    }

    /**
     * @param  list<array<string, mixed>>  $debitos
     * @return list<array{cuentacontable_id: int, importe: float}>
     */
    private static function normalizarDebitos(array $debitos): array
    {
        $out = [];
        foreach ($debitos as $debito) {
            if (! is_array($debito)) {
                continue;
            }
            $importe = round(abs((float) ($debito['importe'] ?? 0)), 2);
            if ($importe < 0.0001) {
                continue;
            }
            $out[] = [
                'cuentacontable_id' => (int) ($debito['cuentacontable_id'] ?? 0),
                'importe' => $importe,
                'centrocosto_id' => (int) ($debito['centrocosto_id'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{tipo: string, mensaje: string, concepto_ivacompra_id: int, nombre: string}
     */
    private static function avisoSinCuenta(int $conceptoId, string $nombre, bool $esImpuesto): array
    {
        $mensaje = $esImpuesto
            ? 'Falta cuenta contable DEBE en concepto IVA «'.$nombre.'». Configúrela en Conceptos IVA compra.'
            : '«'.$nombre.'» no tiene cuenta: indíquela en la vista previa del asiento (gasto abierto).';

        return [
            'tipo' => $esImpuesto ? 'concepto_sin_cuenta_debe' : 'gasto_sin_cuenta',
            'concepto_ivacompra_id' => $conceptoId,
            'nombre' => $nombre,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function linea(
        int $cuentaId,
        float $importe,
        int $centrocostoId,
        string $observacion,
        ?int $conceptoId,
        string $origen,
        bool $editableCuenta,
        bool $editableImporte,
    ): array {
        return [
            'cuentacontable_id' => $cuentaId,
            'importe' => round($importe, 2),
            'centrocosto_id' => $centrocostoId,
            'observacion' => $observacion,
            'concepto_ivacompra_id' => $conceptoId,
            'origen' => $origen,
            'editable_cuenta' => $editableCuenta,
            'editable_importe' => $editableImporte,
        ];
    }
}
