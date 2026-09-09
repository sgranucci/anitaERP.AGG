<?php

namespace App\Services\Ventas;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\PedidoArticuloInterforming;
use App\Models\Ventas\PedidoInterforming;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use App\Support\Ventas\PedidoEstadosInterforming;
use App\Support\Ventas\PedidoInterformingSupport;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Árbol de aprobación tipo Pedidos (PE) para cualquier cliente.
 * Solo actúa si hay un árbol PE activo; sin árbol = no-op.
 * Mutación de estados ítem Anita (P/A/R) solo en INTERFORMING.
 */
class PedidoInterformingArbolIntegracionService
{
    public const TIPO_COMPROBANTE = 'PE';

    public function __construct(
        private ArbolaprobacionRepositoryInterface $arbolaprobacionRepository,
        private Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacionMovimientoRepository,
        private UsuarioRepositoryInterface $usuarioRepository,
    ) {
    }

    public function nombreTipoArbol(): string
    {
        $idx = array_search(self::TIPO_COMPROBANTE, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'));

        return Arbolaprobacion::$enumTipoArbol[$idx]['nombre'];
    }

    public function findPorPedido(int $pedidoId)
    {
        return Arbolaprobacion_Movimiento::query()
            ->where('pedido_id', $pedidoId)
            ->orderBy('nivel')
            ->orderBy('id')
            ->with('enviousuarios')
            ->with('destinatariousuarios')
            ->get();
    }

    /**
     * Hay al menos un árbol PE activo.
     */
    public function hayArbolActivo(): bool
    {
        $arboles = $this->arbolaprobacionRepository->findPorTipoArbol($this->nombreTipoArbol());

        return $arboles && $arboles->count() > 0;
    }

    /**
     * Dispara el árbol si hay uno activo. Sin árbol configurado → 0 (no-op).
     */
    public function dispararAlGuardar(int $pedidoId): int
    {
        if ($pedidoId <= 0 || ! $this->hayArbolActivo()) {
            return 0;
        }

        return app(\App\Services\Configuracion\ArbolaprobacionService::class)
            ->procesaArbolaprobacion(self::TIPO_COMPROBANTE, $pedidoId, 'insert');
    }

    /**
     * Hook post-grabación: no tumba el pedido si falla el árbol/mail.
     */
    public function dispararAlGuardarSeguro(int $pedidoId): void
    {
        try {
            $this->dispararAlGuardar($pedidoId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function procesaArbol(
        int $comprobanteId,
        string $operacion,
        callable $leeAprobacionComprobante,
        callable $buscaProximoNivel,
        callable $enviaCorreo,
    ): int {
        $pedido = Pedido::query()
            ->with(['pedido_articulos', 'pedido_combinaciones', 'clientes'])
            ->find($comprobanteId);
        if (! $pedido) {
            return 0;
        }

        $tipoarbol = $this->nombreTipoArbol();
        $arbolaprobacion = $this->arbolaprobacionRepository->findPorTipoArbol($tipoarbol);
        if (! $arbolaprobacion || ! $arbolaprobacion->count()) {
            return 0;
        }
        if ($arbolaprobacion->count() > 1) {
            throw new \RuntimeException('Hay más de un árbol de aprobación activo de Pedidos; debe quedar uno solo.');
        }

        $arbol = $arbolaprobacion->first();
        $monto = $this->montoPedido($pedido);
        $monedaId = $this->monedaIdPedido($pedido);
        $centrocostoId = 0;
        foreach ($arbol->arbolaprobacion_niveles as $nivelArbol) {
            if ($nivelArbol->centrocosto_id !== null) {
                $centrocostoId = (int) $nivelArbol->centrocosto_id;
                break;
            }
        }

        while (true) {
            $estadoAprobacionActual = $leeAprobacionComprobante($tipoarbol, $comprobanteId);
            $proximoNivel = $buscaProximoNivel(
                $arbol,
                $centrocostoId,
                $estadoAprobacionActual['nivelactual'],
                $pedido->fecha,
                $monto,
                $monedaId
            );

            if ($proximoNivel['proximonivel'] === -1) {
                $this->finalizaTrasArbolCompleto($comprobanteId, Auth::id() ?? (int) $pedido->usuario_id);

                return -1;
            }

            if ($proximoNivel['proximonivel'] <= 0) {
                return 0;
            }

            if (empty($proximoNivel['proximousuario'])) {
                $this->grabaMovimientoAutomatico($arbol->id, $comprobanteId, (int) $proximoNivel['proximonivel']);

                continue;
            }

            $ip = config('arbolaprobacion.ip_link');
            $ref = (string) ($pedido->codigo ?? $pedido->id);
            $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('VIS'.$comprobanteId.$pedido->fecha.$ref));
            $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar($ip, 'ventas/pedido', (int) $comprobanteId, $hashVisualizar);

            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $uids = $proximoNivel['proximousuarios'] ?? [];
            if (! is_array($uids) || count($uids) === 0) {
                $uids = [$proximoNivel['proximousuario']];
            }
            $uids = array_values(array_unique(array_filter($uids)));

            $ya = Arbolaprobacion_Movimiento::query()
                ->where('pedido_id', $comprobanteId)
                ->where('nivel', $proximoNivel['proximonivel'])
                ->where('estado', $nombrePendiente)
                ->pluck('destinatariousuario_id')
                ->map(fn ($x) => (int) $x)
                ->all();

            foreach ($uids as $uid) {
                $uid = (int) $uid;
                if ($uid <= 0 || in_array($uid, $ya, true)) {
                    continue;
                }

                $hashAprobacion = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make(
                    self::TIPO_COMPROBANTE.'A'.$comprobanteId.$pedido->fecha.$ref.'N'.$estadoAprobacionActual['nivelactual'].'U'.$uid
                ));
                $hashRechazo = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make(
                    self::TIPO_COMPROBANTE.'R'.$comprobanteId.$pedido->fecha.$ref.'N'.$estadoAprobacionActual['nivelactual'].'U'.$uid
                ));
                $linkAprobacion = ArbolAprobacionEnlaceSupport::enlaceAprobar($ip, self::TIPO_COMPROBANTE, (int) $comprobanteId, $hashAprobacion);
                $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo($ip, self::TIPO_COMPROBANTE, (int) $comprobanteId, $hashRechazo);

                $this->arbolaprobacionMovimientoRepository->create([
                    'arbolaprobacion_id' => $arbol->id,
                    'fechaenvio' => Carbon::now(),
                    'enviousuario_id' => Auth::id() ?? $pedido->usuario_id,
                    'requisicion_id' => null,
                    'ordencompra_id' => null,
                    'solicitudpago_id' => null,
                    'ordenventa_id' => null,
                    'pedido_id' => $comprobanteId,
                    'hashaprobacion' => $hashAprobacion,
                    'hashrechazo' => $hashRechazo,
                    'hashvisualizar' => $hashVisualizar,
                    'nivel' => $proximoNivel['proximonivel'],
                    'destinatariousuario_id' => $uid,
                    'fechaproceso' => null,
                    'estado' => $nombrePendiente,
                    'observacion' => '',
                ]);

                try {
                    $enviaCorreo($uid, $tipoarbol, $pedido, $linkAprobacion, $linkRechazo, $linkVisualizar, [
                        'monto_items' => $monto,
                        'moneda_abrev_items' => '',
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return (int) $proximoNivel['proximonivel'];
        }
    }

    public function finalizaTrasArbolCompleto(int $pedidoId, $usuarioId): void
    {
        if (! PedidoInterformingSupport::esInterforming()) {
            return;
        }

        $ahora = Carbon::now()->toDateString();
        PedidoArticuloInterforming::query()
            ->where('pedido_id', $pedidoId)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', PedidoEstadosInterforming::ITEM_PENDIENTE)
                    ->orWhere('estado', PedidoEstadosInterforming::ITEM_CONDICIONAL);
            })
            ->update([
                'estado' => PedidoEstadosInterforming::ITEM_APROBADO,
                'usuario_aprobacion_id' => $usuarioId ?: null,
                'fecha_aprobacion' => $ahora,
            ]);
    }

    public function rechazaPorRechazo(int $pedidoId, $usuarioId, string $observacion): void
    {
        if (! PedidoInterformingSupport::esInterforming()) {
            return;
        }

        PedidoArticuloInterforming::query()
            ->where('pedido_id', $pedidoId)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', '!=', PedidoEstadosInterforming::ITEM_ENTREGADO);
            })
            ->update([
                'estado' => PedidoEstadosInterforming::ITEM_RECHAZADO,
                'usuario_aprobacion_id' => $usuarioId ?: null,
                'fecha_aprobacion' => Carbon::now()->toDateString(),
            ]);

        PedidoInterforming::query()->whereKey($pedidoId)->update([
            'estadopedido' => PedidoEstadosInterforming::CAB_SUSPENDIDO,
            'razon_suspension' => substr(trim($observacion), 0, 30) ?: 'Rechazado árbol',
        ]);
    }

    public function montoPedidoPublico(object $pedido): float
    {
        if ($pedido instanceof Pedido) {
            return $this->montoPedido($pedido);
        }

        return $this->montoPedido(Pedido::query()
            ->with(['pedido_articulos', 'pedido_combinaciones'])
            ->findOrFail((int) $pedido->id));
    }

    private function montoPedido(Pedido $pedido): float
    {
        $pedido->loadMissing(['pedido_articulos', 'pedido_combinaciones']);
        $total = 0.0;

        if ($pedido->pedido_articulos && $pedido->pedido_articulos->count() > 0) {
            foreach ($pedido->pedido_articulos as $item) {
                $total += $this->importeLinea(
                    (float) ($item->cantidad ?? 0),
                    (float) ($item->precio ?? 0),
                    (float) ($item->descuento ?? 0)
                );
            }
        } elseif ($pedido->pedido_combinaciones && $pedido->pedido_combinaciones->count() > 0) {
            foreach ($pedido->pedido_combinaciones as $item) {
                $total += $this->importeLinea(
                    (float) ($item->cantidad ?? 0),
                    (float) ($item->precio ?? 0),
                    (float) ($item->descuento ?? 0)
                );
            }
        }

        $dtoCab = (float) ($pedido->descuento ?? 0);
        if ($dtoCab > 0 && $dtoCab <= 100) {
            $total *= (1 - ($dtoCab / 100));
        }

        return round($total, 2);
    }

    private function importeLinea(float $cant, float $precio, float $dto): float
    {
        if ($dto < 0) {
            $dto = 0;
        }
        if ($dto > 100) {
            $dto = 100;
        }

        return $cant * $precio * (1 - ($dto / 100));
    }

    private function monedaIdPedido(Pedido $pedido): int
    {
        $cab = (int) ($pedido->moneda_id ?? 0);
        if ($cab > 0) {
            return $cab;
        }
        $pedido->loadMissing(['pedido_articulos', 'pedido_combinaciones']);
        foreach ($pedido->pedido_articulos ?? [] as $item) {
            $id = (int) ($item->moneda_id ?? 0);
            if ($id > 0) {
                return $id;
            }
        }
        foreach ($pedido->pedido_combinaciones ?? [] as $item) {
            $id = (int) ($item->moneda_id ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 1;
    }

    private function grabaMovimientoAutomatico(int $arbolId, int $comprobanteId, int $nivel): void
    {
        $token = self::TIPO_COMPROBANTE.'AUTO'.$comprobanteId.'N'.$nivel.str_replace([' ', ':'], '', microtime(false));
        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

        $this->arbolaprobacionMovimientoRepository->create([
            'arbolaprobacion_id' => $arbolId,
            'fechaenvio' => Carbon::now(),
            'enviousuario_id' => Auth::id(),
            'requisicion_id' => null,
            'ordencompra_id' => null,
            'solicitudpago_id' => null,
            'ordenventa_id' => null,
            'pedido_id' => $comprobanteId,
            'hashaprobacion' => ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make($token.'A')),
            'hashrechazo' => ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make($token.'R')),
            'hashvisualizar' => ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make($token.'V')),
            'nivel' => $nivel,
            'destinatariousuario_id' => null,
            'fechaproceso' => Carbon::now(),
            'estado' => $nombreAprobado,
            'observacion' => 'Nivel sin usuario (automático)',
        ]);
    }
}
