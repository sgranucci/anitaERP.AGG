<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;

/**
 * Kilos por artículo de Surmar en un período.
 *
 * Remito (REM) y alta de producción (AP): el kilo es la cantidad del movimiento
 * de stock (entrada o salida). Recepción Anita (COM): la cantidad del movimiento
 * es la pieza y el kilo es el peso neto de la etiqueta. Las recepciones cargadas
 * en anitaERP (RCING) ya traen el kilo en la cantidad del movimiento.
 */
final class KilosArticuloSurmarSupport
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array<string, float|int>,
     *   advertencias: list<string>
     * }
     */
    public static function consultar(array $filtros): array
    {
        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
        $fechaHasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($fechaDesde === '' || $fechaHasta === '') {
            throw new \InvalidArgumentException('Indique desde y hasta fecha.');
        }
        if ($fechaDesde > $fechaHasta) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        $tipo = (string) ($filtros['tipo'] ?? KilosArticuloSurmarFiltros::TIPO_TODOS);
        $abreviaturas = self::abreviaturas($tipo);
        $empresaId = SurmarSupport::EMPRESA_ID;
        $nombreEmpresa = trim((string) DB::table('empresa')->where('id', $empresaId)->value('nombre'));

        $grupos = DB::table('articulo_movimiento as am')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->join('tipotransaccion_stock as ts', 'ts.id', '=', 'am.tipotransaccion_stock_id')
            ->join('depmae as d', 'd.id', '=', 'am.deposito_id')
            ->where('d.empresa_id', $empresaId)
            ->where('am.fecha', '>=', $fechaDesde)
            ->where('am.fecha', '<=', $fechaHasta)
            ->whereIn('ts.abreviatura', $abreviaturas)
            ->when(($filtros['desde_sku'] ?? '') !== '', function ($q) use ($filtros) {
                $q->where('a.sku', '>=', $filtros['desde_sku']);
            })
            ->when(($filtros['hasta_sku'] ?? '') !== '', function ($q) use ($filtros) {
                $q->where('a.sku', '<=', $filtros['hasta_sku']);
            })
            ->groupBy('a.id', 'a.sku', 'a.descripcion', 'ts.abreviatura')
            ->selectRaw('a.id as articulo_id, a.sku, a.descripcion, ts.abreviatura')
            ->selectRaw('SUM(CASE WHEN am.cantidad > 0 THEN am.cantidad ELSE 0 END) as entrada')
            ->selectRaw('SUM(CASE WHEN am.cantidad < 0 THEN ABS(am.cantidad) ELSE 0 END) as salida')
            ->get();

        $filas = [];
        foreach ($grupos as $grupo) {
            $id = (int) $grupo->articulo_id;
            if (! isset($filas[$id])) {
                $filas[$id] = self::filaVacia($id, (string) $grupo->sku, (string) $grupo->descripcion, $nombreEmpresa);
            }
            $abrev = strtoupper(trim((string) $grupo->abreviatura));
            $entrada = (float) $grupo->entrada;
            $salida = (float) $grupo->salida;
            if ($abrev === 'REM') {
                $filas[$id]['entrada'] += $entrada;
                $filas[$id]['salida'] += $salida;
                $filas[$id]['kilos_rem'] += $salida - $entrada;
            } elseif ($abrev === 'AP') {
                $filas[$id]['entrada'] += $entrada;
                $filas[$id]['salida'] += $salida;
                $filas[$id]['kilos_ap'] += $entrada - $salida;
            } elseif ($abrev === 'COM') {
                $filas[$id]['piezas_com'] += $entrada - $salida;
            } elseif ($abrev === 'RCING') {
                $filas[$id]['kilos_com'] += $entrada - $salida;
            }
        }

        $advertencias = [];
        if (in_array('COM', $abreviaturas, true)) {
            $kilosEtiqueta = self::kilosEtiquetaCom($empresaId, $fechaDesde, $fechaHasta, $filtros, $advertencias);
            foreach ($kilosEtiqueta as $articuloId => $kilos) {
                if (! isset($filas[$articuloId])) {
                    continue;
                }
                $filas[$articuloId]['kilos_com'] += $kilos;
            }
        }

        if (in_array('RCING', $abreviaturas, true)) {
            foreach (self::piezasYRechazoRcing($empresaId, $fechaDesde, $fechaHasta, $filtros) as $articuloId => $extra) {
                if (! isset($filas[$articuloId])) {
                    continue;
                }
                $filas[$articuloId]['piezas_com'] += $extra['piezas'];
                $filas[$articuloId]['rechazo'] += $extra['rechazo'];
            }
        }

        $filas = array_values(array_filter($filas, static function (array $fila) use ($tipo): bool {
            return match ($tipo) {
                KilosArticuloSurmarFiltros::TIPO_REM => abs($fila['kilos_rem']) > 0.0001 || $fila['entrada'] > 0.0001 || $fila['salida'] > 0.0001,
                KilosArticuloSurmarFiltros::TIPO_AP => abs($fila['kilos_ap']) > 0.0001 || $fila['entrada'] > 0.0001 || $fila['salida'] > 0.0001,
                KilosArticuloSurmarFiltros::TIPO_COM => abs($fila['piezas_com']) > 0.0001 || abs($fila['kilos_com']) > 0.0001 || abs($fila['rechazo']) > 0.0001,
                default => abs($fila['kilos_rem']) > 0.0001
                    || abs($fila['kilos_ap']) > 0.0001
                    || abs($fila['piezas_com']) > 0.0001
                    || abs($fila['kilos_com']) > 0.0001,
            };
        }));

        usort($filas, static fn (array $a, array $b): int => strcmp((string) $a['sku'], (string) $b['sku']));

        return [
            'filas' => $filas,
            'totales' => self::totales($filas),
            'advertencias' => $advertencias,
        ];
    }

    /**
     * @return list<string>
     */
    public static function abreviaturas(string $tipo): array
    {
        return match ($tipo) {
            KilosArticuloSurmarFiltros::TIPO_REM => ['REM'],
            KilosArticuloSurmarFiltros::TIPO_AP => ['AP'],
            KilosArticuloSurmarFiltros::TIPO_COM => ['COM', 'RCING'],
            default => ['REM', 'AP', 'COM', 'RCING'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaVacia(int $id, string $sku, string $descripcion, string $nombreEmpresa): array
    {
        return [
            'articulo_id' => $id,
            'sku' => $sku,
            'descripcion' => $descripcion,
            'nombreempresa' => $nombreEmpresa,
            'entrada' => 0.0,
            'salida' => 0.0,
            'kilos_rem' => 0.0,
            'kilos_ap' => 0.0,
            'piezas_com' => 0.0,
            'kilos_com' => 0.0,
            'rechazo' => 0.0,
        ];
    }

    /**
     * @param  list<string>  $advertencias
     * @param  array<string, mixed>  $filtros
     * @return array<int, float>
     */
    private static function kilosEtiquetaCom(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        array $filtros,
        array &$advertencias,
    ): array {
        $movimientos = DB::table('articulo_movimiento as am')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->join('tipotransaccion_stock as ts', 'ts.id', '=', 'am.tipotransaccion_stock_id')
            ->join('depmae as d', 'd.id', '=', 'am.deposito_id')
            ->where('d.empresa_id', $empresaId)
            ->where('ts.abreviatura', 'COM')
            ->where('am.fecha', '>=', $fechaDesde)
            ->where('am.fecha', '<=', $fechaHasta)
            ->when(($filtros['desde_sku'] ?? '') !== '', function ($q) use ($filtros) {
                $q->where('a.sku', '>=', $filtros['desde_sku']);
            })
            ->when(($filtros['hasta_sku'] ?? '') !== '', function ($q) use ($filtros) {
                $q->where('a.sku', '<=', $filtros['hasta_sku']);
            })
            ->get(['am.articulo_id', 'a.sku', 'am.concepto']);

        $claves = [];
        $nros = [];
        $ids = [];
        foreach ($movimientos as $mov) {
            $partes = self::partesConcepto((string) $mov->concepto);
            if ($partes === null) {
                continue;
            }
            $articuloId = (int) $mov->articulo_id;
            $claves[self::clave($articuloId, $partes)] = true;
            $nros[$partes['nro']] = true;
            $ids[$articuloId] = (string) $mov->sku;
        }
        if ($claves === []) {
            return [];
        }

        $kilos = [];
        $sospechosas = [];
        $etiquetas = DB::table('stock_etiqueta')
            ->where('empresa_id', $empresaId)
            ->where('anita_tipo', 'COM')
            ->whereIn('articulo_id', array_keys($ids))
            ->whereIn('anita_nro', array_keys($nros))
            ->get([
                'id',
                'articulo_id',
                'anita_letra',
                'anita_sucursal',
                'anita_nro',
                'anita_orden',
                'peso_bruto',
                'peso_neto',
            ]);

        foreach ($etiquetas as $etiqueta) {
            $articuloId = (int) $etiqueta->articulo_id;
            $clave = self::clave($articuloId, [
                'letra' => strtoupper(trim((string) $etiqueta->anita_letra)),
                'suc' => (int) $etiqueta->anita_sucursal,
                'nro' => (int) $etiqueta->anita_nro,
                'orden' => (int) $etiqueta->anita_orden,
            ]);
            if (! isset($claves[$clave])) {
                continue;
            }
            $neto = (float) $etiqueta->peso_neto;
            $bruto = (float) $etiqueta->peso_bruto;
            $kilos[$articuloId] = ($kilos[$articuloId] ?? 0.0) + $neto;
            if ($bruto > 0 && $neto > ($bruto * 3) && $neto > 1000) {
                $sku = $ids[$articuloId] ?? (string) $articuloId;
                $sospechosas[$sku] = true;
            }
        }

        if ($sospechosas !== []) {
            $lista = implode(', ', array_keys($sospechosas));
            $advertencias[] = 'Hay etiquetas de recepción con peso neto muy por encima del peso bruto ('
                .$lista
                .'). Esos kilos quedan como están cargados en la etiqueta.';
        }

        return $kilos;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array{piezas: float, rechazo: float}>
     */
    private static function piezasYRechazoRcing(int $empresaId, string $fechaDesde, string $fechaHasta, array $filtros): array
    {
        $rows = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('articulo_movimiento as am', 'am.id', '=', 'rpa.articulo_movimiento_id')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->join('tipotransaccion_stock as ts', 'ts.id', '=', 'am.tipotransaccion_stock_id')
            ->join('depmae as d', 'd.id', '=', 'am.deposito_id')
            ->where('d.empresa_id', $empresaId)
            ->where('ts.abreviatura', 'RCING')
            ->where('am.fecha', '>=', $fechaDesde)
            ->where('am.fecha', '<=', $fechaHasta)
            ->when(($filtros['desde_sku'] ?? '') !== '', function ($q) use ($filtros) {
                $q->where('a.sku', '>=', $filtros['desde_sku']);
            })
            ->when(($filtros['hasta_sku'] ?? '') !== '', function ($q) use ($filtros) {
                $q->where('a.sku', '<=', $filtros['hasta_sku']);
            })
            ->groupBy('rpa.articulo_id')
            ->selectRaw('rpa.articulo_id as articulo_id')
            ->selectRaw('SUM(COALESCE(rpa.cant_pieza, 0)) as piezas')
            ->selectRaw('SUM(COALESCE(rpa.cantidad_rechazada, 0)) as rechazo')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->articulo_id] = [
                'piezas' => (float) $row->piezas,
                'rechazo' => (float) $row->rechazo,
            ];
        }

        return $out;
    }

    /**
     * @param  array{letra: string, suc: int, nro: int, orden: int}  $partes
     */
    private static function clave(int $articuloId, array $partes): string
    {
        return $articuloId.'|'.$partes['letra'].'|'.$partes['suc'].'|'.$partes['nro'].'|'.$partes['orden'];
    }

    /**
     * @return array{letra: string, suc: int, nro: int, orden: int}|null
     */
    private static function partesConcepto(string $concepto): ?array
    {
        $partes = explode('|', $concepto);
        if (count($partes) < 6 || strtoupper(trim($partes[1])) !== 'COM') {
            return null;
        }

        return [
            'letra' => strtoupper(trim($partes[2])),
            'suc' => (int) $partes[3],
            'nro' => (int) $partes[4],
            'orden' => (int) $partes[5],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float|int>
     */
    private static function totales(array $filas): array
    {
        $totales = [
            'articulos' => count($filas),
            'entrada' => 0.0,
            'salida' => 0.0,
            'kilos_rem' => 0.0,
            'kilos_ap' => 0.0,
            'piezas_com' => 0.0,
            'kilos_com' => 0.0,
            'rechazo' => 0.0,
        ];
        foreach ($filas as $fila) {
            foreach (['entrada', 'salida', 'kilos_rem', 'kilos_ap', 'piezas_com', 'kilos_com', 'rechazo'] as $clave) {
                $totales[$clave] += (float) $fila[$clave];
            }
        }

        return $totales;
    }
}
