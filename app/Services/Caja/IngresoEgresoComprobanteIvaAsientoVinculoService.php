<?php

namespace App\Services\Caja;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\Cuentacontable;
use App\Support\Compras\ComprobanteProveedorAsientoCuadreSupport;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;

/**
 * Escribe las FK del DEBE del asiento IE → concepto del comprobante.
 * El control factura↔asiento suma por `comprobante_proveedor_id`; este servicio
 * es quien las graba (el formulario del asiento no las lleva).
 */
class IngresoEgresoComprobanteIvaAsientoVinculoService
{
    public function vincularPorCajaMovimiento(int $cajaMovimientoId): void
    {
        $asiento = Asiento::query()
            ->where('caja_movimiento_id', $cajaMovimientoId)
            ->orderByDesc('id')
            ->first();

        if (! $asiento) {
            return;
        }

        $comprobantes = Comprobante_Proveedor::query()
            ->where('caja_movimiento_id', $cajaMovimientoId)
            ->where('origen_entrada', ComprobanteProveedorOrigenEntrada::INGRESO_EGRESO)
            ->with(['comprobante_proveedor_conceptos.concepto_ivacompras.concepto_ivacompra_empresas'])
            ->get();

        if ($comprobantes->isEmpty()) {
            return;
        }

        $pendientes = $this->armarPendientes($comprobantes);
        $this->completarCodigos($pendientes);

        $movimientos = Asiento_Movimiento::query()
            ->where('asiento_id', $asiento->id)
            ->where('monto', '>', 0)
            ->orderBy('id')
            ->get();

        $codigosMov = Cuentacontable::query()
            ->whereIn('id', $movimientos->pluck('cuentacontable_id')->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values())
            ->pluck('codigo', 'id');

        foreach ($movimientos as $mov) {
            // Ya tiene FK: no re-matchear por importe; solo sacar el pendiente consumido.
            $conceptoFk = (int) ($mov->comprobante_proveedor_concepto_id ?? 0);
            if ($conceptoFk > 0) {
                $idxConsumido = self::indicePendientePorConceptoId($pendientes, $conceptoFk);
                if ($idxConsumido !== null) {
                    array_splice($pendientes, $idxConsumido, 1);
                }

                continue;
            }

            $cuentaId = (int) $mov->cuentacontable_id;
            $codigo = trim((string) ($codigosMov[$cuentaId] ?? ''));
            $idx = self::indicePendiente($pendientes, $cuentaId, $codigo, (float) $mov->monto);
            if ($idx === null) {
                continue;
            }

            $origen = $pendientes[$idx];
            array_splice($pendientes, $idx, 1);

            $mov->forceFill([
                'comprobante_proveedor_id' => $origen['comprobante_proveedor_id'],
                'comprobante_proveedor_concepto_id' => $origen['comprobante_proveedor_concepto_id'],
                'concepto_ivacompra_id' => $origen['concepto_ivacompra_id'],
            ])->save();
        }
    }

    /**
     * Empata cuenta (id empresa o código homologado) + importe.
     * Tolera centavos de cuadre del asiento (concepto 31.233,96 vs línea 31.233,97).
     *
     * @param  list<array{cuenta_id: int, codigo: string, monto: float}>  $pendientes
     */
    public static function indicePendiente(
        array $pendientes,
        int $cuentaId,
        string $codigo,
        float $monto,
        float $tolerancia = ComprobanteProveedorAsientoCuadreSupport::TOLERANCIA,
    ): ?int {
        $monto = round($monto, 2);
        $codigo = trim($codigo);
        $tolerancia = max(0.0, round($tolerancia, 2));

        $exactoCodigo = null;
        $mejorCuenta = null;
        $mejorCuentaDiff = null;
        $mejorCodigo = null;
        $mejorCodigoDiff = null;

        foreach ($pendientes as $i => $pendiente) {
            $pendienteMonto = round((float) ($pendiente['monto'] ?? 0), 2);
            $diff = abs($pendienteMonto - $monto);
            if ($diff > $tolerancia + 0.00001) {
                continue;
            }

            $mismaCuenta = $cuentaId > 0 && (int) ($pendiente['cuenta_id'] ?? 0) === $cuentaId;
            $mismoCodigo = $codigo !== '' && $codigo === trim((string) ($pendiente['codigo'] ?? ''));

            if ($mismaCuenta) {
                if ($diff < 0.00001) {
                    return $i;
                }
                if ($mejorCuentaDiff === null || $diff < $mejorCuentaDiff) {
                    $mejorCuenta = $i;
                    $mejorCuentaDiff = $diff;
                }
            } elseif ($mismoCodigo) {
                if ($diff < 0.00001 && $exactoCodigo === null) {
                    $exactoCodigo = $i;
                }
                if ($mejorCodigoDiff === null || $diff < $mejorCodigoDiff) {
                    $mejorCodigo = $i;
                    $mejorCodigoDiff = $diff;
                }
            }
        }

        return $mejorCuenta ?? $exactoCodigo ?? $mejorCodigo;
    }

    /**
     * @param  list<array{comprobante_proveedor_concepto_id?: int}>  $pendientes
     */
    public static function indicePendientePorConceptoId(array $pendientes, int $conceptoId): ?int
    {
        if ($conceptoId <= 0) {
            return null;
        }

        foreach ($pendientes as $i => $pendiente) {
            if ((int) ($pendiente['comprobante_proveedor_concepto_id'] ?? 0) === $conceptoId) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Comprobante_Proveedor>  $comprobantes
     * @return list<array{cuenta_id: int, codigo: string, monto: float, comprobante_proveedor_id: int, comprobante_proveedor_concepto_id: int, concepto_ivacompra_id: int}>
     */
    private function armarPendientes($comprobantes): array
    {
        $pendientes = [];

        foreach ($comprobantes as $comprobante) {
            foreach ($comprobante->comprobante_proveedor_conceptos as $linea) {
                $monto = round(abs((float) $linea->monto), 2);
                if ($monto <= 0) {
                    continue;
                }

                $empresaId = (int) ($comprobante->empresa_id ?? 0);
                $cuentaId = (int) ($linea->cuentacontabledebe_id
                    ?? $linea->concepto_ivacompras?->cuentacontableDebeIdParaEmpresa($empresaId)
                    ?? 0);
                if ($cuentaId <= 0) {
                    continue;
                }

                $pendientes[] = [
                    'cuenta_id' => $cuentaId,
                    'codigo' => '',
                    'monto' => $monto,
                    'comprobante_proveedor_id' => (int) $comprobante->id,
                    'comprobante_proveedor_concepto_id' => (int) $linea->id,
                    'concepto_ivacompra_id' => (int) $linea->concepto_ivacompra_id,
                ];
            }
        }

        return $pendientes;
    }

    /**
     * @param  list<array{cuenta_id: int, codigo: string, monto: float}>  $pendientes
     */
    private function completarCodigos(array &$pendientes): void
    {
        $ids = [];
        foreach ($pendientes as $pendiente) {
            $id = (int) ($pendiente['cuenta_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return;
        }

        $codigos = Cuentacontable::query()->whereIn('id', array_values($ids))->pluck('codigo', 'id');
        foreach ($pendientes as $i => $pendiente) {
            $pendientes[$i]['codigo'] = trim((string) ($codigos[(int) $pendiente['cuenta_id']] ?? ''));
        }
    }
}
