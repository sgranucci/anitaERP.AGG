<?php

namespace App\Support\Ventas\Ferli;

use App\Models\Ventas\Ordentrabajo;
use App\Models\Ventas\Pedido_Combinacion;
use App\Repositories\Ventas\Ordentrabajo_TareaRepositoryInterface;
use App\Services\Stock\PrecioServiceFerli;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Produccion\OrdentrabajoTareaFechaSupport;
use App\Support\Ventas\PedidoPickingFerliSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Ferli, facturación mostrador: cada renglón puede traer una OT de un pedido.
 * Al emitir se marca la OT facturada y el stock sale del lote de esa OT.
 */
final class FacturaMostradorOtFerliSupport
{
    /** @var array<int, array<int, true>> */
    private static array $gruposVistos = [];

    /** @var array<int, array{n: int, ot: string, pedido: string}> */
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
                'ordenestrabajo.ordentrabajo_tareas',
                'ordenestrabajo.ordentrabajo_combinacion_talles.pedido_combinacion_talles',
            ])
            ->where('ot_id', '>', 0)
            ->where(function ($w) use ($clienteId) {
                $w->whereHas('pedidos', function ($p) use ($clienteId) {
                    $p->where('cliente_id', $clienteId);
                })->orWhereHas('pedido_combinacion_talles.pedidos_combinacion_ordenes', function ($o) use ($clienteId) {
                    $o->where('cliente_id', $clienteId);
                });
            });

        if ($texto !== '') {
            $like = '%'.$texto.'%';
            $query->where(function ($w) use ($like) {
                $w->whereHas('ordenestrabajo', function ($o) use ($like) {
                    $o->where('codigo', 'like', $like);
                })->orWhereHas('pedidos', function ($p) use ($like) {
                    $p->where('codigo', 'like', $like);
                })->orWhereHas('articulos', function ($a) use ($like) {
                    $a->where('sku', 'like', $like)->orWhere('descripcion', 'like', $like);
                })->orWhereHas('combinaciones', function ($c) use ($like) {
                    $c->where('codigo', 'like', $like);
                });
            });
        }

        $filas = [];
        foreach ($query->orderByDesc('id')->limit(120)->get() as $pc) {
            $ot = $pc->ordenestrabajo;
            if (! $ot || ! self::perteneceAlCliente($pc, $ot, $clienteId)) {
                continue;
            }
            if (! self::listaParaFacturar($ot->ordentrabajo_tareas, (int) $pc->id)) {
                continue;
            }

            $filas[] = [
                'pedido_combinacion_id' => (int) $pc->id,
                'ordentrabajo_id' => (int) $ot->id,
                'ot_codigo' => (string) ($ot->codigo ?? ''),
                'pedido_codigo' => (string) ($pc->pedidos->codigo ?? ''),
                'sku' => (string) ($pc->articulos->sku ?? ''),
                'descripcion' => (string) ($pc->articulos->descripcion ?? ''),
                'combinacion' => (string) ($pc->combinaciones->codigo ?? ''),
                'pares' => self::paresDeOt($ot, (int) $pc->id),
            ];
            if (count($filas) >= 40) {
                break;
            }
        }

        return $filas;
    }

    /**
     * Datos para completar el renglón (uno por precio de talle).
     *
     * @return array<string, mixed>
     */
    public static function resolver(int $pedidoCombinacionId, int $ordentrabajoId, int $clienteId, string $fecha): array
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return ['error' => 'La OT de pedido solo se factura en Ferli.'];
        }

        $armado = self::armar($pedidoCombinacionId, $ordentrabajoId, $clienteId, $fecha);
        if (isset($armado['error'])) {
            return $armado;
        }

        return [
            'ot_codigo' => $armado['ot_codigo'],
            'pedido_codigo' => $armado['pedido_codigo'],
            'grupos' => $armado['grupos'],
        ];
    }

    /**
     * Reemplazo de un renglón posteado. Null si la línea no trae OT.
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

        $otId = (int) ($data['ordentrabajo_ids'][$off] ?? 0);
        $pcId = (int) ($data['pedido_combinacion_ids'][$off] ?? 0);
        if ($otId <= 0 && $pcId <= 0) {
            return null;
        }
        if ($otId <= 0 || $pcId <= 0) {
            return ['error' => 'El renglón '.($off + 1).' tiene una OT incompleta.'];
        }

        $armado = self::armar($pcId, $otId, $clienteId, $fecha);
        if (isset($armado['error'])) {
            return $armado;
        }

        $indice = (int) ($data['ot_grupo_indices'][$off] ?? 0);
        if (! isset($armado['grupos'][$indice])) {
            return ['error' => 'La OT '.$armado['ot_codigo'].' no tiene el tramo de precio indicado.'];
        }
        if (isset(self::$gruposVistos[$pcId][$indice])) {
            return ['error' => 'La OT '.$armado['ot_codigo'].' del pedido '.$armado['pedido_codigo'].' está repetida en la factura.'];
        }

        self::$gruposVistos[$pcId][$indice] = true;
        self::$gruposEsperados[$pcId] = [
            'n' => count($armado['grupos']),
            'ot' => (string) $armado['ot_codigo'],
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
                return ['error' => 'La OT '.$meta['ot'].' del pedido '.$meta['pedido'].' quedó incompleta. Elegila de nuevo para traer todos los talles.'];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $dataFactura
     */
    public static function marcarFacturadasYConsumirStock(
        array $dataFactura,
        int $ventaId,
        string $fecha,
        int $depositoId
    ): void {
        if (! EntornoEmpresaSupport::esFerli() || $ventaId <= 0) {
            return;
        }

        $pares = [];
        foreach ($dataFactura as $item) {
            $otId = (int) ($item['ordentrabajo_id'] ?? 0);
            $pcId = (int) ($item['pedido_combinacion_id'] ?? 0);
            if ($otId <= 0 || $pcId <= 0) {
                continue;
            }
            $pares[$otId.'|'.$pcId] = [$pcId, $otId];
        }
        if ($pares === []) {
            return;
        }

        $repo = app(Ordentrabajo_TareaRepositoryInterface::class);
        $pcIds = [];
        $otIds = [];
        $ahora = Carbon::now();
        foreach ($pares as [$pcId, $otId]) {
            $ot = Ordentrabajo::query()->with('ordentrabajo_tareas')->find($otId);
            if (! $ot || ! self::listaParaFacturar($ot->ordentrabajo_tareas, $pcId)) {
                $codigo = (string) ($ot->codigo ?? $otId);
                throw new RuntimeException('La OT '.$codigo.' ya no se puede facturar.');
            }

            $repo->create([
                'ordentrabajo_id' => $otId,
                'tarea_id' => config('consprod.TAREA_FACTURADA'),
                'desdefecha' => $ahora,
                'hastafecha' => $ahora,
                'empleado_id' => null,
                'pedido_combinacion_id' => $pcId,
                'estado' => config('consprod.TAREA_ESTADO_FACTURADA'),
                'costo' => 0,
                'usuario_id' => Auth::id(),
                'venta_id' => $ventaId,
            ]);
            $pcIds[] = $pcId;
            $otIds[] = $otId;
        }

        PedidoPickingFerliSupport::grabarConsumoStockOtAlFacturar(
            $pcIds,
            $otIds,
            $fecha,
            $ventaId,
            $depositoId
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function armar(int $pedidoCombinacionId, int $ordentrabajoId, int $clienteId, string $fecha): array
    {
        $claveCache = $pedidoCombinacionId.'|'.$ordentrabajoId.'|'.$clienteId.'|'.substr(trim($fecha), 0, 10);
        if (isset(self::$armadoCache[$claveCache])) {
            return self::$armadoCache[$claveCache];
        }

        $pc = Pedido_Combinacion::query()
            ->with(['articulos', 'combinaciones', 'pedidos'])
            ->find($pedidoCombinacionId);
        $ot = Ordentrabajo::query()
            ->with([
                'ordentrabajo_tareas',
                'ordentrabajo_combinacion_talles.pedido_combinacion_talles.talles',
            ])
            ->find($ordentrabajoId);

        if (! $pc || ! $ot || (int) $pc->ot_id !== (int) $ot->id) {
            return ['error' => 'La OT no corresponde a ese ítem del pedido.'];
        }
        if ($clienteId <= 0 || ! self::perteneceAlCliente($pc, $ot, $clienteId)) {
            return ['error' => 'La OT no es del cliente de la factura.'];
        }
        if (! self::listaParaFacturar($ot->ordentrabajo_tareas, (int) $pc->id)) {
            return ['error' => 'La OT '.$ot->codigo.' no está terminada o ya está facturada.'];
        }

        $articulo = $pc->articulos;
        if (! $articulo) {
            return ['error' => 'La OT '.$ot->codigo.' no tiene artículo.'];
        }

        $fecha = substr(trim($fecha), 0, 10);
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        $precios = app(PrecioServiceFerli::class);
        $combinacionId = (int) ($pc->combinacion_id ?? 0);
        $pctYa = [];
        $grupos = [];

        foreach ($ot->ordentrabajo_combinacion_talles as $oct) {
            $pct = $oct->pedido_combinacion_talles;
            if (! $pct || (int) $pct->pedido_combinacion_id !== (int) $pc->id) {
                continue;
            }
            $pctId = (int) $pct->id;
            if ($pctId > 0 && isset($pctYa[$pctId])) {
                continue;
            }
            if ($pctId > 0) {
                $pctYa[$pctId] = true;
            }

            $talle = $pct->talles;
            if (! $talle) {
                continue;
            }

            $precio = $precios->asignaPrecio($articulo->id, $combinacionId, (string) $talle->id, $fecha);
            $unitario = (float) ($precio[0]['precio'] ?? 0);
            if ($unitario <= 0) {
                return ['error' => 'El artículo '.$articulo->sku.' talle '.($talle->nombre ?? '').' no tiene precio.'];
            }

            $clave = sprintf('%.4f|%s', $unitario, (string) ($precio[0]['listaprecio_id'] ?? ''));
            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'cantidad' => 0.0,
                    'precio' => $unitario,
                    'listaprecio_id' => (int) ($precio[0]['listaprecio_id'] ?? 0),
                    'incluyeimpuesto' => $precio[0]['incluyeimpuesto'] ?? '',
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
            return ['error' => 'La OT '.$ot->codigo.' no tiene pares para facturar.'];
        }

        $base = trim((string) $articulo->descripcion);
        $comb = trim((string) ($pc->combinaciones->codigo ?? ''));
        $sufijo = 'OT '.$ot->codigo.' · Ped. '.($pc->pedidos->codigo ?? '');
        if ($comb !== '') {
            $sufijo .= ' · '.$comb;
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
                'cantidad' => (float) $grupo['cantidad'],
                'precio' => (float) $grupo['precio'],
                'listaprecio_id' => (int) $grupo['listaprecio_id'],
                'incluyeimpuesto' => $grupo['incluyeimpuesto'],
                'articulo_id' => (int) $articulo->id,
                'sku' => (string) $articulo->sku,
                'descripcion' => $detalle,
                'combinacion_id' => $combinacionId,
                'codigocombinacion' => $comb,
                'modulo_id' => (int) ($pc->modulo_id ?? 0),
                'ordentrabajo_id' => (int) $ot->id,
                'pedido_combinacion_id' => (int) $pc->id,
                'ot_codigo' => (string) $ot->codigo,
                'pedido_codigo' => (string) ($pc->pedidos->codigo ?? ''),
            ];
            $indice++;
        }

        return self::$armadoCache[$claveCache] = [
            'ot_codigo' => (string) $ot->codigo,
            'pedido_codigo' => (string) ($pc->pedidos->codigo ?? ''),
            'grupos' => $salida,
        ];
    }

    private static function perteneceAlCliente(Pedido_Combinacion $pc, Ordentrabajo $ot, int $clienteId): bool
    {
        if ((int) ($pc->pedidos->cliente_id ?? 0) === $clienteId) {
            return true;
        }

        // Boleta junta: el cliente va en los talles de esta combinación.
        // No alcanza con que otra combinación de la misma OT sea de este cliente.
        foreach ($ot->ordentrabajo_combinacion_talles as $oct) {
            $pct = $oct->pedido_combinacion_talles;
            if (! $pct || (int) $pct->pedido_combinacion_id !== (int) $pc->id) {
                continue;
            }
            if ((int) ($oct->cliente_id ?? 0) === $clienteId) {
                return true;
            }
        }

        return false;
    }

    private static function paresDeOt(Ordentrabajo $ot, int $pedidoCombinacionId): float
    {
        $pares = 0.0;
        $vistos = [];
        foreach ($ot->ordentrabajo_combinacion_talles as $oct) {
            $pct = $oct->pedido_combinacion_talles;
            if (! $pct || (int) $pct->pedido_combinacion_id !== $pedidoCombinacionId) {
                continue;
            }
            if (isset($vistos[$pct->id])) {
                continue;
            }
            $vistos[$pct->id] = true;
            $pares += (float) $pct->cantidad;
        }

        return $pares;
    }

    /**
     * @param  iterable<int, mixed>  $tareas
     */
    private static function listaParaFacturar(iterable $tareas, int $pedidoCombinacionId): bool
    {
        $terminada = false;
        $facturada = false;
        $secuencia = false;
        $idsSecuencia = array_map('strval', (array) (config('consprod.SECUENCIA_TAREAS')[config('consprod.TAREA_FACTURADA')] ?? []));
        $idsTerminada = [
            (string) config('consprod.TAREA_TERMINADA'),
            (string) config('consprod.TAREA_TERMINADA_STOCK'),
            (string) config('consprod.TAREA_EMPAQUE'),
        ];

        foreach ($tareas as $tarea) {
            $pcTarea = $tarea->pedido_combinacion_id;
            if ($pedidoCombinacionId > 0 && $pcTarea !== null && (int) $pcTarea !== $pedidoCombinacionId) {
                continue;
            }
            $tid = (string) $tarea->tarea_id;
            if (in_array($tid, $idsTerminada, true)) {
                $terminada = true;
            }
            if ($tid === (string) config('consprod.TAREA_FACTURADA') && ! empty($tarea->venta_id)) {
                $facturada = true;
            }
            if (in_array($tid, $idsSecuencia, true) && OrdentrabajoTareaFechaSupport::tieneValor($tarea->hastafecha)) {
                $secuencia = true;
            }
        }

        return $terminada && $secuencia && ! $facturada;
    }
}
