<?php

namespace App\Support\Caja;

use App\Support\Configuracion\ParametroSistemaSupport;

final class TesoreriaCrownCierreSupport
{
    public const CONFORME = 'C';

    public const NO_CONFORME = 'N';

    public const REVISION = 'R';

    public const REVISADA = 'T';

    public const AUDITORIA = 'A';

    public const CIERRE_AUDITORIA = 'F';

    /** El cierre queda en la historia y la caja vuelve a operativa. No es un estado de aprobación. */
    public const REABIERTA = 'O';

    /** No se asigna a ningún rol al instalar. Lo tilda Sistemas en Permiso - Rol. */
    public const PERMISO_REABRIR = 'reabrir-tesoreria-crown-caja';

    /** @var array<string, string> */
    public const ESTADOS = [
        self::CONFORME => 'Conforme',
        self::NO_CONFORME => 'No conforme',
        self::REVISION => 'A revisión',
        self::REVISADA => 'Revisada',
        self::AUDITORIA => 'Auditoría',
        self::CIERRE_AUDITORIA => 'Cierre de auditoría',
        self::REABIERTA => 'Reabierta',
    ];

    public static function estado(string $codigo): string
    {
        return self::ESTADOS[$codigo] ?? $codigo;
    }

    public static function terminal(string $estado): bool
    {
        return in_array($estado, [self::CONFORME, self::REVISADA, self::CIERRE_AUDITORIA], true);
    }

    public static function tolerancia(): float
    {
        return ParametroSistemaSupport::decimal(
            ParametroSistemaSupport::CLAVE_TESORERIA_CROWN_TOLERANCIA_AUDITORIA,
            50000
        );
    }

    public static function signo(float $diferencia): string
    {
        if (abs($diferencia) < 0.005) {
            return 'Sin diferencia';
        }

        return $diferencia < 0 ? 'Faltante' : 'Excedente';
    }
}
