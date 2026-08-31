<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Concepto_Imputacion_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;

/**
 * Cascada de cuentas del asiento de sueldos (fase 0).
 * Override concepto → rubro → tipo → cuentas automáticas.
 */
final class SueldosAsientoMapeoSupport
{
    public const ALCANCE_CONCEPTO = 'concepto';

    public const ALCANCE_RUBRO = 'rubro';

    public const ALCANCE_TIPO = 'tipo';

    /** @var array<string, string> */
    public const ALCANCES = [
        self::ALCANCE_CONCEPTO => 'Concepto',
        self::ALCANCE_RUBRO => 'Rubro costo laboral',
        self::ALCANCE_TIPO => 'Tipo de concepto',
    ];

    /**
     * Patas fijas que deben estar cargadas para el MVP de devengamiento.
     *
     * @return list<string>
     */
    public static function clavesAutomaticasFase0(): array
    {
        return [
            CuentaAutomaticaClaves::SUELDOS_A_PAGAR,
            CuentaAutomaticaClaves::SUELDOS_GASTO_REMUNERATIVO,
            CuentaAutomaticaClaves::SUELDOS_GASTO_NO_REMUNERATIVO,
            CuentaAutomaticaClaves::SUELDOS_GASTO_CONTRIBUCION,
            CuentaAutomaticaClaves::SUELDOS_PASIVO_RETENCION,
            CuentaAutomaticaClaves::SUELDOS_PASIVO_CONTRIBUCION,
        ];
    }

    /** @return list<string> */
    public static function tiposImputables(): array
    {
        return ConceptoTipo::TIPOS_IMPUTABLES;
    }

    public static function esTipoImputable(?string $tipo): bool
    {
        return in_array((string) $tipo, self::tiposImputables(), true);
    }

    public static function tipoRequiereDebe(?string $tipo): bool
    {
        return in_array((string) $tipo, ['remunerativo', 'no_remunerativo', 'asignacion', 'contribucion'], true);
    }

    public static function tipoRequiereHaber(?string $tipo): bool
    {
        return in_array((string) $tipo, ['descuento', 'aporte', 'retencion', 'contribucion'], true);
    }

    public static function clavePara(string $alcance, ?int $conceptoId, ?string $rubro, ?string $tipo): string
    {
        return match ($alcance) {
            self::ALCANCE_CONCEPTO => (string) max(0, (int) $conceptoId),
            self::ALCANCE_RUBRO => trim((string) $rubro),
            self::ALCANCE_TIPO => trim((string) $tipo),
            default => '',
        };
    }

    public static function etiquetaAlcance(string $alcance): string
    {
        return self::ALCANCES[$alcance] ?? $alcance;
    }

    public static function etiquetaClave(Concepto_Imputacion_Sueldos $fila): string
    {
        return match ($fila->alcance) {
            self::ALCANCE_CONCEPTO => self::etiquetaConcepto($fila),
            self::ALCANCE_RUBRO => RubroCostoLaboral::etiqueta($fila->rubro),
            self::ALCANCE_TIPO => ConceptoTipo::etiquetaTipo($fila->tipo),
            default => (string) $fila->clave,
        };
    }

    /**
     * Código de cuenta Anita que este concepto resta (p. ej. cargas 521060006).
     */
    public static function codigoRestaDesdeObservacion(?string $observacion): ?string
    {
        if (! preg_match('/resta\s+(\d{6,12})/i', (string) $observacion, $m)) {
            return null;
        }

        return $m[1];
    }

    /**
     * @return array{
     *   cuenta_debe_id: ?int,
     *   cuenta_haber_id: ?int,
     *   origen: ?string,
     *   omitido: bool,
     *   observacion: ?string,
     *   resta_codigo: ?string
     * }
     */
    public static function resolver(int $empresaId, Concepto_Sueldos $concepto): array
    {
        if ($empresaId <= 0) {
            return self::vacio(null, true);
        }

        $override = self::fila($empresaId, self::ALCANCE_CONCEPTO, (string) $concepto->id);
        if ($override !== null) {
            return self::desdeFila($override, 'concepto');
        }

        if (! self::esTipoImputable($concepto->tipo)) {
            return self::vacio('omitido', true);
        }

        $rubro = trim((string) ($concepto->rubro_costo_laboral ?? ''));
        if ($rubro !== '') {
            $filaRubro = self::fila($empresaId, self::ALCANCE_RUBRO, $rubro);
            if ($filaRubro !== null) {
                return self::desdeFila($filaRubro, 'rubro');
            }
        }

        $filaTipo = self::fila($empresaId, self::ALCANCE_TIPO, (string) $concepto->tipo);
        if ($filaTipo !== null) {
            return self::desdeFila($filaTipo, 'tipo');
        }

        return self::desdeAutomaticas($empresaId, (string) $concepto->tipo);
    }

    /**
     * @param  array{cuenta_debe_id: ?int, cuenta_haber_id: ?int, origen: ?string, omitido: bool}  $resuelto
     */
    public static function estaResuelto(array $resuelto, ?string $tipo): bool
    {
        if (! empty($resuelto['omitido'])) {
            return true;
        }
        if (self::tipoRequiereDebe($tipo) && (int) ($resuelto['cuenta_debe_id'] ?? 0) <= 0) {
            return false;
        }
        if (self::tipoRequiereHaber($tipo) && (int) ($resuelto['cuenta_haber_id'] ?? 0) <= 0) {
            return false;
        }

        if ((int) ($resuelto['cuenta_debe_id'] ?? 0) > 0 || (int) ($resuelto['cuenta_haber_id'] ?? 0) > 0) {
            return true;
        }

        return self::tipoRequiereDebe($tipo) || self::tipoRequiereHaber($tipo);
    }

    /**
     * @return array{cuenta_debe_id: ?int, cuenta_haber_id: ?int, origen: string, omitido: bool}
     */
    public static function desdeAutomaticas(int $empresaId, string $tipo): array
    {
        $debe = null;
        $haber = null;

        switch ($tipo) {
            case 'remunerativo':
            case 'asignacion':
                $debe = CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::SUELDOS_GASTO_REMUNERATIVO);
                break;
            case 'no_remunerativo':
                $debe = CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::SUELDOS_GASTO_NO_REMUNERATIVO);
                break;
            case 'descuento':
            case 'aporte':
            case 'retencion':
                $haber = CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::SUELDOS_PASIVO_RETENCION);
                break;
            case 'contribucion':
                $debe = CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::SUELDOS_GASTO_CONTRIBUCION);
                $haber = CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::SUELDOS_PASIVO_CONTRIBUCION);
                break;
        }

        return [
            'cuenta_debe_id' => self::idONull($debe),
            'cuenta_haber_id' => self::idONull($haber),
            'origen' => 'automatica',
            'omitido' => false,
            'observacion' => null,
            'resta_codigo' => null,
        ];
    }

    private static function fila(int $empresaId, string $alcance, string $clave): ?Concepto_Imputacion_Sueldos
    {
        if ($clave === '') {
            return null;
        }

        return Concepto_Imputacion_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->where('alcance', $alcance)
            ->where('clave', $clave)
            ->first();
    }

    /**
     * @return array{
     *   cuenta_debe_id: ?int,
     *   cuenta_haber_id: ?int,
     *   origen: string,
     *   omitido: bool,
     *   observacion: ?string,
     *   resta_codigo: ?string
     * }
     */
    private static function desdeFila(Concepto_Imputacion_Sueldos $fila, string $origen): array
    {
        $obs = trim((string) ($fila->observacion ?? ''));

        return [
            'cuenta_debe_id' => self::idONull($fila->cuenta_debe_id),
            'cuenta_haber_id' => self::idONull($fila->cuenta_haber_id),
            'origen' => $origen,
            'omitido' => false,
            'observacion' => $obs !== '' ? $obs : null,
            'resta_codigo' => self::codigoRestaDesdeObservacion($obs),
        ];
    }

    /**
     * @return array{
     *   cuenta_debe_id: null,
     *   cuenta_haber_id: null,
     *   origen: ?string,
     *   omitido: bool,
     *   observacion: null,
     *   resta_codigo: null
     * }
     */
    private static function vacio(?string $origen, bool $omitido): array
    {
        return [
            'cuenta_debe_id' => null,
            'cuenta_haber_id' => null,
            'origen' => $origen,
            'omitido' => $omitido,
            'observacion' => null,
            'resta_codigo' => null,
        ];
    }

    private static function idONull(mixed $id): ?int
    {
        $n = (int) $id;

        return $n > 0 ? $n : null;
    }

    private static function etiquetaConcepto(Concepto_Imputacion_Sueldos $fila): string
    {
        $concepto = $fila->concepto;
        if ($concepto === null) {
            return 'Concepto #'.$fila->clave;
        }

        return str_pad((string) $concepto->codigo, 4, '0', STR_PAD_LEFT).' — '.$concepto->descripcion;
    }
}
