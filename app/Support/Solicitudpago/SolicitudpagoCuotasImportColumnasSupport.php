<?php

namespace App\Support\Solicitudpago;

use App\Imports\Solicitudpago\SolicitudpagoCuotasImportLecturaCruda;
use App\Support\Contable\AsientoImportColumnasSupport;
use App\Support\Stock\PrecioImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Columnas del Excel/CSV de cuotas SP.
 * Modo título: mapeo automático por alias; el operador puede corregirlo en la vista previa.
 * Modo número: sin fila de títulos; columnas por índice (Columna 1, 2…).
 */
final class SolicitudpagoCuotasImportColumnasSupport
{
    public const MAX_FILAS_BUSQUEDA_ENCABEZADO = 15;

    public const MODO_COLUMNAS_TITULO = 'titulo';

    public const MODO_COLUMNAS_NUMERO = 'numero';

    /** @var array<string, array{label: string, requerido: bool, aliases: list<string>}> */
    private const CAMPOS = [
        'nro_cuota' => [
            'label' => 'Nro cuota',
            'requerido' => false,
            'aliases' => ['nro', 'nro_cuota', 'numero', 'numero_cuota', 'cuota', 'nrocuota', 'n'],
        ],
        'fecha_vencimiento' => [
            'label' => 'Vencimiento',
            'requerido' => true,
            'aliases' => [
                'vto', 'vencimiento', 'fecha_vto', 'fecha_vencimiento', 'fecha', 'fvto', 'vence',
            ],
        ],
        'monto' => [
            'label' => 'Monto',
            'requerido' => true,
            'aliases' => ['monto', 'importe', 'valor', 'importe_cuota', 'monto_cuota'],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function campos(): array
    {
        return array_keys(self::CAMPOS);
    }

    /**
     * @return array<string, array{label: string, requerido: bool}>
     */
    public static function metaCampos(): array
    {
        $out = [];
        foreach (self::CAMPOS as $campo => $meta) {
            $out[$campo] = [
                'label' => $meta['label'],
                'requerido' => $meta['requerido'],
            ];
        }

        return $out;
    }

    public static function normalizarModoColumnas(?string $modo): string
    {
        $modo = strtolower(trim((string) $modo));

        return $modo === self::MODO_COLUMNAS_NUMERO
            ? self::MODO_COLUMNAS_NUMERO
            : self::MODO_COLUMNAS_TITULO;
    }

    public static function esModoNumero(?string $modo): bool
    {
        return self::normalizarModoColumnas($modo) === self::MODO_COLUMNAS_NUMERO;
    }

    /**
     * @param  list<array<int, mixed>>  $filasDatos
     * @return list<array{indice: int, titulo: string}>
     */
    public static function headersSinteticosDesdeFilas(array $filasDatos): array
    {
        $maxCol = 0;
        foreach ($filasDatos as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            foreach (array_keys($fila) as $k) {
                if (is_int($k) || ctype_digit((string) $k)) {
                    $maxCol = max($maxCol, ((int) $k) + 1);
                }
            }
            $maxCol = max($maxCol, count($fila));
        }
        $headers = [];
        for ($i = 0; $i < $maxCol; $i++) {
            $headers[] = [
                'indice' => $i,
                'titulo' => 'Columna '.($i + 1),
            ];
        }

        return $headers;
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<string, int|null>
     */
    public static function resolverMapaDesdeEncabezados(array $headers): array
    {
        $normalizados = [];
        foreach ($headers as $indice => $celda) {
            $nombre = PrecioImportColumnasSupport::normalizarNombreColumna((string) $celda);
            if ($nombre !== '') {
                $normalizados[(int) $indice] = $nombre;
            }
        }

        $pares = [];
        foreach (self::CAMPOS as $campo => $meta) {
            foreach ($meta['aliases'] as $alias) {
                $normal = PrecioImportColumnasSupport::normalizarNombreColumna($alias);
                $pares[] = [$campo, $normal, strlen($normal)];
            }
        }
        usort($pares, static fn (array $a, array $b): int => $b[2] <=> $a[2]);

        $asignado = [];
        $usados = [];
        foreach ($pares as [$campo, $alias]) {
            if (isset($asignado[$campo])) {
                continue;
            }
            foreach ($normalizados as $indice => $nombre) {
                if (isset($usados[$indice]) || $nombre !== $alias) {
                    continue;
                }
                $usados[$indice] = $campo;
                $asignado[$campo] = $indice;
                break;
            }
        }

        // Fallback: alias contenido en el título (ej. "fecha vencimiento cuota").
        foreach (self::CAMPOS as $campo => $meta) {
            if (isset($asignado[$campo])) {
                continue;
            }
            foreach ($normalizados as $indice => $nombre) {
                if (isset($usados[$indice])) {
                    continue;
                }
                foreach ($meta['aliases'] as $alias) {
                    $a = PrecioImportColumnasSupport::normalizarNombreColumna($alias);
                    if ($a !== '' && (str_contains($nombre, $a) || str_contains($a, $nombre))) {
                        $usados[$indice] = $campo;
                        $asignado[$campo] = $indice;
                        break 2;
                    }
                }
            }
        }

        $mapa = [];
        foreach (self::CAMPOS as $campo => $meta) {
            $mapa[$campo] = $asignado[$campo] ?? null;
        }

        return $mapa;
    }

    /**
     * @param  UploadedFile|string  $archivo
     */
    public static function detectarFilaEncabezado(
        UploadedFile|string $archivo,
        ?int $filaIndicada = null,
        int $hojaIndice = 0,
        string $delimiter = ';'
    ): int {
        if ($filaIndicada !== null && $filaIndicada >= 1 && $filaIndicada <= 50) {
            return $filaIndicada;
        }

        $hoja = Excel::toArray(new SolicitudpagoCuotasImportLecturaCruda($delimiter), $archivo)[$hojaIndice] ?? [];
        $limite = min(self::MAX_FILAS_BUSQUEDA_ENCABEZADO, count($hoja));

        for ($i = 0; $i < $limite; $i++) {
            $fila = $hoja[$i] ?? [];
            if (! is_array($fila)) {
                continue;
            }
            if (self::pareceFilaEncabezado($fila)) {
                return $i + 1;
            }
        }

        return 1;
    }

    /**
     * @param  array<int, mixed>  $primeraFila
     */
    public static function pareceFilaEncabezado(array $primeraFila): bool
    {
        $mapa = self::resolverMapaDesdeEncabezados($primeraFila);

        return ($mapa['fecha_vencimiento'] ?? null) !== null
            && ($mapa['monto'] ?? null) !== null;
    }

    /**
     * @param  array<string, int|null>  $auto
     * @param  array<string, mixed>  $override
     * @param  array<int, mixed>  $headersRaw
     * @return array<string, int|null>
     */
    public static function fusionarMapa(
        array $auto,
        array $override,
        bool $porNumero = false,
        array $headersRaw = []
    ): array {
        $mapa = $auto;
        foreach (self::campos() as $campo) {
            if (! array_key_exists($campo, $override)) {
                continue;
            }
            $valor = $override[$campo];
            if ($valor === null || trim((string) $valor) === '') {
                continue;
            }
            $indice = self::resolverValorMapeoAIndice((string) $valor, $porNumero, $headersRaw);
            if ($indice !== null) {
                $mapa[$campo] = $indice;
            }
        }

        return $mapa;
    }

    /**
     * @param  array<int, mixed>  $headersRaw
     */
    public static function resolverValorMapeoAIndice(string $valor, bool $porNumero, array $headersRaw = []): ?int
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if ($porNumero || AsientoImportColumnasSupport::esReferenciaNumerica($valor)) {
            return AsientoImportColumnasSupport::indiceDesdeReferencia($valor);
        }

        $buscado = PrecioImportColumnasSupport::normalizarNombreColumna($valor);
        foreach ($headersRaw as $indice => $celda) {
            $titulo = PrecioImportColumnasSupport::normalizarNombreColumna((string) $celda);
            if ($titulo !== '' && $titulo === $buscado) {
                return (int) $indice;
            }
        }

        return null;
    }

    /**
     * @param  array<string, int|null>  $mapa
     * @param  list<array{indice: int, titulo: string}>  $headers
     * @return array<string, string>
     */
    public static function mappingParaUi(array $mapa, array $headers, bool $porNumero): array
    {
        $porIndice = [];
        foreach ($headers as $h) {
            $porIndice[(int) $h['indice']] = (string) ($h['titulo'] ?? '');
        }

        $ui = [];
        foreach (self::campos() as $campo) {
            $indice = $mapa[$campo] ?? null;
            if ($indice === null) {
                $ui[$campo] = '';

                continue;
            }
            $indice = (int) $indice;
            if ($porNumero) {
                $ui[$campo] = (string) ($indice + 1);
            } else {
                $titulo = trim($porIndice[$indice] ?? '');
                $ui[$campo] = $titulo !== '' ? $titulo : (string) ($indice + 1);
            }
        }

        return $ui;
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array<string, int|null>  $mapa
     */
    public static function valorCampo(array $fila, array $mapa, string $campo): mixed
    {
        $indice = $mapa[$campo] ?? null;
        if ($indice === null) {
            return null;
        }

        return $fila[$indice] ?? null;
    }

    public static function parseFecha(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        if ($raw instanceof \DateTimeInterface) {
            return $raw->format('Y-m-d');
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw);

                return $dt->format('Y-m-d');
            } catch (\Throwable) {
                // fall through
            }
        }
        $raw = str_replace('/', '-', $raw);
        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function parseMonto(mixed $raw): float
    {
        if ($raw === null) {
            return 0.0;
        }
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        $raw = trim(Str::of((string) $raw)->replace(['$', ' '], '')->__toString());
        if ($raw === '') {
            return 0.0;
        }
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        return (float) $raw;
    }

    /**
     * Compat: lectura simple (auto) para el endpoint legacy.
     *
     * @return list<array{nro_cuota: int, fecha_vencimiento: string, monto: float}>
     */
    public static function leerFilas(UploadedFile|string $archivo): array
    {
        $path = $archivo instanceof UploadedFile
            ? ($archivo->getRealPath() ?: $archivo->path())
            : $archivo;
        if ($path === false || $path === '') {
            return [];
        }

        $delimiter = AsientoImportColumnasSupport::detectarSeparadorCsv($archivo);
        $sheets = Excel::toArray(new SolicitudpagoCuotasImportLecturaCruda($delimiter), $path);
        $matriz = $sheets[0] ?? [];
        if ($matriz === []) {
            return [];
        }

        $headerRowIdx = null;
        $limite = min(count($matriz), self::MAX_FILAS_BUSQUEDA_ENCABEZADO);
        for ($i = 0; $i < $limite; $i++) {
            if (self::pareceFilaEncabezado($matriz[$i] ?? [])) {
                $headerRowIdx = $i;
                break;
            }
        }
        if ($headerRowIdx === null) {
            return [];
        }

        $mapa = self::resolverMapaDesdeEncabezados($matriz[$headerRowIdx]);
        if (($mapa['fecha_vencimiento'] ?? null) === null || ($mapa['monto'] ?? null) === null) {
            throw new \RuntimeException('El Excel debe tener columnas de vencimiento y monto (acepta alias).');
        }

        $filas = [];
        $nroAuto = 1;
        for ($i = $headerRowIdx + 1; $i < count($matriz); $i++) {
            $row = $matriz[$i] ?? [];
            if (! is_array($row)) {
                continue;
            }
            $fecha = self::parseFecha(self::valorCampo($row, $mapa, 'fecha_vencimiento'));
            $monto = self::parseMonto(self::valorCampo($row, $mapa, 'monto'));
            if ($fecha === null || $monto == 0.0) {
                continue;
            }
            $nroRaw = self::valorCampo($row, $mapa, 'nro_cuota');
            $nro = (int) $nroRaw;
            if ($nro <= 0) {
                $nro = $nroAuto;
            }
            $nroAuto = max($nroAuto, $nro) + 1;
            $filas[] = [
                'nro_cuota' => $nro,
                'fecha_vencimiento' => $fecha,
                'monto' => $monto,
            ];
        }

        return $filas;
    }
}
