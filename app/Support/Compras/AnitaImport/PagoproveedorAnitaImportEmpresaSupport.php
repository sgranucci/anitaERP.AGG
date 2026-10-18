<?php

declare(strict_types=1);

namespace App\Support\Compras\AnitaImport;

/**
 * Empresas Anita ↔ ERP al importar OPP/OPA (AGG multi vs Crown/Ferli mono).
 */
final class PagoproveedorAnitaImportEmpresaSupport
{
    /**
     * @return list<int>
     */
    public static function empresasAnitaImport(): array
    {
        $empresas = config('pagoproveedor.anita_import_empresas', [1, 2, 3]);
        if (! is_array($empresas) || $empresas === []) {
            return [1, 2, 3];
        }

        return array_values(array_unique(array_map('intval', $empresas)));
    }

    /**
     * Anita pag_empresa → empresa_id ERP.
     * Mono (Crown): pag_empresa=0 → anita_import_empresa_erp (default 1).
     */
    public static function empresaErpDesdeAnita(int $pagEmpresa): int
    {
        if ($pagEmpresa > 0) {
            return $pagEmpresa;
        }

        return max(1, (int) config('pagoproveedor.anita_import_empresa_erp', 1));
    }

    /**
     * Código Anita a usar en claves auxpag/retención al mirar un pago ERP.
     * Mono sin columna empresa: siempre 0.
     */
    public static function empresaAnitaClaveDesdeErp(int $empresaIdErp): int
    {
        if (! self::auxpagTieneEmpresa()) {
            return 0;
        }

        $cod = \App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaIdErp);
        if ($cod <= 0) {
            $cod = $empresaIdErp;
        }

        return max(0, $cod);
    }

    public static function auxpagTieneEmpresa(): bool
    {
        $flag = config('pagoproveedor.anita_auxpag_tiene_empresa');
        if ($flag === null) {
            return (bool) config('pagoproveedor.anita_multiempresa', true);
        }

        return (bool) $flag;
    }
}
