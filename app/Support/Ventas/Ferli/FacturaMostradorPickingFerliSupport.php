<?php

namespace App\Support\Ventas\Ferli;

use App\Models\Ventas\Pedido_Combinacion;
use App\Services\Stock\PrecioServiceFerli;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\PedidoEstadoCabeceraSupport;
use App\Support\Ventas\PedidoPickingFerliSupport;
use RuntimeException;

/**
 * Ferli, facturación mostrador: un renglón puede traer una línea de picking pendiente.
 * Al emitir se marca facturada y se consume el stock igual que la factura de picking.
 */
final class FacturaMostradorPickingFerliSupport
{
    /** @var array<int, array<int, true>> */
    private static array $gruposVistos = [];

    /** @var array<int, array{n: int, picking: string, pedido: string}> */
    private static array $gruposEsperados = [];

    /** @var array<string, array<string, mixed>> */
    private static array $armadoCache = [];

    public static function reiniciarSeguimiento(): void
    {
        self::$gruposVistos = [];
        self::$gruposEsperados = [];
        self::$armadoCache = [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarPendientes(int $clienteId, string $texto): array
    {
        if (! EntornoEmpresaSupport::esFerli() || $clienteId <= 0) {
            return [];
        }

        $texto = trim($texto);
        $query = Pedido_Combinacion::query()
            ->with([
                'pedidos:id,codigo,cliente_id',
                'articulos:id,sku,descripcion',
                'combinaciones:id,codigo',
                'pickingCabecera:id,codigo',
                'pedido_combinacion_talles',
            ])
            ->where('picking', PedidoPickingFerliSupport::MARCADO)
            ->where(function ($f) {
                $f->whereNull('picking_facturado')
                    ->orWhere('picking_facturado', '<>', PedidoPickingFerliSupport::FACTURADO);
            })
            ->where(function ($e) {
                $e->whereNull('estado')->orWhere('estado', '<>', 'A');
            })
            ->whereHas('pedidos', function ($p) use ($clienteId) {
                $p->where('cliente_id', $clienteId);
            });

        if ($texto !== '') {
            $like = '%'.$texto.'%';
            $query->where(function ($w) use ($like, $texto) {
                $w->where('picking_lote_codigo', 'like', $like)
                    ->orWhereHas('pedidos', function ($p) use ($like) {
                        $p->where('codigo', 'like', $like);
                    })
                    ->orWhereHas('articulos', function ($a) use ($like) {
                        $a->where('sku', 'like', $like)->orWhere('descripcion', 'like', $like);
                    })
                    ->orWhereHas('combinaciones', function ($c) use ($like) {
                        $c->where('codigo', 'like', $like);
                    })
                    ->orWhereHas('pickingCabecera', function ($k) use ($like) {
                        $k->where('codigo', 'like', $like);
                    });
                if (ctype_digit($texto)) {
                    $w->orWhereHas('pickingCabecera', function ($k) use ($texto) {
                        $k->where('codigo', (int) $texto);
                    });
                }
            });
        }

        $filas = [];
        foreach ($query->orderByDesc('id')->limit(120)->get() as $pc) {
            if (! self::pendiente($pc) || (int) ($pc->pedidos->cliente_id ?? 0) !== $clienteId) {
                continue;
            }

            $filas[] = [
                'pedido_combinacion_id' => (int) $pc->id,
                'pedido_id' => (int) ($pc->pedido_id ?? 0),
                'picking_codigo' => (string) ($pc->pickingCabecera->codigo ?? ''),
                'pedido_codigo' => (string) ($pc->pedidos->codigo ?? ''),
                'sku' => (string) ($pc->articulos->sku ?? ''),
                'descripcion' => (string) ($pc->articulos->descripcion ?? ''),
                'combinacion' => (string) ($pc->combinaciones->codigo ?? ''),
                'lote' => (string) ($pc->picking_lote_codigo ?? ''),
                'pares' => self::paresDeLinea($pc),
                'descuento' => round((float) ($pc->descuento ?? 0), 4),
            ];
            if (count($filas) >= 40) {
                break;
            }
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolver(int $pedidoCombinacionId, int $clienteId, string $fecha): array
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return ['error' => 'El picking solo se factura en Ferli.'];
        }

        $armado = self::armar($pedidoCombinacionId, $clienteId, $fecha);
        if (isset($armado['error'])) {
            return $armado;
        }

        return [
            'picking_codigo' => $armado['picking_codigo'],
            'pedido_codigo' => $armado['pedido_codigo'],
            'grupos' => $armado['grupos'],
        ];
    }

    /**
     * Reemplazo de un renglón posteado. Null si la línea no trae picking.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function lineaSiCorresponde(
        array $data,
        int $off,
        int $clienteId,
        string $fecha,
        bool $esPos,
        bool $esNc
    ): ?array {
        if (! EntornoEmpresaSupport::esFerli() || $esPos || $esNc) {
            return null;
        }

        $pcId = (int) ($data['picking_pedido_combinacion_ids'][$off] ?? 0);
        if ($pcId <= 0) {
            return null;
        }

        $otId = (int) ($data['ordentrabajo_ids'][$off] ?? 0);
        $pcOt = (int) ($data['pedido_combinacion_ids'][$off] ?? 0);
        if ($otId > 0 || $pcOt > 0) {
            return ['error' => 'El renglón '.($off + 1).' no puede ser OT y picking a la vez.'];
        }

        $armado = self::armar($pcId, $clienteId, $fecha);
        if (isset($armado['error'])) {
            return $armado;
        }

        $indice = (int) ($data['picking_grupo_indices'][$off] ?? 0);
        if (! isset($armado['grupos'][$indice])) {
            return ['error' => 'El picking '.$armado['picking_codigo'].' no tiene el tramo de precio indicado.'];
        }
        if (isset(self::$gruposVistos[$pcId][$indice])) {
            return ['error' => 'El picking '.$armado['picking_codigo'].' del pedido '.$armado['pedido_codigo'].' está repetido en la factura.'];
        }

        self::$gruposVistos[$pcId][$indice] = true;
        self::$gruposEsperados[$pcId] = [
            'n' => count($armado['grupos']),
            'picking' => (string) $armado['picking_codigo'],
            'pedido' => (string) $armado['pedido_codigo'],
        ];

        return $armado['grupos'][$indice];
    }

    /**
     * @return array{error: string}|null
     */
    public static function errorSiFaltanGrupos(): ?array
    {
        foreach (self::$gruposEsperados as $pcId => $meta) {
            $vistos = count(self::$gruposVistos[$pcId] ?? []);
            if ($vistos !== (int) $meta['n']) {
                return ['error' => 'El picking '.$meta['picking'].' del pedido '.$meta['pedido'].' quedó incompleto. Elegilo de nuevo para traer todos los talles.'];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $dataFactura
     */
    public static function marcarFacturadasYConsumirStock(array $dataFactura, int $ventaId, string $fecha): void
    {
        if (! EntornoEmpresaSupport::esFerli() || $ventaId <= 0) {
            return;
        }

        $pcIds = [];
        foreach ($dataFactura as $item) {
            if (($item['origen_mostrador'] ?? '') !== 'picking') {
                continue;
            }
            $pcId = (int) ($item['pedido_combinacion_id'] ?? 0);
            if ($pcId > 0) {
                $pcIds[$pcId] = $pcId;
            }
        }
        if ($pcIds === []) {
            return;
        }

        $pedidos = [];
        foreach ($pcIds as $pcId) {
            $linea = Pedido_Combinacion::query()->find($pcId);
            if (! $linea || ! self::pendiente($linea)) {
                throw new RuntimeException('La línea de picking ya no se puede facturar.');
            }

            PedidoPickingFerliSupport::marcarFacturado($pcId, $ventaId);
            $linea->refresh();
            PedidoPickingFerliSupport::asegurarConsumoStockPickingAlFacturar(
                $linea,
                $fecha,
                $ventaId
            );

            $pedidoId = (int) ($linea->pedido_id ?? 0);
            if ($pedidoId > 0) {
                $pedidos[$pedidoId] = $pedidoId;
            }
        }

        foreach ($pedidos as $pedidoId) {
            PedidoEstadoCabeceraSupport::refrescar($pedidoId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function armar(int $pedidoCombinacionId, int $clienteId, string $fecha): array
    {
        $claveCache = $pedidoCombinacionId.'|'.$clienteId.'|'.substr(trim($fecha), 0, 10);
        if (isset(self::$armadoCache[$claveCache])) {
            return self::$armadoCache[$claveCache];
        }

        $pc = Pedido_Combinacion::query()
            ->with([
                'articulos',
                'combinaciones',
                'pedidos',
                'pickingCabecera',
                'pedido_combinacion_talles.talles',
            ])
            ->find($pedidoCombinacionId);

        if (! $pc || ! self::pendiente($pc)) {
            return ['error' => 'La línea de picking no está pendiente o ya fue facturada.'];
        }
        if ($clienteId <= 0 || (int) ($pc->pedidos->cliente_id ?? 0) !== $clienteId) {
            return ['error' => 'El picking no es del cliente de la factura.'];
        }

        $lote = trim((string) ($pc->picking_lote_codigo ?? ''));
        if ($lote === '' || $lote === '0') {
            return ['error' => 'La línea de picking no tiene lote/OT.'];
        }

        $articulo = $pc->articulos;
        if (! $articulo) {
            return ['error' => 'La línea de picking no tiene artículo.'];
        }

        $fecha = substr(trim($fecha), 0, 10);
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        $precios = app(PrecioServiceFerli::class);
        $combinacionId = (int) ($pc->combinacion_id ?? 0);
        $grupos = [];

        foreach ($pc->pedido_combinacion_talles as $pct) {
            if ((float) $pct->cantidad == 0.0) {
                continue;
            }
            $talle = $pct->talles;
            if (! $talle) {
                continue;
            }

            $precio = $precios->asignaPrecio($articulo->id, $combinacionId, (string) $talle->id, $fecha);
            if (! isset($precio[0])) {
                return ['error' => 'El artículo '.$articulo->sku.' talle '.($talle->nombre ?? '').' no tiene precio.'];
            }

            $unitario = round((float) $pct->precio, 4);
            if ($unitario <= 0) {
                $unitario = (float) ($precio[0]['precio'] ?? 0);
            }
            if ($unitario <= 0) {
                return ['error' => 'El artículo '.$articulo->sku.' talle '.($talle->nombre ?? '').' no tiene precio.'];
            }

            $clave = sprintf('%.4f|%s', $unitario, (string) ($pc->listaprecio_id ?: ($precio[0]['listaprecio_id'] ?? '')));
            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'cantidad' => 0.0,
                    'precio' => $unitario,
                    'listaprecio_id' => (int) ($pc->listaprecio_id ?: ($precio[0]['listaprecio_id'] ?? 0)),
                    'incluyeimpuesto' => $precio[0]['incluyeimpuesto'] ?? ($pc->incluyeimpuesto ?? ''),
                    'talles' => [],
                ];
            }
            $grupos[$clave]['cantidad'] += (float) $pct->cantidad;
            $nombreTalle = trim((string) ($talle->nombre ?? ''));
            if ($nombreTalle !== '') {
                $grupos[$clave]['talles'][] = $nombreTalle;
            }
        }

        if ($grupos === []) {
            return ['error' => 'El picking no tiene pares para facturar.'];
        }

        $pickingCodigo = (string) ($pc->pickingCabecera->codigo ?? '');
        $pedidoCodigo = (string) ($pc->pedidos->codigo ?? '');
        $base = trim((string) $articulo->descripcion);
        $comb = trim((string) ($pc->combinaciones->codigo ?? ''));
        $sufijo = 'Picking '.$pickingCodigo.' · Ped. '.$pedidoCodigo;
        if ($comb !== '') {
            $sufijo .= ' · '.$comb;
        }
        if ($lote !== '') {
            $sufijo .= ' · lote '.$lote;
        }

        $otId = (int) ($pc->picking_ordentrabajo_id ?? 0);
        if ($otId <= 0) {
            $otId = (int) ($pc->ot_id ?? 0);
        }

        $salida = [];
        $indice = 0;
        $variosPrecios = count($grupos) > 1;
        foreach ($grupos as $grupo) {
            $detalle = $base.' · '.$sufijo;
            if ($variosPrecios && $grupo['talles'] !== []) {
                $detalle .= ' · talles '.implode(', ', $grupo['talles']);
            }
            $salida[] = [
                'indice' => $indice,
                'origen_mostrador' => 'picking',
                'cantidad' => (float) $grupo['cantidad'],
                'precio' => (float) $grupo['precio'],
                'descuento' => round((float) ($pc->descuento ?? 0), 4),
                'listaprecio_id' => (int) $grupo['listaprecio_id'],
                'incluyeimpuesto' => $grupo['incluyeimpuesto'],
                'articulo_id' => (int) $articulo->id,
                'sku' => (string) $articulo->sku,
                'descripcion' => $detalle,
                'combinacion_id' => $combinacionId,
                'codigocombinacion' => $comb,
                'modulo_id' => (int) ($pc->modulo_id ?? 0),
                'ordentrabajo_id' => $otId > 0 ? $otId : 0,
                'pedido_combinacion_id' => (int) $pc->id,
                'picking_codigo' => $pickingCodigo,
                'pedido_codigo' => $pedidoCodigo,
            ];
            $indice++;
        }

        return self::$armadoCache[$claveCache] = [
            'picking_codigo' => $pickingCodigo,
            'pedido_codigo' => $pedidoCodigo,
            'grupos' => $salida,
        ];
    }

    private static function pendiente(Pedido_Combinacion $pc): bool
    {
        if (($pc->picking ?? PedidoPickingFerliSupport::NO_MARCADO) !== PedidoPickingFerliSupport::MARCADO) {
            return false;
        }
        if (($pc->picking_facturado ?? PedidoPickingFerliSupport::NO_MARCADO) === PedidoPickingFerliSupport::FACTURADO) {
            return false;
        }
        if (($pc->estado ?? 'N') === 'A') {
            return false;
        }

        return true;
    }

    private static function paresDeLinea(Pedido_Combinacion $pc): float
    {
        $pares = 0.0;
        foreach ($pc->pedido_combinacion_talles as $pct) {
            $pares += (float) $pct->cantidad;
        }
        if ($pares == 0.0) {
            $pares = (float) ($pc->cantidad ?? 0);
        }

        return $pares;
    }
}
