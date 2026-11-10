<?php

namespace App\Support\Contable;

use App\Imports\Contable\AsientoImportLecturaCruda;
use App\Support\Archivo\TextoUtf8Support;
use App\Support\Stock\PrecioImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Nombres / índices de columnas del Excel/CSV de importación de asientos contables.
 */
final class AsientoImportColumnasSupport
{
    public const MAX_FILAS_BUSQUEDA_ENCABEZADO = 15;

    public const MODO_COLUMNAS_TITULO = 'titulo';

    public const MODO_COLUMNAS_NUMERO = 'numero';

    public const MODO_IMPORTE_DEBE_HABER = 'debe_haber';

    public const MODO_IMPORTE_MONTO_DH = 'monto_dh';

    public const SEPARADOR_CSV_PUNTO_Y_COMA = ';';

    public const SEPARADOR_CSV_COMA = ',';

    public const SEPARADOR_CSV_TAB = "\t";

    public const SEPARADOR_CSV_PIPE = '|';

    public const SEPARADOR_CSV_AUTO = 'auto';

    public const SEPARADOR_CSV_DEFAULT = self::SEPARADOR_CSV_AUTO;

    /** @var list<string> */
    public const SEPARADORES_CSV = [
        self::SEPARADOR_CSV_PUNTO_Y_COMA,
        self::SEPARADOR_CSV_COMA,
        self::SEPARADOR_CSV_TAB,
        self::SEPARADOR_CSV_PIPE,
    ];

    public const COL_CUENTA_DEFAULT = 'cuenta';

    public const COL_DEBE_DEFAULT = 'debe';

    public const COL_HABER_DEFAULT = 'haber';

    public const COL_MONTO_DEFAULT = 'monto';

    public const COL_DH_DEFAULT = 'dh';

    public const COL_CENTROCOSTO_DEFAULT = 'centrocosto';

    public const COL_MONEDA_DEFAULT = 'moneda';

    public const COL_COTIZACION_DEFAULT = 'cotizacion';

    public const COL_DETALLE_DEFAULT = 'detalle';

    /** Defaults 1-based cuando el archivo no tiene fila de títulos. */
    public const COL_CUENTA_NUMERO_DEFAULT = '1';

    public const COL_DEBE_NUMERO_DEFAULT = '2';

    public const COL_HABER_NUMERO_DEFAULT = '3';

    public const COL_MONTO_NUMERO_DEFAULT = '2';

    public const COL_DH_NUMERO_DEFAULT = '3';

    /** Opcionales en modo número: vacío = no se usa esa columna. */
    public const COL_CENTROCOSTO_NUMERO_DEFAULT = '';

    public const COL_MONEDA_NUMERO_DEFAULT = '';

    public const COL_COTIZACION_NUMERO_DEFAULT = '';

    public const COL_DETALLE_NUMERO_DEFAULT = '';

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_CUENTA = [
        'cuenta',
        'cuenta_contable',
        'codigo_cuenta',
        'cod_cuenta',
        'cuentacontable',
        'cta',
        'cta_contable',
        'codigo',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_DEBE = [
        'debe',
        'debito',
        'deb',
        'importe_debe',
        'monto_debe',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_HABER = [
        'haber',
        'credito',
        'hab',
        'importe_haber',
        'monto_haber',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_CENTROCOSTO = [
        'centrocosto',
        'centro_costo',
        'centro_de_costo',
        'cc',
        'ccos',
        'ccosto',
        'c_costo',
        'codigo_cc',
        'cod_cc',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_MONEDA = [
        'moneda',
        'mon',
        'currency',
        'divisa',
        'cod_moneda',
        'codigo_moneda',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_COTIZACION = [
        'cotizacion',
        'cotización',
        'tipo_cambio',
        'tc',
        'cambio',
        'cotiz',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_DETALLE = [
        'detalle',
        'observacion',
        'observación',
        'concepto',
        'descripcion',
        'descripción',
        'glosa',
        'leyenda',
        'comentario',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_MONTO = [
        'monto',
        'importe',
        'amount',
        'valor',
        'importe_asiento',
        'monto_asiento',
    ];

    /** @var list<string> */
    public const ALIAS_ENCABEZADO_DH = [
        'dh',
        'd_h',
        'debe_haber',
        'debito_credito',
        'tipo',
        'lado',
        'signo',
        'dh_mov',
        'tipo_mov',
    ];

    public static function normalizarNombreColumna(string $nombre): string
    {
        return PrecioImportColumnasSupport::normalizarNombreColumna($nombre);
    }

    public static function normalizarModoColumnas(?string $modo): string
    {
        $modo = strtolower(trim((string) $modo));

        return $modo === self::MODO_COLUMNAS_NUMERO
            ? self::MODO_COLUMNAS_NUMERO
            : self::MODO_COLUMNAS_TITULO;
    }

    public static function normalizarModoImporte(?string $modo): string
    {
        $modo = strtolower(trim((string) $modo));

        return $modo === self::MODO_IMPORTE_MONTO_DH
            ? self::MODO_IMPORTE_MONTO_DH
            : self::MODO_IMPORTE_DEBE_HABER;
    }

    /**
     * Valores de formulario: `auto` `;` `,` `|` `tab` `\t`.
     * Devuelve el delimitador real o `auto` si hay que detectar.
     */
    public static function normalizarSeparadorCsv(?string $separador): string
    {
        $valor = trim((string) $separador);
        if ($valor === '' || strtolower($valor) === self::SEPARADOR_CSV_AUTO) {
            return self::SEPARADOR_CSV_AUTO;
        }
        if ($valor === 'tab' || $valor === '\\t') {
            return self::SEPARADOR_CSV_TAB;
        }

        if (in_array($valor, self::SEPARADORES_CSV, true)) {
            return $valor;
        }

        return self::SEPARADOR_CSV_AUTO;
    }

    public static function etiquetaSeparadorCsv(string $separador): string
    {
        return match ($separador) {
            self::SEPARADOR_CSV_AUTO => 'Automático',
            self::SEPARADOR_CSV_COMA => 'Coma (,)',
            self::SEPARADOR_CSV_TAB => 'Tabulación',
            self::SEPARADOR_CSV_PIPE => 'Pipe (|)',
            self::SEPARADOR_CSV_PUNTO_Y_COMA => 'Punto y coma (;)',
            default => 'Punto y coma (;)',
        };
    }

    public static function valorFormularioSeparadorCsv(string $separador): string
    {
        if ($separador === self::SEPARADOR_CSV_TAB) {
            return 'tab';
        }
        if ($separador === self::SEPARADOR_CSV_AUTO || $separador === '') {
            return self::SEPARADOR_CSV_AUTO;
        }

        return $separador;
    }

    /**
     * Detecta `;` `,` tab o `|` mirando las primeras líneas del CSV.
     */
    public static function detectarSeparadorCsv(UploadedFile|string $archivo): string
    {
        $path = $archivo instanceof UploadedFile
            ? ($archivo->getRealPath() ?: $archivo->path())
            : $archivo;
        if ($path === false || $path === '' || ! is_file($path)) {
            return self::SEPARADOR_CSV_PUNTO_Y_COMA;
        }

        $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        $nombre = $archivo instanceof UploadedFile
            ? strtolower((string) $archivo->getClientOriginalExtension())
            : $ext;
        if (! in_array($nombre !== '' ? $nombre : $ext, ['csv', 'txt'], true)) {
            return self::SEPARADOR_CSV_PUNTO_Y_COMA;
        }

        $raw = @file_get_contents($path, false, null, 0, 16384);
        if (! is_string($raw) || $raw === '') {
            return self::SEPARADOR_CSV_PUNTO_Y_COMA;
        }

        $texto = TextoUtf8Support::normalizar($raw);
        $lineas = preg_split("/\r\n|\n|\r/", $texto) ?: [];
        $muestra = [];
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }
            $muestra[] = $linea;
            if (count($muestra) >= 8) {
                break;
            }
        }
        if ($muestra === []) {
            return self::SEPARADOR_CSV_PUNTO_Y_COMA;
        }

        $candidatos = [
            self::SEPARADOR_CSV_PUNTO_Y_COMA => 0,
            self::SEPARADOR_CSV_COMA => 0,
            self::SEPARADOR_CSV_TAB => 0,
            self::SEPARADOR_CSV_PIPE => 0,
        ];
        foreach ($muestra as $linea) {
            foreach ($candidatos as $sep => $_) {
                $cols = str_getcsv($linea, $sep);
                $n = count(array_filter($cols, static fn ($c) => trim((string) $c) !== ''));
                if ($n >= 2) {
                    $candidatos[$sep] += $n;
                }
            }
        }

        arsort($candidatos);
        $mejor = array_key_first($candidatos);

        return ($mejor !== null && $candidatos[$mejor] > 0)
            ? $mejor
            : self::SEPARADOR_CSV_PUNTO_Y_COMA;
    }

    /**
     * Resuelve el delimitador efectivo (auto → detectado).
     *
     * @return array{delimiter: string, automatico: bool}
     */
    public static function resolverSeparadorCsv(UploadedFile|string $archivo, ?string $separadorCsv = null): array
    {
        $normalizado = self::normalizarSeparadorCsv($separadorCsv);
        if ($normalizado !== self::SEPARADOR_CSV_AUTO) {
            return ['delimiter' => $normalizado, 'automatico' => false];
        }

        return [
            'delimiter' => self::detectarSeparadorCsv($archivo),
            'automatico' => true,
        ];
    }

    /**
     * @param  UploadedFile|string  $archivo
     * @return list<array<int, mixed>>
     */
    public static function leerHojas(UploadedFile|string $archivo, ?string $separadorCsv = null): array
    {
        $resuelto = self::resolverSeparadorCsv($archivo, $separadorCsv);

        return Excel::toArray(new AsientoImportLecturaCruda($resuelto['delimiter']), $archivo);
    }

    /**
     * Número 1-based o letra Excel (A, B, …, AA).
     */
    public static function esReferenciaNumerica(string $valor): bool
    {
        $valor = trim($valor);
        if ($valor === '') {
            return false;
        }

        if (ctype_digit($valor) && (int) $valor >= 1) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]{1,3}$/', $valor);
    }

    /**
     * @return int|null índice 0-based
     */
    public static function indiceDesdeReferencia(string $valor): ?int
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if (ctype_digit($valor)) {
            $n = (int) $valor;
            if ($n < 1 || $n > 200) {
                return null;
            }

            return $n - 1;
        }

        if (! preg_match('/^[A-Za-z]{1,3}$/', $valor)) {
            return null;
        }

        $letras = strtoupper($valor);
        $indice = 0;
        $len = strlen($letras);
        for ($i = 0; $i < $len; $i++) {
            $indice = ($indice * 26) + (ord($letras[$i]) - 64);
        }

        if ($indice < 1 || $indice > 200) {
            return null;
        }

        return $indice - 1;
    }

    /**
     * @return array{indice: int, titulo: string, clave_normalizada: string}
     */
    public static function columnaPorIndice(int $indice0Based): array
    {
        $n = $indice0Based + 1;

        return [
            'indice' => $indice0Based,
            'titulo' => 'Columna '.$n,
            'clave_normalizada' => (string) $n,
        ];
    }

    /**
     * @param  array<int, mixed>  $celdasEncabezado
     * @param  list<string>  $alias
     * @return array{indice: int, titulo: string, clave_normalizada: string}|null
     */
    public static function resolverColumnaModo(
        string $modoColumnas,
        array $celdasEncabezado,
        string $nombreConfigurado,
        string $defaultTitulo,
        array $alias
    ): ?array {
        $configurado = trim($nombreConfigurado);
        $default = trim($defaultTitulo);
        if ($configurado === '') {
            $configurado = $default;
        }
        if ($configurado === '') {
            return null;
        }

        if ($modoColumnas === self::MODO_COLUMNAS_NUMERO || self::esReferenciaNumerica($configurado)) {
            $indice = self::indiceDesdeReferencia($configurado);
            if ($indice === null) {
                return null;
            }

            return self::columnaPorIndice($indice);
        }

        return self::resolverColumna(
            $celdasEncabezado,
            $configurado,
            $default !== '' ? $default : $configurado,
            $alias
        );
    }

    /**
     * Normaliza D / H (y variantes) a 'D' o 'H'.
     */
    public static function normalizarDh(mixed $valor): ?string
    {
        $texto = self::normalizarTextoCelda($valor);
        if ($texto === '') {
            return null;
        }

        $clave = self::normalizarNombreColumna($texto);
        if (in_array($clave, ['d', 'debe', 'debito', 'deb', 'dr', 'debit'], true)) {
            return 'D';
        }
        if (in_array($clave, ['h', 'haber', 'credito', 'hab', 'cr', 'credit', 'c'], true)) {
            return 'H';
        }

        $primera = mb_strtoupper(mb_substr(trim($texto), 0, 1));
        if ($primera === 'D') {
            return 'D';
        }
        if ($primera === 'H' || $primera === 'C') {
            return 'H';
        }

        return null;
    }

    /**
     * @param  UploadedFile|string  $archivo
     * @return list<array{indice: int, nombre: string}>
     */
    public static function hojasParaSelector(UploadedFile|string $archivo): array
    {
        return PrecioImportColumnasSupport::hojasParaSelector($archivo);
    }

    public static function indiceHojaDesdeRequest(?int $hojaIndice1Based, int $cantidadHojas): int
    {
        return PrecioImportColumnasSupport::indiceHojaDesdeRequest($hojaIndice1Based, $cantidadHojas);
    }

    /**
     * @param  UploadedFile|string  $archivo
     */
    public static function detectarFilaEncabezado(
        UploadedFile|string $archivo,
        ?int $filaIndicada = null,
        int $hojaIndice = 0,
        ?string $separadorCsv = null
    ): int {
        if ($filaIndicada !== null && $filaIndicada >= 1 && $filaIndicada <= 50) {
            return $filaIndicada;
        }

        $hoja = self::leerHojas($archivo, $separadorCsv)[$hojaIndice] ?? [];
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
        $celdas = array_values(array_filter(array_map(
            static fn ($v) => self::normalizarNombreColumna((string) $v),
            $primeraFila
        ), static fn ($v) => $v !== ''));

        if ($celdas === []) {
            return false;
        }

        $grupos = [
            self::ALIAS_ENCABEZADO_CUENTA,
            self::ALIAS_ENCABEZADO_DEBE,
            self::ALIAS_ENCABEZADO_HABER,
            self::ALIAS_ENCABEZADO_MONTO,
            self::ALIAS_ENCABEZADO_DH,
            self::ALIAS_ENCABEZADO_CENTROCOSTO,
            self::ALIAS_ENCABEZADO_MONEDA,
            self::ALIAS_ENCABEZADO_DETALLE,
        ];

        foreach ($celdas as $celda) {
            foreach ($grupos as $alias) {
                $info = PrecioImportColumnasSupport::resolverColumnaEnEncabezados(
                    [$celda],
                    $alias[0],
                    $alias[0],
                    $alias
                );
                if ($info !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $celdasEncabezado
     * @param  list<string>  $alias
     * @return array{indice: int, titulo: string, clave_normalizada: string}|null
     */
    public static function resolverColumna(
        array $celdasEncabezado,
        string $nombreConfigurado,
        string $default,
        array $alias
    ): ?array {
        return PrecioImportColumnasSupport::resolverColumnaEnEncabezados(
            $celdasEncabezado,
            $nombreConfigurado,
            $default,
            $alias
        );
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $columna
     */
    public static function valorCeldaFila(array $fila, ?array $columna): mixed
    {
        return PrecioImportColumnasSupport::valorCeldaFila($fila, $columna);
    }

    public static function normalizarTextoCelda(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        return trim(TextoUtf8Support::normalizar((string) $valor));
    }

    public static function normalizarCodigoCuenta(mixed $valor): string
    {
        $texto = self::normalizarTextoCelda($valor);
        if ($texto === '') {
            return '';
        }

        // Excel a veces entrega 111010001 como float 111010001.0
        if (is_numeric($valor) && ! str_contains($texto, 'E') && ! str_contains($texto, 'e')) {
            $float = (float) $valor;
            if (abs($float - round($float)) < 0.0000001) {
                return (string) (int) round($float);
            }
        }

        return $texto;
    }

    public static function normalizarImporte(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_int($valor) || is_float($valor)) {
            return round((float) $valor, 2);
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        return self::parsearImporteTexto($texto);
    }

    /**
     * Acepta importe argentino (1.025.504,12) y el de Excel en inglés (1,025,504.12).
     */
    public static function parsearImporteTexto(string $texto): ?float
    {
        $texto = trim($texto);
        if ($texto === '' || $texto === '-' || $texto === '—') {
            return null;
        }

        $negativo = false;
        if (preg_match('/^\((.*)\)$/', $texto, $paren)) {
            $negativo = true;
            $texto = $paren[1];
        }

        $texto = str_replace(['$', ' '], '', $texto);
        $texto = preg_replace('/[^\d,.\-]/', '', $texto) ?? '';
        if ($texto === '' || $texto === '-') {
            return null;
        }

        if (str_starts_with($texto, '-')) {
            $negativo = true;
            $texto = substr($texto, 1);
        }

        $tieneComa = str_contains($texto, ',');
        $tienePunto = str_contains($texto, '.');

        if (preg_match('/^\d{1,3}([.,]\d{3})+[.,]\d{1,2}$/', $texto)) {
            // 8.912.936.58, 1.025.504,12 o el mixto del OCR 2.494,744.04
            $ultimo = max((int) strrpos($texto, '.'), (int) strrpos($texto, ','));
            $entero = preg_replace('/[.,]/', '', substr($texto, 0, $ultimo)) ?? '';
            $texto = $entero.'.'.substr($texto, $ultimo + 1);
        } elseif ($tieneComa && $tienePunto) {
            if (strrpos($texto, ',') > strrpos($texto, '.')) {
                $texto = str_replace('.', '', $texto);
                $texto = str_replace(',', '.', $texto);
            } else {
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($tieneComa) {
            if (preg_match('/^\d{1,3}(,\d{3})+$/', $texto)) {
                $texto = str_replace(',', '', $texto);
            } else {
                $texto = str_replace(',', '.', $texto);
            }
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto)) {
            $texto = str_replace('.', '', $texto);
        }

        if (! is_numeric($texto)) {
            return null;
        }

        $importe = round((float) $texto, 2);

        return $negativo ? -$importe : $importe;
    }

    public static function formatearImporte(?float $importe): string
    {
        if ($importe === null) {
            return '';
        }

        return number_format($importe, 2, ',', '.');
    }
}
