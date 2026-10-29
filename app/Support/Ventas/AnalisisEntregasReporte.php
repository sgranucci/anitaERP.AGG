<?php

namespace App\Support\Ventas;

use App\Support\Ventas\PedidoEstadosInterforming as Estados;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Análisis de entregas y pendientes (l-analisisent.c).
 * Importes en la moneda del ítem. El fason parte la línea en virgen y fason.
 */
final class AnalisisEntregasReporte
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public static function filas(array $filtros): array
    {
        $detalle = [];
        foreach (self::consultar($filtros) as $row) {
            if (! self::pasaEstadoYSituacion($row, $filtros)) {
                continue;
            }
            foreach (self::expandirFason($row, $filtros) as $linea) {
                $detalle[] = $linea;
            }
        }

        return self::conCortes($detalle, (string) ($filtros['orden'] ?? AnalisisEntregasFiltros::ORDEN_PEDIDO), (string) ($filtros['modo'] ?? AnalisisEntregasFiltros::MODO_DETALLE));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{lineas: int, monedas: list<array{moneda: string, subtotal: float, descuento: float, total: float, pendiente: float, kilos: float}>}
     */
    public static function resumen(array $filas): array
    {
        $monedas = [];
        $lineas = 0;
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? '') !== 'detalle') {
                continue;
            }
            $lineas++;
            $mon = (string) ($fila['moneda'] ?? '');
            if (! isset($monedas[$mon])) {
                $monedas[$mon] = [
                    'moneda' => $mon,
                    'subtotal' => 0.0,
                    'descuento' => 0.0,
                    'total' => 0.0,
                    'pendiente' => 0.0,
                    'kilos' => 0.0,
                ];
            }
            $monedas[$mon]['subtotal'] += (float) $fila['subtotal'];
            $monedas[$mon]['descuento'] += (float) $fila['descuento'];
            $monedas[$mon]['total'] += (float) $fila['total'];
            $monedas[$mon]['pendiente'] += (float) $fila['importe_pendiente'];
            $monedas[$mon]['kilos'] += (float) $fila['cant_kg'];
        }

        return [
            'lineas' => $lineas,
            'monedas' => array_values($monedas),
        ];
    }

    /**
     * @param  array{lineas?: int, monedas?: list<array<string, mixed>>}  $resumen
     */
    public static function resumenTexto(array $resumen): string
    {
        $partes = ['Líneas: '.number_format((int) ($resumen['lineas'] ?? 0), 0, ',', '.')];
        foreach ($resumen['monedas'] ?? [] as $moneda) {
            $sigla = (string) ($moneda['moneda'] ?? '');
            $partes[] = 'Total '.$sigla.' '.number_format((float) ($moneda['total'] ?? 0), 2, ',', '.');
            $partes[] = 'Pendiente '.$sigla.' '.number_format((float) ($moneda['pendiente'] ?? 0), 2, ',', '.');
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public static function paginar(array $filas, int $perPage, int $page, array $query): LengthAwarePaginator
    {
        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);
        $total = count($filas);
        $slice = array_slice($filas, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => $query,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<object>
     */
    private static function consultar(array $filtros): array
    {
        $q = DB::table('pedido as p')
            ->join('pedido_articulo as i', 'i.pedido_id', '=', 'p.id')
            ->join('cliente as c', 'c.id', '=', 'p.cliente_id')
            ->join('articulo as a', 'a.id', '=', 'i.articulo_id')
            ->leftJoin('vendedor as v', 'v.id', '=', 'p.vendedor_id')
            ->leftJoin('unidadmedida as u', 'u.id', '=', 'i.unidadmedida_id')
            ->leftJoin('moneda as m', 'm.id', '=', 'i.moneda_id')
            ->leftJoin('moneda as mf', 'mf.id', '=', 'i.moneda_fason_id')
            ->leftJoin('motivocierrepedido as mc', 'mc.id', '=', 'i.motivocierrepedido_id')
            ->where('a.sku', 'not like', 'texto%')
            ->select([
                'p.id as pedido_id',
                'p.fecha as fecha_pedido',
                'p.fechaentrega as fecha_entrega_cab',
                'p.descuento as dto_cab',
                'p.descuentointegrado as dto_integrado_cab',
                'p.estadopedido',
                'p.estado as estado_erp',
                'p.tipo_comprobante',
                'p.letra_comprobante',
                'p.sucursal_comprobante',
                'p.numero_comprobante',
                'p.vendedor_id',
                'c.id as cliente_id',
                'c.codigo as cliente_codigo',
                'c.nombre as cliente_nombre',
                'v.codigo as vendedor_codigo',
                'v.nombre as vendedor_nombre',
                'a.id as articulo_id',
                'a.sku',
                'a.descripcion as articulo_desc',
                'a.peso',
                'a.coeficienteconversion',
                'i.numeroitem',
                'i.cantidad',
                'i.cantidad_entregada',
                'i.cantidad_alter',
                'i.precio',
                'i.descuento as dto_art',
                'i.descuentointegrado as dto_integrado_item',
                'i.porc_fason',
                'i.precio_fason',
                'i.partida',
                'i.estado as estado_item',
                'i.estado_cierre',
                'i.fechaentrega as fecha_entrega_item',
                'u.abreviatura as umd',
                'm.abreviatura as moneda',
                'mf.abreviatura as moneda_fason',
                'mc.nombre as motivo_cierre',
            ]);

        self::aplicarFechas($q, 'p.fecha', $filtros['fecha_pedido_desde'] ?? '', $filtros['fecha_pedido_hasta'] ?? '');
        self::aplicarFechaEntrega($q, $filtros);
        self::aplicarId($q, 'p.cliente_id', (int) ($filtros['cliente_id'] ?? 0));
        self::aplicarId($q, 'i.articulo_id', (int) ($filtros['articulo_id'] ?? 0));
        self::aplicarId($q, 'p.vendedor_id', (int) ($filtros['vendedor_id'] ?? 0));

        if ($filtros['sucursal_desde'] !== null) {
            $q->where('p.sucursal_comprobante', '>=', (int) $filtros['sucursal_desde']);
        }
        if ($filtros['sucursal_hasta'] !== null) {
            $q->where('p.sucursal_comprobante', '<=', (int) $filtros['sucursal_hasta']);
        }

        $orden = (string) ($filtros['orden'] ?? AnalisisEntregasFiltros::ORDEN_PEDIDO);
        if ($orden === AnalisisEntregasFiltros::ORDEN_CLIENTE) {
            $q->orderBy('c.codigo')->orderByDesc('p.fecha')->orderByDesc('p.numero_comprobante')->orderBy('i.numeroitem');
        } elseif ($orden === AnalisisEntregasFiltros::ORDEN_ARTICULO) {
            $q->orderBy('a.sku')->orderByDesc('p.fecha')->orderBy('i.numeroitem');
        } elseif ($orden === AnalisisEntregasFiltros::ORDEN_VENDEDOR) {
            $q->orderBy('v.codigo')->orderBy('c.codigo')->orderByDesc('p.fecha')->orderBy('i.numeroitem');
        } else {
            $q->orderByDesc('p.numero_comprobante')->orderBy('p.letra_comprobante')->orderBy('i.numeroitem');
        }

        return $q->get()->all();
    }

    private static function aplicarFechas($q, string $columna, string $desde, string $hasta): void
    {
        if ($desde !== '') {
            $q->where($columna, '>=', $desde.' 00:00:00');
        }
        if ($hasta !== '') {
            $q->where($columna, '<=', $hasta.' 23:59:59');
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function aplicarFechaEntrega($q, array $filtros): void
    {
        $desde = (string) ($filtros['fecha_entrega_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_entrega_hasta'] ?? '');
        if ($desde === '' && $hasta === '') {
            return;
        }

        $q->where(function ($w) use ($desde, $hasta) {
            $w->where(function ($item) use ($desde, $hasta) {
                $item->whereNotNull('i.fechaentrega');
                if ($desde !== '') {
                    $item->where('i.fechaentrega', '>=', $desde);
                }
                if ($hasta !== '') {
                    $item->where('i.fechaentrega', '<=', $hasta);
                }
            })->orWhere(function ($cab) use ($desde, $hasta) {
                $cab->whereNull('i.fechaentrega');
                if ($desde !== '') {
                    $cab->where('p.fechaentrega', '>=', $desde.' 00:00:00');
                }
                if ($hasta !== '') {
                    $cab->where('p.fechaentrega', '<=', $hasta.' 23:59:59');
                }
            });
        });
    }

    private static function aplicarId($q, string $columna, int $id): void
    {
        if ($id > 0) {
            $q->where($columna, $id);
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function pasaEstadoYSituacion(object $row, array $filtros): bool
    {
        $cab = Estados::normalizarCodigoCabecera(
            (string) ($row->estadopedido ?? ''),
            (string) ($row->estado_erp ?? '')
        );
        if ($cab === Estados::CAB_SUSPENDIDO) {
            return false;
        }

        $estadoFiltro = (string) ($filtros['estado'] ?? AnalisisEntregasFiltros::ESTADO_TODOS);
        if ($estadoFiltro !== AnalisisEntregasFiltros::ESTADO_ANULADO && $cab === Estados::CAB_ANULADO) {
            return false;
        }

        $cantidad = (float) $row->cantidad;
        $entregada = (float) $row->cantidad_entregada;
        $cerrado = strtoupper(trim((string) $row->estado_cierre)) === 'C';
        $letra = strtoupper(trim((string) $row->estado_item));

        $ok = match ($estadoFiltro) {
            AnalisisEntregasFiltros::ESTADO_CON_SALDO => $entregada < $cantidad,
            AnalisisEntregasFiltros::ESTADO_APROBADO => $letra === Estados::ITEM_APROBADO,
            AnalisisEntregasFiltros::ESTADO_RECHAZADO => $letra === Estados::ITEM_RECHAZADO,
            AnalisisEntregasFiltros::ESTADO_PRODUCCION => $letra === Estados::ITEM_PRODUCCION,
            AnalisisEntregasFiltros::ESTADO_ENTREGADO => $letra === Estados::ITEM_ENTREGADO,
            AnalisisEntregasFiltros::ESTADO_CONDICIONAL => $letra === Estados::ITEM_CONDICIONAL,
            AnalisisEntregasFiltros::ESTADO_ANULADO => $cab === Estados::CAB_ANULADO,
            default => true,
        };
        if (! $ok) {
            return false;
        }

        $saldo = $cantidad - $entregada;

        return match ((string) ($filtros['situacion'] ?? AnalisisEntregasFiltros::SITUACION_TODOS)) {
            AnalisisEntregasFiltros::SITUACION_PENDIENTES => $saldo > 0.009 && ! $cerrado,
            AnalisisEntregasFiltros::SITUACION_CERRADOS => $cerrado,
            AnalisisEntregasFiltros::SITUACION_CUMPLIDOS => ! $cerrado && $saldo <= 0.009,
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private static function expandirFason(object $row, array $filtros): array
    {
        $porc = (float) $row->porc_fason;
        $esFason = (int) $row->partida === Estados::PARTIDA_FASON || abs($porc) > 0.0000001;
        $lineas = [];
        if ($esFason) {
            if (abs($porc - 100.0) > 0.0001) {
                $lineas[] = self::armarLinea($row, $filtros, 'V', 1.0 - ($porc / 100.0));
            }
            $lineas[] = self::armarLinea($row, $filtros, 'F', $porc / 100.0);
        } else {
            $lineas[] = self::armarLinea($row, $filtros, 'V', 1.0);
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private static function armarLinea(object $row, array $filtros, string $material, float $coef): array
    {
        $cantidad = (float) $row->cantidad;
        $entregada = (float) $row->cantidad_entregada;
        $pendiente = $cantidad - $entregada;
        $um = strtoupper(trim((string) $row->umd));
        $peso = (float) $row->peso;
        if (abs($peso) < 0.0000001) {
            $peso = (float) $row->coeficienteconversion;
        }

        if ($um === 'KG' || $um === 'K') {
            $uni = abs($peso) > 0.0000001 ? $cantidad / $peso : $cantidad;
            $kg = $cantidad;
        } elseif ($um !== '') {
            $uni = $cantidad;
            $kg = abs($peso) > 0.0000001 ? $cantidad * $peso : (float) $row->cantidad_alter;
        } else {
            $uni = $cantidad;
            $kg = 0.0;
        }

        $uni *= $coef;
        $kg *= $coef;
        $entregada *= $coef;
        $pendiente *= $coef;

        $precioLista = $material === 'F' ? (float) $row->precio_fason : (float) $row->precio;
        if ($material === 'F' && abs($precioLista) < 0.0000001) {
            $precioLista = PedidoInterformingFasonSupport::precioFason((float) $row->precio, (float) $row->porc_fason);
        }
        $neto = round($precioLista * self::coefDescuento($row), 4);
        $subtotal = round($precioLista * $cantidad * $coef, 2);
        $total = round($neto * $cantidad * $coef, 2);
        $importePend = round($neto * ($cantidad - (float) $row->cantidad_entregada) * $coef, 2);

        $cerrado = strtoupper(trim((string) $row->estado_cierre)) === 'C';
        $letraItem = strtoupper(trim((string) $row->estado_item));
        $estado = match ($letraItem) {
            Estados::ITEM_APROBADO => $cerrado ? 'X' : 'A',
            Estados::ITEM_PENDIENTE => 'P',
            Estados::ITEM_RECHAZADO => 'R',
            Estados::ITEM_PRODUCCION => 'D',
            Estados::ITEM_ENTREGADO => 'E',
            Estados::ITEM_CONDICIONAL => 'C',
            default => $letraItem !== '' ? $letraItem : '',
        };

        $fechaEntrega = self::fechaYmd($row->fecha_entrega_item) ?: self::fechaYmd($row->fecha_entrega_cab);
        $moneda = $material === 'F'
            ? self::monedaTexto($row->moneda_fason ?: $row->moneda)
            : self::monedaTexto($row->moneda);

        $grupo = match ((string) ($filtros['orden'] ?? '')) {
            AnalisisEntregasFiltros::ORDEN_CLIENTE => trim((string) $row->cliente_codigo),
            AnalisisEntregasFiltros::ORDEN_ARTICULO => trim((string) $row->sku),
            AnalisisEntregasFiltros::ORDEN_VENDEDOR => trim((string) ($row->vendedor_codigo ?? '')),
            default => '',
        };
        $grupoTexto = match ((string) ($filtros['orden'] ?? '')) {
            AnalisisEntregasFiltros::ORDEN_CLIENTE => trim((string) $row->cliente_codigo).' '.trim((string) $row->cliente_nombre),
            AnalisisEntregasFiltros::ORDEN_ARTICULO => trim((string) $row->sku).' '.trim((string) $row->articulo_desc),
            AnalisisEntregasFiltros::ORDEN_VENDEDOR => trim((string) ($row->vendedor_codigo ?? '')).' '.trim((string) ($row->vendedor_nombre ?? 'Sin vendedor')),
            default => '',
        };

        return [
            'tipo_fila' => 'detalle',
            'grupo' => $grupo.'|'.$moneda,
            'grupo_texto' => trim($grupoTexto),
            'cliente_id' => (int) $row->cliente_id,
            'cliente_codigo' => trim((string) $row->cliente_codigo),
            'cliente_nombre' => trim((string) $row->cliente_nombre),
            'articulo_id' => (int) $row->articulo_id,
            'articulo_sku' => trim((string) $row->sku),
            'articulo_desc' => trim((string) $row->articulo_desc),
            'tipo' => trim((string) $row->tipo_comprobante),
            'numero' => self::numero($row),
            'pedido_id' => (int) $row->pedido_id,
            'fecha_pedido' => self::dmy($row->fecha_pedido),
            'fecha_entrega' => self::dmy($fechaEntrega),
            'atraso' => self::atraso($fechaEntrega, (string) ($filtros['fecha_parametro'] ?? '')),
            'cant_uni' => $uni,
            'cant_kg' => $kg,
            'umd' => $um,
            'cant_entregada' => $entregada,
            'cant_pendiente' => $pendiente,
            'moneda' => $moneda,
            'precio' => $precioLista,
            'virgen' => $material === 'V' ? $kg : 0.0,
            'fason' => $material === 'F' ? $kg : 0.0,
            'estado' => $estado,
            'motivo' => $cerrado ? trim((string) ($row->motivo_cierre ?? '')) : '',
            'subtotal' => $subtotal,
            'descuento' => round($total - $subtotal, 2),
            'total' => $total,
            'importe_pendiente' => $importePend,
            'etiqueta' => '',
        ];
    }

    private static function coefDescuento(object $row): float
    {
        $coef = (1.0 - ((float) $row->dto_art / 100.0)) * (1.0 - ((float) $row->dto_cab / 100.0));
        foreach ([(string) $row->dto_integrado_item, (string) $row->dto_integrado_cab] as $texto) {
            if (! preg_match_all('/\d+(?:[.,]\d+)?/', $texto, $hallados)) {
                continue;
            }
            foreach ($hallados[0] as $numero) {
                $porc = (float) str_replace(',', '.', $numero);
                if ($porc > 0.0 && $porc < 100.0) {
                    $coef *= (1.0 - ($porc / 100.0));
                }
            }
        }

        return $coef;
    }

    /**
     * @param  list<array<string, mixed>>  $detalle
     * @return list<array<string, mixed>>
     */
    private static function conCortes(array $detalle, string $orden, string $modo): array
    {
        $conGrupo = $orden !== AnalisisEntregasFiltros::ORDEN_PEDIDO;
        $salida = [];
        $actual = null;
        $acumulado = self::acumuladorVacio();
        $general = [];

        $volcarGrupo = function () use (&$salida, &$actual, &$acumulado, $modo): void {
            if ($actual === null) {
                return;
            }
            $salida[] = self::filaCorte('subtotal', 'Total '.$actual, $acumulado);
            if ($modo === AnalisisEntregasFiltros::MODO_TOTALES) {
                return;
            }
        };

        foreach ($detalle as $linea) {
            $mon = (string) $linea['moneda'];
            if (! isset($general[$mon])) {
                $general[$mon] = self::acumuladorVacio();
            }
            self::sumar($general[$mon], $linea);

            if ($conGrupo) {
                $clave = (string) $linea['grupo'];
                if ($actual !== null && $clave !== $acumulado['clave']) {
                    $volcarGrupo();
                    $acumulado = self::acumuladorVacio();
                }
                $actual = (string) $linea['grupo_texto'].' '.$mon;
                $acumulado['clave'] = $clave;
                self::sumar($acumulado, $linea);
                if ($modo === AnalisisEntregasFiltros::MODO_DETALLE) {
                    $salida[] = $linea;
                }
            } elseif ($modo === AnalisisEntregasFiltros::MODO_DETALLE) {
                $salida[] = $linea;
            }
        }

        if ($conGrupo && $actual !== null && self::tieneImporte($acumulado)) {
            $salida[] = self::filaCorte('subtotal', 'Total '.$actual, $acumulado);
        }

        foreach ($general as $mon => $tot) {
            $salida[] = self::filaCorte('total', 'Total general '.$mon, $tot);
        }

        if ($modo === AnalisisEntregasFiltros::MODO_TOTALES && $conGrupo) {
            return $salida;
        }

        return $salida;
    }

    /**
     * @return array{clave: string, cant_uni: float, cant_kg: float, cant_entregada: float, cant_pendiente: float, virgen: float, fason: float, subtotal: float, descuento: float, total: float, importe_pendiente: float, moneda: string}
     */
    private static function acumuladorVacio(): array
    {
        return [
            'clave' => '',
            'cant_uni' => 0.0,
            'cant_kg' => 0.0,
            'cant_entregada' => 0.0,
            'cant_pendiente' => 0.0,
            'virgen' => 0.0,
            'fason' => 0.0,
            'subtotal' => 0.0,
            'descuento' => 0.0,
            'total' => 0.0,
            'importe_pendiente' => 0.0,
            'moneda' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $acum
     * @param  array<string, mixed>  $linea
     */
    private static function sumar(array &$acum, array $linea): void
    {
        foreach (['cant_uni', 'cant_kg', 'cant_entregada', 'cant_pendiente', 'virgen', 'fason', 'subtotal', 'descuento', 'total', 'importe_pendiente'] as $clave) {
            $acum[$clave] += (float) $linea[$clave];
        }
        $acum['moneda'] = (string) $linea['moneda'];
    }

    /**
     * @param  array<string, mixed>  $acum
     */
    private static function tieneImporte(array $acum): bool
    {
        return abs((float) $acum['cant_kg']) > 0.0000001
            || abs((float) $acum['total']) > 0.0000001
            || abs((float) $acum['cant_uni']) > 0.0000001;
    }

    /**
     * @param  array<string, mixed>  $acum
     * @return array<string, mixed>
     */
    private static function filaCorte(string $tipo, string $etiqueta, array $acum): array
    {
        return [
            'tipo_fila' => $tipo,
            'grupo' => '',
            'grupo_texto' => '',
            'cliente_id' => 0,
            'cliente_codigo' => '',
            'cliente_nombre' => $etiqueta,
            'articulo_id' => 0,
            'articulo_sku' => '',
            'articulo_desc' => '',
            'tipo' => '',
            'numero' => '',
            'pedido_id' => 0,
            'fecha_pedido' => '',
            'fecha_entrega' => '',
            'atraso' => null,
            'cant_uni' => $acum['cant_uni'],
            'cant_kg' => $acum['cant_kg'],
            'umd' => '',
            'cant_entregada' => $acum['cant_entregada'],
            'cant_pendiente' => $acum['cant_pendiente'],
            'moneda' => $acum['moneda'],
            'precio' => null,
            'virgen' => $acum['virgen'],
            'fason' => $acum['fason'],
            'estado' => '',
            'motivo' => '',
            'subtotal' => round((float) $acum['subtotal'], 2),
            'descuento' => round((float) $acum['descuento'], 2),
            'total' => round((float) $acum['total'], 2),
            'importe_pendiente' => round((float) $acum['importe_pendiente'], 2),
            'etiqueta' => $etiqueta,
        ];
    }

    private static function numero(object $row): string
    {
        $letra = trim((string) $row->letra_comprobante);
        if ($letra === '') {
            $letra = 'X';
        }

        return $letra
            .sprintf('%04d', (int) $row->sucursal_comprobante)
            .'-'
            .sprintf('%08d', (int) $row->numero_comprobante);
    }

    private static function monedaTexto(mixed $abreviatura): string
    {
        $texto = trim((string) $abreviatura);

        return $texto !== '' ? $texto : '$';
    }

    private static function fechaYmd(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }
        $dt = date_create($texto);

        return $dt instanceof \DateTimeInterface ? $dt->format('Y-m-d') : '';
    }

    private static function dmy(mixed $valor): string
    {
        $ymd = self::fechaYmd($valor);
        if ($ymd === '') {
            return '';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);

        return $dt instanceof \DateTime ? $dt->format('d/m/Y') : '';
    }

    private static function atraso(string $fechaEntrega, string $fechaParametro): ?int
    {
        if ($fechaEntrega === '' || $fechaParametro === '') {
            return null;
        }
        $entrega = \DateTime::createFromFormat('Y-m-d', $fechaEntrega);
        $parametro = \DateTime::createFromFormat('Y-m-d', $fechaParametro);
        if (! $entrega instanceof \DateTime || ! $parametro instanceof \DateTime) {
            return null;
        }
        $entrega->setTime(0, 0, 0);
        $parametro->setTime(0, 0, 0);

        return (int) round(($entrega->getTimestamp() - $parametro->getTimestamp()) / 86400);
    }
}
