<?php

namespace App\Support\Ventas\Gastronomia;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas contables del proceso de cierre de jornada gastronomía (por empresa).
 */
final class CierreJornadaProcesoConfigSupport
{
    private const TABLA = 'gastronomia_cierre_jornada_config';

    /**
     * @return array{
     *   cuenta_ventas_id:?int,
     *   cuenta_iva_id:?int,
     *   cuenta_impuesto_interno_id:?int,
     *   cuenta_fondo_fijo_maquinas_id:?int,
     *   completo:bool
     * }
     */
    public static function paraEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return self::vacios();
        }

        $defaults = [
            'cuenta_ventas_id' => self::intOrNull(config('gastronomia.cierre_jornada_cuenta_ventas_id')),
            'cuenta_iva_id' => self::intOrNull(config('gastronomia.cierre_jornada_cuenta_iva_id')),
            'cuenta_impuesto_interno_id' => self::intOrNull(config('gastronomia.cierre_jornada_cuenta_impuesto_interno_id')),
            'cuenta_fondo_fijo_maquinas_id' => self::intOrNull(config('gastronomia.cierre_jornada_cuenta_fondo_fijo_maquinas_id')),
        ];

        if (! Schema::hasTable(self::TABLA)) {
            return self::normalizar($defaults);
        }

        $row = DB::table(self::TABLA)->where('empresa_id', $empresaId)->first();
        if ($row === null) {
            return self::normalizar($defaults);
        }

        return self::normalizar([
            'cuenta_ventas_id' => $row->cuenta_ventas_id ?? $defaults['cuenta_ventas_id'],
            'cuenta_iva_id' => $row->cuenta_iva_id ?? $defaults['cuenta_iva_id'],
            'cuenta_impuesto_interno_id' => $row->cuenta_impuesto_interno_id ?? $defaults['cuenta_impuesto_interno_id'],
            'cuenta_fondo_fijo_maquinas_id' => $row->cuenta_fondo_fijo_maquinas_id ?? $defaults['cuenta_fondo_fijo_maquinas_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function guardar(int $empresaId, array $data): array
    {
        if ($empresaId <= 0) {
            throw new \InvalidArgumentException('Empresa inválida.');
        }

        if (! Schema::hasTable(self::TABLA)) {
            throw new \RuntimeException('Tabla de configuración no disponible. Ejecute migraciones.');
        }

        $payload = [
            'empresa_id' => $empresaId,
            'cuenta_ventas_id' => self::intOrNull($data['cuenta_ventas_id'] ?? null),
            'cuenta_iva_id' => self::intOrNull($data['cuenta_iva_id'] ?? null),
            'cuenta_impuesto_interno_id' => self::intOrNull($data['cuenta_impuesto_interno_id'] ?? null),
            'cuenta_fondo_fijo_maquinas_id' => self::intOrNull($data['cuenta_fondo_fijo_maquinas_id'] ?? null),
            'updated_at' => now(),
        ];

        $existe = DB::table(self::TABLA)->where('empresa_id', $empresaId)->exists();
        if ($existe) {
            DB::table(self::TABLA)->where('empresa_id', $empresaId)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table(self::TABLA)->insert($payload);
        }

        return self::paraEmpresa($empresaId);
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    public static function faltantes(array $cfg): array
    {
        $falt = [];
        if (empty($cfg['cuenta_ventas_id'])) {
            $falt[] = 'Cuenta de ventas';
        }
        if (empty($cfg['cuenta_iva_id'])) {
            $falt[] = 'Cuenta de IVA (débito fiscal)';
        }

        return $falt;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function normalizar(array $raw): array
    {
        $cfg = [
            'cuenta_ventas_id' => self::intOrNull($raw['cuenta_ventas_id'] ?? null),
            'cuenta_iva_id' => self::intOrNull($raw['cuenta_iva_id'] ?? null),
            'cuenta_impuesto_interno_id' => self::intOrNull($raw['cuenta_impuesto_interno_id'] ?? null),
            'cuenta_fondo_fijo_maquinas_id' => self::intOrNull($raw['cuenta_fondo_fijo_maquinas_id'] ?? null),
        ];
        $cfg['completo'] = $cfg['cuenta_ventas_id'] > 0 && $cfg['cuenta_iva_id'] > 0;

        return $cfg;
    }

  /**
     * @return array<string, mixed>
     */
    private static function vacios(): array
    {
        return self::normalizar([]);
    }

    private static function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $id = (int) $v;

        return $id > 0 ? $id : null;
    }
}
