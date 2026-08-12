<?php

namespace App\Support\Caja;

use App\Models\Compras\Concepto_Ivacompra;
use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;

/**
 * Arma líneas DEBE de asiento desde conceptos IVA compra (ingreso/egreso).
 */
final class IngresoEgresoComprobanteIvaAsientoSupport
{
    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @return list<array{cuentacontable_id: int, importe: float, observacion: string, concepto_ivacompra_id: int|null}>
     */
    public static function lineasDebeDesdeConceptos(array $conceptos, int $centrocostoId = 1, ?int $empresaId = null): array
    {
        $lineas = [];

        foreach ($conceptos as $concepto) {
            $conceptoId = (int) ($concepto['concepto_ivacompra_id'] ?? 0);
            $monto = round(abs((float) ($concepto['monto'] ?? 0)), 2);
            if ($conceptoId <= 0 || $monto <= 0) {
                continue;
            }

            $modelo = Concepto_Ivacompra::query()->with('concepto_ivacompra_empresas')->find($conceptoId);
            if (! $modelo) {
                throw new \RuntimeException('Concepto IVA compra id «'.$conceptoId.'» inexistente.');
            }

            $tipoConcepto = (string) ($modelo->tipoconcepto ?? '');
            if (! ComprobanteProveedorConceptoIvaTipos::esNeto($tipoConcepto)
                && ! ComprobanteProveedorConceptoIvaTipos::esImpuesto($tipoConcepto)) {
                throw new \RuntimeException(
                    'Concepto IVA «'.$modelo->nombre.'» con tipo «'.$tipoConcepto.'» no admite tesorería.'
                );
            }

            $empresaLinea = (int) ($concepto['empresa_id'] ?? $empresaId ?? 0);
            $cuentaId = (int) ($concepto['cuentacontabledebe_id'] ?? 0);
            if ($cuentaId <= 0) {
                $cuentaId = $modelo->cuentacontableDebeIdParaEmpresa($empresaLinea > 0 ? $empresaLinea : null);
            }
            if ($cuentaId <= 0) {
                throw new \RuntimeException(
                    'Falta cuenta contable DEBE en concepto IVA «'.$modelo->nombre.'».'
                );
            }

            $lineas[] = [
                'cuentacontable_id' => $cuentaId,
                'importe' => $monto,
                'centrocosto_id' => $centrocostoId,
                'observacion' => (string) $modelo->nombre,
                'concepto_ivacompra_id' => $conceptoId,
            ];
        }

        return $lineas;
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
            $empresaComp = (int) ($comprobante['empresa_id'] ?? $empresaId ?? 0) ?: null;

            foreach (self::lineasDebeDesdeConceptos($conceptos, $centrocostoId, $empresaComp) as $linea) {
                $lineas[] = $linea;
            }
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @return list<array{tipo: string, mensaje: string, concepto_ivacompra_id?: int, nombre?: string}>
     */
    public static function avisosCuentasFaltantes(array $conceptos, ?int $empresaId = null): array
    {
        $avisos = [];

        foreach ($conceptos as $concepto) {
            $conceptoId = (int) ($concepto['concepto_ivacompra_id'] ?? 0);
            $monto = round(abs((float) ($concepto['monto'] ?? 0)), 2);
            if ($conceptoId <= 0 || $monto <= 0) {
                continue;
            }

            $modelo = Concepto_Ivacompra::query()->with('concepto_ivacompra_empresas')->find($conceptoId);
            if (! $modelo) {
                continue;
            }

            $empresaLinea = (int) ($concepto['empresa_id'] ?? $empresaId ?? 0);
            $cuentaId = (int) ($concepto['cuentacontabledebe_id'] ?? 0);
            if ($cuentaId <= 0) {
                $cuentaId = $modelo->cuentacontableDebeIdParaEmpresa($empresaLinea > 0 ? $empresaLinea : null);
            }
            if ($cuentaId <= 0) {
                $avisos[] = [
                    'tipo' => 'concepto_sin_cuenta_debe',
                    'concepto_ivacompra_id' => $conceptoId,
                    'nombre' => (string) $modelo->nombre,
                    'mensaje' => 'Falta cuenta contable DEBE en concepto IVA «'.$modelo->nombre.'».',
                ];
            }
        }

        return $avisos;
    }
}