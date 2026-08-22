<?php

namespace App\Services\Solicitudpago;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Solicitudpago\Concepto_Solicitudpago_Usuario;
use App\Models\Solicitudpago\Solicitudpago;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Árbol de aprobación de SP: vive en el concepto (concepto_solicitudpago_usuario),
 * no en el ABM global de árboles. Anita: concsolusu / SOLPM_procesa_arbol.
 */
class SolicitudpagoArbolIntegracionService
{
    public const TIPO_COMPROBANTE = 'SP';

    public function __construct(
        private Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacionMovimientoRepository,
        private SolicitudpagoRepositoryInterface $solicitudpagoRepository,
        private UsuarioRepositoryInterface $usuarioRepository,
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
            ->orderBy('nivel')
            ->orderBy('id')
            ->with('enviousuarios')
            ->with('destinatariousuarios')
            ->get();
    }

    /** Valida hashvisualizar (o hash de aprobación/rechazo) para descarga pública desde el mail. */
    public function hashAutorizaDescargaPaquete(int $solicitudpagoId, string $hash): bool
    {
        $hash = ArbolAprobacionEnlaceSupport::normalizarHashRecibido($hash);
        if ($hash === '') {
            return false;
        }

        foreach ($this->findPorSolicitudpago($solicitudpagoId) as $movimiento) {
            foreach (['hashvisualizar', 'hashaprobacion', 'hashrechazo'] as $campo) {
                $almacenado = (string) ($movimiento->{$campo} ?? '');
                if ($almacenado !== '' && ArbolAprobacionEnlaceSupport::hashesCoinciden($hash, $almacenado)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function dispararAlGuardar(int $solicitudpagoId): int
    {
        return app(\App\Services\Configuracion\ArbolaprobacionService::class)
            ->procesaArbolaprobacion(self::TIPO_COMPROBANTE, $solicitudpagoId, 'insert');
    }

    /**
     * @return array{ok: bool, mensaje: string, nivel?: int}
     */
    public function reenviarAlArbolAprobacion(int $solicitudpagoId): array
    {
        $sp = $this->solicitudpagoRepository->findOrFail($solicitudpagoId);

        if ($sp->estado === SolicitudpagoEstados::PAGADA) {
            return [
                'ok' => false,
                'mensaje' => 'No se puede reenviar al árbol una solicitud en estado PAGADA.',
            ];
        }

        return DB::transaction(function () use ($sp, $solicitudpagoId) {
            EloquentAuditDeleteSupport::each(
                Arbolaprobacion_Movimiento::query()
                    ->where('solicitudpago_id', $solicitudpagoId)
            );

            $estadosAptosSinReset = [
                SolicitudpagoEstados::EMITIDA,
                SolicitudpagoEstados::CONTROLADA,
            ];
            if (! in_array($sp->estado, $estadosAptosSinReset, true)) {
                $this->solicitudpagoRepository->cambiarEstado(
                    $solicitudpagoId,
                    SolicitudpagoEstados::EMITIDA,
                    'Reenvío al árbol de aprobación'
                );
            }

            $nivel = app(\App\Services\Configuracion\ArbolaprobacionService::class)
                ->procesaArbolaprobacion(self::TIPO_COMPROBANTE, $solicitudpagoId, 'insert');

            if ($nivel === -1) {
                $spFresh = $this->solicitudpagoRepository->findOrFail($solicitudpagoId);

                return [
                    'ok' => true,
                    'mensaje' => 'No había niveles pendientes en el árbol del concepto; la solicitud quedó '
                        .SolicitudpagoEstados::label($spFresh->estado).'.',
                    'nivel' => -1,
                ];
            }

            if ($nivel <= 0) {
                return [
                    'ok' => false,
                    'mensaje' => 'No se pudo reenviar: la SP no tiene concepto, o el concepto no tiene firmantes '
                        .'operativos (solapa Usuarios del concepto) para el monto de la solicitud. '
                        .'Se limpiaron los movimientos previos.',
                    'nivel' => 0,
                ];
            }

            return [
                'ok' => true,
                'mensaje' => 'La solicitud se envió nuevamente al árbol del concepto (nivel '.$nivel.').',
                'nivel' => (int) $nivel,
            ];
        });
    }

    /**
     * @return array{ok: bool, mensaje: string, enviados?: int, nivel?: int}
     */
    public function reenviarCorreoNivelPendiente(int $solicitudpagoId): array
    {
        $sp = $this->solicitudpagoRepository->findOrFail($solicitudpagoId);

        if ($sp->estado === SolicitudpagoEstados::PAGADA) {
            return [
                'ok' => false,
                'mensaje' => 'No se puede reenviar el correo de una solicitud PAGADA.',
            ];
        }

        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $pendientes = Arbolaprobacion_Movimiento::query()
            ->where('solicitudpago_id', $solicitudpagoId)
            ->where('estado', $nombrePendiente)
            ->whereNotNull('destinatariousuario_id')
            ->where('destinatariousuario_id', '>', 0)
            ->orderBy('nivel')
            ->orderBy('id')
            ->get();

        if ($pendientes->isEmpty()) {
            return [
                'ok' => false,
                'mensaje' => 'No hay aprobaciones pendientes con destinatario para reenviar el correo. '
                    .'Si la SP recién se creó y nadie recibió mail, use «Reenviar al árbol» '
                    .'(firmantes del concepto de la solicitud).',
            ];
        }

        $nivelActual = (int) $pendientes->max('nivel');
        $delNivel = $pendientes->where('nivel', $nivelActual)->values();

        $spMail = Solicitudpago::query()
            ->with(['monedas', 'empresas', 'proveedores', 'conceptos', 'formapagosol', 'sectores'])
            ->find($solicitudpagoId);
        if (! $spMail) {
            return [
                'ok' => false,
                'mensaje' => 'Solicitud de pago no encontrada.',
            ];
        }

        $tipoarbol = $this->nombreTipoArbol();
        $ip = (string) config('arbolaprobacion.ip_link');
        $arbolService = app(\App\Services\Configuracion\ArbolaprobacionService::class);

        $enviados = 0;
        $errores = [];
        foreach ($delNivel as $mov) {
            $uid = (int) $mov->destinatariousuario_id;
            if ($uid <= 0) {
                continue;
            }
            $esAvisoPago = $this->esNivelAvisoPago($spMail, $nivelActual);
            if (trim((string) ($mov->hashrechazo ?? '')) === '') {
                $errores[] = 'Movimiento #'.$mov->id.' sin hash de rechazo.';

                continue;
            }
            if (! $esAvisoPago && trim((string) ($mov->hashaprobacion ?? '')) === '') {
                $errores[] = 'Movimiento #'.$mov->id.' sin hashes de aprobación/rechazo.';

                continue;
            }

            $hashVis = (string) ($mov->hashvisualizar ?? $mov->hashaprobacion ?? $mov->hashrechazo);
            $linkAprobacion = $esAvisoPago
                ? ''
                : ArbolAprobacionEnlaceSupport::enlaceAprobar(
                    $ip,
                    self::TIPO_COMPROBANTE,
                    $solicitudpagoId,
                    (string) $mov->hashaprobacion
                );
            $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo(
                $ip,
                self::TIPO_COMPROBANTE,
                $solicitudpagoId,
                (string) $mov->hashrechazo
            );
            $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar(
                $ip,
                'solicitudpago/solicitudpago/visualizar',
                $solicitudpagoId,
                $hashVis
            );
            $extras = $this->armaExtrasMail($spMail, $nivelActual, $hashVis, $ip);

            try {
                $arbolService->enviaCorreo(
                    $uid,
                    $tipoarbol,
                    $spMail,
                    $linkAprobacion,
                    $linkRechazo,
                    $linkVisualizar,
                    $extras
                );
                $mov->update(['fechaenvio' => Carbon::now()]);
                $enviados++;
            } catch (\Throwable $e) {
                $errores[] = 'Usuario '.$uid.': '.$e->getMessage();
            }
        }

        if ($enviados === 0) {
            return [
                'ok' => false,
                'mensaje' => 'No se pudo reenviar el correo. '
                    .($errores !== [] ? implode(' | ', $errores) : 'Sin destinatarios válidos.'),
                'enviados' => 0,
                'nivel' => $nivelActual,
            ];
        }

        $mensaje = 'Se reenvió el correo del nivel '.$nivelActual.' a '.$enviados.' destinatario(s).';
        if ($errores !== []) {
            $mensaje .= ' Observaciones: '.implode(' | ', $errores);
        }

        return [
            'ok' => true,
            'mensaje' => $mensaje,
            'enviados' => $enviados,
            'nivel' => $nivelActual,
        ];
    }

    public function tienePendientesConCorreo(int $solicitudpagoId): bool
    {
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        return Arbolaprobacion_Movimiento::query()
            ->where('solicitudpago_id', $solicitudpagoId)
            ->where('estado', $nombrePendiente)
            ->whereNotNull('destinatariousuario_id')
            ->where('destinatariousuario_id', '>', 0)
            ->exists();
    }

    public function procesaArbol(
        int $comprobanteId,
        string $operacion,
        callable $leeAprobacionComprobante,
        callable $buscaProximoNivel,
        callable $enviaCorreo,
    ): int {
        unset($buscaProximoNivel); // El árbol SP no usa niveles del ABM global.

        $sp = Solicitudpago::query()
            ->with(['monedas', 'empresas', 'proveedores', 'conceptos.usuarios', 'formapagosol', 'sectores', 'archivos'])
            ->find($comprobanteId);
        if (! $sp) {
            return 0;
        }

        if (in_array($sp->estado, [
            SolicitudpagoEstados::PAGADA,
            SolicitudpagoEstados::RECHAZADA,
            SolicitudpagoEstados::TERMINADA,
            SolicitudpagoEstados::SUSPENDIDA,
        ], true)) {
            return 0;
        }

        if (! $sp->concepto_solicitudpago_id) {
            return 0;
        }

        $tipoarbol = $this->nombreTipoArbol();
        $arbolShell = $this->asegurarArbolShell((int) ($sp->empresa_id ?? 0));
        $monto = (float) $sp->monto;
        $empresaId = (int) ($sp->empresa_id ?? 0);

        while (true) {
            $sp->refresh();
            if (in_array($sp->estado, [
                SolicitudpagoEstados::PAGADA,
                SolicitudpagoEstados::RECHAZADA,
                SolicitudpagoEstados::TERMINADA,
                SolicitudpagoEstados::SUSPENDIDA,
            ], true)) {
                return $sp->estado === SolicitudpagoEstados::PAGADA ? -1 : 0;
            }

            $estadoAprobacionActual = $leeAprobacionComprobante($tipoarbol, $comprobanteId);
            $proximoNivel = $this->buscaProximoNivelDesdeConcepto(
                $sp,
                (int) $estadoAprobacionActual['nivelactual'],
                $monto,
                $empresaId
            );

            if ($proximoNivel['proximonivel'] === -1) {
                $this->finalizaTrasArbolCompleto($comprobanteId, Auth::id() ?? (int) $sp->usuario_umod_id);

                return -1;
            }

            if ($proximoNivel['proximonivel'] <= 0) {
                return 0;
            }

            // Nivel EMITIDA o sin firmantes operativos: pasa automático (como árbol general sin usuario).
            if (! empty($proximoNivel['auto'])) {
                $nivelAuto = (int) $proximoNivel['proximonivel'];
                $this->aplicaEstadoTrasAprobarNivel($comprobanteId, $nivelAuto, Auth::id() ?? $sp->usuario_umod_id);
                $obsAuto = 'Nivel sin usuario (automático)';
                $estAuto = (string) ($proximoNivel['documento_estado_al_aprobar'] ?? '');
                if ($estAuto === SolicitudpagoEstados::EMITIDA) {
                    $obsAuto = 'Nivel EMITIDA (automático)';
                } elseif (SolicitudpagoEstados::esAvisoPagoArbol($estAuto)) {
                    $obsAuto = 'Nivel aviso pago sin firmantes (automático → AUTORIZADA)';
                }
                $this->grabaMovimientoArbolAutomaticoSp(
                    (int) $arbolShell->id,
                    $comprobanteId,
                    $nivelAuto,
                    Auth::id() ?? (int) $sp->usuario_umod_id,
                    $obsAuto
                );
                continue;
            }

            $uids = $proximoNivel['proximousuarios'] ?? [];
            if (! is_array($uids) || $uids === []) {
                return 0;
            }

            $esAvisoPago = ! empty($proximoNivel['es_aviso_pago'])
                || SolicitudpagoEstados::esAvisoPagoArbol($proximoNivel['documento_estado_al_aprobar'] ?? null);

            // Aviso a pagadores: la SP debe quedar AUTORIZADA; PAGADA solo la pone el IE/OP.
            if ($esAvisoPago) {
                $this->asegurarAutorizadaAntesDeAvisoPago($comprobanteId, $sp);
                $sp->refresh();
            }

            $ip = (string) config('arbolaprobacion.ip_link');
            $ref = (string) ($sp->codigo ?? $sp->id);
            $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('VIS'.$comprobanteId.$sp->fecha.$ref));
            $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar($ip, 'solicitudpago/solicitudpago/visualizar', (int) $comprobanteId, $hashVisualizar);
            $sp->loadMissing(['monedas', 'empresas', 'proveedores', 'conceptos', 'formapagosol', 'sectores', 'archivos']);
            $mailExtras = $this->armaExtrasMail($sp, (int) $proximoNivel['proximonivel'], $hashVisualizar, $ip);

            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $ya = Arbolaprobacion_Movimiento::query()
                ->where('solicitudpago_id', $comprobanteId)
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
                    self::TIPO_COMPROBANTE.'A'.$comprobanteId.$sp->fecha.$ref.'N'.$estadoAprobacionActual['nivelactual'].'U'.$uid
                ));
                $hashRechazo = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make(
                    self::TIPO_COMPROBANTE.'R'.$comprobanteId.$sp->fecha.$ref.'N'.$estadoAprobacionActual['nivelactual'].'U'.$uid
                ));
                // Nivel aviso pago: sin botón aprobar; CTA = IE. Rechazo sí.
                $linkAprobacion = $esAvisoPago
                    ? ''
                    : ArbolAprobacionEnlaceSupport::enlaceAprobar($ip, self::TIPO_COMPROBANTE, (int) $comprobanteId, $hashAprobacion);
                $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo($ip, self::TIPO_COMPROBANTE, (int) $comprobanteId, $hashRechazo);

                $enviaCorreo($uid, $tipoarbol, $sp, $linkAprobacion, $linkRechazo, $linkVisualizar, $mailExtras);

                $this->arbolaprobacionMovimientoRepository->create([
                    'arbolaprobacion_id' => $arbolShell->id,
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
                    'observacion' => $esAvisoPago ? 'Aviso a pagadores (IE)' : '',
                ]);
                $creados++;
            }

            if ($creados === 0 && $ya === []) {
                return 0;
            }

            return (int) $proximoNivel['proximonivel'];
        }
    }

    public function finalizaTrasArbolCompleto(int $solicitudpagoId, $usuarioId): void
    {
        unset($usuarioId);
        $sp = Solicitudpago::query()->find($solicitudpagoId);
        if (! $sp) {
            return;
        }

        if (in_array($sp->estado, [
            SolicitudpagoEstados::PAGADA,
            SolicitudpagoEstados::RECHAZADA,
            SolicitudpagoEstados::TERMINADA,
            SolicitudpagoEstados::SUSPENDIDA,
        ], true)) {
            return;
        }

        $destino = $this->estadoFinalTrasArbolCompleto($sp);
        if ($destino === null || $destino === $sp->estado) {
            return;
        }

        $leyenda = $destino === SolicitudpagoEstados::AUTORIZADA
            ? 'Autorizada por árbol del concepto'
            : 'Árbol del concepto: '.$destino;

        $this->solicitudpagoRepository->cambiarEstado($solicitudpagoId, $destino, $leyenda);
    }

    /**
     * Tras aprobar un nivel: aplica documento_estado_al_aprobar del concepto.
     * PAGADA en el árbol = aviso a pagadores → cabecera AUTORIZADA (PAGADA solo vía IE/OP).
     */
    public function aplicaEstadoTrasAprobarNivel(int $solicitudpagoId, int $nivelAprobado, $usuarioId = null): void
    {
        unset($usuarioId);
        $sp = Solicitudpago::query()->find($solicitudpagoId);
        if (! $sp) {
            return;
        }

        if (in_array($sp->estado, [
            SolicitudpagoEstados::PAGADA,
            SolicitudpagoEstados::RECHAZADA,
            SolicitudpagoEstados::TERMINADA,
            SolicitudpagoEstados::SUSPENDIDA,
        ], true)) {
            return;
        }

        if ($this->esNivelAvisoPago($sp, $nivelAprobado)) {
            $this->asegurarAutorizadaAntesDeAvisoPago($solicitudpagoId, $sp);

            return;
        }

        $destino = $this->estadoTrasAprobarNivel($sp, $nivelAprobado);
        if ($destino === null || $destino === $sp->estado || $destino === SolicitudpagoEstados::EMITIDA) {
            return;
        }

        $leyenda = match ($destino) {
            SolicitudpagoEstados::AUTORIZADA => 'Autorizada por árbol del concepto (nivel '.$nivelAprobado.')',
            SolicitudpagoEstados::CONTROLADA => 'Árbol de aprobación: controlada (nivel '.$nivelAprobado.')',
            default => 'Árbol de aprobación: '.$destino.' (nivel '.$nivelAprobado.')',
        };

        $this->solicitudpagoRepository->cambiarEstado($solicitudpagoId, $destino, $leyenda);
    }

    /**
     * Cierre de seguridad tras procesaArbol: sin pendientes → estado final del último nivel;
     * con pendientes → estado del último nivel aprobado (si está configurado).
     */
    public function asegurarEstadoTrasProcesarArbol(int $solicitudpagoId): void
    {
        $sp = Solicitudpago::query()->find($solicitudpagoId);
        if (! $sp) {
            return;
        }

        if (in_array($sp->estado, [
            SolicitudpagoEstados::PAGADA,
            SolicitudpagoEstados::RECHAZADA,
            SolicitudpagoEstados::TERMINADA,
            SolicitudpagoEstados::SUSPENDIDA,
        ], true)) {
            return;
        }

        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $tienePendientes = Arbolaprobacion_Movimiento::query()
            ->where('solicitudpago_id', $solicitudpagoId)
            ->where('estado', $nombrePendiente)
            ->exists();

        if (! $tienePendientes) {
            $this->finalizaTrasArbolCompleto($solicitudpagoId, Auth::id() ?? (int) $sp->usuario_umod_id);

            return;
        }

        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];
        $nivelMaxAprobado = (int) Arbolaprobacion_Movimiento::query()
            ->where('solicitudpago_id', $solicitudpagoId)
            ->where('estado', $nombreAprobado)
            ->max('nivel');

        if ($nivelMaxAprobado <= 0) {
            return;
        }

        $destino = $this->documentoEstadoNivelConcepto($sp, $nivelMaxAprobado);
        if ($destino === null || $destino === SolicitudpagoEstados::EMITIDA) {
            return;
        }
        // Aviso pago no fija PAGADA; pendientes de ese nivel no deben “finalizar” como pagadas.
        if (SolicitudpagoEstados::esAvisoPagoArbol($destino)) {
            $destino = SolicitudpagoEstados::AUTORIZADA;
        }
        if ($destino === $sp->estado) {
            return;
        }

        $this->solicitudpagoRepository->cambiarEstado(
            $solicitudpagoId,
            $destino,
            'Árbol de aprobación: '.$destino.' (nivel '.$nivelMaxAprobado.')'
        );
    }

    /**
     * Estado de cabecera al aprobar el nivel (null / sin cambio en aviso pago).
     * PAGADA en config = aviso a pagadores → no se expone como destino de cabecera.
     */
    public function estadoTrasAprobarNivel(Solicitudpago $sp, int $nivelQueSeAprueba): ?string
    {
        $cfg = $this->documentoEstadoNivelConcepto($sp, $nivelQueSeAprueba);
        if (SolicitudpagoEstados::esAvisoPagoArbol($cfg)) {
            return null;
        }
        if ($cfg !== null) {
            return $cfg;
        }

        // Fallback legacy si el nivel no tiene estado cargado.
        $siguiente = $this->buscaProximoNivelDesdeConcepto(
            $sp,
            $nivelQueSeAprueba,
            (float) ($sp->monto ?? 0),
            (int) ($sp->empresa_id ?? 0)
        );
        if (! empty($siguiente['es_aviso_pago'])) {
            return SolicitudpagoEstados::AUTORIZADA;
        }
        $prox = (int) ($siguiente['proximonivel'] ?? 0);
        if ($prox === -1) {
            return SolicitudpagoEstados::AUTORIZADA;
        }
        if ($prox > 0) {
            return SolicitudpagoEstados::CONTROLADA;
        }

        return null;
    }

    public function esNivelAvisoPago(Solicitudpago $sp, int $nivel): bool
    {
        return SolicitudpagoEstados::esAvisoPagoArbol($this->documentoEstadoNivelConcepto($sp, $nivel));
    }

    public function rechazaPorRechazo(int $solicitudpagoId, $usuarioId, string $observacion): void
    {
        $this->solicitudpagoRepository->cambiarEstado(
            $solicitudpagoId,
            SolicitudpagoEstados::RECHAZADA,
            mb_substr(trim($observacion) !== '' ? $observacion : 'Rechazada en árbol', 0, 80)
        );
    }

    /**
     * Extras del mail (aprobación o aviso a pagadores).
     *
     * @return array<string, mixed>
     */
    private function armaExtrasMail(Solicitudpago $sp, int $nivelQueSeEnvia, string $hashVisualizar, string $ip): array
    {
        $esAvisoPago = $this->esNivelAvisoPago($sp, $nivelQueSeEnvia);
        $estadoCodigo = $esAvisoPago ? null : $this->estadoTrasAprobarNivel($sp, $nivelQueSeEnvia);
        $estadoTras = $estadoCodigo !== null ? SolicitudpagoEstados::label($estadoCodigo) : null;

        $monto = (float) ($sp->monto ?? 0);
        $monedaAbr = trim((string) (optional($sp->monedas)->abreviatura ?? ''));
        // AR: miles con punto, decimales con coma (igual que requisiciones de sala).
        $montoFmt = number_format($monto, 2, ',', '.');

        $extras = [
            'es_aviso_pago' => $esAvisoPago,
            'estado_tras_aprobar' => $estadoTras,
            'monto_items' => $monto,
            'monto_items_fmt' => $montoFmt,
            'moneda_abrev_items' => $monedaAbr,
            'link_descarga_paquete' => ArbolAprobacionEnlaceSupport::enlaceDescargaPaqueteSolicitudpago(
                $ip,
                (int) $sp->id,
                $hashVisualizar
            ),
            'tiene_archivos' => $sp->relationLoaded('archivos')
                ? $sp->archivos->isNotEmpty()
                : $sp->archivos()->exists(),
        ];

        if ($esAvisoPago) {
            $extras['link_pago'] = ArbolAprobacionEnlaceSupport::enlaceCrearIngresoEgresoDesdeSp($ip, (int) $sp->id, [
                'empresa_id' => (int) ($sp->empresa_id ?? 0) ?: null,
                'proveedor_id' => (int) ($sp->proveedor_id ?? 0) ?: null,
                'detalle' => 'Pago SP '.($sp->codigo ?? $sp->id)
                    .($sp->detalle ? ' — '.$sp->detalle : ''),
            ]);
        }

        return $extras;
    }

    /**
     * @return array{
     *     proximonivel: int,
     *     proximousuario: ?int,
     *     proximousuarios: list<int>,
     *     documento_estado_al_aprobar: ?string,
     *     auto: bool,
     *     es_aviso_pago: bool
     * }
     */
    private function buscaProximoNivelDesdeConcepto(
        Solicitudpago $sp,
        int $nivelActual,
        float $monto,
        int $empresaId
    ): array {
        $vacio = [
            'proximonivel' => 0,
            'proximousuario' => null,
            'proximousuarios' => [],
            'documento_estado_al_aprobar' => null,
            'auto' => false,
            'es_aviso_pago' => false,
        ];
        $completo = [
            'proximonivel' => -1,
            'proximousuario' => null,
            'proximousuarios' => [],
            'documento_estado_al_aprobar' => null,
            'auto' => false,
            'es_aviso_pago' => false,
        ];

        $conceptoId = (int) ($sp->concepto_solicitudpago_id ?? 0);
        if ($conceptoId <= 0) {
            return $vacio;
        }

        $filas = Concepto_Solicitudpago_Usuario::query()
            ->where('concepto_solicitudpago_id', $conceptoId)
            ->where('nivel', '>', 0)
            ->where(function ($q) use ($monto) {
                $q->whereNull('desde_monto')
                    ->orWhere('desde_monto', '<=', $monto);
            })
            ->orderBy('nivel')
            ->orderBy('id')
            ->get(['nivel', 'usuario_id', 'documento_estado_al_aprobar']);

        if ($filas->isEmpty()) {
            // Sin filas aplicables: no hay circuito → finaliza.
            return $completo;
        }

        /** @var array<int, array{uids: list<int>, estado: ?string}> $porNivel */
        $porNivel = [];
        foreach ($filas as $fila) {
            $n = (int) $fila->nivel;
            if ($n <= $nivelActual) {
                continue;
            }
            if (! isset($porNivel[$n])) {
                $porNivel[$n] = ['uids' => [], 'estado' => null];
            }
            $uid = (int) ($fila->usuario_id ?? 0);
            if ($uid > 0) {
                $porNivel[$n]['uids'][] = $uid;
            }
            if ($porNivel[$n]['estado'] === null) {
                $est = strtoupper(trim((string) ($fila->documento_estado_al_aprobar ?? '')));
                if ($est !== '' && in_array($est, SolicitudpagoEstados::valoresArbolAprobacion(), true)) {
                    $porNivel[$n]['estado'] = $est;
                }
            }
        }

        if ($porNivel === []) {
            return $completo;
        }

        ksort($porNivel, SORT_NUMERIC);

        foreach ($porNivel as $nivel => $cfg) {
            $estado = $cfg['estado'] ?? SolicitudpagoEstados::estadoArbolPorNivel((int) $nivel);
            $uids = array_values(array_unique(array_filter($cfg['uids'])));
            if ($uids !== []) {
                if ($empresaId > 0) {
                    $uids = $this->usuarioRepository->filtrarIdsFirmantesArbolPorEmpresa($uids, $empresaId);
                } else {
                    $uids = $this->usuarioRepository->filtrarIdsOperativos($uids);
                }
                $uids = array_values(array_filter(array_map('intval', $uids)));
            }

            $autoEmitida = $estado === SolicitudpagoEstados::EMITIDA;
            $autoSinUsuario = $uids === [];
            $esAvisoPago = SolicitudpagoEstados::esAvisoPagoArbol($estado);

            if ($autoEmitida || $autoSinUsuario) {
                return [
                    'proximonivel' => (int) $nivel,
                    'proximousuario' => null,
                    'proximousuarios' => [],
                    'documento_estado_al_aprobar' => $estado,
                    'auto' => true,
                    'es_aviso_pago' => false,
                ];
            }

            return [
                'proximonivel' => (int) $nivel,
                'proximousuario' => $uids[0],
                'proximousuarios' => $uids,
                'documento_estado_al_aprobar' => $estado,
                'auto' => false,
                'es_aviso_pago' => $esAvisoPago,
            ];
        }

        return $completo;
    }

    private function documentoEstadoNivelConcepto(Solicitudpago $sp, int $nivel): ?string
    {
        $conceptoId = (int) ($sp->concepto_solicitudpago_id ?? 0);
        if ($conceptoId <= 0 || $nivel <= 0) {
            return null;
        }

        $filas = Concepto_Solicitudpago_Usuario::query()
            ->where('concepto_solicitudpago_id', $conceptoId)
            ->where('nivel', $nivel)
            ->orderBy('id')
            ->get(['documento_estado_al_aprobar']);

        foreach ($filas as $fila) {
            $est = strtoupper(trim((string) ($fila->documento_estado_al_aprobar ?? '')));
            if ($est !== '' && in_array($est, SolicitudpagoEstados::valoresArbolAprobacion(), true)) {
                return $est;
            }
        }

        return SolicitudpagoEstados::estadoArbolPorNivel($nivel);
    }

    private function estadoFinalTrasArbolCompleto(Solicitudpago $sp): string
    {
        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $nivelMax = (int) Arbolaprobacion_Movimiento::query()
            ->where('solicitudpago_id', $sp->id)
            ->where('estado', $nombreAprobado)
            ->max('nivel');

        if ($nivelMax > 0) {
            $cfg = $this->normalizarEstadoCabeceraDesdeArbol(
                $this->documentoEstadoNivelConcepto($sp, $nivelMax)
            );
            if ($cfg !== null) {
                return $cfg;
            }
        }

        // Sin movimientos: último nivel de aprobación del concepto (ignora aviso pago).
        $conceptoId = (int) ($sp->concepto_solicitudpago_id ?? 0);
        if ($conceptoId > 0) {
            $niveles = Concepto_Solicitudpago_Usuario::query()
                ->where('concepto_solicitudpago_id', $conceptoId)
                ->orderByDesc('nivel')
                ->get(['nivel', 'documento_estado_al_aprobar']);
            foreach ($niveles as $fila) {
                $est = strtoupper(trim((string) ($fila->documento_estado_al_aprobar ?? '')));
                if ($est === '') {
                    $est = (string) (SolicitudpagoEstados::estadoArbolPorNivel((int) $fila->nivel) ?? '');
                }
                if (SolicitudpagoEstados::esAvisoPagoArbol($est) || $est === SolicitudpagoEstados::EMITIDA) {
                    continue;
                }
                $cfg = $this->normalizarEstadoCabeceraDesdeArbol($est);
                if ($cfg !== null) {
                    return $cfg;
                }
            }
        }

        return SolicitudpagoEstados::AUTORIZADA;
    }

    /** PAGADA en árbol no es estado de cabecera; EMITIDA no cierra el circuito. */
    private function normalizarEstadoCabeceraDesdeArbol(?string $estado): ?string
    {
        $estado = strtoupper(trim((string) $estado));
        if ($estado === '' || $estado === SolicitudpagoEstados::EMITIDA) {
            return null;
        }
        if (SolicitudpagoEstados::esAvisoPagoArbol($estado)) {
            return SolicitudpagoEstados::AUTORIZADA;
        }
        if (in_array($estado, SolicitudpagoEstados::valoresArbolAprobacion(), true)) {
            return $estado;
        }

        return null;
    }

    private function asegurarAutorizadaAntesDeAvisoPago(int $solicitudpagoId, Solicitudpago $sp): void
    {
        if (in_array($sp->estado, [
            SolicitudpagoEstados::AUTORIZADA,
            SolicitudpagoEstados::PAGADA,
            SolicitudpagoEstados::RECHAZADA,
            SolicitudpagoEstados::TERMINADA,
            SolicitudpagoEstados::SUSPENDIDA,
        ], true)) {
            return;
        }

        $this->solicitudpagoRepository->cambiarEstado(
            $solicitudpagoId,
            SolicitudpagoEstados::AUTORIZADA,
            'Autorizada por árbol del concepto (aviso a pagadores)'
        );
    }

    private function grabaMovimientoArbolAutomaticoSp(
        int $arbolaprobacionId,
        int $solicitudpagoId,
        int $nivel,
        int $envioUid,
        string $observacion
    ): void {
        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $token = self::TIPO_COMPROBANTE.'AUTO'.$solicitudpagoId.'N'.$nivel.str_replace([' ', ':'], '', microtime(false));
        $hashAprobacion = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make($token.'A'));
        $hashRechazo = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make($token.'R'));
        $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make($token.'V'));

        $this->arbolaprobacionMovimientoRepository->create([
            'arbolaprobacion_id' => $arbolaprobacionId,
            'fechaenvio' => Carbon::now(),
            'enviousuario_id' => $envioUid > 0 ? $envioUid : null,
            'requisicion_id' => null,
            'ordencompra_id' => null,
            'solicitudpago_id' => $solicitudpagoId,
            'ordenventa_id' => null,
            'pedido_id' => null,
            'hashaprobacion' => $hashAprobacion,
            'hashrechazo' => $hashRechazo,
            'hashvisualizar' => $hashVisualizar,
            'nivel' => $nivel,
            'destinatariousuario_id' => null,
            'fechaproceso' => Carbon::now(),
            'estado' => $nombreAprobado,
            'observacion' => $observacion,
        ]);
    }

    /**
     * Contenedor FK para arbolaprobacion_movimiento (los niveles reales están en el concepto).
     */
    private function asegurarArbolShell(int $empresaId): Arbolaprobacion
    {
        $tipo = $this->nombreTipoArbol();
        $q = Arbolaprobacion::query()
            ->where('tipoarbol', $tipo)
            ->where('estado', 'Activo')
            ->orderBy('id');

        if ($empresaId > 0) {
            $arbol = (clone $q)->where('empresa_id', $empresaId)->first()
                ?? (clone $q)->whereNull('empresa_id')->first();
        } else {
            $arbol = $q->first();
        }

        if ($arbol) {
            return $arbol;
        }

        return Arbolaprobacion::query()->create([
            'nombre' => 'SP — árbol por concepto (contenedor)',
            'tipoarbol' => $tipo,
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'recordatorio' => 'N',
            'diasinrespuesta' => 2,
            'diavencimientorecordatorio' => 15,
            'estado' => 'Activo',
        ]);
    }
}
