<?php

declare(strict_types=1);

namespace App\Support\Configuracion\PadronMipyme;

use App\Support\Configuracion\PadronIibb\PadronIibbArchivoSupport;
use DateTime;
use RuntimeException;
use ZipArchive;

/**
 * Deja el padrón MiPyME listo para leer: detecta ZIP (extensión o firma PK),
 * lo extrae y describe el CSV/TXT de adentro (columnas, encoding, muestra).
 */
final class PadronMipymeArchivoSupport
{
    public const EXTENSIONES_DATOS = ['csv', 'txt', 'dat', 'tsv', ''];

    public const EXTENSIONES_ENTRADA = ['csv', 'txt', 'zip'];

    /**
     * @return array{
     *     ruta: string,
     *     era_zip: bool,
     *     extraido: bool,
     *     nombre_origen: string,
     *     nombre_extraido: string|null,
     *     tamanio_origen: int,
     *     tamanio_datos: int
     * }
     */
    public static function resolver(string $entrada, ?string $nombreOriginal = null): array
    {
        if (! is_file($entrada)) {
            throw new RuntimeException("No existe el archivo: {$entrada}");
        }
        if (! is_readable($entrada)) {
            throw new RuntimeException("No se puede leer el archivo: {$entrada}");
        }

        $nombreOrigen = $nombreOriginal !== null && $nombreOriginal !== ''
            ? $nombreOriginal
            : basename($entrada);
        $eraZip = PadronIibbArchivoSupport::pareceZip($entrada);
        $nombreInterno = null;
        $ruta = PadronIibbArchivoSupport::resolver(
            $entrada,
            self::EXTENSIONES_DATOS,
            static function (array $entradas, ZipArchive $zip) use (&$nombreInterno): array {
                $elegida = self::elegirEntradaZip($entradas, $zip);
                $nombreInterno = (string) ($elegida['interno'] ?? $elegida['nombre'] ?? '');

                return $elegida;
            }
        );

        if ($ruta !== $entrada && PadronIibbArchivoSupport::pareceZip($ruta)) {
            $anidadoNombre = null;
            $anidado = PadronIibbArchivoSupport::resolver(
                $ruta,
                self::EXTENSIONES_DATOS,
                static function (array $entradas, ZipArchive $zip) use (&$anidadoNombre): array {
                    $elegida = self::elegirEntradaZip($entradas, $zip);
                    $anidadoNombre = (string) ($elegida['interno'] ?? $elegida['nombre'] ?? '');

                    return $elegida;
                }
            );
            if ($anidado !== $ruta) {
                PadronIibbArchivoSupport::limpiarTemporal($ruta);
                $ruta = $anidado;
                $nombreInterno = $anidadoNombre ?: $nombreInterno;
            }
        }

        return [
            'ruta' => $ruta,
            'era_zip' => $eraZip,
            'extraido' => $ruta !== $entrada,
            'nombre_origen' => $nombreOrigen,
            'nombre_extraido' => $ruta !== $entrada
                ? ($nombreInterno !== null && $nombreInterno !== '' ? $nombreInterno : basename($ruta))
                : null,
            'tamanio_origen' => (int) filesize($entrada),
            'tamanio_datos' => (int) filesize($ruta),
        ];
    }

    /**
     * Si hay un solo archivo de datos, lo usa aunque se llame mypimes.csv o no tenga extensión.
     * Si hay varios, elige el que más parece el padrón (CUIT + ; + tamaño).
     *
     * @param  list<array{indice: int, nombre: string, interno: string, extension: string, tamanio: int}>  $entradas
     * @return array{indice: int, nombre: string, interno: string, extension: string, tamanio: int}
     */
    public static function elegirEntradaZip(array $entradas, ZipArchive $zip): array
    {
        if ($entradas === []) {
            throw new RuntimeException('El ZIP no contiene ningún archivo de datos.');
        }
        if (count($entradas) === 1) {
            return $entradas[0];
        }

        $mejor = $entradas[0];
        $mejorPuntaje = PHP_INT_MIN;

        foreach ($entradas as $entrada) {
            $puntaje = self::puntuarEntradaZip($entrada, $zip);
            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejor = $entrada;
            }
        }

        return $mejor;
    }

    /**
     * @param  array{indice: int, nombre: string, interno: string, extension: string, tamanio: int}  $entrada
     */
    private static function puntuarEntradaZip(array $entrada, ZipArchive $zip): int
    {
        $puntaje = 0;
        $nombre = mb_strtolower((string) $entrada['nombre']);
        $extension = (string) $entrada['extension'];

        if (in_array($extension, ['csv', 'txt', 'dat', 'tsv', ''], true)) {
            $puntaje += 25;
        }
        if (in_array($extension, ['xlsx', 'xls', 'pdf', 'xml', 'jpg', 'jpeg', 'png', 'gif'], true)) {
            $puntaje -= 60;
        }

        foreach (['mipyme', 'mypime', 'mipymes', 'mypymes', 'padron', 'nomina', 'listado'] as $pista) {
            if (str_contains($nombre, $pista)) {
                $puntaje += 20;
                break;
            }
        }

        $puntaje += min(15, (int) floor(((int) $entrada['tamanio']) / 200000));

        $preview = $zip->getFromIndex((int) $entrada['indice'], 8192);
        if (! is_string($preview) || $preview === '') {
            return $puntaje;
        }

        $inicio = ltrim($preview);
        if (str_starts_with($inicio, '%PDF') || str_starts_with($inicio, '<?xml') || str_starts_with($inicio, '<html')) {
            return $puntaje - 80;
        }
        if (preg_match('/\d{2}-?\d{8}-?\d|\b\d{11}\b/', $preview) === 1) {
            $puntaje += 45;
        }
        if (substr_count($preview, ';') >= 2) {
            $puntaje += 15;
        }

        return $puntaje;
    }

    public static function extensionPermitida(string $nombre): bool
    {
        $ext = strtolower((string) pathinfo($nombre, PATHINFO_EXTENSION));

        return in_array($ext, self::EXTENSIONES_ENTRADA, true);
    }

    public static function limpiarTemporal(string $archivo): void
    {
        PadronIibbArchivoSupport::limpiarTemporal($archivo);
    }

    /**
     * Inspecciona el CSV/TXT ya resuelto (después de descomprimir si hacía falta).
     *
     * @param  array<string, mixed>  $resuelto
     * @return array<string, mixed>
     */
    public static function analizar(string $rutaDatos, array $resuelto = []): array
    {
        $handle = fopen($rutaDatos, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo del padrón para analizarlo.');
        }

        try {
            $muestraCruda = [];
            $lineasTotales = 0;
            while (($linea = fgets($handle)) !== false) {
                $lineasTotales++;
                if (count($muestraCruda) < 12) {
                    $muestraCruda[] = $linea;
                }
            }
        } finally {
            fclose($handle);
        }

        if ($lineasTotales === 0 || $muestraCruda === []) {
            return self::resultadoAnalisis(false, 'El archivo está vacío.', $resuelto, [
                'lineas_totales' => 0,
            ]);
        }

        $encoding = self::detectarEncoding(implode('', $muestraCruda));
        $primera = self::aUtf8($muestraCruda[0], $encoding);
        $delimitador = self::detectarDelimitador($primera);
        $filasMuestra = [];
        foreach ($muestraCruda as $linea) {
            $filasMuestra[] = str_getcsv(self::aUtf8($linea, $encoding), $delimitador);
        }

        $mapeo = self::mapearColumnas($filasMuestra[0] ?? []);
        $advertencias = [];
        if ($mapeo['cuit'] === null) {
            return self::resultadoAnalisis(false, 'No se detectó una columna de CUIT en el archivo.', $resuelto, [
                'encoding' => $encoding,
                'delimitador' => $delimitador,
                'lineas_totales' => $lineasTotales,
                'mapeo' => $mapeo,
                'advertencias' => ['El archivo no parece el padrón MiPyME (CUIT;nombre;actividad;fecha).'],
            ]);
        }

        $muestra = [];
        $importablesMuestra = 0;
        $omitidasMuestra = 0;
        foreach ($filasMuestra as $idx => $fila) {
            if ($idx === 0 && $mapeo['tiene_cabecera']) {
                continue;
            }
            $normalizada = self::normalizarFila($fila, $mapeo);
            if ($normalizada === null) {
                $omitidasMuestra++;

                continue;
            }
            $importablesMuestra++;
            if (count($muestra) < 5) {
                $muestra[] = $normalizada;
            }
        }

        if ($importablesMuestra === 0) {
            return self::resultadoAnalisis(false, 'No se encontraron filas válidas del padrón en la muestra inicial.', $resuelto, [
                'encoding' => $encoding,
                'delimitador' => $delimitador,
                'lineas_totales' => $lineasTotales,
                'mapeo' => $mapeo,
                'muestra' => [],
                'advertencias' => ['Revise el separador y que el CUIT esté en la primera columna (o en un encabezado reconocible).'],
            ]);
        }

        $lineasDatos = $mapeo['tiene_cabecera'] ? max(0, $lineasTotales - 1) : $lineasTotales;
        if ($resuelto['era_zip'] ?? false) {
            $advertencias[] = 'Se detectó un ZIP: se descomprimió '
                . ($resuelto['nombre_extraido'] ?? 'el archivo de datos')
                . ' antes de importar.';
        }

        return self::resultadoAnalisis(true, 'Archivo listo para importar.', $resuelto, [
            'encoding' => $encoding,
            'delimitador' => $delimitador,
            'lineas_totales' => $lineasTotales,
            'lineas_datos' => $lineasDatos,
            'importables_muestra' => $importablesMuestra,
            'omitidas_muestra' => $omitidasMuestra,
            'mapeo' => $mapeo,
            'columnas' => self::etiquetasColumnas($mapeo, $filasMuestra[0] ?? []),
            'muestra' => $muestra,
            'advertencias' => $advertencias,
        ]);
    }

    /**
     * @param  array<string, mixed>  $analisis
     * @return \Generator<int, array{cuit: string, nombre: string, actividad: string, fechainicio: string}>
     */
    public static function iterarFilasValidas(string $rutaDatos, array $analisis): \Generator
    {
        $handle = fopen($rutaDatos, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo del padrón para importarlo.');
        }

        $encoding = (string) ($analisis['encoding'] ?? 'UTF-8');
        $delimitador = (string) ($analisis['delimitador'] ?? ';');
        /** @var array{cuit: int|null, nombre: int|null, actividad: int|null, fechainicio: int|null, tiene_cabecera: bool} $mapeo */
        $mapeo = $analisis['mapeo'] ?? [
            'cuit' => 0,
            'nombre' => 1,
            'actividad' => 2,
            'fechainicio' => 3,
            'tiene_cabecera' => false,
        ];

        try {
            $nroLinea = 0;
            while (($linea = fgets($handle)) !== false) {
                $nroLinea++;
                if ($nroLinea === 1 && ($mapeo['tiene_cabecera'] ?? false)) {
                    continue;
                }
                $fila = str_getcsv(self::aUtf8($linea, $encoding), $delimitador);
                $normalizada = self::normalizarFila($fila, $mapeo);
                if ($normalizada === null) {
                    continue;
                }

                yield $nroLinea => $normalizada;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array{cuit: int|null, nombre: int|null, actividad: int|null, fechainicio: int|null, tiene_cabecera: bool}  $mapeo
     * @return array{cuit: string, nombre: string, actividad: string, fechainicio: string}|null
     */
    public static function normalizarFila(array $fila, array $mapeo): ?array
    {
        if (count($fila) === 1 && is_string($fila[0] ?? null) && str_contains((string) $fila[0], ';')) {
            $fila = str_getcsv((string) $fila[0], ';');
        }

        $idxCuit = $mapeo['cuit'] ?? 0;
        $cuit = self::soloDigitos((string) ($fila[$idxCuit] ?? ''));
        if ($cuit === '' || strlen($cuit) < 10 || strlen($cuit) > 13) {
            return null;
        }

        $idxNombre = $mapeo['nombre'] ?? 1;
        $nombre = mb_substr(trim((string) ($fila[$idxNombre] ?? '')), 0, 255);
        if ($nombre === '') {
            return null;
        }

        $idxActividad = $mapeo['actividad'] ?? null;
        $actividad = $idxActividad === null
            ? ''
            : mb_substr(trim((string) ($fila[$idxActividad] ?? '')), 0, 255);

        $idxFecha = $mapeo['fechainicio'] ?? null;
        $fecha = $idxFecha === null ? null : self::fechaYmd($fila[$idxFecha] ?? null);
        if ($fecha === null) {
            return null;
        }

        return [
            'cuit' => $cuit,
            'nombre' => $nombre,
            'actividad' => $actividad,
            'fechainicio' => $fecha,
        ];
    }

    /**
     * @param  array<int, mixed>  $primeraFila
     * @return array{cuit: int|null, nombre: int|null, actividad: int|null, fechainicio: int|null, tiene_cabecera: bool}
     */
    public static function mapearColumnas(array $primeraFila): array
    {
        $mapeo = [
            'cuit' => null,
            'nombre' => null,
            'actividad' => null,
            'fechainicio' => null,
            'tiene_cabecera' => false,
        ];

        if (self::esCuit((string) ($primeraFila[0] ?? ''))) {
            $mapeo['cuit'] = 0;
            $mapeo['nombre'] = 1;
            $n = count($primeraFila);
            if ($n >= 4) {
                $mapeo['actividad'] = 2;
                $mapeo['fechainicio'] = 3;
            } elseif ($n === 3 && self::fechaYmd($primeraFila[2] ?? null) !== null) {
                $mapeo['fechainicio'] = 2;
            } else {
                $mapeo['actividad'] = 2;
                $mapeo['fechainicio'] = 3;
            }

            return $mapeo;
        }

        $mapeo['tiene_cabecera'] = true;
        foreach ($primeraFila as $i => $titulo) {
            $h = self::normalizarEncabezado((string) $titulo);
            if ($mapeo['cuit'] === null && self::encabezadoEs($h, ['cuit', 'nrodoc', 'nrodocumento', 'documento', 'cuitcuil', 'cuil'])) {
                $mapeo['cuit'] = (int) $i;
            } elseif ($mapeo['nombre'] === null && self::encabezadoEs($h, ['nombre', 'denominacion', 'razonsocial', 'razon', 'apellidoynombre'])) {
                $mapeo['nombre'] = (int) $i;
            } elseif ($mapeo['actividad'] === null && self::encabezadoEs($h, ['actividad', 'ciiu', 'sector', 'seccion', 'descripcionactividad'])) {
                $mapeo['actividad'] = (int) $i;
            } elseif ($mapeo['fechainicio'] === null && self::encabezadoEs($h, ['fechainicio', 'fechainiciovigencia', 'fecha', 'fechadesde', 'desde', 'vigenciadesde'])) {
                $mapeo['fechainicio'] = (int) $i;
            }
        }

        $mapeo['cuit'] ??= 0;
        $mapeo['nombre'] ??= 1;
        if ($mapeo['fechainicio'] === null) {
            $mapeo['fechainicio'] = count($primeraFila) >= 4 ? 3 : 2;
        }

        return $mapeo;
    }

    public static function fechaYmd(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '' || $texto === '0') {
            return null;
        }

        if (is_numeric($texto) && ! preg_match('/^\d{8}$/', $texto)) {
            $texto = (string) (int) $texto;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'Ymd', 'dmY', 'd/m/y', 'd-m-y'] as $fmt) {
            $fecha = DateTime::createFromFormat('!' . $fmt, $texto);
            if ($fecha instanceof DateTime && $fecha->format($fmt) === $texto) {
                return $fecha->format('Y-m-d');
            }
        }

        $ts = strtotime($texto);
        if ($ts === false || $ts <= 0) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    public static function formatearTamanio(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', '') . ' KB';
        }

        return number_format($bytes / (1024 * 1024), 1, ',', '') . ' MB';
    }

    /**
     * @param  array<string, mixed>  $resuelto
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function resultadoAnalisis(bool $ok, string $mensaje, array $resuelto, array $extra): array
    {
        return array_merge([
            'ok' => $ok,
            'mensaje' => $mensaje,
            'era_zip' => (bool) ($resuelto['era_zip'] ?? false),
            'extraido' => (bool) ($resuelto['extraido'] ?? false),
            'nombre_origen' => (string) ($resuelto['nombre_origen'] ?? ''),
            'nombre_extraido' => $resuelto['nombre_extraido'] ?? null,
            'tamanio_origen' => (int) ($resuelto['tamanio_origen'] ?? 0),
            'tamanio_datos' => (int) ($resuelto['tamanio_datos'] ?? 0),
            'encoding' => 'UTF-8',
            'delimitador' => ';',
            'lineas_totales' => 0,
            'lineas_datos' => 0,
            'importables_muestra' => 0,
            'omitidas_muestra' => 0,
            'mapeo' => [
                'cuit' => null,
                'nombre' => null,
                'actividad' => null,
                'fechainicio' => null,
                'tiene_cabecera' => false,
            ],
            'columnas' => [],
            'muestra' => [],
            'advertencias' => [],
        ], $extra);
    }

    /**
     * @param  array{cuit: int|null, nombre: int|null, actividad: int|null, fechainicio: int|null, tiene_cabecera: bool}  $mapeo
     * @param  array<int, mixed>  $primeraFila
     * @return list<array{campo: string, indice: int|null, titulo: string}>
     */
    private static function etiquetasColumnas(array $mapeo, array $primeraFila): array
    {
        $out = [];
        foreach (['cuit' => 'CUIT', 'nombre' => 'Nombre', 'actividad' => 'Actividad', 'fechainicio' => 'Fecha inicio'] as $campo => $etiqueta) {
            $idx = $mapeo[$campo] ?? null;
            $titulo = $idx !== null ? trim((string) ($primeraFila[$idx] ?? '')) : '';
            $out[] = [
                'campo' => $etiqueta,
                'indice' => $idx,
                'titulo' => $mapeo['tiene_cabecera'] ? $titulo : ($idx === null ? '' : 'Columna ' . ((int) $idx + 1)),
            ];
        }

        return $out;
    }

    private static function detectarEncoding(string $muestra): string
    {
        if ($muestra === '' || mb_check_encoding($muestra, 'UTF-8')) {
            return 'UTF-8';
        }

        return 'ISO-8859-1';
    }

    private static function aUtf8(string $texto, string $encoding): string
    {
        $texto = rtrim($texto, "\r\n");
        if ($texto === '' || $encoding === 'UTF-8') {
            return $texto;
        }

        $convertido = @iconv($encoding, 'UTF-8//IGNORE', $texto);

        return $convertido !== false ? $convertido : $texto;
    }

    private static function detectarDelimitador(string $linea): string
    {
        $candidatos = [';' => 0, ',' => 0, "\t" => 0, '|' => 0];
        foreach (array_keys($candidatos) as $sep) {
            $candidatos[$sep] = substr_count($linea, $sep);
        }
        arsort($candidatos);
        $mejor = (string) array_key_first($candidatos);

        return ($candidatos[$mejor] ?? 0) > 0 ? $mejor : ';';
    }

    private static function esCuit(string $valor): bool
    {
        $cuit = self::soloDigitos($valor);

        return strlen($cuit) >= 10 && strlen($cuit) <= 13;
    }

    private static function soloDigitos(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
    }

    private static function normalizarEncabezado(string $titulo): string
    {
        $titulo = trim(mb_strtolower($titulo));
        $titulo = strtr($titulo, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $titulo) ?? '';
    }

    /**
     * @param  list<string>  $alias
     */
    private static function encabezadoEs(string $normalizado, array $alias): bool
    {
        return in_array($normalizado, $alias, true);
    }
}
