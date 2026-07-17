<?php

namespace App\Services\Solicitudpago;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Solicitudpago\Solicitudpago;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Árbol de aprobación para solicitudes de pago (comprobante SP).
 */
class SolicitudpagoArbolIntegracionService
{
    public const TIPO_COMPROBANTE = 'SP';

    public function __construct(
        private ArbolaprobacionRepositoryInterface $arbolaprobacionRepository,
        private Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacionMovimientoRepository,
        private SolicitudpagoRepositoryInterface $solicitudpagoRepository,
    ) {
    }

    public function nombreTipoArbol(): string
    {
        $idx = array_search(self::TIPO_COMPROBANTE, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'));

        return Arbolaprobacion::$enumTipoArbol[$idx]['nombre'];
    }

    public function findPorSolicitudpago(int $solicitudpagoId)
    {
        return Arbolaprobacion_Movimiento::query()
            ->where('solicitudpago_id', $solicitudpagoId)
            ->whereNull('deleted_at')
            ->orderBy('nivel')
            ->orderBy('id')
            ->with('enviousuarios')
            ->with('destinatariousuarios')
            ->get();
    }

    public function dispararAlGuardar(int $solicitudpagoId): int
    {
        if (! config('solicitudpago.arbol_al_crear', true) && ! config('solicitudpago.arbol_al_generar_cuota', false)) {
            // Si ambos flags apagan disparo genérico, igual permitir llamada explícita del job.
        }

        return app(\App\Services\Configuracion\ArbolaprobacionService::class)
            ->procesaArbolaprobacion(self::TIPO_COMPROBANTE, $solicitudpagoId, 'insert');
    }

    public function procesaArbol(
        int $comprobanteId,
        string $operacion,
        callable $leeAprobacionComprobante,
        callable $buscaProximoNivel,
        callable $enviaCorreo,
    ): int {
        $sp = Solicitudpago::query()->with(['monedas', 'empresas'])->find($comprobanteId);
        if (! $sp) {
            return 0;
        }

        if (in_array($sp->estado, [
            SolicitudpagoEstados::AUTORIZADA,
            SolicitudpagoEstados::PAGADA,
            SolicitudpagoEstados::RECHAZADA,
            SolicitudpagoEstados::TERMINADA,
            SolicitudpagoEstados::SUSPENDIDA,
        ], true)) {
            return 0;
        }

        $tipoarbol = $this->nombreTipoArbol();
        $arbolaprobacion = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($tipoarbol, (int) $sp->empresa_id);
        if (! $arbolaprobacion || ! $arbolaprobacion->count()) {
            $arbolaprobacion = $this->arbolaprobacionRepository->findPorTipoArbol($tipoarbol);
        }
        if (! $arbolaprobacion || ! $arbolaprobacion->count()) {
            return 0;
        }
        if ($arbolaprobacion->count() > 1) {
            throw new \RuntimeException('Hay más de un árbol de aprobación activo de Solicitudes de pago; debe quedar uno solo.');
        }

        $arbol = $arbolaprobacion->first();
        $monto = (float) $sp->monto;
        $monedaId = (int) ($sp->moneda_id ?? 0);
        $centrocostoId = 0;

        while (true) {
            $estadoAprobacionActual = $leeAprobacionComprobante($tipoarbol, $comprobanteId);
            $proximoNivel = $buscaProximoNivel(
                $arbol,
                $centrocostoId,
                $estadoAprobacionActual['nivelactual'],
                $sp->fecha,
                $monto,
                $monedaId
            );

            if ($proximoNivel['proximonivel'] === -1) {
                $this->finalizaTrasArbolCompleto($comprobanteId, Auth::id() ?? (int) $sp->usuario_umod_id);

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
            $ref = (string) ($sp->codigo ?? $sp->id);
            $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('VIS'.$comprobanteId.$sp->fecha.$ref));
            $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar($ip, 'solicitudpago/solicitudpago', (int) $comprobanteId, $hashVisualizar);

            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $uids = $proximoNivel['proximousuarios'] ?? [];
            if (! is_array($uids) || count($uids) === 0) {
                $uids = [$proximoNivel['proximousuario']];
            }
            $uids = array_values(array_unique(array_filter($uids)));

            $ya = Arbolaprobacion_Movimiento::query()
                ->where('solicitudpago_id', $comprobanteId)
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
                    self::TIPO_COMPROBANTE.'A'.$comprobanteId.$sp->fecha.$ref.'N'.$estadoAprobacionActual['nivelactual'].'U'.$uid
                ));
                $hashRechazo = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make(
                    self::TIPO_COMPROBANTE.'R'.$comprobanteId.$sp->fecha.$ref.'N'.$estadoAprobacionActual['nivelactual'].'U'.$uid
                ));
                $linkAprobacion = ArbolAprobacionEnlaceSupport::enlaceAprobar($ip, self::TIPO_COMPROBANTE, (int) $comprobanteId, $hashAprobacion);
                $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo($ip, self::TIPO_COMPROBANTE, (int) $comprobanteId, $hashRechazo);

                $enviaCorreo($uid, $tipoarbol, $sp, $linkAprobacion, $linkRechazo, $linkVisualizar, [
                    'monto_items' => $monto,
                    'moneda_abrev_items' => optional($sp->monedas)->abreviatura ?? '',
                ]);

                $this->arbolaprobacionMovimientoRepository->create([
                    'arbolaprobacion_id' => $arbol->id,
                    'fechaenvio' => Carbon::now(),
                    'enviousuario_id' => Auth::id() ?? $sp->usuario_umod_id,
                    'requisicion_id' => null,
                    'ordencompra_id' => null,
                    'solicitudpago_id' => $comprobanteId,
                    'ordenventa_id' => null,
                    'pedido_id' => null,
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

    public function finalizaTrasArbolCompleto(int $solicitudpagoId, $usuarioId): void
    {
        $this->solicitudpagoRepository->cambiarEstado(
            $solicitudpagoId,
            SolicitudpagoEstados::AUTORIZADA,
            'Autorizada por árbol'
        );
    }

    public function rechazaPorRechazo(int $solicitudpagoId, $usuarioId, string $observacion): void
    {
        $this->solicitudpagoRepository->cambiarEstado(
            $solicitudpagoId,
            SolicitudpagoEstados::RECHAZADA,
            mb_substr(trim($observacion) !== '' ? $observacion : 'Rechazada en árbol', 0, 80)
        );
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
            'solicitudpago_id' => $comprobanteId,
            'ordenventa_id' => null,
            'pedido_id' => null,
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
