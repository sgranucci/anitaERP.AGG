<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use App\Support\Configuracion\PadronIibb\PadronIibbArchivoSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Carpeta externa donde se pegan los padrones IIBB (CABA y provincias).
 * ARBA no usa esta bandeja: se baja solo por DFE.
 *
 * entrada/{clave} → procesando/{clave} → ok|error/{clave}
 */
final class PadronIibbBandejaSupport
{
    /** @var list<string> */
    private const EXTENSIONES = ['txt', 'csv', 'zip'];

    /** @var list<string> */
    private const SUFIJOS_TRANSITORIOS = ['.tmp', '.partial', '.crdownload', '.filepart', '.download'];

    /**
     * Clave = subcarpeta relativa a la raíz de la bandeja.
     *
     * @return array<string, array{jurisdiccion: int, tipo: ?string}>
     */
    public static function carpetas(): array
    {
        return [
            'caba' => ['jurisdiccion' => 901, 'tipo' => null],
            'cordoba' => ['jurisdiccion' => 904, 'tipo' => null],
            'entrerios' => ['jurisdiccion' => 908, 'tipo' => null],
            'misiones' => ['jurisdiccion' => 914, 'tipo' => null],
            'santafe' => ['jurisdiccion' => 921, 'tipo' => null],
            'tucuman/tasas' => ['jurisdiccion' => 924, 'tipo' => 'T'],
            'tucuman/coeficientes' => ['jurisdiccion' => 924, 'tipo' => 'C'],
        ];
    }

    public static function directorio(): string
    {
        return rtrim((string) config('padrones_iibb.bandeja.directorio', '/var/www/padrones'), '/');
    }

    public static function estableSegundos(): int
    {
        return max(10, (int) config('padrones_iibb.bandeja.estable_segundos', 90));
    }

    public static function asegurarDirectorios(): void
    {
        $raiz = self::directorio();
        $relativos = array_merge(
            array_keys(self::carpetas()),
            ['procesando', 'ok', 'error']
        );

        foreach (self::carpetas() as $clave => $_) {
            $relativos[] = 'procesando/' . $clave;
            $relativos[] = 'ok/' . $clave;
            $relativos[] = 'error/' . $clave;
        }

        foreach ($relativos as $relativo) {
            $dir = $raiz . '/' . $relativo;
            if (! is_dir($dir) && ! @mkdir($dir, 02770, true) && ! is_dir($dir)) {
                Log::warning('padron_iibb:bandeja:mkdir', ['directorio' => $dir]);

                continue;
            }
            // mkdir respeta el umask: sin este chmod el grupo pierde escritura y el worker no puede mover.
            @chmod($dir, 02770);
        }
        if (is_dir($raiz)) {
            @chmod($raiz, 02770);
        }

        $leeme = $raiz . '/LEEME.txt';
        $texto = self::textoAyuda();
        if (is_dir($raiz) && (! is_file($leeme) || (string) @file_get_contents($leeme) !== $texto)) {
            @file_put_contents($leeme, $texto);
        }
    }

    /**
     * Hay un archivo todavía en procesando para esa jurisdicción: no se toma otro.
     */
    public static function hayEnProceso(string $clave): bool
    {
        $dir = self::directorio() . '/procesando/' . $clave;
        if (! is_dir($dir)) {
            return false;
        }

        foreach (scandir($dir) ?: [] as $nombre) {
            if ($nombre === '.' || $nombre === '..') {
                continue;
            }
            if (is_file($dir . '/' . $nombre)) {
                return true;
            }
        }

        return false;
    }

    /**
     * El archivo más viejo de la carpeta de entrada que ya dejó de crecer.
     */
    public static function archivoListo(string $clave): ?string
    {
        $dir = self::directorio() . '/' . $clave;
        if (! is_dir($dir)) {
            return null;
        }

        $candidatos = [];
        foreach (scandir($dir) ?: [] as $nombre) {
            $ruta = $dir . '/' . $nombre;
            if (! is_file($ruta) || ! self::esArchivoDePadron($nombre, $clave)) {
                continue;
            }
            $mtime = filemtime($ruta);
            $size = filesize($ruta);
            if ($mtime === false || $size === false || $size < 1) {
                continue;
            }
            if ((time() - $mtime) < self::estableSegundos()) {
                continue;
            }
            $candidatos[] = ['ruta' => $ruta, 'mtime' => $mtime];
        }

        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, static fn (array $a, array $b): int => $a['mtime'] <=> $b['mtime']);

        return $candidatos[0]['ruta'];
    }

    /**
     * Pasa el archivo a procesando/{clave}. Devuelve la ruta nueva.
     */
    public static function tomar(string $clave, string $origen): string
    {
        $nombre = self::nombreSeguro(basename($origen));
        $destinoDir = self::directorio() . '/procesando/' . $clave;
        if (! is_dir($destinoDir) && ! @mkdir($destinoDir, 02770, true) && ! is_dir($destinoDir)) {
            throw new \RuntimeException("No se pudo crear {$destinoDir}");
        }

        $destino = $destinoDir . '/' . date('Ymd_His') . '_' . $nombre;
        if (! @rename($origen, $destino)) {
            throw new \RuntimeException("No se pudo mover {$origen} a procesando.");
        }

        return $destino;
    }

    /**
     * Al terminar el job: si el archivo está bajo procesando/, lo mueve a ok/ o error/.
     * No lanza: un fallo al archivar no puede tumbar la importación.
     */
    public static function archivar(string $archivo, bool $ok): void
    {
        try {
            $real = realpath($archivo);
            if ($real === false || ! is_file($real)) {
                return;
            }

            $raiz = realpath(self::directorio());
            if ($raiz === false) {
                return;
            }

            $prefijo = $raiz . DIRECTORY_SEPARATOR . 'procesando' . DIRECTORY_SEPARATOR;
            if (! str_starts_with($real, $prefijo)) {
                return;
            }

            $relativo = substr($real, strlen($prefijo));
            $carpeta = dirname($relativo);
            if ($carpeta === '.' || $carpeta === '') {
                return;
            }

            $destinoDir = $raiz . DIRECTORY_SEPARATOR . ($ok ? 'ok' : 'error') . DIRECTORY_SEPARATOR . $carpeta;
            if (! is_dir($destinoDir) && ! @mkdir($destinoDir, 02770, true) && ! is_dir($destinoDir)) {
                Log::warning('padron_iibb:bandeja:archivar_mkdir', ['directorio' => $destinoDir]);

                return;
            }

            $destino = $destinoDir . DIRECTORY_SEPARATOR . basename($real);
            if (is_file($destino)) {
                $destino = $destinoDir . DIRECTORY_SEPARATOR . time() . '_' . basename($real);
            }

            if (! @rename($real, $destino)) {
                Log::warning('padron_iibb:bandeja:archivar_rename', [
                    'origen' => $real,
                    'destino' => $destino,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('padron_iibb:bandeja:archivar', ['error' => $e->getMessage(), 'archivo' => $archivo]);
        }
    }

    public static function esArchivoDePadron(string $nombre, ?string $clave = null): bool
    {
        if ($nombre === '' || $nombre[0] === '.' || str_starts_with($nombre, '~$')) {
            return false;
        }

        $lower = strtolower($nombre);
        foreach (self::SUFIJOS_TRANSITORIOS as $sufijo) {
            if (str_ends_with($lower, $sufijo)) {
                return false;
            }
        }

        $ext = strtolower((string) pathinfo($nombre, PATHINFO_EXTENSION));
        $extensiones = self::EXTENSIONES;
        if ($clave === 'caba') {
            $extensiones[] = 'rar';
        }
        if (! in_array($ext, $extensiones, true)) {
            return false;
        }

        // Sin unrar el RAR se queda en la carpeta: no se mueve a error.
        if ($ext === 'rar' && PadronIibbArchivoSupport::binarioUnrar() === null) {
            Log::warning('padron_iibb:bandeja:sin_unrar', [
                'archivo' => $nombre,
                'clave' => $clave,
            ]);

            return false;
        }

        return true;
    }

    public static function textoAyuda(): string
    {
        return <<<'TXT'
Padrones de Ingresos Brutos
===========================

Pegue el archivo en la carpeta de la provincia. No lo deje en la raíz.
ARBA no va acá: se descarga solo.

  caba/                  AGIP, ARDJU….TXT o el RAR que publica AGIP
  cordoba/               CSV con punto y coma, líneas P y R
  entrerios/             CSV con punto y coma
  misiones/              CSV con cabecera Periodo_fiscal;régimen;cuit;…
  santafe/               PARP_AAAAMM.csv o el ZIP
  tucuman/tasas/         padrón de tasas
  tucuman/coeficientes/  padrón de coeficientes

Espere a que termine de copiarse. En un par de minutos el archivo pasa a
procesando y, al terminar, a ok o a error.

La pantalla Importar Padrón IIBB sigue sirviendo para cargar a mano.
TXT;
    }

    private static function nombreSeguro(string $nombre): string
    {
        $limpio = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) ?: 'padron.bin';

        return mb_substr($limpio, 0, 180);
    }
}
