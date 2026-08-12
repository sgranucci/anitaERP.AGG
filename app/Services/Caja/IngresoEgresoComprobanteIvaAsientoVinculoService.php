<?php

namespace App\Services\Caja;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
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
            ->whereNull('deleted_at')
            ->with(['comprobante_proveedor_conceptos.concepto_ivacompras.concepto_ivacompra_empresas'])
            ->get();

        if ($comprobantes->isEmpty()) {
            return;
        }

        $pendientes = $this->armarPendientes($comprobantes);

        Asiento_Movimiento::query()
            ->where('asiento_id', $asiento->id)
            ->where('monto', '>', 0)
            ->orderBy('id')
            ->each(function (Asiento_Movimiento $mov) use (&$pendientes): void {
                $monto = round((float) $mov->monto, 2);
                $cuentaId = (int) $mov->cuentacontable_id;
                $clave = $cuentaId.'|'.number_format($monto, 2, '.', '');

                if (! isset($pendientes[$clave]) || $pendientes[$clave] === []) {
                    return;
                }

                $origen = array_shift($pendientes[$clave]);

                $mov->forceFill([
                    'comprobante_proveedor_id' => $origen['comprobante_proveedor_id'],
                    'comprobante_proveedor_concepto_id' => $origen['comprobante_proveedor_concepto_id'],
                    'concepto_ivacompra_id' => $origen['concepto_ivacompra_id'],
                ])->save();
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Comprobante_Proveedor>  $comprobantes
     * @return array<string, list<array{comprobante_proveedor_id: int, comprobante_proveedor_concepto_id: int, concepto_ivacompra_id: int}>>
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

                $clave = $cuentaId.'|'.number_format($monto, 2, '.', '');
                $pendientes[$clave][] = [
                    'comprobante_proveedor_id' => (int) $comprobante->id,
                    'comprobante_proveedor_concepto_id' => (int) $linea->id,
                    'concepto_ivacompra_id' => (int) $linea->concepto_ivacompra_id,
                ];
            }
        }

        return $pendientes;
    }
}
