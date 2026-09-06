<?php

namespace App\Support\Configuracion;

final class ReArbolTriggerCatalog
{
    public const TIPO_CONDICION = 'CONDICION';

    public const EVAL_CUENTAS_ALLOWLIST_TODAS = 'CUENTAS_ALLOWLIST_TODAS';

    public const EVAL_CUENTAS_ALLOWLIST_ALGUNA_FUERA = 'CUENTAS_ALLOWLIST_ALGUNA_FUERA';

    public const EVAL_LINEA_SIN_CUENTA = 'LINEA_SIN_CUENTA';

    public const EVAL_MONTO_MAYOR_IGUAL = 'MONTO_MAYOR_IGUAL';

    public const EVAL_MONTO_MENOR = 'MONTO_MENOR';

    public const EVAL_CUENTA_ESPECIFICA = 'CUENTA_ESPECIFICA';

    public const EVAL_SIEMPRE = 'SIEMPRE';

    public const ACCION_RAMA_A = 'A';

    public const ACCION_RAMA_B = 'B';

    public const ACCION_ALLOWLIST = 'ALLOWLIST';

    public const GRUPO_ALLOWLIST = 'Allowlist';

    public const GRUPO_MONTO = 'Monto';

    public const GRUPO_LINEAS = 'Líneas';

    public const GRUPO_GENERAL = 'General';

    /** @return list<string> */
    public static function tipos(): array
    {
        return [self::TIPO_CONDICION];
    }

    /** @return list<string> */
    public static function evaluadores(): array
    {
        return [
            self::EVAL_CUENTAS_ALLOWLIST_TODAS,
            self::EVAL_CUENTAS_ALLOWLIST_ALGUNA_FUERA,
            self::EVAL_LINEA_SIN_CUENTA,
            self::EVAL_MONTO_MAYOR_IGUAL,
            self::EVAL_MONTO_MENOR,
            self::EVAL_CUENTA_ESPECIFICA,
            self::EVAL_SIEMPRE,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function evaluadoresPorGrupo(): array
    {
        return [
            self::GRUPO_ALLOWLIST => [
                self::EVAL_CUENTAS_ALLOWLIST_TODAS,
                self::EVAL_CUENTAS_ALLOWLIST_ALGUNA_FUERA,
            ],
            self::GRUPO_LINEAS => [
                self::EVAL_LINEA_SIN_CUENTA,
                self::EVAL_CUENTA_ESPECIFICA,
            ],
            self::GRUPO_MONTO => [
                self::EVAL_MONTO_MAYOR_IGUAL,
                self::EVAL_MONTO_MENOR,
            ],
            self::GRUPO_GENERAL => [
                self::EVAL_SIEMPRE,
            ],
        ];
    }

    /** @return list<string> */
    public static function accionesRama(): array
    {
        return [self::ACCION_RAMA_A, self::ACCION_RAMA_B, self::ACCION_ALLOWLIST];
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_CONDICION => 'Condición',
            default => $tipo,
        };
    }

    public static function etiquetaEvaluador(string $evaluador): string
    {
        return match ($evaluador) {
            self::EVAL_CUENTAS_ALLOWLIST_TODAS => 'Todas las líneas en allowlist',
            self::EVAL_CUENTAS_ALLOWLIST_ALGUNA_FUERA => 'Alguna línea fuera de allowlist',
            self::EVAL_LINEA_SIN_CUENTA => 'Alguna línea sin cuenta contable',
            self::EVAL_MONTO_MAYOR_IGUAL => 'Monto ≥ umbral',
            self::EVAL_MONTO_MENOR => 'Monto < umbral',
            self::EVAL_CUENTA_ESPECIFICA => 'Incluye cuenta contable específica',
            self::EVAL_SIEMPRE => 'Siempre (si aplica el CC)',
            default => $evaluador,
        };
    }

    public static function hintEvaluador(string $evaluador): string
    {
        return match ($evaluador) {
            self::EVAL_CUENTAS_ALLOWLIST_TODAS,
            self::EVAL_CUENTAS_ALLOWLIST_ALGUNA_FUERA => 'Usa la allowlist del paso 1 del mismo CC.',
            self::EVAL_LINEA_SIN_CUENTA => 'Dispara si alguna línea válida no tiene partida/cuenta.',
            self::EVAL_MONTO_MAYOR_IGUAL => 'Compara el total de la RE (misma base del árbol). Moneda vacía = cualquier moneda del total.',
            self::EVAL_MONTO_MENOR => 'Compara el total de la RE. Moneda vacía = cualquier moneda del total.',
            self::EVAL_CUENTA_ESPECIFICA => 'Dispara si alguna línea usa la cuenta indicada.',
            self::EVAL_SIEMPRE => 'Útil para overrides temporales (ej. auditoría) con vigencia.',
            default => '',
        };
    }

    public static function usaAllowlist(string $evaluador): bool
    {
        return in_array($evaluador, [
            self::EVAL_CUENTAS_ALLOWLIST_TODAS,
            self::EVAL_CUENTAS_ALLOWLIST_ALGUNA_FUERA,
        ], true);
    }

    public static function usaMonto(string $evaluador): bool
    {
        return in_array($evaluador, [
            self::EVAL_MONTO_MAYOR_IGUAL,
            self::EVAL_MONTO_MENOR,
        ], true);
    }

    public static function usaCuenta(string $evaluador): bool
    {
        return $evaluador === self::EVAL_CUENTA_ESPECIFICA;
    }

    public static function etiquetaAccionRama(string $accion): string
    {
        return match ($accion) {
            self::ACCION_RAMA_A => 'Forzar Rama A',
            self::ACCION_RAMA_B => 'Forzar Rama B',
            self::ACCION_ALLOWLIST => 'Resolver por allowlist',
            default => $accion,
        };
    }

    public static function normalizarAccionRama(?string $accion): string
    {
        $a = strtoupper(trim((string) $accion));

        return in_array($a, self::accionesRama(), true) ? $a : self::ACCION_ALLOWLIST;
    }

    /**
     * ¿El trigger está dentro de su ventana de vigencia para la fecha dada (Y-m-d)?
     */
    public static function vigenciaAplica(?string $desde, ?string $hasta, string $fechaYmd): bool
    {
        $fecha = substr($fechaYmd, 0, 10);
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        $d = $desde ? substr((string) $desde, 0, 10) : null;
        $h = $hasta ? substr((string) $hasta, 0, 10) : null;

        if ($d && $fecha < $d) {
            return false;
        }
        if ($h && $fecha > $h) {
            return false;
        }

        return true;
    }
}
