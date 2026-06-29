<?php

namespace App\Services\Sala;

use App\Mail\Configuracion\MailArbolAprobacion;
use App\Mail\Sala\MailRequisicionSalaRechazoArbol;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Sala\RequisicionSala;
use App\Models\Sala\RequisicionSalaEstado;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaEstadoRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaRepositoryInterface;
use App\Support\Sala\RequisicionSalaLineasLaboratorioSupport;
use App\Support\Sala\RequisicionSalaTransferenciaLaboratorioDeferred;
use App\Support\Sala\RequisicionSalaTransferenciaLaboratorioPreflightSupport;
use App\Support\Sala\RequisicionSalaTotalesCabecera;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Mail;

/**
 * Lógica de árbol de aprobación para requisiciones de sala (comprobante RS).
 */
class RequisicionSalaArbolIntegracionService
{
    public const TIPO_COMPROBANTE = 'RS';

    public function __construct(
        private ArbolaprobacionRepositoryInterface $arbolaprobacionRepository,
        private Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacionMovimientoRepository,
        private RequisicionSalaRepositoryInterface $requisicionSalaRepository,
        private RequisicionSalaEstadoRepositoryInterface $requisicionSalaEstadoRepository,
        private UsuarioRepositoryInterface $usuarioRepository,
    ) {
    }

    public function nombreTipoArbol(): string
    {
        return Arbolaprobacion::$enumTipoArbol[array_search(self::TIPO_COMPROBANTE, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
    }

    public function validaRequestContraArbol(array $data): void
    {
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            return;
        }
        $trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($this->nombreTipoArbol(), $empresaId);
        if ($trees->isEmpty()) {
            throw new \RuntimeException('No hay un árbol de aprobación activo de requisiciones de sala para la empresa seleccionada.');
        }
        if ($trees->count() > 1) {
            throw new \RuntimeException('Hay más de un árbol de aprobación activo de requisiciones de sala para esa empresa; debe quedar uno solo.');
        }
    }

    public function validaModeloContraArbol(RequisicionSala $req): void
    {
        $this->validaRequestContraArbol(['empresa_id' => $req->empresa_id]);
    }

    public function procesaArbol(
        int $comprobanteId,
        string $operacion,
        callable $leeAprobacionComprobante,
        callable $buscaProximoNivel,
    ): int {
        $req = $this->requisicionSalaRepository->find($comprobanteId);
        if (! $req) {
            return 0;
        }

        $arbolaprobacion = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($this->nombreTipoArbol(), (int) $req->empresa_id);
        if (! $arbolaprobacion || ! $arbolaprobacion->count()) {
            return 0;
        }
        if ($arbolaprobacion->count() > 1) {
            throw new \RuntimeException('Hay más de un árbol de aprobación activo de requisiciones de sala para la empresa; debe quedar uno solo.');
        }

        $arbol = $arbolaprobacion->first();
        $arrayReplace = ['/', '%'];
        $centrocostoArbol = (int) $req->centrocosto_id;
        $tipoarbol = $this->nombreTipoArbol();

        while (true) {
            $req = $this->requisicionSalaRepository->find($comprobanteId);
            $totales = RequisicionSalaTotalesCabecera::desdeModelo($req);
            $nombreAprobada = RequisicionSalaEstado::$enumEstado[array_search('A', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
            $nombreRechazada = RequisicionSalaEstado::$enumEstado[array_search('Z', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
            $nombreCumplido = RequisicionSalaEstado::$enumEstado[array_search('3', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
            $nombreSuspendido = RequisicionSalaEstado::$enumEstado[array_search('4', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
            if (in_array($req->estado, [$nombreAprobada, $nombreRechazada, $nombreCumplido, $nombreSuspendido], true)) {
                return 0;
            }

            $estadoAprobacionActual = $leeAprobacionComprobante($tipoarbol, $comprobanteId);
            $proximoNivel = $buscaProximoNivel(
                $arbol,
                $centrocostoArbol,
                $estadoAprobacionActual['nivelactual'],
                $req->fecha,
                $totales['monto'],
                $totales['moneda_id']
            );

            if ($proximoNivel['proximonivel'] === -1) {
                $this->finalizaTrasArbolCompleto($comprobanteId, Auth::check() ? Auth::user()->id : $req->creousuario_id);

                return -1;
            }
            if ($proximoNivel['proximonivel'] <= 0) {
                return 0;
            }

            if (empty($proximoNivel['proximousuario'])) {
                $this->aplicaEstadoPorNombre(
                    $comprobanteId,
                    $proximoNivel['documento_estado_al_aprobar'],
                    'Árbol de aprobación: nivel '.$proximoNivel['proximonivel'].' sin usuario (automático)',
                    $req->creousuario_id
                );
                $this->grabaMovimientoAutomatico($arbol->id, $comprobanteId, $proximoNivel['proximonivel'], $arrayReplace);
                $nombreEnLaboratorio = RequisicionSalaEstado::$enumEstado[array_search('5', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
                $reqTrasAuto = $this->requisicionSalaRepository->find($comprobanteId);
                if ($reqTrasAuto && $reqTrasAuto->estado === $nombreEnLaboratorio) {
                    return 0;
                }

                continue;
            }

            $ip = config('arbolaprobacion.ip_link');
            $hashVisualizar = Hash::make('VISRS'.$comprobanteId.$req->fecha.$req->numerorequisicion);
            $hashVisualizar = str_replace($arrayReplace, '+', $hashVisualizar);
            $linkVisualizar = ModoConsultaUrlSupport::urlVisualizarRequisicionSala(
                $comprobanteId,
                $hashVisualizar
            );

            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $envioUid = Auth::check() ? Auth::user()->id : $req->creousuario_id;
            $uids = $proximoNivel['proximousuarios'] ?? [$proximoNivel['proximousuario']];
            $uids = array_values(array_unique(array_filter($uids)));

            $mailExtras = $this->armaExtrasMail($req, $proximoNivel['documento_estado_al_aprobar']);

            $ya = Arbolaprobacion_Movimiento::where('requisicion_sala_id', $comprobanteId)
                ->where('nivel', $proximoNivel['proximonivel'])
                ->where('estado', $nombrePendiente)
                ->pluck('destinatariousuario_id')->map(fn ($x) => (int) $x)->all();

            foreach ($uids as $uid) {
                $uid = (int) $uid;
                if ($uid <= 0 || in_array($uid, $ya, true)) {
                    continue;
                }

                $hashAprobacion = Hash::make('RS'.'A'.$comprobanteId.$req->fecha.$req->numerorequisicion.'N'.
                    $estadoAprobacionActual['nivelactual'].'U'.$uid);
                $hashRechazo = Hash::make('RS'.'R'.$comprobanteId.$req->fecha.$req->numerorequisicion.'N'.
                    $estadoAprobacionActual['nivelactual'].'U'.$uid);
                $hashAprobacion = str_replace($arrayReplace, '+', $hashAprobacion);
                $hashRechazo = str_replace($arrayReplace, '+', $hashRechazo);
                $linkAprobacion = $ip.'/anitaERP/public/arbolaprobacion/aprobar/RS/'.$comprobanteId.'/'.$hashAprobacion;
                $linkRechazo = $ip.'/anitaERP/public/arbolaprobacion/buscarechazo/RS/'.$comprobanteId.'/'.$hashRechazo;

                $this->enviaCorreo($uid, $req, $linkAprobacion, $linkRechazo, $linkVisualizar, $mailExtras);

                $this->arbolaprobacionMovimientoRepository->create([
                    'arbolaprobacion_id' => $arbol->id,
                    'fechaenvio' => Carbon::now(),
                    'enviousuario_id' => $envioUid,
                    'requisicion_id' => null,
                    'requisicion_sala_id' => $comprobanteId,
                    'ordencompra_id' => null,
                    'solicitudpago_id' => null,
                    'ordenventa_id' => null,
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

            return $proximoNivel['proximonivel'];
        }
    }

    public function aplicaEstadoPorNombre(int $id, ?string $estadoNombre, string $observacion, $usuarioId): void
    {
        if ($estadoNombre === null || $estadoNombre === '' || ! RequisicionSalaEstado::esNombreEstadoValido($estadoNombre)) {
            return;
        }
        $this->requisicionSalaEstadoRepository->creaEstado($id, Carbon::now()->toDateTimeString(), $estadoNombre, $usuarioId, $observacion);
        $this->requisicionSalaRepository->update(['estado' => $estadoNombre], $id);
        $this->ejecutarTransferenciaLaboratorioSiAprobada($id, $estadoNombre, (int) $usuarioId);
    }

    public function finalizaTrasArbolCompleto(int $id, $usuarioId): void
    {
        $aprobada = RequisicionSalaEstado::$enumEstado[array_search('A', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
        $this->requisicionSalaEstadoRepository->creaEstado(
            $id,
            Carbon::now()->toDateTimeString(),
            $aprobada,
            $usuarioId,
            'Árbol de aprobación completado'
        );
        $this->requisicionSalaRepository->update(['estado' => $aprobada], $id);
        $this->ejecutarTransferenciaLaboratorioSiAprobada($id, $aprobada, (int) $usuarioId);
    }

    private function ejecutarTransferenciaLaboratorioSiAprobada(int $id, string $estadoNombre, int $usuarioId): void
    {
        $nombreAprobada = RequisicionSalaEstado::$enumEstado[array_search('A', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
        if ($estadoNombre !== $nombreAprobada) {
            return;
        }
        RequisicionSalaTransferenciaLaboratorioDeferred::encolar($id, $usuarioId);
    }

    public function rechazaPorRechazo(int $id, $usuarioId, string $observacion): void
    {
        $estado = RequisicionSalaEstado::$enumEstado[array_search('Z', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
        $obs = trim($observacion) !== ''
            ? 'Requisición de sala rechazada en árbol: '.$observacion
            : 'Requisición de sala rechazada en árbol de aprobación';
        $this->requisicionSalaEstadoRepository->creaEstado(
            $id,
            Carbon::now()->toDateTimeString(),
            $estado,
            $usuarioId,
            $obs
        );
        $this->requisicionSalaRepository->update(['estado' => $estado], $id);
        $this->enviaCorreoRechazoAlSolicitante($id, (int) $usuarioId, $observacion);
    }

    public function findPorRequisicionSala(int $id)
    {
        return Arbolaprobacion_Movimiento::where('requisicion_sala_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('nivel')
            ->get();
    }

    public function movimientoPendientePorHash(int $id, string $hash, string $modo): ?Arbolaprobacion_Movimiento
    {
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $campoHash = $modo === 'rechazo' ? 'hashrechazo' : 'hashaprobacion';
        foreach ($this->findPorRequisicionSala($id) as $mov) {
            if ($mov->estado === $nombrePendiente && $mov->{$campoHash} === $hash) {
                return $mov;
            }
        }

        return null;
    }

    public function portalDatosPorHash(int $id, string $hash, string $modo): ?array
    {
        $mov = $this->movimientoPendientePorHash($id, $hash, $modo);
        if (! $mov) {
            return null;
        }
        $req = $this->requisicionSalaRepository->find($id);

        return [
            'requisicion_sala' => $req,
            'movimiento' => $mov,
        ];
    }

    private function grabaMovimientoAutomatico(int $arbolId, int $comprobanteId, int $nivel, array $arrayReplace): void
    {
        $token = 'RSAUTO'.$comprobanteId.'N'.$nivel.str_replace([' ', ':'], '', microtime(false));
        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $this->arbolaprobacionMovimientoRepository->create([
            'arbolaprobacion_id' => $arbolId,
            'fechaenvio' => Carbon::now(),
            'enviousuario_id' => Auth::check() ? Auth::user()->id : 1,
            'requisicion_id' => null,
            'requisicion_sala_id' => $comprobanteId,
            'ordencompra_id' => null,
            'solicitudpago_id' => null,
            'ordenventa_id' => null,
            'hashaprobacion' => str_replace($arrayReplace, '+', Hash::make($token.'A')),
            'hashrechazo' => str_replace($arrayReplace, '+', Hash::make($token.'R')),
            'hashvisualizar' => str_replace($arrayReplace, '+', Hash::make($token.'V')),
            'nivel' => $nivel,
            'destinatariousuario_id' => null,
            'fechaproceso' => Carbon::now(),
            'estado' => $nombreAprobado,
            'observacion' => 'Nivel automático',
        ]);
    }

    private function enviaCorreo(int $uid, RequisicionSala $req, string $linkAprobacion, string $linkRechazo, string $linkVisualizar, ?array $mailExtras = null): void
    {
        $usuario = $this->usuarioRepository->find($uid);
        if (! $usuario || ! filled($usuario->email)) {
            return;
        }
        $tipoarbol = $this->nombreTipoArbol();
        Mail::to($usuario->email)->send(new MailArbolAprobacion(
            $req,
            $tipoarbol,
            $linkAprobacion,
            $linkRechazo,
            $linkVisualizar,
            $mailExtras
        ));
    }

    /** @return array<string, mixed> */
    private function armaExtrasMail(RequisicionSala $req, ?string $estadoAlAprobarEsteNivel): array
    {
        $totales = RequisicionSalaTotalesCabecera::desdeModelo($req);
        $estadoTrasAprobar = filled($estadoAlAprobarEsteNivel) ? trim((string) $estadoAlAprobarEsteNivel) : null;
        $generaTm = RequisicionSalaLineasLaboratorioSupport::generaraTransferenciaLaboratorioAlAprobar($req, $estadoTrasAprobar);
        $preflightTm = RequisicionSalaTransferenciaLaboratorioPreflightSupport::evaluar($req, $estadoTrasAprobar);

        return [
            'estado_tras_aprobar' => $estadoTrasAprobar,
            'monto_items' => (float) ($totales['monto'] ?? 0),
            'genera_transferencia_laboratorio' => $generaTm,
            'deposito_laboratorio' => $generaTm
                ? RequisicionSalaLineasLaboratorioSupport::etiquetaDepositoLaboratorio()
                : '',
            'transferencia_laboratorio_preflight' => $preflightTm,
        ];
    }

    private function enviaCorreoRechazoAlSolicitante(int $requisicionId, int $rechazadorId, string $observacion): void
    {
        $req = $this->requisicionSalaRepository->find($requisicionId);
        if (! $req) {
            return;
        }

        $solicitanteId = (int) ($req->creousuario_id ?: $req->usuario_id);
        if ($solicitanteId <= 0) {
            return;
        }

        $solicitante = $this->usuarioRepository->find($solicitanteId);
        if (! $solicitante || ! filled($solicitante->email)) {
            return;
        }

        $rechazador = $this->usuarioRepository->find($rechazadorId);
        $linkEditar = ModoConsultaUrlSupport::urlAbsolutaConConsulta(
            'sala/requisicion-sala/'.$requisicionId.'/editar'
        );

        Mail::to($solicitante->email)->send(new MailRequisicionSalaRechazoArbol(
            $solicitante,
            $req,
            $rechazador,
            trim($observacion),
            $linkEditar
        ));
    }
}
