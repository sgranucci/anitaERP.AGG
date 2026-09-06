<?php

namespace App\Services\Stock;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Stock\Articulo;
use App\Models\Stock\Usoarticulo;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
use App\Repositories\Stock\Articulo_EstadoRepositoryInterface;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use App\Support\Stock\ArticuloAprobacionAltaSupport;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Árbol de aprobación para alta / cambios críticos de artículos (tipo AR).
 * Opt-in vía config articulo.aprobacion_alta.habilitado.
 */
class ArticuloArbolIntegracionService
{
    public const TIPO_COMPROBANTE = 'AR';

    public function __construct(
        private ArbolaprobacionRepositoryInterface $arbolaprobacionRepository,
        private Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacionMovimientoRepository,
        private Articulo_EstadoRepositoryInterface $articuloEstadoRepository,
    ) {}

    public function nombreTipoArbol(): string
    {
        $idx = array_search(self::TIPO_COMPROBANTE, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'), true);

        return (string) (Arbolaprobacion::$enumTipoArbol[$idx]['nombre'] ?? 'Artículos');
    }

    public function findPorArticulo(int $articuloId)
    {
        return Arbolaprobacion_Movimiento::query()
            ->where('articulo_id', $articuloId)
            ->orderBy('nivel')
            ->orderBy('id')
            ->with('enviousuarios')
            ->with('destinatariousuarios')
            ->get();
    }

    /**
     * Alta: si el circuito está off, no hace nada (queda ACTIVO como hoy).
     * Si está on: deja PENDIENTE y dispara árbol / auto-aprueba según uso.
     */
    public function dispararAlGuardar(int $articuloId): int
    {
        if (! ArticuloAprobacionAltaSupport::habilitado()) {
            return 0;
        }

        $articulo = Articulo::query()->with('usoarticulos')->find($articuloId);
        if (! $articulo) {
            return 0;
        }

        $this->marcarEstado(
            $articuloId,
            ArticuloAprobacionAltaSupport::ESTADO_PENDIENTE,
            'Alta pendiente de aprobación'
        );

        return $this->iniciarCircuito($articuloId, 'insert');
    }

    /**
     * Tras editar: reabre si cambió uso o cuentas (o está RECHAZADO y se vuelve a enviar).
     *
     * @param  array<string, mixed>  $antes  snapshot previo (usoarticulo_id, fingerprint cuentas)
     * @param  array<string, mixed>  $despues
     */
    public function evaluarTrasActualizar(int $articuloId, array $antes, array $despues): int
    {
        if (! ArticuloAprobacionAltaSupport::habilitado()) {
            return 0;
        }

        $articulo = Articulo::query()->find($articuloId);
        if (! $articulo) {
            return 0;
        }

        $estado = strtoupper(trim((string) ($articulo->estado ?? '')));
        $usoCambio = (int) ($antes['usoarticulo_id'] ?? 0) !== (int) ($despues['usoarticulo_id'] ?? 0);
        $cuentasCambio = (string) ($antes['cuentas_fp'] ?? '') !== (string) ($despues['cuentas_fp'] ?? '');
        $eraActivo = $estado === ArticuloAprobacionAltaSupport::ESTADO_ACTIVO;
        $eraRechazado = $estado === ArticuloAprobacionAltaSupport::ESTADO_RECHAZADO;
        $eraPendiente = $estado === ArticuloAprobacionAltaSupport::ESTADO_PENDIENTE;

        if ($eraRechazado) {
            $this->anularPendientes($articuloId, 'Reapertura tras rechazo / corrección Compras');

            return $this->iniciarCircuito($articuloId, 'reabrir');
        }

        if ($eraActivo && ($usoCambio || $cuentasCambio)) {
            $this->anularPendientes($articuloId, 'Reapertura por cambio crítico post-ACTIVO');
            $this->marcarEstado(
                $articuloId,
                ArticuloAprobacionAltaSupport::ESTADO_PENDIENTE,
                $usoCambio
                    ? 'Cambio de uso: reabre circuito de aprobación'
                    : 'Cambio de cuentas contables: reabre circuito de aprobación'
            );

            return $this->iniciarCircuito($articuloId, 'reevaluar');
        }

        if ($eraPendiente && $usoCambio) {
            $this->anularPendientes($articuloId, 'Cambio de uso: reevalúa árbol');

            return $this->iniciarCircuito($articuloId, 'reevaluar');
        }

        return 0;
    }

    public function fingerprintCuentas(int $articuloId): string
    {
        if (! Schema::hasTable('articulo_cuentacontable')) {
            return '';
        }

        $rows = \DB::table('articulo_cuentacontable')
            ->where('articulo_id', $articuloId)
            ->orderBy('empresa_id')
            ->orderBy('tipoimputacion')
            ->orderBy('cuentacontable_id')
            ->get(['empresa_id', 'tipoimputacion', 'cuentacontable_id']);

        $parts = [];
        foreach ($rows as $r) {
            $parts[] = (int) $r->empresa_id.'|'.$r->tipoimputacion.'|'.(int) $r->cuentacontable_id;
        }

        return implode(';', $parts);
    }

    public function iniciarCircuito(int $articuloId, string $operacion): int
    {
        $articulo = Articulo::query()->with('usoarticulos')->find($articuloId);
        if (! $articulo) {
            return 0;
        }

        $modo = $this->modoDesdeUso($articulo);
        if ($modo === ArticuloAprobacionAltaSupport::MODO_AUTO) {
            $this->finalizaTrasArbolCompleto($articuloId, Auth::id());

            return -1;
        }

        return app(\App\Services\Configuracion\ArbolaprobacionService::class)
            ->procesaArbolaprobacion(self::TIPO_COMPROBANTE, $articuloId, $operacion);
    }

    public function procesaArbol(
        int $comprobanteId,
        string $operacion,
        callable $leeAprobacionComprobante,
        callable $buscaProximoNivel,
        callable $enviaCorreo,
    ): int {
        $articulo = Articulo::query()->with(['usoarticulos', 'articulo_cuentacontables'])->find($comprobanteId);
        if (! $articulo) {
            return 0;
        }

        $arbol = $this->resolverArbol($articulo);
        if (! $arbol) {
            Log::warning('articulo_arbol_sin_config', [
                'articulo_id' => $comprobanteId,
                'usoarticulo_id' => (int) ($articulo->usoarticulo_id ?? 0),
            ]);

            return 0;
        }

        $tipoarbol = $this->nombreTipoArbol();
        $monto = 0.0;
        $monedaId = 0;
        $centrocostoId = 0;
        $ref = (string) ($articulo->sku ?? $articulo->id);
        $fecha = Carbon::now()->toDateString();

        while (true) {
            $estadoAprobacionActual = $leeAprobacionComprobante($tipoarbol, $comprobanteId);
            $proximoNivel = $buscaProximoNivel(
                $arbol,
                $centrocostoId,
                $estadoAprobacionActual['nivelactual'],
                $fecha,
                $monto,
                $monedaId
            );

            if ($proximoNivel['proximonivel'] === -1) {
                $this->finalizaTrasArbolCompleto($comprobanteId, Auth::id());

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
            $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(
                Hash::make('VIS'.$comprobanteId.$fecha.$ref)
            );
            $linkBandeja = function_exists('urlAppAbsoluta')
                ? urlAppAbsoluta('mis-aprobaciones')
                : url('mis-aprobaciones');
            $linkVisualizar = $linkBandeja;

            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
                array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
            ]['nombre'];
            $uids = $proximoNivel['proximousuarios'] ?? [];
            if (! is_array($uids) || count($uids) === 0) {
                $uids = [$proximoNivel['proximousuario']];
            }
            $uids = array_values(array_unique(array_filter($uids)));

            $ya = Arbolaprobacion_Movimiento::query()
                ->where('articulo_id', $comprobanteId)
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
                    self::TIPO_COMPROBANTE.'A'.$comprobanteId.$fecha.$ref.'N'.$estadoAprobacionActual['nivelactual'].'U'.$uid
                ));
                $hashRechazo = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make(
                    self::TIPO_COMPROBANTE.'R'.$comprobanteId.$fecha.$ref.'N'.$estadoAprobacionActual['nivelactual'].'U'.$uid
                ));
                $linkAprobacion = ArbolAprobacionEnlaceSupport::enlaceAprobar(
                    $ip,
                    self::TIPO_COMPROBANTE,
                    (int) $comprobanteId,
                    $hashAprobacion
                );
                $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo(
                    $ip,
                    self::TIPO_COMPROBANTE,
                    (int) $comprobanteId,
                    $hashRechazo
                );

                $enviaCorreo($uid, $tipoarbol, $articulo, $linkAprobacion, $linkRechazo, $linkVisualizar, [
                    'link_bandeja' => $linkBandeja,
                    'sku' => $ref,
                ]);

                $this->arbolaprobacionMovimientoRepository->create([
                    'arbolaprobacion_id' => $arbol->id,
                    'fechaenvio' => Carbon::now(),
                    'enviousuario_id' => Auth::id(),
                    'requisicion_id' => null,
                    'ordencompra_id' => null,
                    'solicitudpago_id' => null,
                    'ordenventa_id' => null,
                    'pedido_id' => null,
                    'propuesta_pago_id' => null,
                    'articulo_id' => $comprobanteId,
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

    public function finalizaTrasArbolCompleto(int $articuloId, $usuarioId): void
    {
        $this->marcarEstado(
            $articuloId,
            ArticuloAprobacionAltaSupport::ESTADO_ACTIVO,
            'Artículo aprobado — circuito de aprobación completo',
            $usuarioId
        );
    }

    public function rechazaPorRechazo(int $articuloId, $usuarioId, string $observacion): void
    {
        $this->anularPendientes($articuloId, 'Rechazo: '.$observacion);
        $this->marcarEstado(
            $articuloId,
            ArticuloAprobacionAltaSupport::ESTADO_RECHAZADO,
            'Rechazado en árbol — Compras debe corregir y reabrir: '.$observacion,
            $usuarioId
        );
    }

    private function modoDesdeUso(Articulo $articulo): string
    {
        $uso = $articulo->usoarticulos;
        if (! $uso instanceof Usoarticulo) {
            $uso = Usoarticulo::query()->find((int) ($articulo->usoarticulo_id ?? 0));
        }
        $modo = strtolower(trim((string) ($uso->aprobacion_modo ?? ArticuloAprobacionAltaSupport::MODO_DEFAULT)));

        if (! in_array($modo, [
            ArticuloAprobacionAltaSupport::MODO_AUTO,
            ArticuloAprobacionAltaSupport::MODO_ARBOL,
            ArticuloAprobacionAltaSupport::MODO_DEFAULT,
        ], true)) {
            return ArticuloAprobacionAltaSupport::MODO_DEFAULT;
        }

        return $modo;
    }

    private function resolverArbol(Articulo $articulo): ?Arbolaprobacion
    {
        $uso = $articulo->usoarticulos;
        if (! $uso instanceof Usoarticulo) {
            $uso = Usoarticulo::query()->find((int) ($articulo->usoarticulo_id ?? 0));
        }

        $modo = $this->modoDesdeUso($articulo);
        if ($modo === ArticuloAprobacionAltaSupport::MODO_ARBOL) {
            $id = (int) ($uso->arbolaprobacion_id ?? 0);
            if ($id > 0) {
                $arbol = $this->arbolaprobacionRepository->find($id);
                if ($arbol) {
                    $est = strtoupper(trim((string) ($arbol->estado ?? '')));
                    if ($est === 'ACTIVO') {
                        return $arbol;
                    }
                }
            }
        }

        // default / arbol sin id: primer árbol activo de tipo Artículos
        $tipoarbol = $this->nombreTipoArbol();
        $coleccion = $this->arbolaprobacionRepository->findPorTipoArbol($tipoarbol);
        if (! $coleccion || ! $coleccion->count()) {
            return null;
        }

        // Preferir árbol cuyo nombre contenga "default" (case-insensitive); sino el primero.
        $preferido = $coleccion->first(function ($a) {
            return stripos((string) ($a->nombre ?? ''), 'default') !== false
                || stripos((string) ($a->nombre ?? ''), 'contadur') !== false;
        });

        return $preferido ?: $coleccion->first();
    }

    private function marcarEstado(int $articuloId, string $estado, string $observacion, $usuarioId = null): void
    {
        Articulo::query()->whereKey($articuloId)->update(['estado' => $estado]);

        $uid = (int) ($usuarioId ?: Auth::id() ?: 0);
        $data = [
            'estadofechas' => [Carbon::now()],
            'estados' => [$estado],
            'estadoobservaciones' => [mb_substr($observacion, 0, 250)],
            'estadousuarios' => [$uid > 0 ? $uid : null],
        ];
        $this->articuloEstadoRepository->create($data, $articuloId);
    }

    private function anularPendientes(int $articuloId, string $observacion): void
    {
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];
        $nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        Arbolaprobacion_Movimiento::query()
            ->where('articulo_id', $articuloId)
            ->where('estado', $nombrePendiente)
            ->update([
                'estado' => $nombreSinEfecto,
                'fechaproceso' => Carbon::now(),
                'observacion' => mb_substr($observacion, 0, 250),
            ]);
    }

    private function grabaMovimientoAutomatico(int $arbolId, int $comprobanteId, int $nivel): void
    {
        $token = self::TIPO_COMPROBANTE.'AUTO'.$comprobanteId.'N'.$nivel.str_replace([' ', ':'], '', microtime(false));
        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $this->arbolaprobacionMovimientoRepository->create([
            'arbolaprobacion_id' => $arbolId,
            'fechaenvio' => Carbon::now(),
            'enviousuario_id' => Auth::id(),
            'requisicion_id' => null,
            'ordencompra_id' => null,
            'solicitudpago_id' => null,
            'ordenventa_id' => null,
            'pedido_id' => null,
            'propuesta_pago_id' => null,
            'articulo_id' => $comprobanteId,
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
