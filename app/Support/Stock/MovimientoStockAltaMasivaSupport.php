<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Depmae;
use App\Models\Stock\Modulo;
use App\Models\Stock\Talle;
use App\Models\Stock\Tipotransaccion_Stock;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Alta masiva de un movimiento de stock desde un Excel de curva de talles
 * (SKU, combinación, color y una columna por talle).
 *
 * Ferli: el depósito tiene que ser un local y el artículo se mira en el catálogo de locales.
 * El resto de las instalaciones usa el catálogo estándar del movimiento.
 */
final class MovimientoStockAltaMasivaSupport
{
    /**
     * @return array{
     *     canal: string,
     *     canal_etiqueta: string,
     *     cabecera_errores: list<string>,
     *     cabecera_avisos: list<string>,
     *     resumen: array{filas:int,ok:int,errores:int,avisos:int,pares:float,omitidas:int},
     *     lineas: list<array<string, mixed>>
     * }
     */
    public static function previsualizar(
        UploadedFile|string $archivo,
        int $depositoId,
        int $empresaId,
        ?Tipotransaccion_Stock $tipo = null
    ): array {
        $filas = self::leerFilas($archivo);
        $cabeceraErrores = self::erroresCabecera($depositoId, $empresaId, $tipo);
        $cabeceraAvisos = self::avisosCabecera($tipo);
        $talles = self::mapaTalles();
        $lineas = [];
        $ok = 0;
        $errores = 0;
        $avisos = 0;
        $pares = 0.0;

        foreach ($filas as $fila) {
            $resuelta = self::resolverFila($fila, $talles);
            $lineas[] = $resuelta;
            if ($resuelta['errores'] !== []) {
                $errores++;
            } else {
                $ok++;
                $pares += (float) $resuelta['pares'];
            }
            if ($resuelta['avisos'] !== []) {
                $avisos++;
            }
        }

        return [
            'canal' => self::canal(),
            'canal_etiqueta' => self::etiquetaCanal(),
            'cabecera_errores' => $cabeceraErrores,
            'cabecera_avisos' => $cabeceraAvisos,
            'resumen' => [
                'filas' => count($lineas),
                'ok' => $ok,
                'errores' => $errores,
                'avisos' => $avisos,
                'pares' => $pares,
                'omitidas' => 0,
            ],
            'lineas' => $lineas,
        ];
    }

    public static function puedeGrabar(array $preview): bool
    {
        return ($preview['cabecera_errores'] ?? []) === []
            && (int) ($preview['resumen']['ok'] ?? 0) > 0
            && (int) ($preview['resumen']['errores'] ?? 0) === 0;
    }

    /**
     * Arma el array que espera MovimientoStockService::guardaMovimientoStock.
     *
     * @param  array<string, mixed>  $preview
     * @param  array<string, mixed>  $cabecera
     * @return array<string, mixed>
     */
    public static function payloadParaGrabar(array $preview, array $cabecera): array
    {
        $articulos = [];
        $skus = [];
        $combinaciones = [];
        $cantidades = [];
        $medidas = [];
        $modulos = [];
        $precios = [];
        $precioCabecera = (float) str_replace(',', '.', (string) ($cabecera['precio'] ?? 0));
        $moduloAbiertoId = self::moduloAbiertoId();

        foreach ($preview['lineas'] as $linea) {
            if (($linea['errores'] ?? []) !== [] || (int) ($linea['articulo_id'] ?? 0) <= 0) {
                continue;
            }
            $articulos[] = (int) $linea['articulo_id'];
            $skus[] = (string) ($linea['sku'] ?? '');
            $combinaciones[] = (int) ($linea['combinacion_id'] ?? 0) > 0
                ? (int) $linea['combinacion_id']
                : null;
            $cantidades[] = (float) $linea['pares'];
            $medidas[] = json_encode($linea['medidas'], JSON_UNESCAPED_UNICODE);
            // La curva del Excel es libre: sin módulo, la edición pide uno y al elegirlo blanquea los talles.
            $modulos[] = ($linea['medidas'] ?? []) !== [] ? $moduloAbiertoId : null;
            $precios[] = $precioCabecera > 0 ? $precioCabecera : 0;
        }

        $n = count($articulos);
        $nulos = array_fill(0, $n, null);
        $ceros = array_fill(0, $n, 0);
        $vacios = array_fill(0, $n, '');

        $lote = trim((string) ($cabecera['lote'] ?? ''));

        return [
            'tipotransaccion_stock_id' => (int) ($cabecera['tipotransaccion_stock_id'] ?? 0),
            'fecha' => (string) ($cabecera['fecha'] ?? ''),
            'empresa_id' => (int) ($cabecera['empresa_id'] ?? 0),
            'deposito_id' => (int) ($cabecera['deposito_id'] ?? 0),
            'lote' => $lote !== '' ? $lote : 'LOTE DE ALTA',
            'leyenda' => trim((string) ($cabecera['leyenda'] ?? '')),
            'precio' => $precioCabecera,
            'articulos_id' => $articulos,
            'skus' => $skus,
            'combinaciones_id' => $combinaciones,
            'cantidades' => $cantidades,
            'medidas' => $medidas,
            'precios' => $precios,
            'modulos_id' => $modulos,
            'cajas' => $ceros,
            'piezas' => $ceros,
            'listasprecios_id' => $nulos,
            'incluyeimpuestos' => $nulos,
            'monedas_id' => $nulos,
            'descuentos' => $ceros,
            'loteids' => $nulos,
            'numeropartes' => $vacios,
            'colores_id' => $nulos,
            'talles_id' => $nulos,
            'sentidos' => $vacios,
            'loteimportacion_id' => null,
        ];
    }

    public static function moduloAbiertoId(): ?int
    {
        $id = Modulo::query()->where('codigo', '99')->value('id')
            ?? Modulo::query()->where('nombre', 'like', '%abierto%')->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function esParaLocales(): bool
    {
        return MovimientoStockFerliSupport::esCalzadosFerli();
    }

    public static function canal(): string
    {
        return self::esParaLocales()
            ? CombinacionEstadoCanalSupport::AMBITO_LOCAL
            : 'NORMAL';
    }

    public static function etiquetaCanal(): string
    {
        return self::esParaLocales() ? 'Locales' : 'Estándar';
    }

    /**
     * @return list<string>
     */
    public static function erroresCabecera(int $depositoId, int $empresaId, ?Tipotransaccion_Stock $tipo): array
    {
        $errores = [];

        if ($empresaId <= 0) {
            $errores[] = 'Falta la empresa.';
        }

        if ($depositoId <= 0) {
            $errores[] = 'Falta el depósito.';
        } elseif ($empresaId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($depositoId, $empresaId)) {
            $errores[] = 'El depósito no pertenece a la empresa o no está autorizado para su usuario.';
        } elseif (self::esParaLocales() && ! MovimientoStockFerliSupport::depositoEsDeLocalVenta($depositoId)) {
            $errores[] = 'En Ferli esta alta es para un depósito de local.';
        }

        if ($tipo === null) {
            $errores[] = 'Falta el tipo de transacción.';
        } else {
            $tipoId = (int) $tipo->id;
            if (! UsuarioTipotransaccionStockAutorizado::tipotransaccionAutorizada($tipoId)) {
                $errores[] = 'El tipo de transacción no está autorizado para su usuario.';
            }
            $operacion = (string) ($tipo->operacion ?? '');
            if (! in_array($operacion, ['E', 'S'], true)) {
                $errores[] = 'Elija un tipo de entrada o de salida. Las transferencias y los canjes se cargan en su propia pantalla.';
            }
            if ((bool) ($tipo->maneja_contabilidad ?? false)) {
                $errores[] = 'Este tipo lleva contabilidad y no se graba desde el alta masiva.';
            }
            if ((bool) ($tipo->alta_npu ?? false) || (bool) ($tipo->baja_npu ?? false)) {
                $errores[] = 'El alta y la baja de NPU no se cargan con esta planilla.';
            }
        }

        return $errores;
    }

    /**
     * @return list<string>
     */
    public static function avisosCabecera(?Tipotransaccion_Stock $tipo): array
    {
        if ($tipo === null) {
            return [];
        }

        $signo = (string) ($tipo->signo ?? '');
        if ($signo === 'R') {
            return ['El tipo elegido resta stock. Un alta de pedido suele usar un tipo que suma.'];
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function leerFilas(UploadedFile|string $archivo): array
    {
        $path = $archivo instanceof UploadedFile ? (string) $archivo->getRealPath() : $archivo;
        $wb = IOFactory::load($path);
        $filas = [];
        foreach ($wb->getAllSheets() as $ws) {
            $filas = array_merge($filas, self::leerHoja($ws));
        }
        $wb->disconnectWorksheets();

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function leerHoja(Worksheet $ws): array
    {
        $maxFila = (int) $ws->getHighestDataRow();
        $maxCol = min(40, Coordinate::columnIndexFromString($ws->getHighestDataColumn() ?: 'A'));
        $filas = [];
        $layout = null;

        for ($r = 1; $r <= $maxFila; $r++) {
            $celdas = [];
            for ($c = 1; $c <= $maxCol; $c++) {
                $celdas[$c] = self::valorCelda($ws, $c, $r);
            }

            $encabezado = self::detectarEncabezado($celdas);
            if ($encabezado !== null) {
                $layout = $encabezado;

                continue;
            }

            if ($layout === null) {
                continue;
            }

            $sku = self::texto($celdas[$layout['sku']] ?? null);
            if ($sku === '' || self::esFilaTotal($sku)) {
                continue;
            }

            $talles = [];
            $pares = 0.0;
            foreach ($layout['talles'] as $col => $medida) {
                $cant = self::cantidad($celdas[$col] ?? null);
                if ($cant < 0.000001) {
                    continue;
                }
                $talles[(string) $medida] = ($talles[(string) $medida] ?? 0) + $cant;
                $pares += $cant;
            }

            if ($pares < 0.000001) {
                continue;
            }

            $totalExcel = null;
            if (($layout['total'] ?? 0) > 0) {
                $totalExcel = self::cantidad($celdas[$layout['total']] ?? null);
            }

            $filas[] = [
                'fila' => $r,
                'sku_excel' => $sku,
                'codigo_combinacion' => $layout['combinacion'] > 0
                    ? self::texto($celdas[$layout['combinacion']] ?? null)
                    : '',
                'color' => $layout['color'] > 0
                    ? self::texto($celdas[$layout['color']] ?? null)
                    : '',
                'talles' => $talles,
                'pares' => $pares,
                'total_excel' => $totalExcel,
            ];
        }

        return $filas;
    }

    /**
     * @param  array<int, mixed>  $celdas
     * @return array{sku:int,combinacion:int,color:int,total:int,talles:array<int,string>}|null
     */
    private static function detectarEncabezado(array $celdas): ?array
    {
        $skuCol = 0;
        $combCol = 0;
        $colorCol = 0;
        $totalCol = 0;
        $talles = [];

        foreach ($celdas as $col => $valor) {
            $txt = self::normEncabezado($valor);
            if ($txt === 'sku' || $txt === 'articulo' || $txt === 'art') {
                $skuCol = (int) $col;
            } elseif ($txt === 'combinacion' || $txt === 'comb') {
                $combCol = (int) $col;
            } elseif ($txt === 'color') {
                $colorCol = (int) $col;
            } elseif (in_array($txt, ['ps', 'total', 'pares'], true)) {
                $totalCol = (int) $col;
            } elseif (preg_match('/^(\d{1,2})$/', $txt, $m) === 1) {
                $medida = (int) $m[1];
                if ($medida >= 5 && $medida <= 60) {
                    $talles[(int) $col] = (string) $medida;
                }
            }
        }

        if ($skuCol <= 0 || $talles === []) {
            return null;
        }

        return [
            'sku' => $skuCol,
            'combinacion' => $combCol,
            'color' => $colorCol,
            'total' => $totalCol,
            'talles' => $talles,
        ];
    }

    private static function esFilaTotal(string $sku): bool
    {
        return str_starts_with(self::normEncabezado($sku), 'total');
    }

    /**
     * @param  array<string, Talle>  $talles
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private static function resolverFila(array $fila, array $talles): array
    {
        $errores = [];
        $avisos = [];
        $skuExcel = (string) $fila['sku_excel'];
        $articulo = self::buscarArticulo($skuExcel);

        if ($articulo === null) {
            $errores[] = 'SKU no encontrado.';
        } elseif (! self::esParaLocales() && ! ArticuloSeleccionOperativaSupport::esSeleccionable($articulo)) {
            $errores[] = 'El artículo no está activo en el catálogo estándar.';
        } elseif (self::esParaLocales()) {
            $estadoLocal = strtoupper(trim((string) ($articulo->estado_local ?? '')));
            if ($estadoLocal !== '' && ! in_array($estadoLocal, ['ACTIVO', 'A'], true)) {
                $avisos[] = 'El artículo no está activo en el catálogo de locales.';
            }
        }

        $combinacion = null;
        if ($articulo !== null) {
            $combinacion = self::resolverCombinacion(
                (int) $articulo->id,
                (string) $fila['codigo_combinacion'],
                (string) $fila['color'],
                $errores,
                $avisos
            );
        }

        $medidas = [];
        foreach ($fila['talles'] as $medida => $cant) {
            $talle = $talles[(string) $medida] ?? null;
            if ($talle === null) {
                $errores[] = 'Talle inexistente: '.$medida.'.';

                continue;
            }
            $medidas[] = [
                'medida' => (string) ($talle->nombre !== '' ? $talle->nombre : $medida),
                'cantidad' => (float) $cant,
                'precio' => 0,
                'listaprecio' => null,
                'incluyeimpuesto' => null,
                'moneda' => null,
                'talle_id' => (int) $talle->id,
            ];
        }

        $totalExcel = $fila['total_excel'];
        if ($totalExcel !== null && abs((float) $totalExcel - (float) $fila['pares']) > 0.05) {
            $avisos[] = 'La suma de talles ('.self::fmtPares((float) $fila['pares']).') no cierra con el total de la fila ('.self::fmtPares((float) $totalExcel).').';
        }

        $partes = [];
        foreach ($medidas as $m) {
            $partes[] = $m['medida'].':'.self::fmtPares((float) $m['cantidad']);
        }

        return [
            'fila' => (int) $fila['fila'],
            'sku_excel' => $skuExcel,
            'sku' => $articulo?->sku ?? '',
            'articulo_id' => $articulo ? (int) $articulo->id : 0,
            'articulo' => $articulo ? trim((string) $articulo->descripcion) : '',
            'combinacion_id' => $combinacion ? (int) $combinacion->id : 0,
            'combinacion' => $combinacion ? trim((string) $combinacion->codigo) : (string) $fila['codigo_combinacion'],
            'color_excel' => (string) $fila['color'],
            'color_maestro' => $combinacion ? trim((string) $combinacion->nombre) : '',
            'talles_txt' => implode(' · ', $partes),
            'pares' => (float) $fila['pares'],
            'medidas' => $medidas,
            'errores' => $errores,
            'avisos' => $avisos,
        ];
    }

    private static function buscarArticulo(string $skuExcel): ?Articulo
    {
        $variantes = self::variantesSku($skuExcel);
        if ($variantes === []) {
            return null;
        }

        $candidatos = Articulo::query()
            ->whereIn('sku', $variantes)
            ->orderBy('id')
            ->get();

        if ($candidatos->isEmpty()) {
            return null;
        }

        $preferido = self::skuSinSeparadores($skuExcel);
        $exacto = $candidatos->first(static fn (Articulo $a) => (string) $a->sku === $preferido);
        if ($exacto !== null) {
            return $exacto;
        }

        return $candidatos->first();
    }

    /**
     * @return list<string>
     */
    private static function variantesSku(string $skuExcel): array
    {
        $raw = trim($skuExcel);
        $sinSep = self::skuSinSeparadores($raw);
        $out = [];
        foreach ([$raw, $sinSep] as $v) {
            if ($v !== '') {
                $out[$v] = $v;
            }
        }

        return array_values($out);
    }

    private static function skuSinSeparadores(string $sku): string
    {
        return strtoupper(str_replace(['-', ' ', '/', '.'], '', trim($sku)));
    }

    /**
     * @param  list<string>  $errores
     * @param  list<string>  $avisos
     */
    private static function resolverCombinacion(
        int $articuloId,
        string $codigo,
        string $color,
        array &$errores,
        array &$avisos
    ): ?Combinacion {
        $codigo = trim($codigo);
        $combos = Combinacion::query()->where('articulo_id', $articuloId)->get();
        if ($combos->isEmpty()) {
            $errores[] = 'El artículo no tiene combinaciones.';

            return null;
        }

        $porCodigo = $combos->filter(static function (Combinacion $combo) use ($codigo) {
            if ($codigo === '') {
                return false;
            }
            $cod = trim((string) $combo->codigo);
            if (strcasecmp($cod, $codigo) === 0) {
                return true;
            }

            return ctype_digit($codigo) && ctype_digit($cod) && (int) $cod === (int) $codigo;
        })->values();

        $elegida = null;
        if ($porCodigo->count() === 1) {
            $elegida = $porCodigo->first();
        } elseif ($porCodigo->count() > 1) {
            $elegida = self::mejorPorColor($porCodigo->all(), $color);
            if ($elegida === null) {
                $nombres = $porCodigo->map(static fn (Combinacion $c) => trim($c->codigo.' '.$c->nombre))->implode(', ');
                $errores[] = 'Hay varias combinaciones con código '.$codigo.' ('.$nombres.').';

                return null;
            }
        } else {
            $elegida = self::mejorPorColor($combos->all(), $color);
            if ($elegida === null) {
                $errores[] = $codigo !== ''
                    ? 'Combinación '.$codigo.' no encontrada.'
                    : 'No se pudo ubicar la combinación por el color.';

                return null;
            }
            if ($codigo !== '') {
                $avisos[] = 'No está el código '.$codigo.'; se usó la combinación '.$elegida->codigo.' por el color.';
            }
        }

        if ($elegida !== null && $color !== '' && ! self::coloresParecidos($color, (string) $elegida->nombre)) {
            $avisos[] = 'El color del Excel ('.$color.') no coincide con '.$elegida->codigo.'-'.$elegida->nombre.'.';
        }

        if ($elegida !== null && ! self::combinacionActivaEnCanal($elegida)) {
            $avisos[] = self::esParaLocales()
                ? 'La combinación está inactiva en locales.'
                : 'La combinación está inactiva.';
        }

        return $elegida;
    }

    /**
     * @param  list<Combinacion>  $combos
     */
    private static function mejorPorColor(array $combos, string $color): ?Combinacion
    {
        $exactos = [];
        $cerca = [];
        foreach ($combos as $combo) {
            if (self::coloresIguales($color, (string) $combo->nombre)) {
                $exactos[] = $combo;

                continue;
            }
            if (self::coloresParecidos($color, (string) $combo->nombre)) {
                $cerca[] = $combo;
            }
        }
        if (count($exactos) === 1) {
            return $exactos[0];
        }
        if ($exactos === [] && count($cerca) === 1) {
            return $cerca[0];
        }

        return null;
    }

    private static function combinacionActivaEnCanal(Combinacion $combo): bool
    {
        if (self::esParaLocales() && CombinacionEstadoCanalSupport::columnasEstadoDisponibles()) {
            $estado = strtoupper(trim((string) ($combo->estado_local ?? '')));
        } else {
            $estado = strtoupper(trim((string) ($combo->estado ?? '')));
        }

        return in_array($estado, [CombinacionEstadoCanalSupport::ESTADO_ACTIVO, 'ACTIVO'], true);
    }

    private static function coloresIguales(string $a, string $b): bool
    {
        $na = self::normColor($a);
        $nb = self::normColor($b);

        return $na !== '' && $na === $nb;
    }

    private static function coloresParecidos(string $a, string $b): bool
    {
        $na = self::normColor($a);
        $nb = self::normColor($b);
        if ($na === '' || $nb === '') {
            return true;
        }
        if ($na === $nb || str_contains($na, $nb) || str_contains($nb, $na)) {
            return true;
        }

        return strlen($na) >= 4 && strlen($nb) >= 4 && levenshtein($na, $nb) <= 2;
    }

    private static function normColor(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $t = strtoupper(trim($t !== false ? $t : $s));
        $t = preg_replace('/[^A-Z0-9]/', '', $t) ?? '';
        $t = preg_replace('/^\d+/', '', $t) ?? $t;
        // La planilla viene en portugués (PRETO, BRANCO) y el maestro en castellano.
        $t = str_replace(['PRETO', 'BRANCO'], ['NEGRO', 'BLANCO'], $t);

        return $t;
    }

    /**
     * @return array<string, Talle>
     */
    private static function mapaTalles(): array
    {
        $map = [];
        foreach (Talle::query()->get(['id', 'codigo', 'nombre']) as $talle) {
            foreach ([(string) $talle->nombre, (string) $talle->codigo] as $clave) {
                $clave = trim($clave);
                if ($clave !== '' && ! isset($map[$clave])) {
                    $map[$clave] = $talle;
                }
            }
        }

        return $map;
    }

    private static function valorCelda(Worksheet $ws, int $col, int $fila): mixed
    {
        $celda = $ws->getCell(Coordinate::stringFromColumnIndex($col).$fila);
        try {
            return $celda->getCalculatedValue();
        } catch (\Throwable) {
            return $celda->getValue();
        }
    }

    private static function texto(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_int($valor)) {
            return (string) $valor;
        }
        if (is_float($valor)) {
            if (floor($valor) == $valor) {
                return (string) (int) $valor;
            }

            return rtrim(rtrim(sprintf('%.4F', $valor), '0'), '.');
        }

        return trim((string) $valor);
    }

    private static function cantidad(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $t = str_replace(' ', '', trim((string) $valor));
        if ($t === '') {
            return 0.0;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $t) === 1) {
            $t = str_replace('.', '', $t);
            $t = str_replace(',', '.', $t);
        } elseif (preg_match('/^\d+,\d{3}$/', $t) === 1) {
            $t = str_replace(',', '', $t);
        } else {
            $t = str_replace(',', '.', $t);
        }

        return is_numeric($t) ? (float) $t : 0.0;
    }

    private static function normEncabezado(mixed $valor): string
    {
        $texto = self::texto($valor);
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $t = strtoupper(trim($t !== false ? $t : $texto));
        $t = preg_replace('/[^A-Z0-9]/', '', $t) ?? '';

        return strtolower($t);
    }

    private static function fmtPares(float $n): string
    {
        $txt = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

        return $txt === '' ? '0' : $txt;
    }
}
