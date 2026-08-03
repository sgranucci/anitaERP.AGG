<?php

namespace App\Repositories\Uif;

use App\ApiAnita;

/**
 * Copia archivos UIF desde el montaje local de Anita (o rutas derivadas) al storage público del ERP.
 * La tabla en Anita es opcional vía env (ANITA_UIF_ARCHIVOS_TABLA_*).
 */
final class AnitaUifArchivosSync
{
    /** @var array<int, list<string>>|null */
    private static ?array $indiceBasenamesCliente = null;

    /** @var array<string, list<string>>|null clave "{cliente}-{premio}" */
    private static ?array $indiceBasenamesPremio = null;

    /**
     * Escanea una vez carpetas planas de adjuntos para sync bulk (evita glob por cliente).
     */
    public static function warmIndicesDesdeDirectorios(string $dirClientes, string $dirPremios): array
    {
        self::$indiceBasenamesCliente = [];
        self::$indiceBasenamesPremio = [];

        $dirCli = rtrim($dirClientes, '/');
        if (is_dir($dirCli)) {
            foreach (scandir($dirCli, SCANDIR_SORT_NONE) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dirCli.'/'.$entry;
                if (! is_file($path)) {
                    continue;
                }
                if (! preg_match('/^0*(\d+)-/', $entry, $m)) {
                    continue;
                }
                $cid = (int) $m[1];
                self::$indiceBasenamesCliente[$cid][] = $entry;
            }
        }

        $dirPre = rtrim($dirPremios, '/');
        if (is_dir($dirPre)) {
            foreach (scandir($dirPre, SCANDIR_SORT_NONE) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dirPre.'/'.$entry;
                if (! is_file($path)) {
                    continue;
                }
                if (! preg_match('/^0*(\d+)-(\d+)-/', $entry, $m)) {
                    continue;
                }
                $key = ((int) $m[1]).'-'.((int) $m[2]);
                self::$indiceBasenamesPremio[$key][] = $entry;
            }
        }

        return [
            'clientes_archivos' => array_sum(array_map('count', self::$indiceBasenamesCliente)),
            'premios_archivos' => array_sum(array_map('count', self::$indiceBasenamesPremio)),
            'clientes_con_archivo' => count(self::$indiceBasenamesCliente),
            'premios_con_archivo' => count(self::$indiceBasenamesPremio),
            'dir_clientes' => $dirCli,
            'dir_premios' => $dirPre,
        ];
    }

    /** @deprecated Preferir {@see warmIndicesDesdeDirectorios} */
    public static function warmIndicesDesdeMount(string $mount): array
    {
        $mount = rtrim($mount, '/');

        return self::warmIndicesDesdeDirectorios($mount.'/clientes', $mount.'/premios');
    }

    public static function clearIndices(): void
    {
        self::$indiceBasenamesCliente = null;
        self::$indiceBasenamesPremio = null;
    }

    /**
     * @param  array<int, object>  $filasApi
     * @return array<int, string> Nombres de archivo (sin path) a importar
     */
    public static function mergeNombresArchivo(array $filasApi, array $nombresFs): array
    {
        $out = [];
        foreach ($filasApi as $row) {
            $n = self::nombreDesdeFila($row);
            if ($n !== '') {
                $out[$n] = true;
            }
        }
        foreach ($nombresFs as $n) {
            $n = (string) $n;
            if ($n !== '') {
                $out[$n] = true;
            }
        }

        return array_keys($out);
    }

    public static function nombreDesdeFila(object $fila): string
    {
        foreach (['carchivo', 'cnombrearchivo', 'archivo', 'nombrearchivo'] as $k) {
            if (isset($fila->$k) && (string) $fila->$k !== '') {
                return basename((string) $fila->$k);
            }
        }

        return '';
    }

    /**
     * @return array<int, object>
     */
    public static function listarDesdeAnita(string $tabla, string $campos, string $sistema, string $whereArmado): array
    {
        if ($tabla === '') {
            return [];
        }
        $apiAnita = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => $tabla,
            'sistema' => $sistema,
            'campos' => $campos,
            'whereArmado' => $whereArmado,
        ];
        $json = $apiAnita->apiCallEscritura($payload);
        $rows = json_decode($json);
        if (! is_array($rows)) {
            return [];
        }

        return $rows;
    }

    /**
     * @return array<int, string> basenames bajo el directorio de montaje
     */
    public static function listarBasenamesEnDirectorios(array $directorios): array
    {
        $out = [];
        foreach ($directorios as $dir) {
            if ($dir === '' || ! is_dir($dir)) {
                continue;
            }
            foreach (glob(rtrim($dir, '/').'/*') ?: [] as $path) {
                if (is_file($path)) {
                    $out[] = basename($path);
                }
            }
        }

        return array_values(array_unique($out));
    }

    public static function directoriosCandidatosCliente(string $mount, int $inroclienteid): array
    {
        $mount = rtrim($mount, '/');
        if ($mount === '') {
            return [];
        }
        $pad = str_pad((string) $inroclienteid, 6, '0', STR_PAD_LEFT);

        return [
            $mount.'/clientes/'.$pad,
            $mount.'/clientes_uif/'.$pad,
            $mount.'/'.$pad,
            $mount.'/c'.$pad,
        ];
    }

    /**
     * Lista basenames en {@see directoriosCandidatosCliente} cuyo nombre empieza por id cliente (ej. 1009- o 001009-).
     * Evita cargar miles de archivos al escanear toda clientes/.
     *
     * @return array<int, string>
     */
    public static function listarBasenamesClientePorPrefijo(string $mount, int $inroclienteid): array
    {
        $mount = rtrim($mount, '/');
        if ($mount === '' || $inroclienteid <= 0) {
            return [];
        }
        if (self::$indiceBasenamesCliente !== null) {
            return array_values(array_unique(self::$indiceBasenamesCliente[$inroclienteid] ?? []));
        }
        $dir = $mount.'/clientes';
        if (! is_dir($dir)) {
            return [];
        }
        $prefixes = [
            (string) $inroclienteid.'-',
            str_pad((string) $inroclienteid, 6, '0', STR_PAD_LEFT).'-',
        ];
        $out = [];
        foreach (glob($dir.'/*') ?: [] as $path) {
            if (! is_file($path)) {
                continue;
            }
            $base = basename($path);
            foreach ($prefixes as $p) {
                if ($base !== '' && strncmp($base, $p, strlen($p)) === 0) {
                    $out[] = $base;
                    break;
                }
            }
        }

        return array_values(array_unique($out));
    }

    public static function directoriosCandidatosPremio(string $mount, int $inroclienteid, int $inropremioid): array
    {
        $mount = rtrim($mount, '/');
        if ($mount === '') {
            return [];
        }
        $pad = str_pad((string) $inroclienteid, 6, '0', STR_PAD_LEFT);

        return [
            $mount.'/premios/'.$inropremioid,
            $mount.'/premios_uif/'.$inropremioid,
            $mount.'/'.$pad.'/'.$inropremioid,
            $mount.'/clientes/'.$pad.'/premios/'.$inropremioid,
        ];
    }

    /**
     * Archivos en premios/ cuyo nombre empieza por "{inroclienteid}-{inropremioid}-".
     *
     * @return array<int, string>
     */
    public static function listarBasenamesPremioPorPrefijo(string $mount, int $inroclienteid, int $inropremioid): array
    {
        $mount = rtrim($mount, '/');
        if ($mount === '' || $inroclienteid <= 0 || $inropremioid <= 0) {
            return [];
        }
        if (self::$indiceBasenamesPremio !== null) {
            $key = $inroclienteid.'-'.$inropremioid;

            return array_values(array_unique(self::$indiceBasenamesPremio[$key] ?? []));
        }
        $dir = $mount.'/premios';
        if (! is_dir($dir)) {
            return [];
        }
        $prefixes = [
            $inroclienteid.'-'.$inropremioid.'-',
            str_pad((string) $inroclienteid, 6, '0', STR_PAD_LEFT).'-'.$inropremioid.'-',
        ];
        $out = [];
        foreach (glob($dir.'/*') ?: [] as $path) {
            if (! is_file($path)) {
                continue;
            }
            $base = basename($path);
            foreach ($prefixes as $p) {
                if ($base !== '' && strncmp($base, $p, strlen($p)) === 0) {
                    $out[] = $base;
                    break;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<int, string>
     */
    public static function rutasOrigenCandidatas(string $mount, int $inroclienteid, string $nombreArchivo): array
    {
        $mount = rtrim($mount, '/');
        $nombreArchivo = basename($nombreArchivo);
        if ($mount === '' || $nombreArchivo === '') {
            return [];
        }
        $pad = str_pad((string) $inroclienteid, 6, '0', STR_PAD_LEFT);
        $sinExt = pathinfo($nombreArchivo, PATHINFO_FILENAME);

        return array_values(array_filter([
            /* Layout plano típico en Anita (adjuntos de cliente en clientes/) */
            $mount.'/clientes/'.$nombreArchivo,
            $mount.'/'.$nombreArchivo,
            $mount.'/'.$pad.'/'.$nombreArchivo,
            $mount.'/clientes/'.$pad.'/'.$nombreArchivo,
            $mount.'/clientes_uif/'.$pad.'/'.$nombreArchivo,
            $mount.'/CLIUIF-'.$pad.'.'.$nombreArchivo,
            $mount.'/CLIUIF-'.$pad.'.'.$sinExt,
        ]));
    }

    /**
     * @return array<int, string>
     */
    public static function rutasOrigenCandidatasPremio(
        string $mount,
        int $inroclienteid,
        int $inropremioid,
        string $nombreArchivo
    ): array {
        $mount = rtrim($mount, '/');
        $nombreArchivo = basename($nombreArchivo);
        if ($mount === '' || $nombreArchivo === '') {
            return [];
        }
        $pad = str_pad((string) $inroclienteid, 6, '0', STR_PAD_LEFT);

        $primero = array_merge(
            [
                $mount.'/premios/'.$nombreArchivo,
                $mount.'/premios/'.$inropremioid.'/'.$nombreArchivo,
                $mount.'/premios_uif/'.$inropremioid.'/'.$nombreArchivo,
                $mount.'/'.$pad.'/'.$inropremioid.'/'.$nombreArchivo,
            ],
            self::rutasOrigenCandidatas($mount, $inroclienteid, $nombreArchivo)
        );

        return array_values(array_unique(array_filter($primero)));
    }

    public static function primeraRutaExistente(array $candidatas): ?string
    {
        foreach ($candidatas as $p) {
            if ($p !== '' && is_file($p)) {
                return $p;
            }
        }

        return null;
    }
}
