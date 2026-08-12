<?php

namespace App\Services\Compras;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Repositories\Compras\PropuestaPagoRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Árbol de aprobación de Propuesta de pagos (tipo PP).
 * Usa ABM global filtrado por empresa + desdemonto/hastamonto del lote.
 */
class PropuestaPagoArbolIntegracionService
{
    public const TIPO_COMPROBANTE = 'PP';

    public function __construct(
        private ArbolaprobacionRepositoryInterface $arbolaprobacionRepository,
        private Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacionMovimientoRepository,
        private PropuestaPagoRepositoryInterface $propuestaPagoRepository,
    ) {
    }

    public function nombreTipoArbol(): string
    {
        $idx = array_search(self::TIPO_COMPROBANTE, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'));
        if ($idx === false) {
            return 'Propuesta de pagos';
        }

        return Arbolaprobacion::$enumTipoArbol[$idx]['nombre'];
    }

    public function findPorPropuestaPago(int $propuestaPagoId)
    {
        return Arbolaprobacion_Movimiento::query()
            ->where('propuesta_pago_id', $propuestaPagoId)
            ->whereNull('deleted_at')
            ->orderBy('nivel')
            ->orderBy('id')
            ->with('enviousuarios')
            ->with('destinatariousuarios')
            ->get();
    }

    public function dispararAlEnviarAprobacion(int $propuestaPagoId): int
    {
        Arbolaprobacion_Movimiento::query()
            ->where('propuesta_pago_id', $propuestaPagoId)
            ->delete();

        return app(\App\Services\Configuracion\ArbolaprobacionService::class)
            ->procesaArbolaprobacion(self::TIPO_COMPROBANTE, $propuestaPagoId, 'insert');
    }

    public function procesaArbol(
        int $comprobanteId,
        string $operacion,
        callable $leeAprobacionComprobante,
        callable $buscaProximoNivel,
        callable $enviaCorreo,
    ): int {
        unset($buscaProximoNivel); // usamos filtro local por monto (sin exigir CC)

        $propuesta = $this->propuestaPagoRepository->findOrFail($comprobanteId);
        if (in_array((string) $propuesta->estado, ['EJECUTADA', 'EJECUTADA_PARCIAL', 'ANULADA'], true)) {
            return 0;
        }

        $tipoarbol = $this->nombreTipoArbol();
        $arboles = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa(
            $tipoarbol,
            (int) $propuesta->empresa_id
        );
        if (! $arboles || ! $arboles->count()) {
            $arboles = $this->arbolaprobacionRepository->findPorTipoArbol($tipoarbol);
        }
        if (! $arboles || ! $arboles->count()) {
            return 0;
        }

        $arbol = $arboles->first();
        $monto = (float) $propuesta->monto_total;
        $monedaId = (int) ($propuesta->moneda_id ?: 1);
        $fecha = $propuesta->fecha?->format('Y-m-d') ?? date('Y-m-d');

        while (true) {
            $estadoAprobacionActual = $leeAprobacionComprobante($tipoarbol, $comprobanteId);
            $proximoNivel = $this->buscaProximoNivelPorMonto(
                $arbol,
                (int) $estadoAprobacionActual['nivelactual'],
                $monto,
                $monedaId
            );

            if ($proximoNivel['proximonivel'] === -1) {
                $this->marcarAutorizada($comprobanteId, 'Árbol completo');

                return -1;
            }

            if ($proximoNivel['proximonivel'] <= 0) {
                return 0;
            }

            if (empty($proximoNivel['proximousuario'])) {
                $this->grabaMovimientoAutomatico($arbol->id, $comprobanteId, (int) $proximoNivel['proximonivel']);
                continue;
            }

            $ip = (string) config('arbolaprobacion.ip_link');
            $ref = (string) $comprobanteId;
            $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(
                Hash::make('VIS'.$comprobanteId.$fecha.$ref)
            );
            $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar(
                $ip,
                'compras/propuesta-pago',
                (int) $comprobanteId,
                $hashVisualizar
            );

            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
                array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
            ]['nombre'];

            $uids = $proximoNivel['proximousuarios'] ?? [];
            if (! is_array($uids) || $uids === []) {
                $uids = [$proximoNivel['proximousuario']];
            }
            $uids = array_values(array_unique(array_filter($uids)));

            $ya = Arbolaprobacion_Movimiento::query()
                ->where('propuesta_pago_id', $comprobanteId)
                ->where('nivel', $proximoNivel['proximonivel'])
                ->where('estado', $nombrePendiente)
                ->pluck('destinatariousuario_id')
                ->map(fn ($x) => (int) $x)
                ->all();

            $creados = 0;
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

                $this->arbolaprobacionMovimientoRepository->create([
                    'arbolaprobacion_id' => $arbol->id,
                    'fechaenvio' => Carbon::now(),
                    'enviousuario_id' => Auth::id(),
                    'propuesta_pago_id' => $comprobanteId,
                    'hashaprobacion' => $hashAprobacion,
                    'hashrechazo' => $hashRechazo,
                    'hashvisualizar' => $hashVisualizar,
                    'nivel' => $proximoNivel['proximonivel'],
                    'destinatariousuario_id' => $uid,
                    'estado' => $nombrePendiente,
                    'observacion' => 'Propuesta de pagos #'.$comprobanteId,
                ]);

                try {
                    $enviaCorreo(
                        $uid,
                        $tipoarbol,
                        (object) ['id' => $comprobanteId, 'fecha' => $fecha, 'codigo' => $ref],
                        $linkAprobacion,
                        $linkRechazo,
                        $linkVisualizar,
                        []
                    );
                } catch (\Throwable $e) {
                    // Correo no bloquea el movimiento.
                }
                $creados++;
            }

            return (int) $proximoNivel['proximonivel'];
        }
    }

    public function marcarAutorizada(int $propuestaPagoId, string $obs = ''): void
    {
        $this->propuestaPagoRepository->cambiarEstado($propuestaPagoId, 'AUTORIZADA', $obs ?: 'Autorizada por árbol');
        \App\Support\Compras\PropuestaPagoBridgeBancarioSupport::fijarMontoAutorizado(
            $this->propuestaPagoRepository->findOrFail($propuestaPagoId)
        );
    }

    public function rechazaPorRechazo(int $propuestaPagoId, $usuarioId, string $observacion = ''): void
    {
        $this->propuestaPagoRepository->cambiarEstado(
            $propuestaPagoId,
            'RECHAZADA',
            'Rechazada en árbol: '.$observacion
        );
    }

    /**
     * @return array{proximonivel:int,proximousuario:?int,proximousuarios:list<int>}
     */
    private function buscaProximoNivelPorMonto(Arbolaprobacion $arbol, int $nivelActual, float $monto, int $monedaId): array
    {
        $candidatos = [];
        foreach ($arbol->arbolaprobacion_niveles as $nivel) {
            if ((int) $nivel->nivel <= $nivelActual) {
                continue;
            }
            $desde = (float) ($nivel->desdemonto ?? 0);
            $hasta = (float) ($nivel->hastamonto ?? 0);
            $enRango = true;
            if ($hasta > 0 && $monto > $hasta) {
                $enRango = false;
            }
            if ($desde > 0 && $monto < $desde) {
                $enRango = false;
            }
            if (! $enRango) {
                continue;
            }
            if ($nivel->moneda_id && (int) $nivel->moneda_id !== $monedaId && $monedaId > 0) {
                // Misma moneda o sin filtrar: si el nivel fija moneda distinta, igual aplica (lote multi-moneda).
            }
            $candidatos[] = [
                'nivel' => (int) $nivel->nivel,
                'usuario_id' => $nivel->usuario_id ? (int) $nivel->usuario_id : null,
            ];
        }

        if ($candidatos === []) {
            return [
                'proximonivel' => $nivelActual > 0 ? -1 : 0,
                'proximousuario' => null,
                'proximousuarios' => [],
            ];
        }

        usort($candidatos, fn ($a, $b) => $a['nivel'] <=> $b['nivel']);
        $proxNivel = (int) $candidatos[0]['nivel'];
        $enNivel = array_values(array_filter($candidatos, fn ($c) => (int) $c['nivel'] === $proxNivel));
        $uids = array_values(array_unique(array_filter(array_map(fn ($c) => $c['usuario_id'], $enNivel))));

        return [
            'proximonivel' => $proxNivel,
            'proximousuario' => $uids[0] ?? null,
            'proximousuarios' => $uids,
        ];
    }

    private function grabaMovimientoAutomatico(int $arbolId, int $propuestaId, int $nivel): void
    {
        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $this->arbolaprobacionMovimientoRepository->create([
            'arbolaprobacion_id' => $arbolId,
            'fechaenvio' => Carbon::now(),
            'fechaproceso' => Carbon::now(),
            'enviousuario_id' => Auth::id(),
            'propuesta_pago_id' => $propuestaId,
            'nivel' => $nivel,
            'destinatariousuario_id' => Auth::id(),
            'estado' => $nombreAprobado,
            'observacion' => 'Nivel automático (sin firmante)',
        ]);
    }
}
