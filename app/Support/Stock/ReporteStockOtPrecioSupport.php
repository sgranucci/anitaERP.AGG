<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;

/**
 * Precio de la columna PRECIO del Stock por OT.
 *
 * La lista de fábrica (rango de talle + numeración de la línea) manda.
 * El precio grabado en el movimiento queda de respaldo: un alta o un consumo
 * pueden traer la lista web (WEB / OFERTA / LUGANO) y el MAX del lote se queda
 * con ese importe aunque el saldo sea de la lista de fábrica.
 *
 * La curva no se parte. Las listas de fábrica de la numeración (niños: 16/26,
 * 27/33 y 34/41) salen juntas, una columna por lista, sin importar qué talles
 * tenga el saldo.
 *
 * Misma preferencia que PrecioServiceFerli::resuelvePrecio: combinación, salvo
 * que el genérico tenga vigencia estrictamente posterior.
 */
final class ReporteStockOtPrecioSupport
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public static function aplicar(array $filas, ?string $fecha = null): array
    {
        if ($filas === []) {
            return $filas;
        }

        $articuloIds = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['articulo_id'] ?? 0);
            if ($id > 0) {
                $articuloIds[$id] = $id;
            }
        }
        if ($articuloIds === []) {
            return $filas;
        }

        $fecha = self::fechaYmd($fecha);
        $tipoPorArticulo = self::tipoNumeracionPorArticulo(array_values($articuloIds));
        $listas = self::listasFabrica();
        $indice = self::indexarPrecios(self::filasPrecio(array_values($articuloIds), $fecha));

        $out = [];
        foreach ($filas as $fila) {
            unset($fila['_presentacion']);
            $fila['precios_fabrica'] = self::preciosFabricaDeFila($fila, $tipoPorArticulo, $listas, $indice);
            $out[] = $fila;
        }

        return $out;
    }

    /**
     * Las listas de fábrica de la numeración del artículo, en orden de talle.
     * La curva de la fila no se toca.
     *
     * @param  array<string, mixed>  $fila
     * @param  array<int, int>  $tipoPorArticulo
     * @param  list<array{id: int, tiponumeracion_id: int, desde: int, hasta: int}>  $listas
     * @param  array<int, array<int, array{g: ?array{fecha: string, precio: float}, c: array<int, array{fecha: string, precio: float}>}>>  $indice
     * @return list<array{id: int, etiqueta: string, precio: float}>
     */
    public static function preciosFabricaDeFila(
        array $fila,
        array $tipoPorArticulo,
        array $listas,
        array $indice,
    ): array {
        $articuloId = (int) ($fila['articulo_id'] ?? 0);
        $tipo = (int) ($tipoPorArticulo[$articuloId] ?? 0);
        if ($articuloId <= 0 || $tipo <= 0) {
            return [];
        }

        $combinacionId = (int) ($fila['combinacion_id'] ?? 0);
        $propias = [];
        foreach ($listas as $lista) {
            if ((int) $lista['tiponumeracion_id'] !== $tipo) {
                continue;
            }
            $propias[] = $lista;
        }
        usort($propias, static function (array $a, array $b): int {
            return ((int) $a['desde']) <=> ((int) $b['desde']);
        });

        $out = [];
        foreach ($propias as $lista) {
            $precio = self::elegirVigente($indice[$articuloId][(int) $lista['id']] ?? null, $combinacionId);
            $out[] = [
                'id' => (int) $lista['id'],
                'etiqueta' => (int) $lista['desde'].'/'.(int) $lista['hasta'],
                'precio' => ($precio !== null && $precio > 0) ? (float) $precio : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Encabezados de las columnas de precio del reporte, de menor talle a mayor.
     * Sin listas de fábrica queda la columna única de antes.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<string>
     */
    public static function etiquetasPrecio(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            foreach ($fila['precios_fabrica'] ?? [] as $precio) {
                if (! is_array($precio)) {
                    continue;
                }
                $etiqueta = trim((string) ($precio['etiqueta'] ?? ''));
                if ($etiqueta !== '' && ! in_array($etiqueta, $out, true)) {
                    $out[] = $etiqueta;
                }
            }
        }
        usort($out, static function (string $a, string $b): int {
            return ((int) $a) <=> ((int) $b);
        });

        return $out === [] ? ['PRECIO'] : $out;
    }

    /**
     * Parte la fila si hay talles en más de una lista de fábrica.
     *
     * @param  array<string, mixed>  $fila
     * @param  array<int, int>  $tipoPorArticulo
     * @param  list<array{id: int, tiponumeracion_id: int, desde: int, hasta: int}>  $listas
     * @param  array<int, array<int, array{g: ?array{fecha: string, precio: float}, c: array<int, array{fecha: string, precio: float}>}>>  $indice
     * @return list<array<string, mixed>>
     */
    public static function expandirPorLista(
        array $fila,
        array $tipoPorArticulo,
        array $listas,
        array $indice,
    ): array {
        $grupos = self::medidasPorLista($fila, $tipoPorArticulo, $listas);
        if (count($grupos) <= 1) {
            $precio = self::precioDeFila($fila, $tipoPorArticulo, $listas, $indice);
            if ($precio !== null && $precio > 0) {
                $fila['precio'] = $precio;
            }
            unset($fila['_presentacion']);

            return [$fila];
        }

        $articuloId = (int) ($fila['articulo_id'] ?? 0);
        $combinacionId = (int) ($fila['combinacion_id'] ?? 0);
        $presentacion = is_array($fila['_presentacion'] ?? null) ? $fila['_presentacion'] : null;
        $partes = [];
        foreach ($grupos as $grupo) {
            $parte = $fila;
            unset($parte['_presentacion']);
            $parte['medidas'] = $grupo['medidas'];
            $parte['total_pares'] = $grupo['total_pares'];
            $listaId = (int) $grupo['listaprecio_id'];
            if ($listaId > 0) {
                $precio = self::elegirVigente($indice[$articuloId][$listaId] ?? null, $combinacionId);
                if ($precio !== null && $precio > 0) {
                    $parte['precio'] = $precio;
                }
            }
            $curva = self::curvaDelTramo($grupo['medidas'], $presentacion);
            if ($curva !== null) {
                $parte['curva_presentada'] = $curva;
            }
            $partes[] = $parte;
        }

        if (self::mismoPrecio($partes)) {
            $precio = $partes[0]['precio'] ?? null;
            if ($precio !== null && (float) $precio > 0) {
                $fila['precio'] = $precio;
            }
            unset($fila['_presentacion']);

            return [$fila];
        }

        return $partes;
    }

    /**
     * El tramo hereda la curva de la fila entera. Si no había módulo, muestra
     * el saldo de ese tramo y no busca otro módulo más chico.
     *
     * @param  list<array{medida?: mixed, cantidad?: mixed}>  $medidasTramo
     * @param  array{medidas?: array<int|string, int|float>, modulos?: int, coincide?: bool}|null  $presentacion
     * @return array{medidas: array<int|string, int|float>, pares_modulo: float, modulos: int}|null
     */
    public static function curvaDelTramo(array $medidasTramo, ?array $presentacion): ?array
    {
        if ($presentacion === null || ! is_array($presentacion['medidas'] ?? null)) {
            return null;
        }

        $coincide = ! empty($presentacion['coincide']);
        $unidad = [];
        $pares = 0.0;
        foreach ($medidasTramo as $item) {
            if (! is_array($item)) {
                continue;
            }
            $nombre = trim((string) ($item['medida'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $clave = ctype_digit($nombre) ? (int) $nombre : $nombre;
            if ($coincide) {
                $cant = $presentacion['medidas'][$clave] ?? $presentacion['medidas'][$nombre] ?? null;
                if ($cant === null) {
                    continue;
                }
            } else {
                $cant = (float) ($item['cantidad'] ?? 0);
            }
            $unidad[$clave] = $cant;
            $pares += (float) $cant;
        }

        $modulos = $coincide ? (int) ($presentacion['modulos'] ?? 1) : 1;
        if ($modulos < 1) {
            $modulos = 1;
        }

        return [
            'medidas' => $unidad,
            'pares_modulo' => $pares,
            'modulos' => $modulos,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $partes
     */
    private static function mismoPrecio(array $partes): bool
    {
        $visto = null;
        foreach ($partes as $parte) {
            $precio = round((float) ($parte['precio'] ?? 0), 2);
            if ($visto === null) {
                $visto = $precio;
                continue;
            }
            if ($precio !== $visto) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<int, int>  $tipoPorArticulo
     * @param  list<array{id: int, tiponumeracion_id: int, desde: int, hasta: int}>  $listas
     * @return list<array{listaprecio_id: int, medidas: list<array{medida: string, cantidad: float}>, total_pares: float}>
     */
    public static function medidasPorLista(
        array $fila,
        array $tipoPorArticulo,
        array $listas,
    ): array {
        $articuloId = (int) ($fila['articulo_id'] ?? 0);
        $tipo = (int) ($tipoPorArticulo[$articuloId] ?? 0);
        if ($articuloId <= 0 || $tipo <= 0 || $listas === []) {
            return [];
        }

        /** @var array<int, array{listaprecio_id: int, medidas: list<array{medida: string, cantidad: float}>, total_pares: float, orden: int}> $buckets */
        $buckets = [];
        $orden = 0;
        foreach ($fila['medidas'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $medida = trim((string) ($item['medida'] ?? ''));
            $cantidad = (float) ($item['cantidad'] ?? 0);
            if ($medida === '' || abs($cantidad) < 0.0001) {
                continue;
            }
            $listaId = self::listaParaTalle($medida, $tipo, $listas);
            if (! isset($buckets[$listaId])) {
                $buckets[$listaId] = [
                    'listaprecio_id' => $listaId,
                    'medidas' => [],
                    'total_pares' => 0.0,
                    'orden' => $orden++,
                ];
            }
            $buckets[$listaId]['medidas'][] = ['medida' => $medida, 'cantidad' => $cantidad];
            $buckets[$listaId]['total_pares'] += $cantidad;
        }

        if ($buckets === []) {
            return [];
        }

        uasort($buckets, static function (array $a, array $b) use ($listas): int {
            $desdeA = self::desdeLista((int) $a['listaprecio_id'], $listas);
            $desdeB = self::desdeLista((int) $b['listaprecio_id'], $listas);
            if ($desdeA !== $desdeB) {
                return $desdeA <=> $desdeB;
            }

            return $a['orden'] <=> $b['orden'];
        });

        $out = [];
        foreach ($buckets as $bucket) {
            unset($bucket['orden']);
            $out[] = $bucket;
        }

        return $out;
    }

    /**
     * @param  list<array{id: int, tiponumeracion_id: int, desde: int, hasta: int}>  $listas
     */
    private static function desdeLista(int $listaId, array $listas): int
    {
        if ($listaId <= 0) {
            return PHP_INT_MAX;
        }
        foreach ($listas as $lista) {
            if ((int) $lista['id'] === $listaId) {
                return (int) $lista['desde'];
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<int, int>  $tipoPorArticulo
     * @param  list<array{id: int, tiponumeracion_id: int, desde: int, hasta: int}>  $listas
     * @param  array<int, array<int, array{g: ?array{fecha: string, precio: float}, c: array<int, array{fecha: string, precio: float}>}>>  $indice
     */
    public static function precioDeFila(
        array $fila,
        array $tipoPorArticulo,
        array $listas,
        array $indice,
    ): ?float {
        $articuloId = (int) ($fila['articulo_id'] ?? 0);
        if ($articuloId <= 0) {
            return null;
        }

        $tipo = (int) ($tipoPorArticulo[$articuloId] ?? 0);
        if ($tipo <= 0 || $listas === []) {
            return null;
        }

        $combinacionId = (int) ($fila['combinacion_id'] ?? 0);
        $visto = [];
        foreach (self::tallesPorCantidad($fila['medidas'] ?? []) as $talle) {
            if (isset($visto[$talle])) {
                continue;
            }
            $visto[$talle] = true;
            $listaId = self::listaParaTalle($talle, $tipo, $listas);
            if ($listaId <= 0) {
                continue;
            }
            $precio = self::elegirVigente($indice[$articuloId][$listaId] ?? null, $combinacionId);
            if ($precio !== null && $precio > 0) {
                return $precio;
            }
        }

        return null;
    }

    /**
     * @param  list<array{medida?: mixed, cantidad?: mixed}>|array<string, mixed>  $medidas
     * @return list<string>
     */
    public static function tallesPorCantidad(array $medidas): array
    {
        $pares = [];
        foreach ($medidas as $clave => $item) {
            if (is_array($item)) {
                $nombre = trim((string) ($item['medida'] ?? ''));
                $cant = abs((float) ($item['cantidad'] ?? 0));
            } else {
                $nombre = trim((string) $clave);
                $cant = abs((float) $item);
            }
            if ($nombre === '' || $cant < 0.0001) {
                continue;
            }
            $pares[] = ['talle' => $nombre, 'cant' => $cant];
        }
        usort($pares, static function (array $a, array $b): int {
            if ($a['cant'] === $b['cant']) {
                return 0;
            }

            return $a['cant'] < $b['cant'] ? 1 : -1;
        });

        $out = [];
        foreach ($pares as $par) {
            $out[] = $par['talle'];
        }

        return $out;
    }

    /**
     * @param  list<array{id: int, tiponumeracion_id: int, desde: int, hasta: int}>  $listas
     */
    public static function listaParaTalle(string $talle, int $tipoNumeracionId, array $listas): int
    {
        if ($tipoNumeracionId <= 0 || ! preg_match('/^\d+$/', trim($talle))) {
            return 0;
        }
        $n = (int) $talle;
        foreach ($listas as $lista) {
            if ((int) $lista['tiponumeracion_id'] !== $tipoNumeracionId) {
                continue;
            }
            if ($n >= (int) $lista['desde'] && $n <= (int) $lista['hasta']) {
                return (int) $lista['id'];
            }
        }

        return 0;
    }

    /**
     * @param  array{g: ?array{fecha: string, precio: float}, c: array<int, array{fecha: string, precio: float}>}|null  $bucket
     */
    public static function elegirVigente(?array $bucket, int $combinacionId): ?float
    {
        if ($bucket === null) {
            return null;
        }

        $generico = $bucket['g'] ?? null;
        $combinacion = ($combinacionId > 0) ? ($bucket['c'][$combinacionId] ?? null) : null;

        if ($generico && (! $combinacion || $generico['fecha'] > $combinacion['fecha'])) {
            return (float) $generico['precio'];
        }
        if ($combinacion) {
            return (float) $combinacion['precio'];
        }
        if ($generico) {
            return (float) $generico['precio'];
        }

        return null;
    }

    /**
     * @param  list<array{articulo_id: int, combinacion_id: int, listaprecio_id: int, fechavigencia: string, precio: float, id: int}>  $filas
     * @return array<int, array<int, array{g: ?array{fecha: string, precio: float}, c: array<int, array{fecha: string, precio: float}>}>>
     */
    public static function indexarPrecios(array $filas): array
    {
        $indice = [];
        $ids = [];
        foreach ($filas as $fila) {
            $articuloId = (int) $fila['articulo_id'];
            $listaId = (int) $fila['listaprecio_id'];
            $precio = (float) $fila['precio'];
            if ($articuloId <= 0 || $listaId <= 0 || $precio <= 0) {
                continue;
            }
            $fecha = self::fechaYmd((string) $fila['fechavigencia']);
            $id = (int) ($fila['id'] ?? 0);
            $comb = (int) $fila['combinacion_id'];
            if (! isset($indice[$articuloId][$listaId])) {
                $indice[$articuloId][$listaId] = ['g' => null, 'c' => []];
            }
            $claveId = $comb > 0 ? 'c:'.$comb : 'g';
            $anterior = $ids[$articuloId][$listaId][$claveId] ?? -1;
            $actual = $indice[$articuloId][$listaId];
            $vigente = $comb > 0 ? ($actual['c'][$comb] ?? null) : $actual['g'];
            if ($vigente !== null && ($fecha < $vigente['fecha'] || ($fecha === $vigente['fecha'] && $id <= $anterior))) {
                continue;
            }
            $ids[$articuloId][$listaId][$claveId] = $id;
            $celda = ['fecha' => $fecha, 'precio' => $precio];
            if ($comb > 0) {
                $indice[$articuloId][$listaId]['c'][$comb] = $celda;
            } else {
                $indice[$articuloId][$listaId]['g'] = $celda;
            }
        }

        return $indice;
    }

    /**
     * @return list<array{id: int, tiponumeracion_id: int, desde: int, hasta: int}>
     */
    private static function listasFabrica(): array
    {
        $out = [];
        $filas = DB::table('listaprecio')
            ->orderBy('id')
            ->get(['id', 'tiponumeracion_id', 'desdetalle', 'hastatalle']);
        foreach ($filas as $fila) {
            $desde = trim((string) ($fila->desdetalle ?? ''));
            $hasta = trim((string) ($fila->hastatalle ?? ''));
            if (! preg_match('/^\d+$/', $desde) || ! preg_match('/^\d+$/', $hasta)) {
                continue;
            }
            $out[] = [
                'id' => (int) $fila->id,
                'tiponumeracion_id' => (int) ($fila->tiponumeracion_id ?? 0),
                'desde' => (int) $desde,
                'hasta' => (int) $hasta,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, int>
     */
    private static function tipoNumeracionPorArticulo(array $articuloIds): array
    {
        $out = [];
        $filas = DB::table('articulo')
            ->join('linea', 'linea.id', '=', 'articulo.linea_id')
            ->whereIn('articulo.id', $articuloIds)
            ->get(['articulo.id as articulo_id', 'linea.tiponumeracion_id']);
        foreach ($filas as $fila) {
            $out[(int) $fila->articulo_id] = (int) ($fila->tiponumeracion_id ?? 0);
        }

        return $out;
    }

    /**
     * @param  list<int>  $articuloIds
     * @return list<array{articulo_id: int, combinacion_id: int, listaprecio_id: int, fechavigencia: string, precio: float, id: int}>
     */
    private static function filasPrecio(array $articuloIds, string $fecha): array
    {
        $out = [];
        $filas = DB::table('precio')
            ->whereIn('articulo_id', $articuloIds)
            ->where('fechavigencia', '<=', $fecha)
            ->where('precio', '>', 0)
            ->get(['id', 'articulo_id', 'combinacion_id', 'listaprecio_id', 'fechavigencia', 'precio']);
        foreach ($filas as $fila) {
            $out[] = [
                'id' => (int) $fila->id,
                'articulo_id' => (int) $fila->articulo_id,
                'combinacion_id' => (int) ($fila->combinacion_id ?? 0),
                'listaprecio_id' => (int) $fila->listaprecio_id,
                'fechavigencia' => self::fechaYmd((string) $fila->fechavigencia),
                'precio' => (float) $fila->precio,
            ];
        }

        return $out;
    }

    private static function fechaYmd(?string $fecha): string
    {
        $texto = trim((string) $fecha);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $texto, $m)) {
            return $m[1];
        }

        return date('Y-m-d');
    }
}
