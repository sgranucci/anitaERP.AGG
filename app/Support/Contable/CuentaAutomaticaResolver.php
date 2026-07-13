<?php

namespace App\Support\Contable;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Resuelve cuentas de patás fijas del sistema: override módulo → catálogo central → env.
 */
final class CuentaAutomaticaResolver
{
    private const TABLA_CENTRAL = 'contabilidad_cuenta_automatica';

    public static function resolverId(int $empresaId, string $clave): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        $override = self::overrideModulo($empresaId, $clave);
        if ($override !== null) {
            return $override;
        }

        $central = self::central($empresaId, $clave);
        if ($central !== null) {
            return $central;
        }

        return self::envFallback($clave);
    }

    public static function resolverIdObligatorio(int $empresaId, string $clave, string $mensaje): int
    {
        $id = self::resolverId($empresaId, $clave);
        if ($id === null || $id <= 0) {
            throw new RuntimeException($mensaje);
        }

        return $id;
    }

    /**
     * Valor almacenado en el catálogo central (sin override de módulo).
     */
    public static function centralId(int $empresaId, string $clave): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        return self::central($empresaId, $clave) ?? self::envFallback($clave);
    }

    /**
     * @return array<string, ?int> clave => cuentacontable_id efectivo
     */
    public static function mapaEfectivoParaEmpresa(int $empresaId): array
    {
        $mapa = [];
        foreach (CuentaAutomaticaClaves::todasLasClaves() as $clave) {
            $mapa[$clave] = self::resolverId($empresaId, $clave);
        }

        return $mapa;
    }

    public static function tieneOverrideModulo(int $empresaId, string $clave): bool
    {
        return self::overrideModulo($empresaId, $clave) !== null;
    }

    private static function overrideModulo(int $empresaId, string $clave): ?int
    {
        $meta = CuentaAutomaticaClaves::catalogo()[$clave] ?? null;
        if ($meta === null) {
            return null;
        }

        $tabla = $meta['modulo_tabla'];
        $columna = $meta['modulo_columna'];
        if ($tabla === null || $columna === null || ! Schema::hasTable($tabla)) {
            return null;
        }

        if (! Schema::hasColumn($tabla, $columna)) {
            return null;
        }

        $valor = DB::table($tabla)->where('empresa_id', $empresaId)->value($columna);

        return self::intOrNull($valor);
    }

    private static function central(int $empresaId, string $clave): ?int
    {
        if (! Schema::hasTable(self::TABLA_CENTRAL)) {
            return null;
        }

        $valor = DB::table(self::TABLA_CENTRAL)
            ->where('empresa_id', $empresaId)
            ->where('clave', $clave)
            ->value('cuentacontable_id');

        return self::intOrNull($valor);
    }

    private static function envFallback(string $clave): ?int
    {
        $envConfig = CuentaAutomaticaClaves::catalogo()[$clave]['env_config'] ?? null;
        if ($envConfig === null || $envConfig === '') {
            return null;
        }

        return self::intOrNull(config($envConfig));
    }

    private static function intOrNull(mixed $valor): ?int
    {
        // Maps de config (códigos por empresa) no son IDs; (int)array === 1 en PHP.
        if ($valor === null || $valor === '' || is_array($valor) || is_object($valor)) {
            return null;
        }

        if (! is_numeric($valor)) {
            return null;
        }

        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }
}
