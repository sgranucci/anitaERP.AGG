<?php

namespace App\Support\Sueldos;

use App\Imports\Sueldos\NovedadSueldosImportLecturaCruda;
use App\Support\Contable\AsientoImportColumnasSupport;
use App\Support\Stock\PrecioImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Columnas del Excel/CSV de novedades de liquidación.
 * Modo título: mapeo automático por alias; el operador puede corregirlo en la vista previa.
 * Modo número: sin fila de títulos; columnas por índice (Columna 1, 2…).
 */
final class NovedadSueldosImportColumnasSupport
{
    public const MAX_FILAS_BUSQUEDA_ENCABEZADO = 15;

    public const MODO_COLUMNAS_TITULO = 'titulo';

    public const MODO_COLUMNAS_NUMERO = 'numero';

    /** @var array<string, array{label: string, requerido: bool, aliases: list<string>}> */
    private const CAMPOS = [
        'empresa' => [
            'label' => 'Empresa',
            'requerido' => false,
            'aliases' => ['empresa_id', 'empresa', 'codigo_empresa', 'cod_empresa', 'id_empresa'],
        ],
        'legajo' => [
            'label' => 'Legajo',
            'requerido' => true,
            'aliases' => ['legajo', 'nro_legajo', 'numero_legajo', 'nrolegajo'],
        ],
        'concepto_codigo' => [
            'label' => 'Código concepto',
            'requerido' => true,
            'aliases' => ['concepto_codigo', 'codigo_concepto', 'cod_concepto', 'concepto'],
        ],
        'liquidacion_numero' => [
            'label' => 'N° liquidación',
            'requerido' => false,
            'aliases' => ['liquidacion_numero', 'liquidacion', 'nro_liquidacion', 'numero_liquidacion', 'corrida'],
        ],
        'valor1' => [
            'label' => 'Valor 1',
            'requerido' => false,
            'aliases' => ['valor1', 'valor_1', 'importe', 'cantidad', 'unidades', 'horas'],
        ],
        'valor2' => [
            'label' => 'Valor 2',
            'requerido' => false,
            'aliases' => ['valor2', 'valor_2', 'importe_2', 'precio', 'valor_unitario'],
        ],
        'estado' => [
            'label' => 'Estado',
            'requerido' => false,
            'aliases' => ['estado'],
        ],
        'fecha_vto' => [
            'label' => 'Fecha vto.',
            'requerido' => false,
            'aliases' => ['fecha_vto', 'vencimiento', 'fecha_vencimiento', 'vto'],
        ],
        'fecha_desde' => [
            'label' => 'Fecha desde',
            'requerido' => false,
            'aliases' => ['fecha_desde', 'vigencia_desde', 'desde'],
        ],
        'fecha_hasta' => [
            'label' => 'Fecha hasta',
            'requerido' => false,
            'aliases' => ['fecha_hasta', 'vigencia_hasta', 'hasta'],
        ],
        'nro_interno' => [
            'label' => 'Nro. interno',
            'requerido' => false,
            'aliases' => ['nro_interno', 'numero_interno', 'interno'],
        ],
        'periodo' => [
            'label' => 'Período',
            'requerido' => false,
            'aliases' => ['periodo', 'anio_mes', 'aaaamm'],
        ],
        'observacion' => [
            'label' => 'Observación',
            'requerido' => false,
            'aliases' => ['observacion', 'observaciones', 'obs', 'comentario', 'detalle'],
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
     * Encabezados sintéticos para modo sin título (índice 0-based).
     *
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

        $hoja = Excel::toArray(new NovedadSueldosImportLecturaCruda($delimiter), $archivo)[$hojaIndice] ?? [];
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

        return ($mapa['legajo'] ?? null) !== null
            && (($mapa['concepto_codigo'] ?? null) !== null || ($mapa['valor1'] ?? null) !== null);
    }

    /**
     * Mezcla el mapa automático con lo que tipó el operador.
     * Vacío = conservar auto (no pisar el resto de columnas).
     * En modo número: 1, 2 o A, B (1-based / letra Excel).
     * En modo título: nombre de encabezado o número/letra.
     *
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
                // Conserva el automático; no limpia el resto al tipar un solo campo.
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
     * Valores para los inputs del formulario (número 1-based o título).
     *
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
}
