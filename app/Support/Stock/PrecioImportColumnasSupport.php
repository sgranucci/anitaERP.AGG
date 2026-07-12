<?php

namespace App\Support\Stock;

use App\Imports\Stock\PrecioImportLecturaCruda;
use App\Models\Stock\Listaprecio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Nombres de columnas del Excel de importación de precios.
 */
final class PrecioImportColumnasSupport
{
    public const FORMATO_SIMPLE = 'simple';

    public const FORMATO_LISTAS = 'listas';

    public const MAX_FILAS_BUSQUEDA_ENCABEZADO = 15;

    public const COL_SKU_DEFAULT = 'sku';

    public const COL_DESCRIPCION_DEFAULT = 'descripcion';

    public const COL_PRECIO_DEFAULT = 'precio';

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_SKU = [
        'sku',
        'plu',
        'codigo',
        'codigo_articulo',
        'codigo_plu',
        'articulo',
        'art',
        'item',
        'referencia',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_DESCRIPCION = [
        'descripcion',
        'detalle',
        'nombre',
        'nombre_producto',
        'nombre_del_producto',
        'producto',
        'articulo_descripcion',
        'denominacion',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_PRECIO = [
        'precio',
        'importe',
        'valor',
        'precio_venta',
        'pvp',
        'costo',
    ];

    /** @var list<string> */
    private const STOPWORDS_ENCABEZADO = [
        'o', 'y', 'e', 'de', 'del', 'la', 'las', 'el', 'los', 'en', 'a', 'al', 'un', 'una',
    ];

    /** Umbral mínimo de puntaje para aceptar coincidencia flexible de encabezado. */
    private const PUNTAJE_MINIMO_COINCIDENCIA_FLEXIBLE = 60;

    public static function normalizarNombreColumna(string $nombre): string
    {
        $nombre = trim(Str::ascii($nombre));
        $nombre = preg_replace('/[\/\\\\|:;,.]+/', '_', $nombre) ?? $nombre;
        $nombre = str_replace([' ', '-'], '_', $nombre);
        $nombre = preg_replace('/_+/', '_', $nombre) ?? $nombre;

        return trim(strtolower($nombre), '_');
    }

    /**
     * @param  UploadedFile|string  $archivo
     * @return list<string>
     */
    public static function listarNombresHojas(UploadedFile|string $archivo): array
    {
        $path = $archivo instanceof UploadedFile
            ? ($archivo->getRealPath() ?: $archivo->path())
            : $archivo;

        if ($path === false || $path === '') {
            return ['Hoja1'];
        }

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            if (method_exists($reader, 'listWorksheetNames')) {
                $nombres = $reader->listWorksheetNames($path);

                return $nombres !== [] ? array_values($nombres) : ['Hoja1'];
            }
        } catch (\Throwable $e) {
            // CSV u otro formato sin hojas nombradas.
        }

        return ['Hoja1'];
    }

    /**
     * Convierte número de hoja 1-based del formulario a índice 0-based acotado.
     */
    public static function indiceHojaDesdeRequest(?int $hojaIndice1Based, int $cantidadHojas): int
    {
        if ($cantidadHojas <= 0) {
            return 0;
        }

        $indice = ($hojaIndice1Based ?? 1) - 1;

        return max(0, min($cantidadHojas - 1, $indice));
    }

    /**
     * @param  UploadedFile|string  $archivo
     * @return list<array{indice: int, nombre: string}>
     */
    public static function hojasParaSelector(UploadedFile|string $archivo): array
    {
        $hojas = [];
        foreach (self::listarNombresHojas($archivo) as $i => $nombre) {
            $hojas[] = [
                'indice' => $i + 1,
                'nombre' => (string) $nombre,
            ];
        }

        return $hojas;
    }

    /**
     * Busca la fila de encabezados (1-based). Si hay título o filas vacías arriba, salta hasta encontrar sku/precio/etc.
     *
     * @param  UploadedFile|string  $archivo
     */
    public static function detectarFilaEncabezado(
        UploadedFile|string $archivo,
        ?int $filaIndicada = null,
        int $hojaIndice = 0
    ): int {
        if ($filaIndicada !== null && $filaIndicada >= 1 && $filaIndicada <= 50) {
            return $filaIndicada;
        }

        $hoja = Excel::toArray(new PrecioImportLecturaCruda(), $archivo)[$hojaIndice] ?? [];
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
     * Detecta si una fila del archivo parece encabezado (no datos).
     *
     * @param  array<int, mixed>  $primeraFila
     */
    public static function pareceFilaEncabezado(array $primeraFila): bool
    {
        $celdas = array_values(array_filter(array_map(
            static fn ($v) => self::normalizarNombreColumna((string) $v),
            $primeraFila
        ), static fn ($v) => $v !== ''));

        if ($celdas === []) {
            return false;
        }

        foreach ($celdas as $celda) {
            if (self::coincideGrupoEncabezado($celda, self::ALIAS_ENCABEZADO_SKU)
                || self::coincideGrupoEncabezado($celda, self::ALIAS_ENCABEZADO_DESCRIPCION)
                || self::coincideGrupoEncabezado($celda, self::ALIAS_ENCABEZADO_PRECIO)
                || str_starts_with($celda, 'l_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $celdasEncabezado
     * @param  list<string>  $alias
     * @return array{indice: int, titulo: string, clave_normalizada: string}|null
     */
    public static function resolverColumnaEnEncabezados(
        array $celdasEncabezado,
        string $nombreConfigurado,
        string $default,
        array $alias
    ): ?array {
        $terminos = self::nombresColumnaBusqueda($nombreConfigurado, $default, $alias);
        $mejor = null;
        $mejorPuntaje = 0;

        foreach ($celdasEncabezado as $indice => $celda) {
            $titulo = trim((string) $celda);
            if ($titulo === '') {
                continue;
            }

            $normalizado = self::normalizarNombreColumna($titulo);
            $puntaje = self::puntajeCoincidenciaEncabezado($normalizado, $terminos);

            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejor = [
                    'indice' => (int) $indice,
                    'titulo' => $titulo,
                    'clave_normalizada' => $normalizado,
                ];
            }
        }

        if ($mejor === null) {
            return null;
        }

        $minimo = self::normalizarNombreColumna($nombreConfigurado) === self::normalizarNombreColumna($default)
            ? self::PUNTAJE_MINIMO_COINCIDENCIA_FLEXIBLE
            : 100;

        return $mejorPuntaje >= $minimo ? $mejor : null;
    }

    /**
     * @param  array<int, mixed>  $celdasEncabezado
     * @return list<array{indice: int, titulo: string, codigo_lista: string, listaprecio_id: ?int, listaprecio_nombre: ?string}>
     */
    public static function columnasListasEnEncabezados(array $celdasEncabezado): array
    {
        $columnas = [];
        $codigos = [];

        foreach ($celdasEncabezado as $indice => $celda) {
            $titulo = trim((string) $celda);
            if ($titulo === '') {
                continue;
            }

            $prefijo = substr($titulo, 0, 2);
            if ($prefijo !== 'L_' && $prefijo !== 'l_') {
                continue;
            }

            $codigoLista = str_replace($prefijo, '', $titulo);
            $columnas[] = [
                'indice' => (int) $indice,
                'titulo' => $titulo,
                'codigo_lista' => $codigoLista,
                'listaprecio_id' => null,
                'listaprecio_nombre' => null,
            ];
            $codigos[] = $codigoLista;
        }

        if ($columnas === []) {
            return [];
        }

        $listas = Listaprecio::query()
            ->select('id', 'codigo', 'nombre')
            ->whereIn('codigo', array_values(array_unique($codigos)))
            ->get()
            ->keyBy('codigo');

        foreach ($columnas as &$columna) {
            $lista = $listas->get($columna['codigo_lista']);
            if ($lista) {
                $columna['listaprecio_id'] = (int) $lista->id;
                $columna['listaprecio_nombre'] = (string) $lista->nombre;
            }
        }
        unset($columna);

        return $columnas;
    }

    public static function normalizarValorPrecio(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $texto = preg_replace('/[^\d,.-]/', '', (string) $valor);
        if ($texto === '' || $texto === null) {
            return null;
        }

        return (float) str_replace(',', '.', $texto);
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $columna
     */
    public static function valorCeldaFila(array $fila, ?array $columna): mixed
    {
        if ($columna === null) {
            return null;
        }

        return $fila[$columna['indice']] ?? null;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function valorColumna(array $fila, string $nombreConfigurado): mixed
    {
        foreach (self::nombresColumnaBusqueda($nombreConfigurado, '', []) as $clave) {
            if (array_key_exists($clave, $fila)) {
                return $fila[$clave];
            }

            foreach (array_keys($fila) as $key) {
                if (self::normalizarNombreColumna((string) $key) === $clave) {
                    return $fila[$key];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function valorColumnaSku(array $fila, string $nombreConfigurado): mixed
    {
        return self::valorColumnaPorCampo($fila, $nombreConfigurado, self::COL_SKU_DEFAULT, self::ALIAS_ENCABEZADO_SKU);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function valorColumnaDescripcion(array $fila, string $nombreConfigurado): mixed
    {
        return self::valorColumnaPorCampo($fila, $nombreConfigurado, self::COL_DESCRIPCION_DEFAULT, self::ALIAS_ENCABEZADO_DESCRIPCION);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function valorColumnaPrecio(array $fila, string $nombreConfigurado): mixed
    {
        return self::valorColumnaPorCampo($fila, $nombreConfigurado, self::COL_PRECIO_DEFAULT, self::ALIAS_ENCABEZADO_PRECIO);
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  list<string>  $alias
     */
    private static function valorColumnaPorCampo(array $fila, string $nombreConfigurado, string $default, array $alias): mixed
    {
        $terminos = self::nombresColumnaBusqueda($nombreConfigurado, $default, $alias);

        foreach ($terminos as $clave) {
            if (array_key_exists($clave, $fila)) {
                return $fila[$clave];
            }
        }

        $mejorClave = null;
        $mejorPuntaje = 0;

        foreach (array_keys($fila) as $key) {
            $keyNorm = self::normalizarNombreColumna((string) $key);
            $puntaje = self::puntajeCoincidenciaEncabezado($keyNorm, $terminos);
            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejorClave = $key;
            }
        }

        $minimo = self::normalizarNombreColumna($nombreConfigurado) === self::normalizarNombreColumna($default)
            ? self::PUNTAJE_MINIMO_COINCIDENCIA_FLEXIBLE
            : 100;

        if ($mejorClave !== null && $mejorPuntaje >= $minimo) {
            return $fila[$mejorClave];
        }

        return null;
    }

    /**
     * Si el nombre configurado es el default, prueba también alias habituales del Excel.
     *
     * @param  list<string>  $alias
     * @return list<string>
     */
    public static function nombresColumnaBusqueda(string $nombreConfigurado, string $default, array $alias): array
    {
        $nombres = [self::normalizarNombreColumna($nombreConfigurado)];

        if ($default !== '' && self::normalizarNombreColumna($nombreConfigurado) === self::normalizarNombreColumna($default)) {
            foreach ($alias as $nombreAlias) {
                $nombres[] = self::normalizarNombreColumna($nombreAlias);
            }
        }

        return array_values(array_unique(array_filter($nombres)));
    }

    /**
     * @param  list<string>  $alias
     */
    private static function coincideGrupoEncabezado(string $encabezadoNormalizado, array $alias): bool
    {
        return self::puntajeCoincidenciaEncabezado($encabezadoNormalizado, $alias) >= self::PUNTAJE_MINIMO_COINCIDENCIA_FLEXIBLE;
    }

    /**
     * @param  list<string>  $terminosBusqueda
     */
    private static function puntajeCoincidenciaEncabezado(string $encabezadoNorm, array $terminosBusqueda): int
    {
        if ($encabezadoNorm === '') {
            return 0;
        }

        $tokensEncabezado = self::tokensEncabezado($encabezadoNorm);
        $mejor = 0;

        foreach ($terminosBusqueda as $termino) {
            if ($termino === '') {
                continue;
            }

            if ($encabezadoNorm === $termino) {
                $mejor = max($mejor, 100);

                continue;
            }

            $tokensTermino = self::tokensEncabezado($termino);

            if ($tokensTermino !== [] && $tokensTermino === $tokensEncabezado) {
                $mejor = max($mejor, 95);

                continue;
            }

            foreach ($tokensTermino as $tokenTermino) {
                if (in_array($tokenTermino, $tokensEncabezado, true)) {
                    $mejor = max($mejor, strlen($tokenTermino) >= 4 ? 85 : 80);
                }
            }

            if ($tokensTermino !== []
                && count(array_intersect($tokensTermino, $tokensEncabezado)) === count($tokensTermino)) {
                $mejor = max($mejor, 75);
            }

            if (strlen($termino) >= 4 && preg_match('/(?:^|_)'.preg_quote($termino, '/').'(?:_|$)/', $encabezadoNorm)) {
                $mejor = max($mejor, 70);
            }

            if (strlen($termino) >= 3 && in_array($termino, $tokensEncabezado, true)) {
                $mejor = max($mejor, 80);
            }
        }

        return $mejor;
    }

    /**
     * @return list<string>
     */
    private static function tokensEncabezado(string $tituloNormalizado): array
    {
        $partes = explode('_', $tituloNormalizado);

        return array_values(array_filter(
            $partes,
            static fn (string $token): bool => $token !== ''
                && strlen($token) >= 2
                && ! in_array($token, self::STOPWORDS_ENCABEZADO, true)
        ));
    }

    /**
     * @param  list<string>  $alias
     */
    private static function coincideAlias(string $valor, array $alias): bool
    {
        return in_array($valor, $alias, true);
    }
}
