<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use App\ApiAnita;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Contabilidad_CuentaAutomatica;
use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\Puntoventa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Imputación por punto de venta de las transferencias Crown:
 * cuentas (impcont) + centro de costo (suc_leyenda2 de Anita).
 */
final class TransferenciaCrownImputacionSupport
{
    /** @var array<int, ?int> */
    private static array $cacheCentroPorSucursal = [];

    /** @var array<int, bool> */
    private static array $cacheManejaCc = [];

    /**
     * @return list<array<string, mixed>>
     */
    public static function listar(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $porSucursal = [];
        foreach (self::nombresPuntoVenta($empresaId) as $sucursal => $nombre) {
            $porSucursal[$sucursal] = self::filaVacia($empresaId, $sucursal, $nombre);
        }

        $filas = Contabilidad_CuentaAutomatica::query()
            ->with('cuentacontables:id,codigo,nombre,empresa_id')
            ->where('empresa_id', $empresaId)
            ->where('clave', 'like', 'transferencia_crown.%.%.%')
            ->orderBy('clave')
            ->get();

        foreach ($filas as $fila) {
            $parsed = TransferenciaCrownCuentaClaves::parsearClavePv((string) $fila->clave);
            if ($parsed === null) {
                continue;
            }
            $sucursal = $parsed['sucursal'];
            if (! isset($porSucursal[$sucursal])) {
                $porSucursal[$sucursal] = self::filaVacia($empresaId, $sucursal);
            }
            $cuenta = $fila->cuentacontables;
            if ($cuenta === null || (int) $cuenta->empresa_id !== $empresaId) {
                continue;
            }
            $porSucursal[$sucursal]['cuentas'][TransferenciaCrownCuentaClaves::inputRol($parsed['perfil'], $parsed['rol'])] = [
                'id' => (int) $cuenta->id,
                'codigo' => (string) $cuenta->codigo,
                'nombre' => (string) $cuenta->nombre,
            ];
        }

        foreach (self::centrosPorSucursal($empresaId) as $sucursal => $centro) {
            if (! isset($porSucursal[$sucursal])) {
                $porSucursal[$sucursal] = self::filaVacia($empresaId, $sucursal);
            }
            $porSucursal[$sucursal]['centrocosto'] = $centro;
        }

        ksort($porSucursal);

        return array_values($porSucursal);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fila(int $empresaId, int $sucursal): array
    {
        foreach (self::listar($empresaId) as $fila) {
            if ((int) $fila['sucursal'] === $sucursal) {
                return $fila;
            }
        }

        return self::filaVacia($empresaId, $sucursal);
    }

    /**
     * @param  array<string, mixed>  $cuentas  clave inputRol => cuentacontable_id
     */
    public static function guardar(int $empresaId, int $sucursal, array $cuentas, ?int $centrocostoId = null): void
    {
        if ($empresaId <= 0 || $sucursal <= 0) {
            throw new RuntimeException('Falta la empresa o el punto de venta.');
        }

        if ($centrocostoId !== null && $centrocostoId > 0
            && ! Centrocosto::query()->whereKey($centrocostoId)->exists()) {
            throw new RuntimeException('El centro de costo no existe.');
        }

        DB::transaction(function () use ($empresaId, $sucursal, $cuentas, $centrocostoId): void {
            foreach (TransferenciaCrownCuentaClaves::ROLES as $perfil => $roles) {
                foreach ($roles as $rol => $meta) {
                    $input = TransferenciaCrownCuentaClaves::inputRol($perfil, $rol);
                    if (! array_key_exists($input, $cuentas)) {
                        continue;
                    }
                    $cuentaId = (int) ($cuentas[$input] ?? 0);
                    if ($cuentaId > 0) {
                        $existe = DB::table('cuentacontable')
                            ->where('id', $cuentaId)
                            ->where('empresa_id', $empresaId)
                            ->exists();
                        if (! $existe) {
                            throw new RuntimeException('La cuenta de '.$meta['etiqueta'].' no pertenece a la empresa.');
                        }
                    }

                    Contabilidad_CuentaAutomatica::query()->updateOrCreate(
                        [
                            'empresa_id' => $empresaId,
                            'clave' => TransferenciaCrownCuentaClaves::clavePv($perfil, $rol, $sucursal),
                        ],
                        ['cuentacontable_id' => $cuentaId > 0 ? $cuentaId : null],
                    );
                }
            }

            if (Schema::hasTable('transferencia_crown_pv_centrocosto')) {
                $valorCc = ($centrocostoId !== null && $centrocostoId > 0) ? $centrocostoId : null;
                $ya = DB::table('transferencia_crown_pv_centrocosto')
                    ->where('empresa_id', $empresaId)
                    ->where('sucursal', $sucursal)
                    ->exists();
                if ($ya) {
                    DB::table('transferencia_crown_pv_centrocosto')
                        ->where('empresa_id', $empresaId)
                        ->where('sucursal', $sucursal)
                        ->update(['centrocosto_id' => $valorCc, 'updated_at' => now()]);
                } else {
                    DB::table('transferencia_crown_pv_centrocosto')->insert([
                        'empresa_id' => $empresaId,
                        'sucursal' => $sucursal,
                        'centrocosto_id' => $valorCc,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                unset(self::$cacheCentroPorSucursal[$empresaId * 100000 + $sucursal]);
            }
        });
    }

    public static function centrocostoIdParaSucursal(int $empresaId, int $sucursal): ?int
    {
        if ($empresaId <= 0 || $sucursal <= 0 || ! Schema::hasTable('transferencia_crown_pv_centrocosto')) {
            return null;
        }

        $cacheKey = $empresaId * 100000 + $sucursal;
        if (array_key_exists($cacheKey, self::$cacheCentroPorSucursal)) {
            return self::$cacheCentroPorSucursal[$cacheKey];
        }

        $id = (int) (DB::table('transferencia_crown_pv_centrocosto')
            ->where('empresa_id', $empresaId)
            ->where('sucursal', $sucursal)
            ->value('centrocosto_id') ?? 0);

        self::$cacheCentroPorSucursal[$cacheKey] = $id > 0 ? $id : null;

        return self::$cacheCentroPorSucursal[$cacheKey];
    }

    public static function centrocostoIdParaCuenta(int $empresaId, int $sucursal, int $cuentacontableId): ?int
    {
        if ($cuentacontableId <= 0 || ! self::cuentaManejaCentro($cuentacontableId)) {
            return null;
        }

        return self::centrocostoIdParaSucursal($empresaId, $sucursal);
    }

    /**
     * @return array{cargadas: int, ya_estaban: int, sin_cuenta: int}
     */
    public static function sembrarDesdeAnita(): array
    {
        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'shared',
            'tabla' => 'impcont',
            'campos' => 'impc_clave, impc_cuenta, impc_empresa',
            'whereArmado' => ' WHERE impc_empresa = 1 AND ((impc_clave >= 1000 AND impc_clave < 9000) OR impc_clave IN (4,5,6,8,14,37,41,118,119,208))',
            'path_sistema' => rtrim((string) config('anita.bdd_path', '/usr2/casino'), '/') ?: '/usr2/casino',
        ]);
        $parsed = ApiAnita::parsearRespuestaLista(is_string($raw) ? $raw : null);
        if (($parsed['error_lectura'] ?? '') !== '') {
            throw new RuntimeException('No se pudo leer impcont: '.$parsed['error_lectura']);
        }

        $porClave = [];
        foreach ($parsed['filas'] as $fila) {
            $clave = (int) ($fila->impc_clave ?? 0);
            $cuenta = (int) ($fila->impc_cuenta ?? 0);
            if ($clave > 0 && $cuenta > 0) {
                $porClave[$clave] = $cuenta;
            }
        }

        $empresas = DB::table('puntoventa')->distinct()->pluck('empresa_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $stats = ['cargadas' => 0, 'ya_estaban' => 0, 'sin_cuenta' => 0];
        foreach ($empresas as $empresaId) {
            $ids = self::idsPorCodigo($empresaId);
            foreach (TransferenciaCrownCuentaClaves::impcontCompartidas() as $claveCatalogo => $claveImpcont) {
                self::aplicar($empresaId, $claveCatalogo, (int) ($porClave[$claveImpcont] ?? 0), $ids, $stats);
            }
            foreach ($porClave as $claveImpcont => $numero) {
                $parsedClave = self::perfilDesdeImpcont((int) $claveImpcont);
                if ($parsedClave === null) {
                    continue;
                }
                $clave = TransferenciaCrownCuentaClaves::clavePv(
                    $parsedClave['perfil'],
                    $parsedClave['rol'],
                    $parsedClave['sucursal'],
                );
                self::aplicar($empresaId, $clave, (int) $numero, $ids, $stats);
            }
        }

        return $stats;
    }

    /**
     * Precarga el centro de costo de cada sucursal desde suc_leyenda2 de Anita.
     *
     * @return array{cargadas: int, ya_estaban: int, sin_centro: int, sin_leyenda: int}
     */
    public static function sembrarCentrosDesdeAnita(): array
    {
        $stats = ['cargadas' => 0, 'ya_estaban' => 0, 'sin_centro' => 0, 'sin_leyenda' => 0];
        if (! Schema::hasTable('transferencia_crown_pv_centrocosto')) {
            return $stats;
        }

        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'sucursal',
            'campos' => 'suc_numero, suc_leyenda2',
            'whereArmado' => ' WHERE suc_numero > 0',
            'path_sistema' => rtrim((string) config('anita.bdd_path', '/usr2/casino'), '/') ?: '/usr2/casino',
        ]);
        $parsed = ApiAnita::parsearRespuestaLista(is_string($raw) ? $raw : null);
        if (($parsed['error_lectura'] ?? '') !== '') {
            throw new RuntimeException('No se pudo leer sucursal: '.$parsed['error_lectura']);
        }

        $centros = [];
        foreach (Centrocosto::query()->get(['id', 'codigo']) as $cc) {
            $digitos = preg_replace('/\D+/', '', (string) $cc->codigo) ?? '';
            if ($digitos === '') {
                continue;
            }
            $centros[(string) (int) $digitos] = (int) $cc->id;
            $centros[$digitos] = (int) $cc->id;
        }

        $empresas = DB::table('puntoventa')->distinct()->pluck('empresa_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();
        if ($empresas === []) {
            $empresas = [1];
        }

        foreach ($parsed['filas'] as $fila) {
            $sucursal = (int) ($fila->suc_numero ?? 0);
            $leyenda = trim((string) ($fila->suc_leyenda2 ?? ''));
            if ($sucursal <= 0) {
                continue;
            }
            if ($leyenda === '') {
                $stats['sin_leyenda']++;
                continue;
            }
            $codigoCc = (string) (int) preg_replace('/\D+/', '', $leyenda);
            $centrocostoId = $centros[$codigoCc] ?? 0;
            if ($centrocostoId <= 0) {
                $stats['sin_centro']++;
                continue;
            }

            foreach ($empresas as $empresaId) {
                $existente = DB::table('transferencia_crown_pv_centrocosto')
                    ->where('empresa_id', $empresaId)
                    ->where('sucursal', $sucursal)
                    ->first();
                if ($existente !== null && (int) ($existente->centrocosto_id ?? 0) > 0) {
                    $stats['ya_estaban']++;
                    continue;
                }
                if ($existente === null) {
                    DB::table('transferencia_crown_pv_centrocosto')->insert([
                        'empresa_id' => $empresaId,
                        'sucursal' => $sucursal,
                        'centrocosto_id' => $centrocostoId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('transferencia_crown_pv_centrocosto')
                        ->where('id', $existente->id)
                        ->update(['centrocosto_id' => $centrocostoId, 'updated_at' => now()]);
                }
                $stats['cargadas']++;
            }
        }

        self::$cacheCentroPorSucursal = [];

        return $stats;
    }

    /**
     * @return array{perfil: string, rol: string, sucursal: int}|null
     */
    private static function perfilDesdeImpcont(int $clave): ?array
    {
        foreach (TransferenciaCrownCuentaClaves::ROLES as $perfil => $roles) {
            foreach ($roles as $rol => $meta) {
                $base = (int) $meta['base'];
                if ($clave > $base && $clave < $base + 1000) {
                    return [
                        'perfil' => $perfil,
                        'rol' => $rol,
                        'sucursal' => $clave - $base,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, int>  $idsPorCodigo
     * @param  array{cargadas: int, ya_estaban: int, sin_cuenta: int}  $stats
     */
    private static function aplicar(int $empresaId, string $clave, int $numeroAnita, array $idsPorCodigo, array &$stats): void
    {
        $existente = DB::table('contabilidad_cuenta_automatica')
            ->where('empresa_id', $empresaId)
            ->where('clave', $clave)
            ->first();
        if ($existente !== null && (int) ($existente->cuentacontable_id ?? 0) > 0) {
            $stats['ya_estaban']++;

            return;
        }

        $codigo = str_pad((string) $numeroAnita, 9, '0', STR_PAD_LEFT);
        $cuentaId = $idsPorCodigo[$codigo] ?? 0;
        if ($cuentaId <= 0) {
            $stats['sin_cuenta']++;

            return;
        }

        if ($existente === null) {
            DB::table('contabilidad_cuenta_automatica')->insert([
                'empresa_id' => $empresaId,
                'clave' => $clave,
                'cuentacontable_id' => $cuentaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('contabilidad_cuenta_automatica')
                ->where('id', $existente->id)
                ->update(['cuentacontable_id' => $cuentaId, 'updated_at' => now()]);
        }
        $stats['cargadas']++;
    }

    /**
     * @return array<string, int>
     */
    private static function idsPorCodigo(int $empresaId): array
    {
        $out = [];
        $filas = DB::table('cuentacontable')->where('empresa_id', $empresaId)->get(['id', 'codigo']);
        foreach ($filas as $fila) {
            $digitos = preg_replace('/\D+/', '', (string) $fila->codigo) ?? '';
            if ($digitos === '') {
                continue;
            }
            $out[str_pad($digitos, 9, '0', STR_PAD_LEFT)] = (int) $fila->id;
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private static function nombresPuntoVenta(int $empresaId): array
    {
        $out = [];
        $puntos = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->orderBy('id')
            ->get(['codigo', 'nombre']);
        foreach ($puntos as $punto) {
            $sucursal = (int) preg_replace('/\D+/', '', (string) $punto->codigo);
            if ($sucursal > 0 && ! isset($out[$sucursal])) {
                $out[$sucursal] = (string) $punto->nombre;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{id: int, codigo: string, nombre: string}>
     */
    private static function centrosPorSucursal(int $empresaId): array
    {
        if (! Schema::hasTable('transferencia_crown_pv_centrocosto')) {
            return [];
        }

        $out = [];
        $filas = DB::table('transferencia_crown_pv_centrocosto as t')
            ->leftJoin('centrocosto as c', 'c.id', '=', 't.centrocosto_id')
            ->where('t.empresa_id', $empresaId)
            ->get(['t.sucursal', 't.centrocosto_id', 'c.codigo', 'c.nombre']);
        foreach ($filas as $fila) {
            $out[(int) $fila->sucursal] = [
                'id' => (int) ($fila->centrocosto_id ?? 0),
                'codigo' => (string) ($fila->codigo ?? ''),
                'nombre' => (string) ($fila->nombre ?? ''),
            ];
        }

        return $out;
    }

    private static function cuentaManejaCentro(int $cuentacontableId): bool
    {
        if (array_key_exists($cuentacontableId, self::$cacheManejaCc)) {
            return self::$cacheManejaCc[$cuentacontableId];
        }

        $flag = Cuentacontable::query()->whereKey($cuentacontableId)->value('manejaccosto');
        $maneja = in_array((string) $flag, ['S', '1'], true);
        self::$cacheManejaCc[$cuentacontableId] = $maneja;

        return $maneja;
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaVacia(int $empresaId, int $sucursal, ?string $nombre = null): array
    {
        $cuentas = [];
        foreach (TransferenciaCrownCuentaClaves::ROLES as $perfil => $roles) {
            foreach ($roles as $rol => $meta) {
                $cuentas[TransferenciaCrownCuentaClaves::inputRol($perfil, $rol)] = [
                    'id' => 0,
                    'codigo' => '',
                    'nombre' => '',
                ];
            }
        }

        return [
            'sucursal' => $sucursal,
            'codigo' => str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT),
            'nombre' => $nombre ?? ('Punto de venta '.$sucursal),
            'empresa_id' => $empresaId,
            'cuentas' => $cuentas,
            'centrocosto' => ['id' => 0, 'codigo' => '', 'nombre' => ''],
        ];
    }
}
