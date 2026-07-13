<?php

namespace App\Services\Ventas;

use App\Mail\Configuracion\MailArbolAprobacion;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
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
use Illuminate\Support\Facades\Mail;

/**
 * Árbol de aprobación para pedidos INTERFORMING (comprobante PE).
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
            ->whereNull('deleted_at')
            ->orderBy('nivel')
            ->orderBy('id')
            ->with('enviousuarios')
            ->with('destinatariousuarios')
            ->get();
    }

    /**
     * Dispara el árbol si hay uno activo (no falla si no hay árbol configurado).
     */
    public function dispararAlGuardar(int $pedidoId): int
    {
        if (! PedidoInterformingSupport::esInterforming()) {
            return 0;
        }

        return app(\App\Services\Configuracion\ArbolaprobacionService::class)
            ->procesaArbolaprobacion(self::TIPO_COMPROBANTE, $pedidoId, 'insert');
    }

    public function procesaArbol(
        int $comprobanteId,
        string $operacion,
        callable $leeAprobacionComprobante,
        callable $buscaProximoNivel,
        callable $enviaCorreo,
    ): int {
        $pedido = PedidoInterforming::query()
            ->with(['pedido_articulos', 'clientes', 'moneda'])
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
        $monedaId = (int) ($pedido->moneda_id ?? 0);
        $centrocostoId = 0;
        $arrayReplace = ArbolAprobacionEnlaceSupport::CARACTERES_REEMPLAZO;

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

                $enviaCorreo($uid, $tipoarbol, $pedido, $linkAprobacion, $linkRechazo, $linkVisualizar, [
                    'monto_items' => $monto,
                    'moneda_abrev_items' => $pedido->moneda->abreviatura ?? '',
                ]);

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
            }

            return (int) $proximoNivel['proximonivel'];
        }
    }

    public function finalizaTrasArbolCompleto(int $pedidoId, $usuarioId): void
    {
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

    private function montoPedido(PedidoInterforming $pedido): float
    {
        $total = 0.0;
        foreach ($pedido->pedido_articulos as $item) {
            $cant = (float) ($item->cantidad ?? 0);
            $precio = (float) ($item->precio ?? 0);
            $dto = (float) ($item->descuento ?? 0);
            $total += $cant * $precio * (1 - ($dto / 100));
        }

        return round($total, 2);
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
