<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

/**
 * Criterio único de conciliación: neto = facturas − NC (ERP, rendg Z−NC, asientos ya netos).
 */
final class GastronomiaConciliacionNetoSupport
{
    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    public static function enriquecerFila(array $fila, float $ncErp, float $ncRendg): array
    {
        $erpBruto = round((float) ($fila['ventas_erp'] ?? 0), 2);
        $ncErp = round(max(0.0, $ncErp), 2);
        $ncRendg = round(max(0.0, $ncRendg), 2);

        $erpNeto = round($erpBruto - $ncErp, 2);
        $fila['ventas_erp_bruto'] = $erpBruto;
        $fila['notas_credito_erp'] = $ncErp > 0.0001 ? $ncErp : null;
        $fila['ventas_erp_neto'] = $erpNeto;
        $fila['ventas_erp'] = $erpNeto;

        $anitaBruto = round((float) ($fila['ventas_anita'] ?? 0), 2);
        if ($anitaBruto > 0.0001 || $ncErp > 0.0001) {
            $fila['ventas_anita_bruto'] = $anitaBruto;
            $anitaNeto = round($anitaBruto - $ncErp, 2);
            $fila['ventas_anita'] = $anitaNeto;
            $fila['diff_erp_anita'] = round($erpNeto - $anitaNeto, 2);
        }

        $rendgBruto = ($fila['rendgastro_z'] ?? null) !== null
            ? round((float) $fila['rendgastro_z'], 2)
            : null;

        if ($rendgBruto !== null) {
            $rendgNeto = round($rendgBruto - $ncRendg, 2);
            $fila['rendgastro_z_bruto'] = $rendgBruto;
            $fila['notas_credito_rendg'] = $ncRendg > 0.0001 ? $ncRendg : null;
            $fila['rendgastro_neto'] = $rendgNeto;
            $fila['rendgastro_z'] = $rendgNeto;
            $fila['diff_erp_rendg'] = round($erpNeto - $rendgNeto, 2);
        } else {
            $fila['diff_erp_rendg'] = null;
        }

        return $fila;
    }
}
