<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownAjuste;
use App\Models\Caja\TesoreriaCrownCierre;
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

    /** No se asigna a ningún rol al instalar. Lo tilda Sistemas en Permiso - Rol. */
    public const PERMISO_AJUSTAR = 'ajustar-tesoreria-crown-caja';

    /**
     * Cierra auditorías de caja, resuelve comprobantes no conformes y autoriza reabrir un
     * cierre en auditoría. No se asigna a ningún rol al instalar. Lo tilda Sistemas en Permiso - Rol.
     */
    public const PERMISO_AUDITAR = 'auditar-tesoreria-crown-caja';

    /** Fila de historia cuando se graba un ajuste. No cambia el estado del cierre por sí solo. */
    public const AJUSTE = 'J';

    /** @var array<string, string> */
    public const ESTADOS = [
        self::CONFORME => 'Conforme',
        self::NO_CONFORME => 'No conforme',
        self::REVISION => 'A revisión',
        self::REVISADA => 'Revisada',
        self::AUDITORIA => 'Auditoría',
        self::CIERRE_AUDITORIA => 'Cierre de auditoría',
        self::REABIERTA => 'Reabierta',
        self::AJUSTE => 'Ajuste de caja',
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

    /**
     * El cierre grabado no se pisa. El vigente suma los ajustes al componente
     * que corrigen y mueve la diferencia con el mismo signo de la fórmula.
     *
     * @param  iterable<int, TesoreriaCrownAjuste>  $ajustes
     * @return array{habilitado: float, rendido: float, resultado: float, diferencia: float, signo: string, supera: bool}
     */
    public static function vigente(TesoreriaCrownCierre $cierre, iterable $ajustes): array
    {
        $habilitado = (float) $cierre->efectivo_habilitado;
        $rendido = (float) $cierre->valores_rendidos;
        $resultado = (float) $cierre->resultado;
        $diferencia = (float) $cierre->diferencia;
        $formula = (string) ($cierre->componentes['formula'] ?? '');
        foreach ($ajustes as $ajuste) {
            $importe = round((float) $ajuste->importe, 2);
            if ($ajuste->corrige === TesoreriaCrownAjuste::HABILITACION) {
                $habilitado += $importe;
                if ($formula !== 'empleados') {
                    $diferencia -= $importe;
                }
            } elseif ($ajuste->corrige === TesoreriaCrownAjuste::RENDICION) {
                $rendido += $importe;
                if ($formula !== 'empleados') {
                    $diferencia += $importe;
                }
            } elseif ($ajuste->corrige === TesoreriaCrownAjuste::RESULTADO) {
                $resultado += $importe;
                if ($formula !== 'empleados') {
                    $diferencia -= $importe;
                }
            }
        }
        $habilitado = round($habilitado, 2);
        $rendido = round($rendido, 2);
        $resultado = round($resultado, 2);
        $diferencia = round($diferencia, 2);

        return [
            'habilitado' => $habilitado,
            'rendido' => $rendido,
            'resultado' => $resultado,
            'diferencia' => $diferencia,
            'signo' => self::signo($diferencia),
            'supera' => abs($diferencia) > self::tolerancia() + 0.001,
        ];
    }
}
