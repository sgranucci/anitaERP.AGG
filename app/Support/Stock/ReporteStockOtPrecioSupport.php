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

        foreach ($filas as $i => $fila) {
            $precio = self::precioDeFila($fila, $tipoPorArticulo, $listas, $indice);
            if ($precio !== null && $precio > 0) {
                $filas[$i]['precio'] = $precio;
            }
        }

        return $filas;
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
