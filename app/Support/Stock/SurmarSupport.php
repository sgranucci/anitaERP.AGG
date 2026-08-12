<?php

namespace App\Support\Stock;

/**
 * Gate y constantes del módulo stock/producción Surmar (El Bierzo).
 * Empresa operativa fija id=3 en Bierzo. En AGG el id 3 es REBISCO: no tratarlo como Surmar.
 */
final class SurmarSupport
{
    public const ORIGEN_CARGA_RECEPCION = 'SURMAR';

    /** El Bierzo: Surmar. AGG: ese id es Rebisco — usar esEmpresaSurmar(). */
    public const EMPRESA_ID = 3;

    public const ORIGEN_COM = 'COM';
    public const ORIGEN_DES = 'DES';
    public const ORIGEN_AP = 'AP';
    public const ORIGEN_TRA = 'TRA';
    public const ORIGEN_IMPORT_ANITA = 'IMPORT_ANITA';

    public const ESTADO_DISPONIBLE = 'DISPONIBLE';
    public const ESTADO_RESERVADA = 'RESERVADA';
    public const ESTADO_CONSUMIDA = 'CONSUMIDA';
    public const ESTADO_ANULADA = 'ANULADA';

    public static function empresaId(): int
    {
        return self::EMPRESA_ID;
    }

    /** Entorno El Bierzo (config app.empresa). AGG u otros nunca son Surmar. */
    public static function esEntornoBierzo(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }

    /**
     * Surmar solo si: EMPRESA=EL BIERZO + empresa_id=3 + nombre contiene «surmar».
     * En AGG id 3 es Rebisco → siempre false.
     */
    public static function esEmpresaSurmar(?int $empresaId): bool
    {
        if (! self::esEntornoBierzo()) {
            return false;
        }
        if ((int) $empresaId !== self::EMPRESA_ID) {
            return false;
        }

        $nombre = trim((string) \Illuminate\Support\Facades\DB::table('empresa')
            ->where('id', self::EMPRESA_ID)
            ->value('nombre'));

        return $nombre !== '' && stripos($nombre, 'surmar') !== false;
    }

    public static function abortSiNoSurmar(?int $empresaId = null): void
    {
        if ($empresaId === null || ! self::esEmpresaSurmar($empresaId)) {
            abort(404);
        }
    }

    /**
     * Path Anita para escritura/lectura de documentos Surmar (solo El Bierzo).
     * AGG (id 3 = Rebisco) y otras empresas → null (= config anita.bdd_path).
     */
    public static function pathSistemaAnita(?int $empresaId): ?string
    {
        if (! self::esEmpresaSurmar($empresaId)) {
            return null;
        }

        $path = rtrim((string) config('anita.surmar_path', '/usr2/surmar'), '/');

        return $path !== '' ? $path : '/usr2/surmar';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mergePathSistema(array $payload, ?int $empresaId): array
    {
        $path = self::pathSistemaAnita($empresaId);
        if ($path !== null) {
            $payload['path_sistema'] = $path;
        }

        return $payload;
    }

    /** Empresa en curso para escritura Anita (request-scoped; ColisionSupport / helpers estáticos). */
    private static ?int $escrituraEmpresaId = null;

    public static function fijarEmpresaEscritura(?int $empresaId): void
    {
        self::$escrituraEmpresaId = $empresaId !== null && $empresaId > 0 ? $empresaId : null;
    }

    public static function limpiarEmpresaEscritura(): void
    {
        self::$escrituraEmpresaId = null;
    }

    /**
     * @template T
     * @param  callable(): T  $fn
     * @return T
     */
    public static function conEmpresaEscritura(?int $empresaId, callable $fn): mixed
    {
        $prev = self::$escrituraEmpresaId;
        self::fijarEmpresaEscritura($empresaId);
        try {
            return $fn();
        } finally {
            self::$escrituraEmpresaId = $prev;
        }
    }

    public static function empresaEscrituraActual(): ?int
    {
        return self::$escrituraEmpresaId;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mergePathSistemaContexto(array $payload): array
    {
        return self::mergePathSistema($payload, self::$escrituraEmpresaId);
    }
}
