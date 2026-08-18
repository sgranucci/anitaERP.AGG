<?php

namespace App\Support\Configuracion;

/**
 * Auto-import Anita al abrir un index vacío.
 * En el laboratorio PostgreSQL (`EMPRESA=LAB_PG`) no debe pegarle al bridge.
 */
final class AnitaSyncIndexSupport
{
    public static function autoImportHabilitado(): bool
    {
        return ! EntornoEmpresaSupport::esLabPostgres();
    }
}
