<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Detecta el orden de columnas del remito a partir del encabezado OCR (Cantidad, Artículo, etc.).
 */
final class RecepcionProveedorOcrLayoutSupport
{
    /** @var array<string, list<string>> */
    private const ETIQUETAS = [
        'cantidad' => ['CANTIDAD', 'CANT', 'CANT.'],
        'articulo' => [
            'ARTICULOS Y/O MERCADERIA', 'ARTÍCULOS Y/O MERCADERÍA', 'ARTICULO', 'ARTÍCULO',
            'MERCADERIA', 'MERCADERÍA', 'DESCRIPCION', 'DESCRIPCIÓN',
            'PRODUCTO / SERVICIO', 'PRODUCTO', 'SERVICIO',
        ],
        'codigo' => ['COD. ALT', 'COD ALT', 'CODIGO', 'CÓDIGO', 'COD.', 'SKU'],
        'codigobarra' => [
            'COD. DE BARRAS', 'COD DE BARRAS', 'COD. BARRAS', 'COD BARRAS',
            'CODIGO DE BARRAS', 'CÓDIGO DE BARRAS', 'CODIGO BARRAS', 'CÓDIGO BARRAS',
            'C. BARRAS', 'C.B.', 'EAN-13', 'EAN13', 'EAN', 'BARCODE', 'BARRAS',
        ],
        'unidxbulto' => ['UNID. X BULTO', 'UNID X BULTO', 'UNID.X BULTO', 'U.X BULTO'],
        'unidades' => ['UNIDADES', 'U. MEDIDA', 'U.MEDIDA', 'UM'],
        'peso' => ['PESO', 'KGS', 'KG.'],
        'precio' => ['PRECIO', 'IMPORTE', 'P. UNIT', 'P.UNIT', 'P.UNIT.', 'PRECIO UNIT'],
    ];

    /**
     * @param  list<string>  $filas
     */
    public static function detectarDesdeFilas(array $filas): ?RecepcionProveedorOcrLayout
    {
        foreach ($filas as $fila) {
            $fila = trim($fila);
            if ($fila === '' || mb_strlen($fila) < 8) {
                continue;
            }

            $layout = self::parsearLineaEncabezado($fila);
            if ($layout !== null) {
                return $layout;
            }
        }

        return null;
    }

    public static function detectarDesdeTexto(string $texto): ?RecepcionProveedorOcrLayout
    {
        $filas = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $fila) {
            $fila = trim((string) $fila);
            if ($fila !== '') {
                $filas[] = $fila;
            }
        }

        return self::detectarDesdeFilas($filas);
    }

    private static function parsearLineaEncabezado(string $linea): ?RecepcionProveedorOcrLayout
    {
        $upper = mb_strtoupper($linea);
        $encontradas = [];

        foreach (self::ETIQUETAS as $tipo => $variantes) {
            $mejorPos = null;
            foreach ($variantes as $etiqueta) {
                $pos = mb_stripos($upper, $etiqueta);
                if ($pos === false) {
                    continue;
                }
                if ($mejorPos === null || $pos < $mejorPos) {
                    $mejorPos = $pos;
                }
            }
            if ($mejorPos !== null) {
                $encontradas[$tipo] = $mejorPos;
            }
        }

        if (! isset($encontradas['cantidad']) || count($encontradas) < 2) {
            return null;
        }

        asort($encontradas);

        return new RecepcionProveedorOcrLayout(array_keys($encontradas));
    }

    /**
     * Extrae la cantidad leyendo la celda que corresponde a la columna Cantidad del layout.
     *
     * @return array{cant: float, unidad: ?string, factor: ?float, celda: string}|null
     */
    public static function extraerCantidadColumna(string $fila, ?RecepcionProveedorOcrLayout $layout): ?array
    {
        if ($layout === null) {
            return null;
        }

        $celdas = self::partirFilaEnColumnas($fila, $layout);
        $indice = $layout->indiceCantidad();
        $celda = trim((string) ($celdas[$indice] ?? ''));

        if ($celda !== '') {
            $desdeCelda = self::extraerCantidadDesdeCelda($celda);
            $conUnidadEnFila = self::buscarCantidadPorUnidadMedidaEnFila($fila, $layout);
            if ($desdeCelda !== null && $conUnidadEnFila !== null) {
                return self::elegirCantidadCeldaVsUnidadEnFila($desdeCelda, $conUnidadEnFila);
            }
            if ($desdeCelda !== null) {
                return $desdeCelda;
            }
        }

        $porUnidad = self::buscarCantidadPorUnidadMedidaEnFila($fila, $layout);
        if ($porUnidad !== null) {
            return $porUnidad;
        }

        if ($celda === '' && $indice === 0 && ! preg_match('/^'.RecepcionProveedorOcrLineasParser::PATRON_CODIGO_PROVEEDOR.'\b/u', $fila)) {
            return self::extraerCantidadDesdeCelda($fila);
        }

        return null;
    }

    /**
     * Localiza "N PACK/CAJAS/..." en la fila cuando la columna Cantidad quedó mal partida por OCR.
     *
     * @return array{cant: float, unidad: ?string, factor: ?float, celda: string}|null
     */
    public static function buscarCantidadPorUnidadMedidaEnFila(string $fila, ?RecepcionProveedorOcrLayout $layout = null): ?array
    {
        $fila = trim($fila);
        if ($fila === '') {
            return null;
        }

        $desdeInicio = self::extraerCantidadDigitosHastaUnidad($fila);
        if ($desdeInicio !== null && ($desdeInicio['unidad'] ?? null) !== null) {
            unset($desdeInicio['consumido']);

            return $desdeInicio;
        }

        $unidades = 'CAJAS|PACK|UNIDADES|UNID|BOL|BIDON|LITROS?|KG';
        if (! preg_match_all(
            '/(?<cant>\d+(?:[.,]\d+)?)\s+(?<unidad>'.$unidades.')\b(?:\s+(?:X|x)\s*(?<factor>\d+))?/iu',
            $fila,
            $matches,
            PREG_SET_ORDER
        )) {
            return null;
        }

        $mejor = null;
        $mejorPuntaje = -1;

        foreach ($matches as $m) {
            $cant = RecepcionProveedorOcrNumeroSupport::parsear((string) ($m['cant'] ?? ''));
            if ($cant === null || $cant <= 0) {
                continue;
            }

            $unidad = mb_strtoupper(trim((string) ($m['unidad'] ?? '')));
            $factor = isset($m['factor']) && trim((string) $m['factor']) !== ''
                ? RecepcionProveedorOcrNumeroSupport::parsear((string) $m['factor'])
                : null;

            $puntaje = in_array($unidad, ['PACK', 'CAJAS', 'BOL', 'BIDON'], true) ? 30 : 10;
            if ($factor !== null && $factor > 1) {
                $puntaje += 15;
            }

            if ($layout !== null) {
                $pos = mb_stripos($fila, trim((string) ($m[0] ?? '')));
                $idxCantidad = $layout->indiceCantidad();
                $idxCodigo = $layout->indice('codigo');
                if ($pos !== false && $idxCodigo !== null && $idxCantidad < $idxCodigo && $pos === 0) {
                    $puntaje += 25;
                } elseif ($pos !== false && $idxCodigo !== null && $idxCantidad > $idxCodigo && $pos > 0) {
                    $puntaje += 20;
                }
            }

            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $celda = self::celdaCantidadDesdePartes($cant, $unidad, $factor);
                $mejor = [
                    'cant' => $cant,
                    'unidad' => $unidad,
                    'factor' => $factor,
                    'celda' => $celda,
                ];
            }
        }

        return $mejor;
    }

    /**
     * @param  array{cant: float, unidad: ?string, factor: ?float, celda: string}  $desdeCelda
     * @param  array{cant: float, unidad: ?string, factor: ?float, celda: string}  $conUnidad
     * @return array{cant: float, unidad: ?string, factor: ?float, celda: string}
     */
    private static function elegirCantidadCeldaVsUnidadEnFila(array $desdeCelda, array $conUnidad): array
    {
        if (($desdeCelda['unidad'] ?? null) !== null) {
            return $desdeCelda;
        }

        if (abs($desdeCelda['cant'] - $conUnidad['cant']) < 0.000001) {
            return $conUnidad;
        }

        return $conUnidad;
    }

    private static function celdaCantidadDesdePartes(float $cant, string $unidad, ?float $factor): string
    {
        $celda = rtrim(rtrim(number_format($cant, 6, '.', ''), '0'), '.').' '.$unidad;
        if ($factor !== null && $factor > 1) {
            $celda .= ' X '.(int) $factor;
        }

        return $celda;
    }

    /**
     * Extrae EAN-13 de la columna Cod. barras del layout o, si no hay layout, de la fila.
     */
    public static function extraerCodigoBarraColumna(
        string $fila,
        ?RecepcionProveedorOcrLayout $layout,
        ?float $cantBulto = null,
        ?float $factorEmbalaje = null,
    ): ?string {
        $desdeRemito = RecepcionProveedorOcrCodigoBarraSupport::extraerDeRemitoBuho(
            $fila,
            $cantBulto,
            $factorEmbalaje
        );
        if ($desdeRemito !== null) {
            return $desdeRemito;
        }

        if ($layout !== null) {
            $idx = $layout->indice('codigobarra');
            if ($idx !== null) {
                $celdas = self::partirFilaEnColumnas($fila, $layout);
                $celda = trim((string) ($celdas[$idx] ?? ''));
                if ($celda !== '') {
                    $ean = RecepcionProveedorOcrCodigoBarraSupport::extraerDeCelda($celda);
                    if ($ean !== null) {
                        return $ean;
                    }
                }
            }

            return null;
        }

        return RecepcionProveedorOcrCodigoBarraSupport::extraerDeTexto($fila);
    }

    /**
     * Asigna valores numéricos finales (unid×bulto, unidades, peso) y EAN en el mapa de columnas.
     *
     * @param  list<string>  $tokens
     */
    public static function asignarColumnasFinalesEnMapa(
        array &$mapa,
        RecepcionProveedorOcrLayout $layout,
        array $tokens,
        ?string $eanPreferido = null
    ): void {
        $idxBarcode = $layout->indice('codigobarra');
        if ($eanPreferido !== null && $idxBarcode !== null) {
            $mapa[$idxBarcode] = $eanPreferido;
        }

        $pendientes = array_values(array_filter($tokens, static fn (string $v): bool => trim($v) !== ''));

        if ($idxBarcode !== null && ($mapa[$idxBarcode] ?? '') === '' && $pendientes !== []) {
            foreach ($pendientes as $i => $token) {
                $ean = RecepcionProveedorOcrCodigoBarraSupport::extraerDeCelda($token);
                if ($ean !== null) {
                    $mapa[$idxBarcode] = $ean;
                    unset($pendientes[$i]);
                    break;
                }
            }
            $pendientes = array_values($pendientes);
        }

        $slots = array_values(array_filter([
            $layout->indice('unidxbulto'),
            $layout->indice('unidades'),
            $layout->indice('peso'),
            $layout->indice('precio'),
        ], static fn (?int $v): bool => $v !== null));
        sort($slots);

        foreach ($slots as $i => $slot) {
            if (($mapa[$slot] ?? '') !== '' || ! isset($pendientes[$i])) {
                continue;
            }
            $mapa[$slot] = trim((string) $pendientes[$i]);
        }
    }

    /**
     * Normaliza prefijos de cantidad pegados por OCR (ej. "7PACKX 6" → "7 PACK X 6").
     */
    public static function normalizarPrefijoCantidadRemito(string $fila): string
    {
        $fila = trim($fila);
        if ($fila === '' || ! preg_match('/^\d/u', $fila)) {
            return $fila;
        }

        $extraido = self::extraerCantidadDigitosHastaUnidad($fila);
        if ($extraido === null || ($extraido['unidad'] ?? null) === null) {
            return $fila;
        }

        $consumido = (int) ($extraido['consumido'] ?? 0);
        if ($consumido <= 0) {
            return $fila;
        }

        $prefix = self::celdaCantidadDesdePartes(
            $extraido['cant'],
            (string) $extraido['unidad'],
            $extraido['factor'] ?? null
        );
        $resto = trim(mb_substr($fila, $consumido));

        return $resto === '' ? $prefix : $prefix.' '.$resto;
    }

    /**
     * Cantidad = dígitos iniciales hasta la primera letra; luego unidad (PACK, CAJAS…) y factor opcional.
     *
     * @return array{cant: float, unidad: ?string, factor: ?float, celda: string, consumido: int}|null
     */
    public static function extraerCantidadDigitosHastaUnidad(string $texto): ?array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        $unidades = 'CAJAS|PACK|UNIDADES|UNID|BOL|BIDON|LITROS?|KG';

        if (preg_match(
            '/^(?<cant>\d+(?:[.,]\d+)?)\s+(?<unidad>'.$unidades.')\b(?:\s+(?:X|x)\s*(?<factor>\d+))?/iu',
            $texto,
            $m
        )) {
            $armada = self::armarCantidadExtraida(
                $texto,
                (string) $m['cant'],
                mb_strtoupper(trim((string) $m['unidad'])),
                isset($m['factor']) && trim((string) $m['factor']) !== ''
                    ? RecepcionProveedorOcrNumeroSupport::parsear((string) $m['factor'])
                    : null,
                mb_strlen((string) $m[0])
            );
            if ($armada !== null) {
                return $armada;
            }
        }

        if (! preg_match('/^(?<cant>\d+(?:[.,]\d+)?)(?<letras>[A-Za-z]+)(?<tail>.*)$/u', $texto, $m)) {
            return null;
        }

        $unidad = self::normalizarUnidadDesdeLetrasOcr((string) $m['letras']);
        if ($unidad === null) {
            return null;
        }

        [$factor, $consumido] = self::resolverFactorTrasUnidadOcr(
            (string) $m['tail'],
            mb_strlen((string) $m['cant']) + mb_strlen((string) $m['letras'])
        );

        return self::armarCantidadExtraida($texto, (string) $m['cant'], $unidad, $factor, $consumido);
    }

    /**
     * @return array{cant: float, unidad: ?string, factor: ?float, celda: string}|null
     */
    public static function extraerCantidadDesdeCelda(string $celda): ?array
    {
        $celda = trim($celda);
        if ($celda === '') {
            return null;
        }

        $desdeDigitos = self::extraerCantidadDigitosHastaUnidad($celda);
        if ($desdeDigitos !== null) {
            unset($desdeDigitos['consumido']);

            return $desdeDigitos;
        }

        if (preg_match('/^(?<cant>\d+(?:[.,]\d+)?)$/u', $celda, $m)) {
            $cant = RecepcionProveedorOcrNumeroSupport::parsear((string) $m['cant']);
            if ($cant === null || $cant <= 0) {
                return null;
            }

            return [
                'cant' => $cant,
                'unidad' => null,
                'factor' => null,
                'celda' => $celda,
            ];
        }

        return null;
    }

    /**
     * @return array{cant: float, unidad: ?string, factor: ?float, celda: string, consumido: int}|null
     */
    private static function armarCantidadExtraida(
        string $textoOriginal,
        string $cantRaw,
        string $unidad,
        ?float $factor,
        int $consumido
    ): array {
        $cant = RecepcionProveedorOcrNumeroSupport::parsear($cantRaw);
        if ($cant === null || $cant <= 0) {
            return null;
        }

        return [
            'cant' => $cant,
            'unidad' => $unidad,
            'factor' => $factor,
            'celda' => self::celdaCantidadDesdePartes($cant, $unidad, $factor),
            'consumido' => max(0, $consumido),
        ];
    }

    /**
     * @return array{0: ?float, 1: int} factor y longitud consumida desde el inicio del texto original
     */
    private static function resolverFactorTrasUnidadOcr(string $tail, int $baseConsumido): array
    {
        $tailOriginal = $tail;
        $tail = ltrim($tail);
        $consumido = $baseConsumido + (mb_strlen($tailOriginal) - mb_strlen($tail));

        if ($tail === '') {
            return [null, $consumido];
        }

        if (preg_match('/^'.RecepcionProveedorOcrLineasParser::PATRON_CODIGO_PROVEEDOR.'\b/u', $tail)) {
            return [null, $consumido];
        }

        if (! preg_match('/^(?:X|x)?\s*(?<factor>\d+(?:[.,]\d+)?)(?<rest>\s+.*)?$/u', $tail, $m)) {
            return [null, $consumido];
        }

        $rest = trim((string) ($m['rest'] ?? ''));
        if ($rest !== '' && preg_match('/^'.RecepcionProveedorOcrLineasParser::PATRON_CODIGO_PROVEEDOR.'\b/u', $rest)) {
            $factor = RecepcionProveedorOcrNumeroSupport::parsear((string) $m['factor']);

            return [
                $factor !== null && $factor > 0 ? $factor : null,
                $consumido + mb_strlen((string) $m[0]),
            ];
        }

        if ($rest === '') {
            $factor = RecepcionProveedorOcrNumeroSupport::parsear((string) $m['factor']);

            return [
                $factor !== null && $factor > 0 ? $factor : null,
                $consumido + mb_strlen((string) $m[0]),
            ];
        }

        return [null, $consumido];
    }

    private static function normalizarUnidadDesdeLetrasOcr(string $letras): ?string
    {
        $raw = mb_strtoupper(preg_replace('/[^A-Za-z]/u', '', $letras) ?? '');
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^PAC(?:K|KS|KX)?/u', $raw) || $raw === 'K') {
            return 'PACK';
        }
        if (preg_match('/^CAJ(?:AS?|A)?/u', $raw) || preg_match('/AM[S5]?$/u', $raw)) {
            return 'CAJAS';
        }
        if (str_starts_with($raw, 'UNID')) {
            return 'UNID';
        }
        if (str_starts_with($raw, 'BOL')) {
            return 'BOL';
        }
        if (str_starts_with($raw, 'BIDON')) {
            return 'BIDON';
        }
        if (preg_match('/^LIT(?:RO|S)?/u', $raw)) {
            return preg_match('/^LITROS/u', $raw) ? 'LITROS' : 'LITRO';
        }
        if ($raw === 'KG' || str_starts_with($raw, 'KG')) {
            return 'KG';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function partirFilaEnColumnas(string $fila, RecepcionProveedorOcrLayout $layout): array
    {
        $fila = trim($fila);
        $numCols = $layout->numColumnas();

        $porEspaciado = preg_split('/\s{2,}/u', $fila) ?: [];
        $porEspaciado = array_values(array_filter(array_map('trim', $porEspaciado), static fn (string $p): bool => $p !== ''));
        if (count($porEspaciado) >= $numCols) {
            return array_slice($porEspaciado, 0, $numCols);
        }

        if ($layout->indiceCantidad() === 0) {
            $unidades = 'CAJAS|PACK|UNIDADES|UNID|BOL|BIDON|LITROS?|KG';
            $extraido = self::extraerCantidadDigitosHastaUnidad($fila);
            if ($extraido !== null && ($extraido['unidad'] ?? null) !== null) {
                $consumido = (int) ($extraido['consumido'] ?? 0);
                $resto = trim(mb_substr($fila, $consumido));
                if ($resto !== '') {
                    return self::rellenarColumnas([$extraido['celda'], $resto], $numCols);
                }
            }

            if (preg_match(
                '/^(?<cantidad>\d+\s+(?:'.$unidades.')\b(?:\s+(?:X|x)\s+\d+)?)\s+(?<resto>.+)$/iu',
                $fila,
                $m
            )) {
                return self::rellenarColumnas([trim((string) $m['cantidad']), trim((string) $m['resto'])], $numCols);
            }
        }

        if ($layout->indice('codigo') !== null && preg_match(
            '/^'.RecepcionProveedorOcrLineasParser::PATRON_CODIGO_PROVEEDOR.'\s+/u',
            $fila
        )) {
            return self::partirDesdeCodigoProveedor($fila, $layout);
        }

        return self::rellenarColumnas([$fila], $numCols);
    }

    /**
     * @return list<string>
     */
    private static function partirDesdeCodigoProveedor(string $fila, RecepcionProveedorOcrLayout $layout): array
    {
        $codigo = RecepcionProveedorOcrLineasParser::PATRON_CODIGO_PROVEEDOR;
        $unidades = 'CAJAS|PACK|UNIDADES|UNID|BOL|BIDON|LITROS?|KG';

        if (! preg_match(
            '/^(?<antes>.*?)(?<codigo>'.$codigo.')\s+(?<desc>.+?)(?:\s+(?<barcode>\d{13}))?(?:\s+(?<col4>\d+(?:[.,]\d+)?))?(?:\s+(?<col5>\d+(?:[.,]\d+)?))?(?:\s+(?<col6>\d+(?:[.,]\d+)?))?$/iu',
            $fila,
            $m
        )) {
            return self::rellenarColumnas([$fila], $layout->numColumnas());
        }

        $mapa = array_fill(0, $layout->numColumnas(), '');
        $idxCodigo = $layout->indice('codigo') ?? 1;
        $idxArticulo = $layout->indice('articulo');
        $idxCantidad = $layout->indiceCantidad();

        $antes = trim((string) ($m['antes'] ?? ''));
        if ($antes !== '' && $idxCantidad === 0) {
            $mapa[0] = $antes;
        } elseif ($antes !== '' && $idxArticulo !== null && $idxArticulo < $idxCodigo) {
            $mapa[$idxArticulo] = $antes;
        }

        $mapa[$idxCodigo] = trim((string) $m['codigo']);
        $desc = trim((string) ($m['desc'] ?? ''));
        $resto = '';
        if ($idxArticulo !== null && $idxCantidad !== null) {
            $resto = self::asignarArticuloYCantidadEnMapa($mapa, $idxArticulo, $idxCantidad, $desc);
        } elseif ($idxArticulo !== null && $idxArticulo !== $idxCodigo) {
            $mapa[$idxArticulo] = $desc;
        } else {
            $mapa[$idxCodigo] = trim($mapa[$idxCodigo].' '.$desc);
        }

        $ean = RecepcionProveedorOcrCodigoBarraSupport::extraerDeCelda(trim((string) ($m['barcode'] ?? '')));
        $tokensNumericos = [];
        if ($resto !== '') {
            if ($ean === null) {
                $ean = RecepcionProveedorOcrCodigoBarraSupport::extraerDeTexto($resto);
            }
            if (preg_match_all('/\d+(?:[.,]\d+)?/u', $resto, $nums)) {
                foreach ($nums[0] as $num) {
                    $tokensNumericos[] = trim((string) $num);
                }
            }
        }

        foreach (['col4', 'col5', 'col6'] as $col) {
            $valor = trim((string) ($m[$col] ?? ''));
            if ($valor !== '') {
                $tokensNumericos[] = $valor;
            }
        }

        self::asignarColumnasFinalesEnMapa($mapa, $layout, $tokensNumericos, $ean);

        if ($idxCantidad > 0 && $antes !== '' && preg_match(
            '/(?<cant>\d+(?:[.,]\d+)?)\s+(?<unidad>'.$unidades.')\b(?:\s+(?:X|x)\s*\d+)?/iu',
            $antes,
            $cm
        )) {
            $mapa[$idxCantidad] = trim($cm[0]);
        }

        return array_map(static fn (?string $v): string => trim((string) $v), $mapa);
    }

    private static function asignarArticuloYCantidadEnMapa(
        array &$mapa,
        int $idxArticulo,
        int $idxCantidad,
        string $desc
    ): string {
        $unidades = 'CAJAS|PACK|UNIDADES|UNID|BOL|BIDON|LITROS?|KG';
        $patCant = '(?<cant>\d+(?:[.,]\d+)?)\s+(?<unidad>'.$unidades.')\b(?:\s+(?:X|x)\s*(?<factor>\d+))?';

        if ($idxCantidad < $idxArticulo
            && preg_match('/^'.$patCant.'\s+(?<articulo>.+)$/iu', $desc, $cm)) {
            $mapa[$idxCantidad] = self::celdaCantidadDesdeMatch($cm);
            $mapa[$idxArticulo] = trim((string) $cm['articulo']);

            return '';
        }

        if ($idxCantidad > $idxArticulo
            && preg_match('/^(?<articulo>.+?)\s+'.$patCant.'(?:\s+(?<resto>.+))?$/iu', $desc, $cm)) {
            $mapa[$idxArticulo] = trim((string) $cm['articulo']);
            $mapa[$idxCantidad] = self::celdaCantidadDesdeMatch($cm);

            return trim((string) ($cm['resto'] ?? ''));
        }

        if ($idxArticulo !== $idxCantidad) {
            $mapa[$idxArticulo] = $desc;
        }

        return '';
    }

    /**
     * @param  array<string, string>  $m
     */
    private static function celdaCantidadDesdeMatch(array $m): string
    {
        $cant = trim((string) ($m['cant'] ?? ''));
        $unidad = trim((string) ($m['unidad'] ?? ''));
        $factor = trim((string) ($m['factor'] ?? ''));
        $celda = $cant.' '.$unidad;
        if ($factor !== '') {
            $celda .= ' X '.$factor;
        }

        return trim($celda);
    }

    /**
     * @param  list<string>  $partes
     * @return list<string>
     */
    private static function rellenarColumnas(array $partes, int $numCols): array
    {
        $out = array_fill(0, $numCols, '');
        foreach ($partes as $i => $parte) {
            if ($i < $numCols) {
                $out[$i] = $parte;
            }
        }

        return $out;
    }
}

/**
 * Layout de columnas detectado en encabezado de remito (orden izquierda → derecha).
 */
final class RecepcionProveedorOcrLayout
{
    /** @param list<string> $orden tipos: cantidad, articulo, codigo, codigobarra, unidxbulto, unidades, peso, precio */
    public function __construct(
        public readonly array $orden,
    ) {
    }

    public function indiceCantidad(): int
    {
        $i = array_search('cantidad', $this->orden, true);

        return $i === false ? 0 : (int) $i;
    }

    public function indice(string $tipo): ?int
    {
        $i = array_search($tipo, $this->orden, true);

        return $i === false ? null : (int) $i;
    }

    public function numColumnas(): int
    {
        return count($this->orden);
    }
}
