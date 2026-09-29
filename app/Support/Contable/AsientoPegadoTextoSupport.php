<?php

namespace App\Support\Contable;

/**
 * Decodifica un asiento copiado de un PDF, de la impresión o de una grilla.
 * Columnas: Nro.Cta., Descripcion, C.Cos., Debe, Haber, Descripcion del movimiento.
 * En texto alineado (pdftotext -layout o copia del PDF) el importe se asigna
 * a Debe o Haber según la columna, aunque el número sea más ancho que el título.
 */
final class AsientoPegadoTextoSupport
{
    /** @var list<string> */
    private const ALIAS_CUENTA = [
        'nro_cta',
        'nrocta',
        'nro_cuenta',
        'numero_cuenta',
        'numero_cta',
        'cuenta',
        'codigo_cuenta',
        'cod_cuenta',
        'cuentacontable',
        'cta',
        'cta_contable',
        'codigo',
    ];

    /** @var list<string> */
    private const ALIAS_CENTROCOSTO = [
        'c_cos',
        'ccos',
        'ccosto',
        'centrocosto',
        'centro_costo',
        'centro_de_costo',
        'codigo_cc',
        'cod_cc',
        'cc',
    ];

    /** @var list<string> */
    private const ALIAS_DEBE = [
        'debe',
        'debito',
        'importe_debe',
        'monto_debe',
    ];

    /** @var list<string> */
    private const ALIAS_HABER = [
        'haber',
        'credito',
        'importe_haber',
        'monto_haber',
    ];

    /** @var list<string> */
    private const ALIAS_DETALLE_FUERTE = [
        'descripcion_del_movimiento',
        'desc_movimiento',
        'desc_mov',
        'detalle_movimiento',
        'detalle',
        'observacion',
        'concepto',
        'glosa',
        'leyenda',
        'comentario',
    ];

    /**
     * @return array{
     *     filas: list<array{
     *         codigo_cuenta: string,
     *         codigo_centrocosto: string,
     *         debe: float,
     *         haber: float,
     *         detalle: string
     *     }>,
     *     con_encabezado: bool
     * }
     */
    public static function decodificar(string $texto): array
    {
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto) ?? $texto;
        if (! str_contains($texto, "\t")) {
            $porPosicion = self::decodificarPorPosicion($texto);
            if ($porPosicion['filas'] !== []) {
                return $porPosicion;
            }
        }

        $filasCrudas = self::filasDesdeTexto($texto);
        if ($filasCrudas === []) {
            return ['filas' => [], 'con_encabezado' => false];
        }

        $indiceEncabezado = null;
        $mapa = null;
        foreach ($filasCrudas as $i => $celdas) {
            $mapaTry = self::mapaDesdeEncabezado($celdas);
            if ($mapaTry !== null) {
                $indiceEncabezado = $i;
                $mapa = $mapaTry;
                break;
            }
        }

        $datos = $indiceEncabezado === null
            ? $filasCrudas
            : array_slice($filasCrudas, $indiceEncabezado + 1);

        $filas = [];
        foreach ($datos as $celdas) {
            $mapaFila = $mapa ?? self::mapaPorPosicion($celdas);
            $fila = self::filaDesdeCeldas($celdas, $mapaFila);
            if ($fila === null) {
                continue;
            }
            $filas[] = $fila;
        }

        return [
            'filas' => $filas,
            'con_encabezado' => $indiceEncabezado !== null,
        ];
    }

    /**
     * Líneas de palabras con posición horizontal (caracteres del PDF o píxeles del OCR).
     *
     * @param  list<list<array{t: string, x1: int, x2: int}>>  $lineas
     * @return array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}
     */
    public static function decodificarPalabras(array $lineas): array
    {
        $header = null;
        $headerIdx = null;
        foreach ($lineas as $i => $palabras) {
            $cols = self::columnasDesdePalabras($palabras);
            if ($cols !== null) {
                $header = $cols;
                $headerIdx = $i;
                break;
            }
        }

        if ($header === null) {
            return ['filas' => [], 'con_encabezado' => false];
        }

        $mapa = ['cuenta' => 0, 'centrocosto' => 1, 'debe' => 2, 'haber' => 3, 'detalle' => 4];
        $filas = [];
        foreach ($lineas as $i => $palabras) {
            if ($headerIdx !== null && $i <= $headerIdx) {
                continue;
            }
            $campos = self::celdasDesdePalabras($palabras, $header);
            $fila = self::filaDesdeCeldas([
                $campos['cuenta'],
                $campos['centrocosto'],
                $campos['debe'],
                $campos['haber'],
                $campos['detalle'],
            ], $mapa);
            if ($fila !== null) {
                $filas[] = $fila;
            }
        }

        return ['filas' => $filas, 'con_encabezado' => true];
    }

    /**
     * @return array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}
     */
    private static function decodificarPorPosicion(string $texto): array
    {
        $lineas = preg_split('/\r\n|\r|\n/', $texto) ?: [];
        $palabras = [];
        foreach ($lineas as $linea) {
            $linea = rtrim((string) $linea, "\r\n");
            if (trim($linea) === '') {
                continue;
            }
            $deLinea = self::palabrasDeLinea($linea);
            if ($deLinea !== []) {
                $palabras[] = $deLinea;
            }
        }

        return self::decodificarPalabras($palabras);
    }

    /**
     * @return list<array{t: string, x1: int, x2: int}>
     */
    private static function palabrasDeLinea(string $linea): array
    {
        preg_match_all('/\S+/', $linea, $coincidencias, PREG_OFFSET_CAPTURE);
        $palabras = [];
        foreach ($coincidencias[0] as $coincidencia) {
            $texto = (string) $coincidencia[0];
            $x1 = (int) $coincidencia[1];
            $palabras[] = ['t' => $texto, 'x1' => $x1, 'x2' => $x1 + strlen($texto)];
        }

        return $palabras;
    }

    /**
     * @param  list<array{t: string, x1: int, x2: int}>  $palabras
     * @return array<string, array{x1: int, x2: int, cx: float}>|null
     */
    private static function columnasDesdePalabras(array $palabras): ?array
    {
        $cols = [];
        $n = count($palabras);

        for ($i = 0; $i < $n; $i++) {
            $campo = null;
            $consumir = 1;
            $unidas = [];
            $hasta = min($n, $i + 4);
            for ($k = $i; $k < $hasta; $k++) {
                $unidas[] = AsientoImportColumnasSupport::normalizarNombreColumna((string) ($palabras[$k]['t'] ?? ''));
                $clave = implode('_', array_filter($unidas, static fn ($parte) => $parte !== ''));
                if (in_array($clave, ['descripcion_del_movimiento', 'desc_del_movimiento'], true)) {
                    $campo = 'detalle';
                    $consumir = $k - $i + 1;
                    break;
                }
            }

            if ($campo === null) {
                $clave = AsientoImportColumnasSupport::normalizarNombreColumna($palabras[$i]['t']);
                if (in_array($clave, self::ALIAS_CUENTA, true)) {
                    $campo = 'cuenta';
                } elseif (in_array($clave, self::ALIAS_CENTROCOSTO, true)) {
                    $campo = 'centrocosto';
                } elseif (in_array($clave, self::ALIAS_DEBE, true)) {
                    $campo = 'debe';
                } elseif (in_array($clave, self::ALIAS_HABER, true)) {
                    $campo = 'haber';
                } elseif ($clave === 'descripcion') {
                    $campo = 'descripcion';
                }
            }

            if ($campo === null || isset($cols[$campo])) {
                continue;
            }

            $x1 = (int) $palabras[$i]['x1'];
            $x2 = (int) $palabras[$i + $consumir - 1]['x2'];
            $cols[$campo] = ['x1' => $x1, 'x2' => $x2, 'cx' => ($x1 + $x2) / 2];
            $i += $consumir - 1;
        }

        if (! isset($cols['debe']) && isset($cols['centrocosto'], $cols['haber'])) {
            foreach ($palabras as $palabra) {
                $cx = ($palabra['x1'] + $palabra['x2']) / 2;
                if ($cx <= $cols['centrocosto']['x2'] || $cx >= $cols['haber']['x1']) {
                    continue;
                }
                if (mb_strlen((string) $palabra['t']) > 8) {
                    continue;
                }
                $cols['debe'] = [
                    'x1' => (int) $palabra['x1'],
                    'x2' => (int) $palabra['x2'],
                    'cx' => $cx,
                ];
                break;
            }
        }

        if (! isset($cols['cuenta']) || (! isset($cols['debe']) && ! isset($cols['haber']))) {
            return null;
        }

        return $cols;
    }

    /**
     * @param  list<array{t: string, x1: int, x2: int}>  $palabras
     * @param  array<string, array{x1: int, x2: int, cx: float}>  $cols
     * @return array{cuenta: string, centrocosto: string, debe: string, haber: string, detalle: string}
     */
    private static function celdasDesdePalabras(array $palabras, array $cols): array
    {
        $orden = [];
        foreach ($cols as $campo => $col) {
            $orden[] = ['campo' => $campo, 'cx' => $col['cx']];
        }
        usort($orden, static fn (array $a, array $b): int => $a['cx'] <=> $b['cx']);

        $cantidad = count($orden);
        $limites = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $limites[] = [
                'campo' => $orden[$i]['campo'],
                'izq' => $i === 0 ? -PHP_FLOAT_MAX : ($orden[$i - 1]['cx'] + $orden[$i]['cx']) / 2,
                'der' => $i === $cantidad - 1 ? PHP_FLOAT_MAX : ($orden[$i]['cx'] + $orden[$i + 1]['cx']) / 2,
            ];
        }

        $grupos = [];
        foreach ($palabras as $palabra) {
            $cx = ($palabra['x1'] + $palabra['x2']) / 2;
            foreach ($limites as $limite) {
                if ($cx >= $limite['izq'] && $cx < $limite['der']) {
                    $grupos[$limite['campo']][] = $palabra['t'];
                    break;
                }
            }
        }

        $detalle = trim(implode(' ', $grupos['detalle'] ?? []));
        if ($detalle === '') {
            $detalle = trim(implode(' ', $grupos['descripcion'] ?? []));
        }

        return [
            'cuenta' => trim(implode(' ', $grupos['cuenta'] ?? [])),
            'centrocosto' => trim(implode(' ', $grupos['centrocosto'] ?? [])),
            'debe' => trim(implode(' ', $grupos['debe'] ?? [])),
            'haber' => trim(implode(' ', $grupos['haber'] ?? [])),
            'detalle' => $detalle,
        ];
    }

    /**
     * @return list<list<string>>
     */
    private static function filasDesdeTexto(string $texto): array
    {
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto) ?? $texto;
        $lineas = preg_split('/\r\n|\r|\n/', $texto) ?: [];
        $filas = [];

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }
            $celdas = self::partirLinea($linea);
            if ($celdas === []) {
                continue;
            }
            $filas[] = $celdas;
        }

        return $filas;
    }

    /**
     * @return list<string>
     */
    private static function partirLinea(string $linea): array
    {
        if (str_contains($linea, "\t")) {
            $partes = explode("\t", $linea);
        } elseif (substr_count($linea, ';') >= 2) {
            $partes = explode(';', $linea);
        } elseif (preg_match('/\s{2,}/', $linea)) {
            $partes = preg_split('/\s{2,}/', $linea) ?: [];
        } else {
            $partes = [$linea];
        }

        return array_map(static fn ($celda) => trim((string) $celda), $partes);
    }

    /**
     * @param  list<string>  $celdas
     * @return array{cuenta: int, centrocosto: ?int, debe: ?int, haber: ?int, detalle: ?int}|null
     */
    private static function mapaDesdeEncabezado(array $celdas): ?array
    {
        $mapa = [
            'cuenta' => null,
            'centrocosto' => null,
            'debe' => null,
            'haber' => null,
            'detalle' => null,
        ];
        $detalleDebil = null;

        foreach ($celdas as $indice => $celda) {
            $clave = AsientoImportColumnasSupport::normalizarNombreColumna($celda);
            if ($clave === '') {
                continue;
            }
            if ($mapa['cuenta'] === null && in_array($clave, self::ALIAS_CUENTA, true)) {
                $mapa['cuenta'] = $indice;
                continue;
            }
            if ($mapa['centrocosto'] === null && in_array($clave, self::ALIAS_CENTROCOSTO, true)) {
                $mapa['centrocosto'] = $indice;
                continue;
            }
            if ($mapa['debe'] === null && in_array($clave, self::ALIAS_DEBE, true)) {
                $mapa['debe'] = $indice;
                continue;
            }
            if ($mapa['haber'] === null && in_array($clave, self::ALIAS_HABER, true)) {
                $mapa['haber'] = $indice;
                continue;
            }
            if (in_array($clave, self::ALIAS_DETALLE_FUERTE, true)) {
                $mapa['detalle'] = $indice;
                continue;
            }
            if ($clave === 'descripcion' && $detalleDebil === null) {
                $detalleDebil = $indice;
            }
        }

        if ($mapa['detalle'] === null) {
            $mapa['detalle'] = $detalleDebil;
        }

        if ($mapa['cuenta'] === null || ($mapa['debe'] === null && $mapa['haber'] === null)) {
            return null;
        }

        return $mapa;
    }

    /**
     * Sin títulos: el listado Anita va cuenta, descripción, C.Cos., Debe, Haber, detalle.
     *
     * @param  list<string>  $celdas
     * @return array{cuenta: int, centrocosto: ?int, debe: ?int, haber: ?int, detalle: ?int}
     */
    private static function mapaPorPosicion(array $celdas): array
    {
        $n = count($celdas);
        $ccEnSegunda = $n >= 4
            && self::esCodigoCentro($celdas[1] ?? '')
            && (self::celdaTieneImporte($celdas[2] ?? '') || self::celdaTieneImporte($celdas[3] ?? ''));

        if ($ccEnSegunda && $n < 6) {
            return [
                'cuenta' => 0,
                'centrocosto' => 1,
                'debe' => 2,
                'haber' => 3,
                'detalle' => $n > 4 ? 4 : null,
            ];
        }

        if ($n >= 5) {
            return [
                'cuenta' => 0,
                'centrocosto' => 2,
                'debe' => 3,
                'haber' => 4,
                'detalle' => $n > 5 ? 5 : null,
            ];
        }

        return [
            'cuenta' => 0,
            'centrocosto' => null,
            'debe' => 1,
            'haber' => $n > 2 ? 2 : null,
            'detalle' => $n > 3 ? 3 : null,
        ];
    }

    /**
     * @param  list<string>  $celdas
     * @param  array{cuenta: int, centrocosto: ?int, debe: ?int, haber: ?int, detalle: ?int}  $mapa
     * @return array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}|null
     */
    private static function filaDesdeCeldas(array $celdas, array $mapa): ?array
    {
        $cuenta = self::normalizarCodigoCuentaOcr(self::celda($celdas, $mapa['cuenta']));
        if ($cuenta === '' || preg_match('/^total\b/i', $cuenta)) {
            return null;
        }
        if (! self::esCodigoCuenta($cuenta)) {
            return null;
        }

        $debe = self::importe($celdas, $mapa['debe']);
        $haber = self::importe($celdas, $mapa['haber']);
        if ($debe <= 0 && $haber <= 0) {
            return null;
        }

        $cc = $mapa['centrocosto'] === null ? '' : self::celda($celdas, $mapa['centrocosto']);
        if ($cc !== '' && ! self::esCodigoCentro($cc)) {
            $ccNorm = strtr($cc, ['?' => '7', 'O' => '0', 'o' => '0', 'I' => '1', 'l' => '1']);
            $cc = self::esCodigoCentro($ccNorm) ? $ccNorm : '';
        }

        $detalle = $mapa['detalle'] === null ? '' : self::celda($celdas, $mapa['detalle']);
        if (self::esCodigoCuenta($detalle) || (self::celdaTieneImporte($detalle) && preg_match('/^[\d.,]+$/', $detalle))) {
            $detalle = '';
        }

        return [
            'codigo_cuenta' => $cuenta,
            'codigo_centrocosto' => $cc,
            'debe' => $debe,
            'haber' => $haber,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  list<string>  $celdas
     */
    private static function celda(array $celdas, ?int $indice): string
    {
        if ($indice === null || ! array_key_exists($indice, $celdas)) {
            return '';
        }

        return trim($celdas[$indice]);
    }

    /**
     * @param  list<string>  $celdas
     */
    private static function importe(array $celdas, ?int $indice): float
    {
        $texto = self::celda($celdas, $indice);
        if ($texto === '') {
            return 0.0;
        }

        $importe = AsientoImportColumnasSupport::parsearImporteTexto($texto);

        return $importe !== null && $importe > 0 ? $importe : 0.0;
    }

    public static function normalizarCodigoCuentaOcr(string $codigo): string
    {
        $codigo = trim($codigo);
        $codigo = str_replace(
            ["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2212}", '¡', '¦'],
            ['-', '-', '-', '-', '-', '-', '1', '1'],
            $codigo
        );
        $codigo = rtrim($codigo, '.,:;');
        if ($codigo === '' || self::esCodigoCuenta($codigo)) {
            return $codigo;
        }

        $out = strtr($codigo, [
            'O' => '0', 'o' => '0', 'Q' => '0',
            'I' => '1', 'l' => '1', '|' => '1', '!' => '1',
            'S' => '5', 's' => '5',
            'B' => '8',
            'Z' => '2', 'z' => '2',
            'G' => '6',
            '?' => '7',
        ]);
        $out = preg_replace('/(?<=\d)m(?=\d|-)/i', '10', $out) ?? $out;
        if (self::esCodigoCuenta($out)) {
            return $out;
        }

        $quitados = preg_match_all('/[^\d-]/', $out);
        $limpio = preg_replace('/[^\d-]/', '', $out) ?? '';
        if ($quitados !== false && $quitados <= 2 && self::esCodigoCuenta($limpio)) {
            return $limpio;
        }

        return $codigo;
    }

    /**
     * El texto plano del OCR suele leer mejor el código; el hOCR dice en qué columna cae el importe.
     *
     * @param  list<list<array{t: string, x1: int, x2: int}>>  $lineasHocr
     * @return array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}
     */
    public static function decodificarOcr(string $textoPlano, array $lineasHocr): array
    {
        $cols = null;
        foreach ($lineasHocr as $palabras) {
            $cols = self::columnasDesdePalabras($palabras);
            if ($cols !== null) {
                break;
            }
        }

        $corte = self::corteDebeHaber(self::cajasDeImportes($lineasHocr));
        $filas = [];
        foreach (preg_split('/\r\n|\r|\n/', $textoPlano) ?: [] as $linea) {
            $fila = self::filaDesdeTextoOcr((string) $linea, $lineasHocr, $cols, $corte);
            if ($fila !== null && ! self::tieneCuentaEnLado($filas, $fila)) {
                $filas[] = $fila;
            }
        }

        foreach ($lineasHocr as $palabras) {
            $linea = trim(implode(' ', array_map(static fn (array $palabra): string => (string) ($palabra['t'] ?? ''), $palabras)));
            $fila = self::filaDesdeTextoOcr($linea, $lineasHocr, $cols, $corte);
            if ($fila === null || self::tieneCuenta($filas, $fila['codigo_cuenta'])) {
                continue;
            }
            $filas[] = $fila;
        }

        if ($filas !== []) {
            return ['filas' => $filas, 'con_encabezado' => $cols !== null];
        }

        return self::decodificarPalabras($lineasHocr);
    }

    /**
     * @param  array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}  $parseado
     */
    public static function lecturaIncompleta(string $textoPlano, array $parseado): bool
    {
        $filas = $parseado['filas'];
        if ($filas === []) {
            return true;
        }

        if (self::lineasQueParecenCuenta($textoPlano) > count($filas)) {
            return true;
        }

        return abs(self::sumaLado($filas, 'debe') - self::sumaLado($filas, 'haber')) > 0.009;
    }

    /**
     * @param  array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}  $a
     * @param  array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}  $b
     * @return array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}
     */
    public static function elegirLectura(array $a, array $b): array
    {
        $union = self::fusionarLecturas($a, $b);
        $difUnion = abs(self::sumaLado($union['filas'], 'debe') - self::sumaLado($union['filas'], 'haber'));
        if ($difUnion <= 0.009 && count($union['filas']) >= max(count($a['filas']), count($b['filas']))) {
            return $union;
        }

        $difA = abs(self::sumaLado($a['filas'], 'debe') - self::sumaLado($a['filas'], 'haber'));
        $difB = abs(self::sumaLado($b['filas'], 'debe') - self::sumaLado($b['filas'], 'haber'));
        if (count($b['filas']) > count($a['filas']) && $difB <= $difA + 0.009) {
            return $b;
        }
        if ($difB + 0.009 < $difA && count($b['filas']) >= count($a['filas'])) {
            return $b;
        }

        return $a;
    }

    /**
     * @param  array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}  $a
     * @param  array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}  $b
     * @return array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}
     */
    private static function fusionarLecturas(array $a, array $b): array
    {
        $filas = $a['filas'];
        foreach ($b['filas'] as $fila) {
            if (! self::tieneCuentaEnLado($filas, $fila)) {
                $filas[] = $fila;
            }
        }

        return [
            'filas' => $filas,
            'con_encabezado' => $a['con_encabezado'] || $b['con_encabezado'],
        ];
    }

    /**
     * @param  list<array{codigo_cuenta: string, debe: float, haber: float}>  $filas
     */
    private static function tieneCuenta(array $filas, string $cuenta): bool
    {
        foreach ($filas as $existente) {
            if ($existente['codigo_cuenta'] === $cuenta) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{codigo_cuenta: string, debe: float, haber: float}>  $filas
     */
    private static function tieneCuentaEnLado(array $filas, array $fila): bool
    {
        $lado = ((float) $fila['debe']) > 0 ? 'D' : 'H';
        foreach ($filas as $existente) {
            $ladoExistente = ((float) $existente['debe']) > 0 ? 'D' : 'H';
            if ($existente['codigo_cuenta'] === $fila['codigo_cuenta'] && $ladoExistente === $lado) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{debe: float, haber: float}>  $filas
     */
    private static function sumaLado(array $filas, string $lado): float
    {
        $total = 0.0;
        foreach ($filas as $fila) {
            $total += (float) ($fila[$lado] ?? 0);
        }

        return round($total, 2);
    }

    private static function lineasQueParecenCuenta(string $textoPlano): int
    {
        $cantidad = 0;
        foreach (preg_split('/\r\n|\r|\n/', $textoPlano) ?: [] as $linea) {
            $linea = trim((string) $linea);
            if ($linea === '' || preg_match('/^total\b/i', $linea)) {
                continue;
            }
            $tokens = preg_split('/\s+/', $linea) ?: [];
            [$cuenta] = self::extraerCodigoCuenta($tokens);
            if ($cuenta !== '') {
                $cantidad++;
            }
        }

        return $cantidad;
    }

    /**
     * @param  list<list<array{t: string, x1: int, x2: int}>>  $lineasHocr
     * @param  array<string, array{x1: int, x2: int, cx: float}>|null  $cols
     * @return array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}|null
     */
    private static function filaDesdeTextoOcr(string $linea, array $lineasHocr, ?array $cols, ?float $corte): ?array
    {
        $linea = trim($linea);
        if ($linea === '' || preg_match('/^total\b/i', $linea)) {
            return null;
        }

        $tokens = preg_split('/\s+/', $linea) ?: [];
        if ($tokens === []) {
            return null;
        }

        [$cuenta, $desde] = self::extraerCodigoCuenta($tokens);
        if ($cuenta === '') {
            return null;
        }

        $importes = [];
        foreach ($tokens as $indice => $token) {
            if ($indice < $desde || ! preg_match('/\d[,.]\d/', $token)) {
                continue;
            }
            $importe = AsientoImportColumnasSupport::parsearImporteTexto($token);
            if ($importe === null || $importe <= 0) {
                continue;
            }
            $importes[] = ['indice' => $indice, 'token' => $token, 'importe' => $importe];
        }
        if ($importes === []) {
            return null;
        }

        $cc = '';
        $primero = $importes[0]['indice'];
        for ($i = $desde; $i < $primero; $i++) {
            $candidato = strtr($tokens[$i], ['?' => '7', 'O' => '0', 'o' => '0', 'I' => '1', 'l' => '1', '¡' => '1']);
            if (preg_match('/^\d{1,4}$/', $candidato)) {
                $cc = $candidato;
            }
        }

        $debe = 0.0;
        $haber = 0.0;
        foreach ($importes as $importe) {
            if (self::importeCaeEnDebe($importe['token'], $linea, $lineasHocr, $cols, $corte)) {
                $debe = (float) $importe['importe'];
            } else {
                $haber = (float) $importe['importe'];
            }
        }

        $ultimo = $importes[count($importes) - 1]['indice'];
        $detalle = trim(implode(' ', array_slice($tokens, $ultimo + 1)));
        if ($detalle === '') {
            $medio = [];
            for ($i = $desde; $i < $primero; $i++) {
                if ($cc !== '' && $tokens[$i] === $cc) {
                    continue;
                }
                $medio[] = $tokens[$i];
            }
            $detalle = trim(implode(' ', $medio));
        }

        return [
            'codigo_cuenta' => $cuenta,
            'codigo_centrocosto' => $cc,
            'debe' => $debe,
            'haber' => $haber,
            'detalle' => $detalle,
        ];
    }

    /**
     * El OCR a veces parte el código (52 + ¡090-004) o cambia el guión.
     *
     * @param  list<string>  $tokens
     * @return array{0: string, 1: int}
     */
    private static function extraerCodigoCuenta(array $tokens): array
    {
        $n = count($tokens);
        $acumulado = '';
        for ($i = 0; $i < $n && $i < 4; $i++) {
            $pieza = (string) $tokens[$i];
            if ($acumulado !== '' && preg_match('/\p{L}{2,}/u', $pieza)) {
                break;
            }
            $acumulado .= $pieza;
            $norm = self::normalizarCodigoCuentaOcr($acumulado);
            if (self::esCodigoCuenta($norm)) {
                return [$norm, $i + 1];
            }
        }

        return ['', 0];
    }

    /**
     * @param  list<list<array{t: string, x1: int, x2: int}>>  $lineasHocr
     * @param  array<string, array{x1: int, x2: int, cx: float}>|null  $cols
     */
    private static function importeCaeEnDebe(string $token, string $linea, array $lineasHocr, ?array $cols, ?float $corte): bool
    {
        $cajas = self::cajasDeImportes($lineasHocr);
        $x2 = self::x2DelImporteParaLinea($token, $linea, $lineasHocr) ?? self::x2DelImporte($token, $cajas);

        // En la impresión el número se alinea a la derecha: el grupo de la
        // izquierda es el Debe y el de la derecha es el Haber.
        if ($corte !== null && $x2 !== null) {
            return $x2 < $corte;
        }

        if ($x2 !== null && $cols !== null && isset($cols['haber'])) {
            $distanciaHaber = abs($x2 - $cols['haber']['cx']);
            if (! isset($cols['debe'])) {
                return $x2 < $cols['haber']['x1'];
            }

            return abs($x2 - $cols['debe']['cx']) <= $distanciaHaber;
        }

        return true;
    }

    /**
     * @param  list<list<array{t: string, x1: int, x2: int}>>  $lineasHocr
     * @return list<array{digitos: string, x2: int}>
     */
    private static function cajasDeImportes(array $lineasHocr): array
    {
        $cajas = [];
        foreach ($lineasHocr as $palabras) {
            foreach ($palabras as $palabra) {
                $texto = (string) ($palabra['t'] ?? '');
                if (! preg_match('/\d[,.]\d/', $texto)) {
                    continue;
                }
                $importe = AsientoImportColumnasSupport::parsearImporteTexto($texto);
                if ($importe === null || $importe <= 0) {
                    continue;
                }
                $cajas[] = [
                    'digitos' => preg_replace('/\D/', '', $texto) ?? '',
                    'x2' => (int) $palabra['x2'],
                ];
            }
        }

        return $cajas;
    }

    /**
     * @param  list<array{digitos: string, x2: int}>  $cajas
     */
    private static function corteDebeHaber(array $cajas): ?float
    {
        $xs = array_map(static fn (array $caja): int => $caja['x2'], $cajas);
        sort($xs);
        $mejorGap = 0.0;
        $corte = null;
        for ($i = 1, $n = count($xs); $i < $n; $i++) {
            $gap = $xs[$i] - $xs[$i - 1];
            if ($gap > $mejorGap) {
                $mejorGap = $gap;
                $corte = ($xs[$i] + $xs[$i - 1]) / 2;
            }
        }

        return $mejorGap >= 25 ? $corte : null;
    }

    /**
     * El mismo importe puede estar en el Debe y en el Haber (asiento de amortización).
     * Hay que usar la caja de la línea de esa cuenta, no la mediana de todas.
     *
     * @param  list<list<array{t: string, x1: int, x2: int}>>  $lineasHocr
     */
    private static function x2DelImporteParaLinea(string $token, string $linea, array $lineasHocr): ?int
    {
        $digitos = preg_replace('/\D/', '', $token) ?? '';
        if ($digitos === '') {
            return null;
        }

        $palabrasCuenta = self::palabrasDeLaCuenta($linea, $lineasHocr);
        if ($palabrasCuenta !== null) {
            $x2Linea = null;
            foreach ($palabrasCuenta as $palabra) {
                if (preg_match('/\d[,.]\d/', (string) ($palabra['t'] ?? ''))) {
                    $x2Linea = (int) $palabra['x2'];
                }
            }
            if ($x2Linea !== null) {
                return $x2Linea;
            }
        }

        $candidatos = [];
        foreach ($lineasHocr as $indice => $palabras) {
            foreach ($palabras as $palabra) {
                $digitosPalabra = preg_replace('/\D/', '', (string) ($palabra['t'] ?? '')) ?? '';
                if ($digitosPalabra !== $digitos) {
                    continue;
                }
                $candidatos[] = [
                    'i' => $indice,
                    'x2' => (int) $palabra['x2'],
                    'palabras' => $palabras,
                ];
            }
        }
        if ($candidatos === []) {
            return null;
        }
        if (count($candidatos) === 1) {
            return $candidatos[0]['x2'];
        }

        $mejorX2 = null;
        $mejorPuntos = -1;
        foreach ($candidatos as $candidato) {
            $puntos = 0;
            foreach ($candidato['palabras'] as $palabra) {
                $texto = (string) ($palabra['t'] ?? '');
                if ($texto !== '' && str_contains($linea, $texto)) {
                    $puntos++;
                }
            }
            if ($puntos > $mejorPuntos) {
                $mejorPuntos = $puntos;
                $mejorX2 = $candidato['x2'];
            }
        }

        return $mejorX2;
    }

    /**
     * @param  list<list<array{t: string, x1: int, x2: int}>>  $lineasHocr
     * @return list<array{t: string, x1: int, x2: int}>|null
     */
    private static function palabrasDeLaCuenta(string $linea, array $lineasHocr): ?array
    {
        $tokens = preg_split('/\s+/', trim($linea)) ?: [];
        [$cuenta] = self::extraerCodigoCuenta($tokens);
        if ($cuenta === '') {
            return null;
        }

        $exacta = null;
        $mejor = null;
        $mejorDist = 3;
        foreach ($lineasHocr as $palabras) {
            if ($palabras === []) {
                continue;
            }
            $crudos = [
                (string) $palabras[0]['t'],
                (string) $palabras[0]['t'].(string) ($palabras[1]['t'] ?? ''),
            ];
            foreach ($crudos as $crudo) {
                $norm = self::normalizarCodigoCuentaOcr($crudo);
                if ($norm === $cuenta) {
                    $exacta = $palabras;
                    break 2;
                }
                if (self::esCodigoCuenta($norm) && $norm !== $cuenta && ! preg_match('/\p{L}/u', $crudo)) {
                    continue;
                }
                if ($crudo === '' || abs(strlen($crudo) - strlen($cuenta)) > 3) {
                    continue;
                }
                $dist = levenshtein($crudo, $cuenta);
                if ($dist <= 2 && $dist < $mejorDist) {
                    $mejorDist = $dist;
                    $mejor = $palabras;
                }
            }
        }

        return $exacta ?? $mejor;
    }

    /**
     * @param  list<array{digitos: string, x2: int}>  $cajas
     */
    private static function x2DelImporte(string $token, array $cajas): ?int
    {
        $digitos = preg_replace('/\D/', '', $token) ?? '';
        if ($digitos === '') {
            return null;
        }

        $xs = [];
        foreach ($cajas as $caja) {
            if ($caja['digitos'] === $digitos) {
                $xs[] = $caja['x2'];
            }
        }
        if ($xs === []) {
            return null;
        }

        sort($xs);

        return $xs[(int) floor((count($xs) - 1) / 2)];
    }

    public static function esCodigoCuenta(string $texto): bool
    {
        $texto = trim($texto);

        return (bool) preg_match('/^\d{3,}(?:-\d{1,6})?$/', $texto);
    }

    private static function esCodigoCentro(string $texto): bool
    {
        return (bool) preg_match('/^\d{1,6}$/', trim($texto));
    }

    private static function celdaTieneImporte(string $texto): bool
    {
        $importe = AsientoImportColumnasSupport::parsearImporteTexto($texto);

        return $importe !== null && abs($importe) > 0;
    }
}
