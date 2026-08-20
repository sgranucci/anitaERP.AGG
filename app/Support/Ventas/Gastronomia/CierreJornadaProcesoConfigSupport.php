<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Contable\Cuentacontable;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas contables del proceso de cierre de jornada gastronomía (por empresa).
 */
final class CierreJornadaProcesoConfigSupport
{
    private const TABLA = 'gastronomia_cierre_jornada_config';

    /** @var list<string> */
    private const CAMPOS_CUENTA = [
        'cuenta_ventas_id',
        'cuenta_iva_id',
        'cuenta_ventas_kiosco_id',
        'cuenta_fondo_fijo_maquinas_id',
        'cuenta_diferencia_caja_id',
    ];

    private const CAMPO_PUNTOVENTA = 'puntoventa_id';

    private const CAMPO_PORCENTAJE = 'porcentaje';

    /**
     * @return array{
     *   cuenta_ventas_id:?int,
     *   cuenta_iva_id:?int,
     *   cuenta_ventas_kiosco_id:?int,
     *   cuenta_fondo_fijo_maquinas_id:?int,
     *   cuenta_diferencia_caja_id:?int,
     *   puntoventa_id:?int,
     *   completo:bool
     * }
     */
    public static function paraEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return self::vacios();
        }

        $puntoventaId = null;
        $porcentaje = null;
        if (Schema::hasTable(self::TABLA)) {
            $row = DB::table(self::TABLA)->where('empresa_id', $empresaId)->first(['puntoventa_id', 'porcentaje']);
            $puntoventaId = self::intOrNull($row?->puntoventa_id ?? null);
            $porcentaje = self::floatOrNull($row?->porcentaje ?? null);
        }

        return self::normalizar([
            'cuenta_ventas_id' => CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_WAITRY_VENTAS),
            'cuenta_iva_id' => CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_WAITRY_IVA),
            'cuenta_ventas_kiosco_id' => CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_WAITRY_VENTAS_KIOSCO),
            'cuenta_fondo_fijo_maquinas_id' => CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_WAITRY_FONDO_FIJO_MAQUINAS),
            'cuenta_diferencia_caja_id' => CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_WAITRY_DIFERENCIA_CAJA),
            'puntoventa_id' => $puntoventaId,
            'porcentaje' => $porcentaje,
        ]);
    }

    /**
     * Configuración con código y nombre de cada cuenta (para UI).
     *
     * @return array<string, mixed>
     */
    public static function paraEmpresaConDetalle(int $empresaId): array
    {
        return self::enriquecerConCuentas(self::paraEmpresa($empresaId), $empresaId);
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
            'puntoventa_id' => self::intOrNull($data['puntoventa_id'] ?? null),
            'porcentaje' => self::floatOrNull($data['porcentaje'] ?? null),
            'cuenta_ventas_id' => self::intOrNull($data['cuenta_ventas_id'] ?? null),
            'cuenta_iva_id' => self::intOrNull($data['cuenta_iva_id'] ?? null),
            'cuenta_ventas_kiosco_id' => self::intOrNull($data['cuenta_ventas_kiosco_id'] ?? null),
            'cuenta_fondo_fijo_maquinas_id' => self::intOrNull($data['cuenta_fondo_fijo_maquinas_id'] ?? null),
            'cuenta_diferencia_caja_id' => self::intOrNull($data['cuenta_diferencia_caja_id'] ?? null),
            'updated_at' => now(),
        ];

        self::validarCuentasEmpresa($empresaId, $payload);
        self::validarPuntoventaEmpresa($empresaId, $payload);
        self::validarPorcentaje($payload);

        $existe = DB::table(self::TABLA)->where('empresa_id', $empresaId)->exists();
        if ($existe) {
            DB::table(self::TABLA)->where('empresa_id', $empresaId)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table(self::TABLA)->insert($payload);
        }

        return self::paraEmpresaConDetalle($empresaId);
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    public static function faltantes(array $cfg, int $empresaId): array
    {
        $falt = [];
        if (empty($cfg['cuenta_ventas_id'])) {
            $falt[] = 'Cuenta de ventas';
        }
        if (empty($cfg['cuenta_iva_id'])) {
            $falt[] = 'Cuenta de IVA (débito fiscal)';
        }
        if (CierreJornadaProcesoPuntoventaSupport::resolverParaEmpresa($empresaId) === null) {
            $falt[] = 'Punto de venta del proceso (config o BD)';
        }

        return $falt;
    }

    /**
     * Porcentaje de redistribución QR/efectivo para el proceso (manual y automático).
     * Prioridad: BD por empresa → mapa env por empresa → default global env.
     */
    public static function resolverPorcentajeParaEmpresa(int $empresaId): float
    {
        if ($empresaId <= 0) {
            return 0.;
        }

        $cfg = self::paraEmpresa($empresaId);
        $pctBd = self::floatOrNull($cfg['porcentaje'] ?? null);
        if ($pctBd !== null) {
            return round(max(0., min(100., $pctBd)), 4);
        }

        $mapa = config('gastronomia.cierre_jornada_porcentaje_por_empresa', []);
        if (is_array($mapa) && isset($mapa[$empresaId])) {
            return round(max(0., min(100., (float) $mapa[$empresaId])), 4);
        }

        return round(max(0., min(100., (float) config('gastronomia.cierre_jornada_porcentaje', 25))), 4);
    }

    /**
     * Objetivo de empresa limitado al tope recodificable de la jornada (3er asiento / fondo fijo).
     */
    public static function resolverPorcentajeParaJornada(int $empresaId, float $maximoRecodificacion): float
    {
        return CierreJornadaProcesoRedistribucionSupport::porcentajeAplicar(
            self::resolverPorcentajeParaEmpresa($empresaId),
            $maximoRecodificacion,
        );
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
            'cuenta_ventas_kiosco_id' => self::intOrNull($raw['cuenta_ventas_kiosco_id'] ?? null),
            'cuenta_fondo_fijo_maquinas_id' => self::intOrNull($raw['cuenta_fondo_fijo_maquinas_id'] ?? null),
            'cuenta_diferencia_caja_id' => self::intOrNull($raw['cuenta_diferencia_caja_id'] ?? null),
            'puntoventa_id' => self::intOrNull($raw['puntoventa_id'] ?? null),
            'porcentaje' => self::floatOrNull($raw['porcentaje'] ?? null),
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

    private static function floatOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private static function enriquecerConCuentas(array $cfg, int $empresaId): array
    {
        foreach (self::CAMPOS_CUENTA as $campoId) {
            $base = preg_replace('/_id$/', '', $campoId);
            $cfg[$base.'_codigo'] = '';
            $cfg[$base.'_nombre'] = '';

            $id = self::intOrNull($cfg[$campoId] ?? null);
            if ($id === null || $empresaId <= 0) {
                continue;
            }

            $cuenta = Cuentacontable::query()
                ->where('id', $id)
                ->where('empresa_id', $empresaId)
                ->first(['id', 'codigo', 'nombre']);

            if ($cuenta !== null) {
                $cfg[$base.'_codigo'] = (string) $cuenta->codigo;
                $cfg[$base.'_nombre'] = (string) $cuenta->nombre;
            }
        }

        $pv = CierreJornadaProcesoPuntoventaSupport::resolverParaEmpresa($empresaId);
        $cfg['puntoventa_proceso'] = $pv;
        $cfg['puntoventa_proceso_codigo'] = $pv['codigo'] ?? '';
        $cfg['puntoventa_proceso_nombre'] = $pv['nombre'] ?? '';
        $cfg['puntoventa_proceso_origen'] = $pv['origen'] ?? '';
        $cfg['porcentaje_resuelto'] = self::resolverPorcentajeParaEmpresa($empresaId);

        return $cfg;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function validarCuentasEmpresa(int $empresaId, array $payload): void
    {
        $etiquetas = [
            'cuenta_ventas_id' => 'Cuenta de ventas',
            'cuenta_iva_id' => 'Cuenta de IVA',
            'cuenta_ventas_kiosco_id' => 'Cuenta ventas de cigarrillos (tabaco)',
            'cuenta_fondo_fijo_maquinas_id' => 'Cuenta de fondo fijo máquinas',
            'cuenta_diferencia_caja_id' => 'Cuenta diferencia de caja (invitaciones $0,01)',
        ];

        foreach (self::CAMPOS_CUENTA as $campoId) {
            $id = self::intOrNull($payload[$campoId] ?? null);
            if ($id === null) {
                continue;
            }

            $existe = Cuentacontable::query()
                ->where('id', $id)
                ->where('empresa_id', $empresaId)
                ->exists();

            if (! $existe) {
                throw new \InvalidArgumentException(
                    ($etiquetas[$campoId] ?? $campoId).' no existe o no pertenece a la empresa seleccionada.',
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function validarPorcentaje(array $payload): void
    {
        $pct = self::floatOrNull($payload[self::CAMPO_PORCENTAJE] ?? null);
        if ($pct === null) {
            return;
        }

        if ($pct < 0 || $pct > 100) {
            throw new \InvalidArgumentException('El porcentaje del proceso debe estar entre 0 y 100.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function validarPuntoventaEmpresa(int $empresaId, array $payload): void
    {
        $id = self::intOrNull($payload[self::CAMPO_PUNTOVENTA] ?? null);
        if ($id === null) {
            return;
        }

        $pv = \App\Models\Ventas\Puntoventa::query()
            ->whereKey($id)
            ->where('empresa_id', $empresaId)
            ->first(['id', 'modofacturacion']);

        if ($pv === null) {
            throw new \InvalidArgumentException(
                'El punto de venta seleccionado no existe o no pertenece a la empresa.',
            );
        }

        if (($pv->modofacturacion ?? '') === 'M') {
            throw new \InvalidArgumentException(
                'El punto de venta del proceso no puede ser manual (modofacturacion M).',
            );
        }
    }
}
