<?php

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaRegla;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas operativas de conciliación (pantalla Contable → configuración).
 * Fallback: config/conciliacion_bancaria.php + env.
 */
final class ConciliacionBancariaReglasSupport
{
    private const CACHE_KEY = 'conciliacion_bancaria.reglas.mapa';

    public const CLAVE_TOLERANCIA_IMPORTE = 'tolerancia_importe';

    public const CLAVE_DIAS_FECHA = 'dias_tolerancia_fecha';

    public const CLAVE_DIAS_CHEQUE = 'dias_tolerancia_fecha_cheque';

    public const CLAVE_DIAS_PAGO = 'dias_tolerancia_fecha_pago';

    public const CLAVE_DIAS_UNICO = 'dias_tolerancia_fecha_unico';

    public const CLAVE_EXCEL_TOLERANCIA = 'excel_tolerancia_importe';

    public const CLAVE_HISTORICO_MESES = 'historico_lookback_meses';

    public const CLAVE_ANOMALIA_IMPORTE = 'anomalia_importe_grande';

    public const CLAVE_ANOMALIA_DIFERENCIA = 'anomalia_diferencia_saldo';

    public const CLAVE_CARATULA_CREDITO_MAX = 'caratula_credito_max_importe';

    public const CLAVE_MULTI_MATCH_AUTO = 'multi_match_auto';

    public const CLAVE_MULTI_MAX_MIEMBROS = 'multi_match_max_miembros';

    public const CLAVE_MULTI_DIAS = 'multi_match_dias';

    public const CLAVE_DOC_TIPO_CAJA = 'documento_tipo_caja';

    public const CLAVE_DOC_TIPO_COMPRA = 'documento_tipo_compra';

    public const CLAVE_CODIGO_TRANSFERENCIA = 'codigo_transferencia_default';

    public const CLAVE_CODIGO_GASTO = 'codigo_gasto_default';

    public const CLAVE_APROBAR_DISTINTO_CERRAR = 'aprobar_distinto_de_cerrar';

    /**
     * @return array<string, array{etiqueta: string, ayuda: string, tipo: string, default: string}>
     */
    public static function definiciones(): array
    {
        return [
            self::CLAVE_TOLERANCIA_IMPORTE => [
                'etiqueta' => 'Tolerancia de importe ($)',
                'ayuda' => 'Diferencia máxima para considerar un match 1:1 o de sumas.',
                'tipo' => 'decimal',
                'default' => (string) config('conciliacion_bancaria.tolerancia_importe', 0.05),
            ],
            self::CLAVE_DIAS_FECHA => [
                'etiqueta' => 'Días tolerancia fecha (general)',
                'ayuda' => 'Ventana entre fecha contable y fecha banco en el match por importe.',
                'tipo' => 'entero',
                'default' => (string) config('conciliacion_bancaria.dias_tolerancia_fecha', 3),
            ],
            self::CLAVE_DIAS_CHEQUE => [
                'etiqueta' => 'Días tolerancia cheques',
                'ayuda' => 'Clearing de cheques (OPP Ch: vs CH DEP).',
                'tipo' => 'entero',
                'default' => (string) config('conciliacion_bancaria.dias_tolerancia_fecha_cheque', 30),
            ],
            self::CLAVE_DIAS_PAGO => [
                'etiqueta' => 'Días tolerancia pagos / transferencias',
                'ayuda' => 'OPP/OPA/TRF vs TRF.DATA y similares.',
                'tipo' => 'entero',
                'default' => (string) config('conciliacion_bancaria.dias_tolerancia_fecha_pago', 7),
            ],
            self::CLAVE_DIAS_UNICO => [
                'etiqueta' => 'Días tolerancia importe único',
                'ayuda' => 'Pasada de importe único 1:1.',
                'tipo' => 'entero',
                'default' => (string) config('conciliacion_bancaria.dias_tolerancia_fecha_unico', 15),
            ],
            self::CLAVE_EXCEL_TOLERANCIA => [
                'etiqueta' => 'Tolerancia Excel Contaduría ($)',
                'ayuda' => 'Al comparar carátula ERP vs Excel de Contaduría.',
                'tipo' => 'decimal',
                'default' => (string) config('conciliacion_bancaria.excel_tolerancia_importe', 1.0),
            ],
            self::CLAVE_HISTORICO_MESES => [
                'etiqueta' => 'Meses de mayor histórico',
                'ayuda' => 'Lookback de cheques previos a la cobertura IB.',
                'tipo' => 'entero',
                'default' => (string) config('conciliacion_bancaria.historico_lookback_meses', 18),
            ],
            self::CLAVE_ANOMALIA_IMPORTE => [
                'etiqueta' => 'Anomalía: importe grande ($)',
                'ayuda' => 'Pendiente a partir del cual la IA marca anomalía.',
                'tipo' => 'decimal',
                'default' => (string) config('conciliacion_bancaria.anomalia_importe_grande', 50000),
            ],
            self::CLAVE_ANOMALIA_DIFERENCIA => [
                'etiqueta' => 'Anomalía: diferencia de saldo ($)',
                'ayuda' => 'Diferencia de carátula a partir de la cual se señala anomalía.',
                'tipo' => 'decimal',
                'default' => (string) config('conciliacion_bancaria.anomalia_diferencia_saldo', 1.0),
            ],
            self::CLAVE_CARATULA_CREDITO_MAX => [
                'etiqueta' => 'Tope crédito en carátula ($)',
                'ayuda' => 'Créditos IB pendientes que entran a carátula (no CABAL).',
                'tipo' => 'decimal',
                'default' => (string) config('conciliacion_bancaria.caratula_credito_max_importe', 45000),
            ],
            self::CLAVE_MULTI_MATCH_AUTO => [
                'etiqueta' => 'Multi-match automático en corrida',
                'ayuda' => 'Tras el 1:1, intenta N:1 / 1:N por suma de importes.',
                'tipo' => 'boolean',
                'default' => '1',
            ],
            self::CLAVE_MULTI_MAX_MIEMBROS => [
                'etiqueta' => 'Máx. movimientos por grupo multi',
                'ayuda' => 'Tope de líneas en un lado del grupo automático (2–8).',
                'tipo' => 'entero',
                'default' => '5',
            ],
            self::CLAVE_MULTI_DIAS => [
                'etiqueta' => 'Días ventana multi-match',
                'ayuda' => 'Ventana de fechas para armar grupos automáticos.',
                'tipo' => 'entero',
                'default' => '7',
            ],
            self::CLAVE_DOC_TIPO_CAJA => [
                'etiqueta' => 'Tipo caja egreso gastos (abreviatura)',
                'ayuda' => 'Abreviatura del tipo de movimiento de caja al generar egreso de gastos bancarios (ej. EGR).',
                'tipo' => 'texto',
                'default' => (string) config('conciliacion_bancaria.documento_gastos.tipo_caja_abreviatura', 'EGR'),
            ],
            self::CLAVE_DOC_TIPO_COMPRA => [
                'etiqueta' => 'Tipo compra ICO gastos (abreviatura)',
                'ayuda' => 'Abreviatura del tipo de comprobante de compra (ej. ICO).',
                'tipo' => 'texto',
                'default' => (string) config('conciliacion_bancaria.documento_gastos.tipo_compra_abreviatura', 'ICO'),
            ],
            self::CLAVE_CODIGO_TRANSFERENCIA => [
                'etiqueta' => 'Código default transferencia',
                'ayuda' => 'Código de codificación cuando no hay match y el importe es crédito (típicamente 10).',
                'tipo' => 'entero',
                'default' => (string) config('conciliacion_bancaria.codigo_transferencia_default', 10),
            ],
            self::CLAVE_CODIGO_GASTO => [
                'etiqueta' => 'Código default gasto',
                'ayuda' => 'Código de codificación cuando no hay match y el importe es débito (típicamente 7).',
                'tipo' => 'entero',
                'default' => (string) config('conciliacion_bancaria.codigo_gasto_default', 7),
            ],
            self::CLAVE_APROBAR_DISTINTO_CERRAR => [
                'etiqueta' => 'Aprobar distinto de quien cerró',
                'ayuda' => 'SoD: el usuario que aprueba no puede ser el mismo que cerró el período.',
                'tipo' => 'boolean',
                'default' => '0',
            ],
        ];
    }

    public static function aprobarDistintoDeCerrar(): bool
    {
        return self::boolean(self::CLAVE_APROBAR_DISTINTO_CERRAR);
    }

    public static function texto(string $clave, ?string $fallback = null): string
    {
        $def = self::definiciones()[$clave]['default'] ?? ($fallback ?? '');

        return (string) (self::mapa()[$clave] ?? $def);
    }

    public static function codigoTransferenciaDefault(): int
    {
        return max(1, self::entero(self::CLAVE_CODIGO_TRANSFERENCIA));
    }

    public static function codigoGastoDefault(): int
    {
        return max(1, self::entero(self::CLAVE_CODIGO_GASTO));
    }

    public static function decimal(string $clave): float
    {
        $def = self::definiciones()[$clave]['default'] ?? '0';

        return (float) (self::mapa()[$clave] ?? $def);
    }

    public static function entero(string $clave): int
    {
        $def = self::definiciones()[$clave]['default'] ?? '0';

        return (int) (self::mapa()[$clave] ?? $def);
    }

    public static function boolean(string $clave): bool
    {
        $def = self::definiciones()[$clave]['default'] ?? '0';
        $v = strtolower(trim((string) (self::mapa()[$clave] ?? $def)));

        return in_array($v, ['1', 'true', 's', 'si', 'sí', 'yes', 'on'], true);
    }

    public static function toleranciaImporte(): float
    {
        return self::decimal(self::CLAVE_TOLERANCIA_IMPORTE);
    }

    public static function diasToleranciaFecha(): int
    {
        return max(0, self::entero(self::CLAVE_DIAS_FECHA));
    }

    public static function multiMatchAuto(): bool
    {
        return self::boolean(self::CLAVE_MULTI_MATCH_AUTO);
    }

    public static function multiMaxMiembros(): int
    {
        return max(2, min(8, self::entero(self::CLAVE_MULTI_MAX_MIEMBROS)));
    }

    public static function multiDias(): int
    {
        return max(0, self::entero(self::CLAVE_MULTI_DIAS));
    }

    public static function diasToleranciaFechaCheque(): int
    {
        return max(30, self::entero(self::CLAVE_DIAS_CHEQUE));
    }

    public static function historicoLookbackMeses(): int
    {
        return max(6, self::entero(self::CLAVE_HISTORICO_MESES));
    }

    /**
     * @return array<string, string>
     */
    public static function mapa(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            if (! self::tieneTabla()) {
                return [];
            }

            return ConciliacionBancariaRegla::query()
                ->pluck('valor', 'clave')
                ->map(fn ($v) => (string) $v)
                ->all();
        });
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    public static function guardar(array $valores): void
    {
        if (! self::tieneTabla()) {
            throw new \RuntimeException('Falta la tabla conciliacion_bancaria_regla.');
        }

        foreach (self::definiciones() as $clave => $def) {
            if (! array_key_exists($clave, $valores)) {
                continue;
            }
            $raw = $valores[$clave];
            if ($def['tipo'] === 'boolean') {
                $valor = filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            } else {
                $valor = trim((string) $raw);
            }
            ConciliacionBancariaRegla::query()->updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valor],
            );
        }
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<array{clave: string, etiqueta: string, ayuda: string, tipo: string, valor: string}>
     */
    public static function paraFormulario(): array
    {
        $mapa = self::mapa();
        $out = [];
        foreach (self::definiciones() as $clave => $def) {
            $out[] = [
                'clave' => $clave,
                'etiqueta' => $def['etiqueta'],
                'ayuda' => $def['ayuda'],
                'tipo' => $def['tipo'],
                'valor' => (string) ($mapa[$clave] ?? $def['default']),
            ];
        }

        return $out;
    }

    private static function tieneTabla(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = Schema::hasTable('conciliacion_bancaria_regla');
            } catch (\Throwable) {
                $ok = false;
            }
        }

        return (bool) $ok;
    }
}
