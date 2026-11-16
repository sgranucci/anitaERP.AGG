<?php

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaEmpresaConfig;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Support\Facades\Schema;

/**
 * Modo de trabajo del workspace de conciliación bancaria.
 *
 * - contaduria: cierre mensual / carátula (AGG: Florencia, Erika).
 * - tesoreria: operación día a día (traer banco, foco en fecha).
 *
 * Prioridad: fila por empresa → parámetro sistema → env/config → default por entorno.
 */
final class ConciliacionBancariaModoSupport
{
    public const CONTADURIA = 'contaduria';

    public const TESORERIA = 'tesoreria';

    /**
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return [
            self::CONTADURIA => 'Contaduría (cierre mensual)',
            self::TESORERIA => 'Tesorería (día a día)',
        ];
    }

    public static function esValido(string $modo): bool
    {
        return array_key_exists($modo, self::opciones());
    }

    public static function normalizar(?string $modo): string
    {
        $modo = strtolower(trim((string) $modo));

        return self::esValido($modo) ? $modo : self::defaultInstalacion();
    }

    public static function defaultInstalacion(): string
    {
        $desdeParam = ParametroSistemaSupport::texto(
            ParametroSistemaSupport::CLAVE_CONCILIACION_BANCARIA_MODO_DEFAULT,
            ''
        );
        if (self::esValido($desdeParam)) {
            return $desdeParam;
        }

        $desdeConfig = strtolower(trim((string) config('conciliacion_bancaria.modo_default', '')));
        if (self::esValido($desdeConfig)) {
            return $desdeConfig;
        }

        // AGG Contaduría; otras instalaciones arrancan en Tesorería.
        return EntornoEmpresaSupport::esAgg() ? self::CONTADURIA : self::TESORERIA;
    }

    public static function paraEmpresa(int $empresaId): string
    {
        if ($empresaId > 0 && self::tieneTablaEmpresaConfig()) {
            $fila = ConciliacionBancariaEmpresaConfig::query()
                ->where('empresa_id', $empresaId)
                ->first();
            if ($fila !== null && self::esValido((string) $fila->modo)) {
                return (string) $fila->modo;
            }
        }

        return self::defaultInstalacion();
    }

    /**
     * @return array{modo: string, persistido: bool}
     */
    public static function guardarParaEmpresa(int $empresaId, string $modo): array
    {
        $modo = self::normalizar($modo);
        if ($empresaId <= 0 || ! self::tieneTablaEmpresaConfig()) {
            return ['modo' => $modo, 'persistido' => false];
        }

        ConciliacionBancariaEmpresaConfig::query()->updateOrCreate(
            ['empresa_id' => $empresaId],
            ['modo' => $modo],
        );

        return ['modo' => $modo, 'persistido' => true];
    }

    /**
     * Preferencias de UI del workspace según el modo.
     *
     * @return array{
     *     modo: string,
     *     etiqueta: string,
     *     fecha_default_hoy: bool,
     *     incluir_anteriores_default: bool,
     *     solo_pendientes_default: bool,
     *     traer_banco_destacado: bool,
     *     mostrar_saldo_historico: bool,
     *     ayuda: string
     * }
     */
    public static function preferenciasUi(int $empresaId): array
    {
        return self::preferenciasDesdeModo(self::paraEmpresa($empresaId));
    }

    /**
     * @return array{
     *     modo: string,
     *     etiqueta: string,
     *     fecha_default_hoy: bool,
     *     incluir_anteriores_default: bool,
     *     solo_pendientes_default: bool,
     *     traer_banco_destacado: bool,
     *     mostrar_saldo_historico: bool,
     *     ayuda: string
     * }
     */
    public static function preferenciasDesdeModo(string $modo): array
    {
        $modo = self::normalizar($modo);
        $esContaduria = $modo === self::CONTADURIA;

        return [
            'modo' => $modo,
            'etiqueta' => self::opciones()[$modo] ?? $modo,
            'fecha_default_hoy' => ! $esContaduria,
            'incluir_anteriores_default' => $esContaduria,
            'solo_pendientes_default' => true,
            'traer_banco_destacado' => ! $esContaduria,
            'mostrar_saldo_historico' => $esContaduria,
            'ayuda' => $esContaduria
                ? 'Modo Contaduría: foco en el mes, pendientes anteriores y saldo conciliado del período.'
                : 'Modo Tesorería: foco en el día, traer movimientos del banco y cruzar pendientes.',
        ];
    }

    private static function tieneTablaEmpresaConfig(): bool
    {
        static $tiene = null;
        if ($tiene === null) {
            try {
                $tiene = Schema::hasTable('conciliacion_bancaria_empresa_config');
            } catch (\Throwable) {
                $tiene = false;
            }
        }

        return (bool) $tiene;
    }
}
