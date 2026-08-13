<?php

namespace App\Services\Ventas;

use App\Models\Stock\Listaprecio;
use App\Models\Ventas\PedidoArticuloInterforming;
use App\Models\Ventas\PedidoInterforming;
use App\Support\Ventas\PedidoEstadosInterforming;
use App\Support\Ventas\PedidoInterformingFasonSupport;
use App\Support\Ventas\PedidoInterformingListadoFiltros;
use App\Support\Ventas\PedidoInterformingSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Persistencia y listado del ABM pedidos INTERFORMING.
 * Reutiliza tablas pedido/pedido_articulo; no altera el flujo Bierzo.
 */
class PedidoInterformingService
{
    public function leePedido(int $id): ?PedidoInterforming
    {
        return PedidoInterforming::query()
            ->with([
                'clientes',
                'condicionesdeventa',
                'vendedores',
                'transportes',
                'zonavtas',
                'deposito',
                'moneda',
                'pedido_articulos.articulos',
                'pedido_articulos.monedas',
                'pedido_articulos.unidadmedidaAlter',
            ])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator<int, PedidoInterforming>|Collection<int, PedidoInterforming>
     */
    public function leePedidos($filtros = null, bool $paginar = true)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => PedidoInterformingListadoFiltros::MODO_TODOS,
                'campo' => 'codigo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = PedidoInterformingListadoFiltros::filtrosVacios();
        }

        $q = PedidoInterforming::query()
            ->with(['clientes', 'vendedores', 'transportes'])
            ->orderByDesc('id');

        if (PedidoInterformingListadoFiltros::tieneCriteriosAplicados($filtros)) {
            PedidoInterformingListadoFiltros::aplicar($q, $filtros);
        }

        return $paginar ? $q->paginate(10) : $q->get();
    }

    /**
     * @deprecated Usar leePedidos($filtros, true)
     */
    public function leePedidosPaginando(?string $busqueda = null, ?string $estado = null)
    {
        $filtros = PedidoInterformingListadoFiltros::filtrosVacios();
        if ($busqueda !== null && trim($busqueda) !== '') {
            $filtros['valor'] = trim($busqueda);
            $filtros['busqueda'] = trim($busqueda);
        }
        if ($estado !== null && $estado !== '') {
            $filtros['modo'] = PedidoInterformingListadoFiltros::MODO_CAMPO;
            $filtros['campo'] = 'estadopedido';
            $filtros['operador'] = 'igual';
            $filtros['valor'] = $estado;
            $filtros['busqueda'] = $estado;
        }

        return $this->leePedidos($filtros, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{id?: int, codigo?: string, error?: string}
     */
    public function guardar(array $data, string $modo = 'create'): array
    {
        PedidoInterformingSupport::abortSiNoInterforming();

        $items = $this->normalizarItems($data['items'] ?? []);
        if ($items === []) {
            return ['error' => 'No puede grabar pedidos sin ítems'];
        }

        if (! empty($data['orden_compra'])) {
            $dup = $this->existeOrdenCompra((string) $data['orden_compra'], isset($data['id']) ? (int) $data['id'] : null);
            if ($dup) {
                return ['error' => 'Orden de compra ya usada en otro pedido'];
            }
        }

        try {
            return DB::transaction(function () use ($data, $items, $modo) {
                if ($modo === 'create') {
                    $pedido = new PedidoInterforming();
                    $pedido->usuario_id = Auth::id();
                    $pedido->estado = 1;
                    $pedido->estadopedido = PedidoEstadosInterforming::CAB_PENTREGAR;
                    $pedido->fecha = $data['fecha'] ?? now()->toDateString();
                } else {
                    $pedido = PedidoInterforming::query()->findOrFail((int) $data['id']);
                }

                $this->mapearCabecera($pedido, $data);
                $pedido->save();

                if ($modo === 'create' && empty($pedido->codigo)) {
                    $pedido->codigo = (string) $pedido->id;
                    $pedido->numero_comprobante = (int) $pedido->id;
                    $pedido->save();
                }

                if ($modo !== 'create') {
                    PedidoArticuloInterforming::query()->where('pedido_id', $pedido->id)->delete();
                }

                $this->guardarItems($pedido, $items);

                try {
                    app(PedidoInterformingArbolIntegracionService::class)->dispararAlGuardar((int) $pedido->id);
                } catch (\Throwable $e) {
                    report($e);
                }

                return [
                    'id' => (int) $pedido->id,
                    'codigo' => (string) $pedido->codigo,
                ];
            });
        } catch (\Throwable $e) {
            report($e);

            return ['error' => 'Error al guardar el pedido: '.$e->getMessage()];
        }
    }

    public function eliminar(int $id): array
    {
        PedidoInterformingSupport::abortSiNoInterforming();

        $pedido = PedidoInterforming::query()->find($id);
        if (! $pedido) {
            return ['error' => 'Pedido inexistente'];
        }

        if (in_array((string) $pedido->estadopedido, [
            PedidoEstadosInterforming::CAB_FACTURADO,
            PedidoEstadosInterforming::CAB_ENTREGADO,
        ], true)) {
            return ['error' => 'No se puede eliminar un pedido facturado o entregado'];
        }

        DB::transaction(function () use ($pedido) {
            PedidoArticuloInterforming::query()->where('pedido_id', $pedido->id)->delete();
            $pedido->delete();
        });

        return ['ok' => true];
    }

    public function existeOrdenCompra(string $ordenCompra, ?int $exceptoPedidoId = null): bool
    {
        $orden = trim($ordenCompra);
        if ($orden === '') {
            return false;
        }

        $q = PedidoInterforming::query()
            ->where('orden_compra', $orden)
            ->where('estadopedido', '!=', PedidoEstadosInterforming::CAB_ANULADO);

        if ($exceptoPedidoId) {
            $q->where('id', '!=', $exceptoPedidoId);
        }

        return $q->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapearCabecera(PedidoInterforming $pedido, array $data): void
    {
        $pedido->fechaentrega = $data['fechaentrega'] ?? $pedido->fechaentrega ?? now()->toDateString();
        $pedido->cliente_id = (int) ($data['cliente_id'] ?? 0);
        $pedido->condicionventa_id = $data['condicionventa_id'] ?? null;
        $pedido->vendedor_id = $data['vendedor_id'] ?? null;
        $pedido->transporte_id = $data['transporte_id'] ?? null;
        $pedido->zonavta_id = $data['zonavta_id'] ?? null;
        $pedido->cliente_entrega_id = $data['cliente_entrega_id'] ?? null;
        $pedido->lugarentrega = $data['lugarentrega'] ?? null;
        $pedido->leyenda = $data['leyenda'] ?? null;
        $pedido->descuento = (float) ($data['descuento'] ?? 0);
        // Anita/AGG graban espacio (columna NOT NULL sin default usable)
        $pedido->descuentointegrado = (string) ($data['descuentointegrado'] ?? ' ');
        if (trim($pedido->descuentointegrado) === '') {
            $pedido->descuentointegrado = ' ';
        }
        $pedido->orden_compra = isset($data['orden_compra']) ? substr(trim((string) $data['orden_compra']), 0, 15) : null;
        $pedido->deposito_id = $data['deposito_id'] ?? null;
        $pedido->moneda_id = $data['moneda_id'] ?? null;
        $pedido->cotizacion = $data['cotizacion'] ?? null;
        $pedido->en_stock = $data['en_stock'] ?? null;
        $pedido->tipo_comprobante = $data['tipo_comprobante'] ?? ($pedido->tipo_comprobante ?? 'PED');
        $pedido->letra_comprobante = $data['letra_comprobante'] ?? ($pedido->letra_comprobante ?? 'X');
        $pedido->sucursal_comprobante = $data['sucursal_comprobante'] ?? ($pedido->sucursal_comprobante ?? 1);
    }

    /**
     * @param  mixed  $raw
     * @return list<array<string, mixed>>
     */
    private function normalizarItems($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            $articuloId = (int) ($item['articulo_id'] ?? 0);
            if ($articuloId <= 0) {
                continue;
            }
            $item['numeroitem'] = (int) ($item['numeroitem'] ?? ($idx + 1));
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function guardarItems(PedidoInterforming $pedido, array $items): void
    {
        $listaDefault = Listaprecio::query()->orderBy('id')->value('id') ?? 1;

        foreach ($items as $item) {
            $item = PedidoInterformingFasonSupport::aplicarAItem($item);
            $cantidad = (float) ($item['cantidad'] ?? 0);
            $row = new PedidoArticuloInterforming();
            $row->pedido_id = $pedido->id;
            $row->articulo_id = (int) $item['articulo_id'];
            $row->numeroitem = (int) $item['numeroitem'];
            $row->cantidad = $cantidad;
            $row->cantidad_a_entregar = (float) ($item['cantidad_a_entregar'] ?? $cantidad);
            $row->cantidad_entregada = (float) ($item['cantidad_entregada'] ?? 0);
            $row->cantidad_facturada = (float) ($item['cantidad_facturada'] ?? 0);
            // Compatibilidad NOT NULL Bierzo
            $row->caja = 0;
            $row->pieza = 0;
            $row->kilo = $cantidad;
            $row->pesada = 0;
            $row->precio = (float) ($item['precio'] ?? 0);
            $row->incluyeimpuesto = $item['incluyeimpuesto'] ?? 'N';
            $row->listaprecio_id = (int) ($item['listaprecio_id'] ?? $listaDefault);
            $row->moneda_id = (int) ($item['moneda_id'] ?? $pedido->moneda_id ?? 1);
            $row->descuento = (float) ($item['descuento'] ?? 0);
            $row->descuentointegrado = (string) ($item['descuentointegrado'] ?? '');
            $row->unidadmedida_id = $item['unidadmedida_id'] ?? null;
            $row->unidadmedida_alter_id = $item['unidadmedida_alter_id'] ?? null;
            $row->cantidad_alter = $item['cantidad_alter'] ?? null;
            $row->fechaentrega = $item['fechaentrega'] ?? $pedido->fechaentrega;
            $row->orden_compra = isset($item['orden_compra'])
                ? substr(trim((string) $item['orden_compra']), 0, 15)
                : $pedido->orden_compra;
            $row->articulo_cliente = $item['articulo_cliente'] ?? null;
            $row->partida = (int) ($item['partida'] ?? PedidoEstadosInterforming::PARTIDA_PROPIO);
            $row->porc_fason = (float) ($item['porc_fason'] ?? 0);
            $row->porc_fason_ant = (float) ($item['porc_fason_ant'] ?? $row->porc_fason);
            $row->precio_fason = (float) ($item['precio_fason'] ?? 0);
            $row->moneda_fason_id = $item['moneda_fason_id'] ?? $row->moneda_id;
            $row->deposito_id = $item['deposito_id'] ?? $pedido->deposito_id;
            $row->ubicacion = $item['ubicacion'] ?? null;
            $row->detalle_ubicacion = $item['detalle_ubicacion'] ?? null;
            $row->observacion = $item['observacion'] ?? null;
            $row->descripcion_aux = $item['descripcion_aux'] ?? null;
            $row->estado = $item['estado'] ?? PedidoEstadosInterforming::ITEM_PENDIENTE;
            $row->estado_cierre = $item['estado_cierre'] ?? PedidoEstadosInterforming::CIERRE_ABIERTO;
            $row->save();
        }
    }
}
