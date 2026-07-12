<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

use App\Models\Configuracion\Empresa;

final class SicoreEmpresaAnitaSupport
{
    public static function codigoEmpresaAnita(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        $empresa = Empresa::query()->find($empresaId);
        if ($empresa === null) {
            return $empresaId;
        }

        $codigo = trim((string) ($empresa->codigo ?? ''));
        if ($codigo !== '' && ctype_digit($codigo)) {
            return (int) $codigo;
        }

        return $empresaId;
    }
}
