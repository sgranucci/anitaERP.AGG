<?php

namespace App\Services\Contable;

use App\Mail\Contable\AsientoCambioEstado;
use App\Mail\Contable\AsientoPendienteAprobacion;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Token;
use App\Models\Contable\Configuracion_AsientoContable;
use App\Models\Seguridad\Usuario;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Support\Contable\AsientoCuentaUsuarioSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AsientoAprobacionService
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
    ) {}

    /**
     * @param  list<int|string|null>  $cuentacontableIds
     * @return array{requiere_aprobacion: bool, cuentas_no_autorizadas: list<int>, cuentas_detalle: list<array{id:int,codigo:string,nombre:string}>}
     */
    public function evaluarCuentas(int $usuarioId, array $cuentacontableIds): array
    {
        $noAutorizadas = AsientoCuentaUsuarioSupport::cuentasNoAutorizadas($usuarioId, $cuentacontableIds);

        return [
            'requiere_aprobacion' => $noAutorizadas !== [],
            'cuentas_no_autorizadas' => $noAutorizadas,
            'cuentas_detalle' => AsientoCuentaUsuarioSupport::detalleCuentas($noAutorizadas),
        ];
    }

    public function listarPendientes()
    {
        return Asiento::query()
            ->with(['empresas', 'tipoasientos', 'usuarios'])
            ->where('estado_aprobacion', Asiento::ESTADO_APROBACION_PENDIENTE)
            ->orderByDesc('created_at')
            ->get();
    }

    public function buscar(int $id): Asiento
    {
        return $this->asientoRepository->find($id);
    }

    public function enviarMailAprobacion(Asiento $asiento): void
    {
        $config = Configuracion_AsientoContable::vigente();
        if (! $config->enviar_mail_aprobacion) {
            return;
        }

        $email = $config->emailAprobadorValido();
        if ($email === null) {
            Log::warning('Asiento aprobación: mail_aprobador no configurado', ['asiento_id' => $asiento->id]);

            return;
        }

        $asiento->loadMissing(['empresas', 'tipoasientos', 'usuarios', 'asiento_movimientos.cuentacontables']);

        $expira = now()->addHours((int) ($config->horas_validez_token ?? 168));
        $links = $this->linksAprobacion($asiento, $expira);

        try {
            $mailable = new AsientoPendienteAprobacion($asiento, $links, $config);
            $envio = Mail::to($email);
            $copias = $config->copiasComoArray();
            if ($copias !== []) {
                $envio->cc($copias);
            }
            $envio->send($mailable);
        } catch (\Throwable $e) {
            Log::error('Asiento aprobación: falló envío mail', [
                'asiento_id' => $asiento->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function aprobar(int $asientoId, ?int $aprobadorId, ?string $observacion = null): Asiento
    {
        return DB::transaction(function () use ($asientoId, $aprobadorId, $observacion) {
            $asiento = $this->asientoRepository->find($asientoId);

            if (! $asiento->estaPendienteAprobacion()) {
                throw new \RuntimeException('El asiento no está pendiente de aprobación.');
            }

            $payloadAnita = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asiento);
            $this->asientoRepository->sincronizarCtamovAnita($payloadAnita);

            $asiento->estado_aprobacion = Asiento::ESTADO_APROBACION_CONFIRMADO;
            $asiento->aprobador_id = $aprobadorId;
            $asiento->aprobado_el = now();
            $asiento->save();

            $this->invalidarTokens($asiento);
            $this->notificarSolicitante($asiento->fresh(['usuarios']), 'aprobado', $observacion);

            return $asiento;
        });
    }

    public function rechazar(int $asientoId, ?int $aprobadorId, ?string $motivo = null): Asiento
    {
        return DB::transaction(function () use ($asientoId, $aprobadorId, $motivo) {
            $asiento = $this->asientoRepository->find($asientoId);

            if (! $asiento->estaPendienteAprobacion()) {
                throw new \RuntimeException('El asiento no está pendiente de aprobación.');
            }

            $asiento->estado_aprobacion = Asiento::ESTADO_APROBACION_RECHAZADO;
            $asiento->aprobador_id = $aprobadorId;
            $asiento->rechazado_el = now();
            $asiento->motivo_rechazo = $motivo;
            $asiento->save();

            $this->invalidarTokens($asiento);
            $this->notificarSolicitante($asiento->fresh(['usuarios']), 'rechazado', $motivo);

            return $asiento;
        });
    }

    public function consumirToken(string $token, string $accionEsperada): Asiento_Token
    {
        $row = Asiento_Token::where('token', $token)->first();
        if (! $row) {
            throw new \RuntimeException('Enlace inválido.');
        }
        if ($row->accion !== $accionEsperada) {
            throw new \RuntimeException('Enlace para una acción distinta.');
        }
        if (! $row->estaActivo()) {
            throw new \RuntimeException('Este enlace ya fue utilizado o expiró.');
        }

        $row->usado_el = now();
        $row->save();

        return $row;
    }

    /**
     * @return array{aprobar: string, rechazar: string, visualizar: string}
     */
    private function linksAprobacion(Asiento $asiento, $expira): array
    {
        $tokenAprobar = $this->crearToken($asiento, Asiento_Token::ACCION_APROBAR, $expira);
        $tokenRechazar = $this->crearToken($asiento, Asiento_Token::ACCION_RECHAZAR, $expira);
        $tokenVer = $this->crearToken($asiento, Asiento_Token::ACCION_VISUALIZAR, $expira);

        return [
            'aprobar' => route('asiento_aprobar_publico', ['token' => $tokenAprobar->token]),
            'rechazar' => route('asiento_rechazar_publico', ['token' => $tokenRechazar->token]),
            'visualizar' => route('asiento_ver_publico', ['token' => $tokenVer->token]),
        ];
    }

    private function crearToken(Asiento $asiento, string $accion, $expira): Asiento_Token
    {
        return Asiento_Token::create([
            'asiento_id' => $asiento->id,
            'token' => Str::random(60),
            'accion' => $accion,
            'usuario_destino_id' => null,
            'expira_el' => $expira,
        ]);
    }

    private function invalidarTokens(Asiento $asiento): void
    {
        Asiento_Token::where('asiento_id', $asiento->id)
            ->whereNull('usado_el')
            ->update(['usado_el' => now()]);
    }

    private function notificarSolicitante(Asiento $asiento, string $tipo, ?string $mensaje): void
    {
        /** @var Usuario|null $solicitante */
        $solicitante = $asiento->usuarios;
        if (! $solicitante || empty($solicitante->email)) {
            return;
        }

        $config = Configuracion_AsientoContable::vigente();

        try {
            Mail::to($solicitante->email)->send(new AsientoCambioEstado($asiento, $tipo, $mensaje, $config));
        } catch (\Throwable $e) {
            Log::error('Asiento aprobación: falló aviso al solicitante', [
                'asiento_id' => $asiento->id,
                'tipo' => $tipo,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
