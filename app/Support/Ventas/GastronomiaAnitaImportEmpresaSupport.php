<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Puntoventa;

/**
 * Filtro por ven_empresa / stkv_empresa en bridge Anita (AGG).
 * Sucursal 00031 comparte numeración Kandiko (FAK, empresa 2) y Rebisco (FAC, empresa 3).
 */
final class GastronomiaAnitaImportEmpresaSupport
{
    public static function usaFiltroEmpresaAnita(): bool
    {
        return strtoupper(trim((string) config('app.empresa'))) === 'AGG';
    }

    public static function codigoEmpresa(int $empresaId): string
    {
        $codigo = Empresa::query()->whereKey($empresaId)->value('codigo');

        return trim((string) ($codigo ?: $empresaId));
    }

    public static function whereEmpresa(string $prefijo, string|int|null $empresaCodigo): string
    {
        if (! self::usaFiltroEmpresaAnita()) {
            return '';
        }

        $codigo = trim((string) $empresaCodigo);
        if ($codigo === '') {
            return '';
        }

        return ' AND '.$prefijo."_empresa='".addslashes($codigo)."'";
    }

    /**
     * Tipo ven_tipo esperado al leer cabecera venta en Anita para el PV/empresa ERP.
     */
    public static function tipoVentaAnita(
        Puntoventa $puntoventa,
        string|int|null $empresaCodigo = null,
    ): string {
        $empresaCodigo ??= $puntoventa->empresas?->codigo ?? $puntoventa->empresa_id;

        return KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge(
            KandikoAnitaVentaTipoSupport::TIPO_NUMERADOR,
            (string) $puntoventa->codigo,
            $empresaCodigo,
            $puntoventa->modofacturacion ?? null,
        );
    }

    /**
     * Tipos a consultar en tabla venta (solo cabecera; detalle stkmov/resvta puede seguir FAC).
     *
     * @return list<string>
     */
    public static function tiposCabeceraVentaAnita(
        Puntoventa $puntoventa,
        string|int|null $empresaCodigo = null,
    ): array {
        $tipo = self::tipoVentaAnita($puntoventa, $empresaCodigo);

        if (self::usaFiltroEmpresaAnita()) {
            return [$tipo];
        }

        if ($tipo === KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE) {
            return KandikoAnitaVentaTipoSupport::tiposAnitaEquivalentesFacErp();
        }

        return [KandikoAnitaVentaTipoSupport::TIPO_NUMERADOR];
    }

    /**
     * Tipos para tablas de detalle (stkmov, vengrav, resvta, vencae).
     *
     * @return list<string>
     */
    public static function tiposDetalleAnita(string $tipoCabecera): array
    {
        $tipoCabecera = strtoupper(trim($tipoCabecera));
        if ($tipoCabecera === KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE) {
            return KandikoAnitaVentaTipoSupport::tiposAnitaEquivalentesFacErp();
        }

        return [$tipoCabecera !== '' ? $tipoCabecera : KandikoAnitaVentaTipoSupport::TIPO_NUMERADOR];
    }

    public static function cabeceraCorrespondeAlPv(
        object $cab,
        Puntoventa $puntoventa,
        string|int|null $empresaCodigo = null,
    ): bool {
        $tipo = strtoupper(trim((string) ($cab->ven_tipo ?? '')));
        $empresaCodigo ??= $puntoventa->empresas?->codigo ?? $puntoventa->empresa_id;

        if (self::usaFiltroEmpresaAnita()) {
            $empCab = trim((string) ($cab->ven_empresa ?? ''));
            if ($empCab !== '' && $empCab !== trim((string) $empresaCodigo)) {
                return false;
            }
        }

        return KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv(
            $tipo,
            (string) $puntoventa->codigo,
            $empresaCodigo,
            $puntoventa->modofacturacion ?? null,
        );
    }
}
