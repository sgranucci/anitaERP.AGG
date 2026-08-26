<?php

namespace App\Support\Uif;

use App\Support\Archivos\ArchivoAdjuntoCacheSupport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storage UIF en file server /scan (mismo árbol que Anita).
 * Sync: solo registra en BD; no copia a disco del ERP.
 * Multi-empresa: {@see withOrigen()} apunta a clientes_KSA / premios_KSA / fotos_clientes_KSA, etc.
 */
final class ClienteUifArchivoStorage
{
    /** @var array<string, string>|null */
    private static ?array $origenOverride = null;

    /**
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public static function withOrigen(string $origen, callable $callback)
    {
        $prev = self::$origenOverride;
        self::$origenOverride = self::configOrigen($origen);
        try {
            return $callback();
        } finally {
            self::$origenOverride = $prev;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function configOrigen(string $origen): array
    {
        $origen = strtolower(trim($origen));
        $all = config('uif.anita_origenes', []);
        if (! isset($all[$origen]) || ! is_array($all[$origen])) {
            throw new \InvalidArgumentException("Origen UIF desconocido: {$origen}");
        }

        return $all[$origen];
    }

    public static function servidorDefault(string $origen): string
    {
        return (string) (self::configOrigen($origen)['servidor'] ?? config('anita.ip', ''));
    }

    public static function salaId(string $origen): int
    {
        return (int) (self::configOrigen($origen)['sala_id'] ?? 1);
    }

    public static function mountAnita(): string
    {
        return rtrim((string) config('uif.anita_uif_archivos.mount', '/scan/uif/archivos'), DIRECTORY_SEPARATOR);
    }

    public static function dirClientes(): string
    {
        if (self::$origenOverride !== null) {
            return rtrim((string) (self::$origenOverride['archivos_clientes'] ?? ''), DIRECTORY_SEPARATOR);
        }
        $configured = rtrim((string) config('uif.ARCHIVOS_CLIENTES_PATH', ''), DIRECTORY_SEPARATOR);
        if ($configured !== '') {
            return $configured;
        }

        return self::mountAnita().DIRECTORY_SEPARATOR.'clientes';
    }

    public static function dirPremios(): string
    {
        if (self::$origenOverride !== null) {
            return rtrim((string) (self::$origenOverride['archivos_premios'] ?? ''), DIRECTORY_SEPARATOR);
        }
        $configured = rtrim((string) config('uif.ARCHIVOS_PREMIOS_PATH', ''), DIRECTORY_SEPARATOR);
        if ($configured !== '') {
            return $configured;
        }

        return self::mountAnita().DIRECTORY_SEPARATOR.'premios';
    }

    public static function dirFotosPremio(): string
    {
        if (self::$origenOverride !== null) {
            return rtrim((string) (self::$origenOverride['fotos_premios'] ?? ''), DIRECTORY_SEPARATOR);
        }
        $configured = rtrim((string) config('uif.FOTOS_PREMIOS_PATH', ''), DIRECTORY_SEPARATOR);
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('uif.FOTOS_CLIENTES_PATH', '/scan/tesoreria/fotos_clientes'), DIRECTORY_SEPARATOR);
    }

    /** Carpetas de fotos premio de todos los orígenes (lectura UI). */
    public static function dirsFotosPremioTodos(): array
    {
        $dirs = [];
        foreach (config('uif.anita_origenes', []) as $cfg) {
            $d = rtrim((string) ($cfg['fotos_premios'] ?? ''), DIRECTORY_SEPARATOR);
            if ($d !== '') {
                $dirs[$d] = true;
            }
        }
        $dirs[self::dirFotosPremio()] = true;

        return array_keys($dirs);
    }

    /** Carpetas adjuntos cliente de todos los orígenes (lectura UI). */
    public static function dirsClientesTodos(): array
    {
        $dirs = [];
        foreach (config('uif.anita_origenes', []) as $cfg) {
            $d = rtrim((string) ($cfg['archivos_clientes'] ?? ''), DIRECTORY_SEPARATOR);
            if ($d !== '') {
                $dirs[$d] = true;
            }
        }
        $dirs[self::dirClientes()] = true;

        return array_keys($dirs);
    }

    public static function dirsPremiosTodos(): array
    {
        $dirs = [];
        foreach (config('uif.anita_origenes', []) as $cfg) {
            $d = rtrim((string) ($cfg['archivos_premios'] ?? ''), DIRECTORY_SEPARATOR);
            if ($d !== '') {
                $dirs[$d] = true;
            }
        }
        $dirs[self::dirPremios()] = true;

        return array_keys($dirs);
    }

    /** Si false (default), el sync Anita solo registra filas BD sin copy a /var. */
    public static function syncDebeCopiar(): bool
    {
        return (bool) config('uif.SYNC_COPIAR_ARCHIVOS', false);
    }

    public static function ensureDir(string $dir): bool
    {
        if ($dir === '') {
            return false;
        }
        if (is_dir($dir)) {
            return is_writable($dir);
        }

        return @mkdir($dir, 0775, true) || is_dir($dir);
    }

    public static function absoluteClienteAdjunto(int $clienteUifId, string $nombrearchivo): ?string
    {
        $base = basename($nombrearchivo);
        if ($base === '' || $clienteUifId <= 0) {
            return null;
        }

        $candidates = [];
        foreach (self::dirsClientesTodos() as $dir) {
            $candidates[] = $dir.DIRECTORY_SEPARATOR.$base;
        }
        $candidates[] = public_path('storage/archivos/clientes_uif/'.$clienteUifId.'/'.$base);
        $candidates[] = public_path('storage/archivos/clientes_uif/'.$base);

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function absolutePremioAdjunto(int $premioLocalId, string $nombrearchivo): ?string
    {
        $base = basename($nombrearchivo);
        if ($base === '' || $premioLocalId <= 0) {
            return null;
        }

        $candidates = [];
        foreach (self::dirsPremiosTodos() as $dir) {
            $candidates[] = $dir.DIRECTORY_SEPARATOR.$base;
        }
        $candidates[] = public_path('storage/archivos/clientes_premios_uif/'.$premioLocalId.'/'.$base);
        $candidates[] = public_path('storage/archivos/clientes_premios_uif/'.$base);

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function absoluteFotoPremio(?string $basename): ?string
    {
        if ($basename === null || $basename === '') {
            return null;
        }
        $base = basename($basename);
        $candidates = [];
        foreach (self::dirsFotosPremioTodos() as $dir) {
            $candidates[] = $dir.DIRECTORY_SEPARATOR.$base;
        }
        $candidates[] = storage_path('app/public/imagenes/fotos_uif/'.$base);
        $candidates[] = public_path('storage/imagenes/fotos_uif/'.$base);

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function versionCache(?string $absolutePath): string
    {
        return ArchivoAdjuntoCacheSupport::versionCache($absolutePath);
    }

    /**
     * @return array{v: string}
     */
    public static function queryVersion(?string $absolutePath): array
    {
        return ArchivoAdjuntoCacheSupport::queryVersion($absolutePath);
    }

    public static function aplicarAntiCacheNavegador(Response $response): Response
    {
        return ArchivoAdjuntoCacheSupport::aplicarAntiCacheNavegador($response);
    }

    public static function urlClienteAdjunto(int $clienteUifId, string $nombrearchivo): string
    {
        $base = basename($nombrearchivo);
        $path = self::absoluteClienteAdjunto($clienteUifId, $base);

        return route('cliente_uif_archivo', array_merge(
            ['id' => $clienteUifId, 'archivo' => $base],
            self::queryVersion($path)
        ));
    }

    public static function urlPremioAdjunto(int $premioLocalId, string $nombrearchivo): string
    {
        $base = basename($nombrearchivo);
        $path = self::absolutePremioAdjunto($premioLocalId, $base);

        return route('cliente_premio_uif_archivo', array_merge(
            ['id' => $premioLocalId, 'archivo' => $base],
            self::queryVersion($path)
        ));
    }

    public static function urlFotoPremio(?string $basename): ?string
    {
        if ($basename === null || $basename === '') {
            return null;
        }
        $path = self::absoluteFotoPremio($basename);
        if ($path === null) {
            return null;
        }

        return route('cliente_premio_uif_foto_archivo', array_merge(
            ['archivo' => basename($basename)],
            self::queryVersion($path)
        ));
    }

    /** No borrar originales Anita pago_* del file server compartido. */
    public static function esFotoPremioCompartidaAnita(string $basename): bool
    {
        return (bool) preg_match('/^pago_\d+\./i', basename($basename));
    }
}
