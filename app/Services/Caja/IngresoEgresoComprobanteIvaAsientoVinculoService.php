<?php

namespace App\Services\Caja;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\Cuentacontable;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;

/**
 * Vincula líneas DEBE del asiento del IE con conceptos IVA del comprobante (mayor por conceptos / EFE).
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
     * La cuenta del concepto es la de la empresa; el asiento a veces quedó
     * con el id del maestro (mismo código, otra empresa). Empata por id y,
     * si no, por código + importe.
     *
     * @param  list<array{cuenta_id: int, codigo: string, monto: float}>  $pendientes
     */
    public static function indicePendiente(array $pendientes, int $cuentaId, string $codigo, float $monto): ?int
    {
        $monto = round($monto, 2);
        $codigo = trim($codigo);
        $porCodigo = null;

        foreach ($pendientes as $i => $pendiente) {
            if (round((float) ($pendiente['monto'] ?? 0), 2) !== $monto) {
                continue;
            }
            if ($cuentaId > 0 && (int) ($pendiente['cuenta_id'] ?? 0) === $cuentaId) {
                return $i;
            }
            if ($porCodigo === null && $codigo !== '' && $codigo === trim((string) ($pendiente['codigo'] ?? ''))) {
                $porCodigo = $i;
            }
        }

        return $porCodigo;
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
