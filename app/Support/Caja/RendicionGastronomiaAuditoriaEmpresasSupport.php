<?php

namespace App\Support\Caja;

/**
 * Resuelve empresas a auditar en jobs/commands nocturnos.
 */
final class RendicionGastronomiaAuditoriaEmpresasSupport
{
    /**
     * @return list<int>
     */
    public static function empresasParaAuditoria(?int $overrideEmpresaId = null): array
    {
        if ($overrideEmpresaId !== null && $overrideEmpresaId > 0) {
            return [$overrideEmpresaId];
        }

        $ids = config('rendicion_gastronomia_anita.auditoria_diaria.empresas_ids', []);
        if (is_array($ids) && $ids !== []) {
            return array_values(array_filter(array_map('intval', $ids)));
        }

        $legacy = (int) config('rendicion_gastronomia_anita.auditoria_diaria.empresa_id', 1);

        return $legacy > 0 ? [$legacy] : [];
    }

    /**
     * @return list<int>
     */
    public static function empresasVentasAnitaDiaria(?int $overrideEmpresaId = null): array
    {
        if ($overrideEmpresaId !== null && $overrideEmpresaId > 0) {
            return [$overrideEmpresaId];
        }

        $ids = config('gastronomia.auditoria_anita_diaria.empresas_ids', []);
        if (is_array($ids) && $ids !== []) {
            return array_values(array_filter(array_map('intval', $ids)));
        }

        $legacy = (int) config('gastronomia.auditoria_anita_diaria.empresa_id', 1);

        return $legacy > 0 ? [$legacy] : [];
    }
}
