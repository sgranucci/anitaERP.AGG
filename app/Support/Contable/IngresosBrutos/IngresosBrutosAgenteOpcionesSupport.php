<?php

declare(strict_types=1);

namespace App\Support\Contable\IngresosBrutos;

use App\Models\Configuracion\Provincia;
use App\Support\Configuracion\EmpresaJurisdiccionIibbSupport;

/**
 * Opciones del reporte de Ingresos Brutos según los tildes de
 * Configuración general → Agentes IIBB por empresa.
 *
 * CABA (jurisdicción 901) y Buenos Aires (902) solo aparecen si la empresa
 * está nominada como agente de percepción o de retención en esa jurisdicción.
 */
final class IngresosBrutosAgenteOpcionesSupport
{
    public const JURISDICCION_CABA = 901;

    public const JURISDICCION_BUENOS_AIRES = 902;

    /**
     * @return array<string, string>
     */
    public static function tiposParaEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return self::sinNominacion() ? self::tiposArbaLegado() : [];
        }

        $percibe = EmpresaJurisdiccionIibbSupport::jurisdiccionesPercepcion($empresaId);
        $retiene = EmpresaJurisdiccionIibbSupport::jurisdiccionesRetencion($empresaId);

        $out = [];
        if (in_array(self::JURISDICCION_BUENOS_AIRES, $retiene, true)) {
            $out[IngresosBrutosListadoFiltros::TIPO_RETENCIONES] = IngresosBrutosListadoFiltros::TIPOS[IngresosBrutosListadoFiltros::TIPO_RETENCIONES];
        }
        if (in_array(self::JURISDICCION_BUENOS_AIRES, $percibe, true)) {
            $out[IngresosBrutosListadoFiltros::TIPO_PERCEPCIONES] = IngresosBrutosListadoFiltros::TIPOS[IngresosBrutosListadoFiltros::TIPO_PERCEPCIONES];
        }
        if (in_array(self::JURISDICCION_CABA, $retiene, true)) {
            $out[IngresosBrutosListadoFiltros::TIPO_RETENCIONES_CABA] = IngresosBrutosListadoFiltros::TIPOS[IngresosBrutosListadoFiltros::TIPO_RETENCIONES_CABA];
        }
        if (in_array(self::JURISDICCION_CABA, $percibe, true)) {
            $out[IngresosBrutosListadoFiltros::TIPO_PERCEPCIONES_CABA] = IngresosBrutosListadoFiltros::TIPOS[IngresosBrutosListadoFiltros::TIPO_PERCEPCIONES_CABA];
        }

        if ($out === [] && self::sinNominacion()) {
            return self::tiposArbaLegado();
        }

        return $out;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<int, array<string, string>>
     */
    public static function mapaPorEmpresas(array $empresaIds): array
    {
        $map = [];
        foreach ($empresaIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $map[$id] = self::tiposParaEmpresa($id);
        }

        return $map;
    }

    /**
     * @return array{id: int, codigo: string, nombre: string}|null
     */
    public static function datosProvincia(int $jurisdiccion): ?array
    {
        $provincia = Provincia::query()
            ->where('jurisdiccion', (string) $jurisdiccion)
            ->orderBy('id')
            ->first();

        if ($provincia === null) {
            $provincia = Provincia::query()
                ->where('codigo', (string) $jurisdiccion)
                ->orderBy('id')
                ->first();
        }

        if ($provincia === null) {
            return null;
        }

        return [
            'id' => (int) $provincia->id,
            'codigo' => (string) ($provincia->codigo ?? ''),
            'nombre' => (string) ($provincia->nombre ?? ''),
        ];
    }

    /**
     * Instalación sin grilla guardada y sin listas en el .env: se mantiene ARBA,
     * que es lo que la pantalla ofrecía antes de existir la nominación.
     */
    /**
     * @return array<string, string>
     */
    private static function tiposArbaLegado(): array
    {
        return [
            IngresosBrutosListadoFiltros::TIPO_RETENCIONES => IngresosBrutosListadoFiltros::TIPOS[IngresosBrutosListadoFiltros::TIPO_RETENCIONES],
            IngresosBrutosListadoFiltros::TIPO_PERCEPCIONES => IngresosBrutosListadoFiltros::TIPOS[IngresosBrutosListadoFiltros::TIPO_PERCEPCIONES],
        ];
    }

    private static function sinNominacion(): bool
    {
        if (! EmpresaJurisdiccionIibbSupport::matrizUsaFallbackEnv()) {
            return false;
        }

        return EmpresaJurisdiccionIibbSupport::desdeEnv('agente_percepcion_iibb') === []
            && EmpresaJurisdiccionIibbSupport::desdeEnv('agente_retencion_iibb') === [];
    }
}
