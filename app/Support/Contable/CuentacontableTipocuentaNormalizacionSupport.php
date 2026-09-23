<?php

namespace App\Support\Contable;

use App\Repositories\Contable\CuentacontableRepositoryInterface;

/**
 * Alinea cuentacontable.tipocuenta con Anita ctamae.ctam_tipo
 * vía CuentacontableRepository::tipocuentaErpDesdeAnita() (mapeo por entorno).
 */
final class CuentacontableTipocuentaNormalizacionSupport
{
    /**
     * @param  list<string>|null  $empresasCodigo
     * @return array{en_anita:int,actualizados:int,iguales:int,sin_cuenta:int,errores:list<string>}
     */
    public static function sincronizarDesdeAnita(bool $dryRun = true, ?array $empresasCodigo = null): array
    {
        return app(CuentacontableRepositoryInterface::class)
            ->sincronizarTipocuentaDesdeAnita($dryRun, $empresasCodigo);
    }
}
