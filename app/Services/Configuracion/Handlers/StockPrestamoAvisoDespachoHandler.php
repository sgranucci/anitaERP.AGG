<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Mail\Stock\PrestamoAprobacion;
use App\Mail\Stock\PrestamoCambioEstado;
use App\Mail\Stock\PrestamoRecordatorio;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Configuracion_Prestamo;
use App\Models\Stock\Prestamo;
use App\Models\Stock\Prestamo_Token;
use App\Repositories\Stock\Deposito_AdministradorRepositoryInterface;
use App\Repositories\Stock\PrestamoRepositoryInterface;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Configuracion\PrestamoAvisoPlantillaSupport;
use App\Support\Stock\PrestamoEnlacePublicoSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StockPrestamoAvisoDespachoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private PrestamoRepositoryInterface $prestamoRepository,
        private Deposito_AdministradorRepositoryInterface $depAdminRepository,
        private ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        match ($tipo->codigo) {
            'prestamo_solicitud' => $this->despacharSolicitud($tipo, $entityId),
            'prestamo_recordatorio' => $this->despacharRecordatorio($tipo, $entityId, (bool) ($opciones['vencido'] ?? false)),
            'prestamo_aprobado_solicitante' => $this->despacharCambioEstadoSolicitante($tipo, $entityId, 'aprobado', $opciones['mensaje'] ?? null),
            'prestamo_rechazado_solicitante' => $this->despacharCambioEstadoSolicitante($tipo, $entityId, 'rechazado', $opciones['mensaje'] ?? null),
            default => Log::warning('StockPrestamoAvisoDespachoHandler: código no soportado', ['codigo' => $tipo->codigo]),
        };
    }

    public function contextoFiltro(int $entityId): array
    {
        $prestamo = $this->prestamoRepository->findConRelaciones($entityId);

        return [
            'empresa_id' => optional($prestamo->depositoDestino)->empresa_id
                ? (int) $prestamo->depositoDestino->empresa_id
                : null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        return $this->placeholdersPrestamo($this->prestamoRepository->findConRelaciones($entityId));
    }

    public function linkConsulta(int $entityId): ?string
    {
        return PrestamoEnlacePublicoSupport::urlConsultaMail($entityId);
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    private function despacharSolicitud(ModuloAvisoTipo $tipo, int $entityId): void
    {
        $config = Configuracion_Prestamo::vigente();
        if (! $config->enviar_aprobacion) {
            return;
        }

        $prestamo = $this->prestamoRepository->findConRelaciones($entityId);
        $prestamo->loadMissing(['items.articulos:id,sku,descripcion', 'depositoOrigen', 'depositoDestino', 'solicitante']);

        $admins = $this->depAdminRepository->porDeposito($prestamo->deposito_destino_id);
        if ($admins->isEmpty()) {
            Log::warning('Prestamo aviso solicitud: depósito destino sin administradores', [
                'prestamo_id' => $entityId,
                'deposito_destino_id' => $prestamo->deposito_destino_id,
            ]);
        }

        $placeholders = $this->placeholdersPrestamo($prestamo);
        $expira = now()->addHours((int) ($config->horas_validez_token ?? 168));
        $enviados = [];

        foreach ($admins as $admin) {
            /** @var Usuario|null $usuario */
            $usuario = $admin->usuarios;
            if (! $usuario || empty($usuario->email)) {
                continue;
            }

            $links = $this->linksAprobacion($prestamo, $usuario, $expira);
            try {
                $mailable = new PrestamoAprobacion($prestamo, $usuario, $links, $config, $tipo, $placeholders);
                if ($from = PrestamoAvisoPlantillaSupport::remitente($tipo, $config)) {
                    $mailable->from($from);
                }
                Mail::to($usuario->email)->send($mailable);
                $enviados[] = strtolower($usuario->email);
            } catch (\Throwable $e) {
                Log::error('Prestamo aviso solicitud: falló envío a admin', [
                    'prestamo_id' => $entityId,
                    'usuario_id' => $usuario->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->enviarCopiaInformativa($tipo, $config, $prestamo, $placeholders, $enviados);
    }

    private function despacharRecordatorio(ModuloAvisoTipo $tipo, int $entityId, bool $vencido): void
    {
        $config = Configuracion_Prestamo::vigente();
        $prestamo = $this->prestamoRepository->findConRelaciones($entityId);
        $prestamo->loadMissing(['items.articulos:id,sku,descripcion', 'depositoOrigen', 'depositoDestino', 'solicitante']);

        $admins = $this->depAdminRepository->porDeposito($prestamo->deposito_destino_id);
        $destinatarios = $admins->pluck('usuarios.email')->filter()->unique()->values()->all();
        if ($destinatarios === []) {
            return;
        }

        $placeholders = $this->placeholdersPrestamo($prestamo);
        $vencido = (bool) ($opciones['vencido'] ?? false);

        try {
            $mailable = new PrestamoRecordatorio($prestamo, $config, $vencido, $tipo, $placeholders);
            if ($from = PrestamoAvisoPlantillaSupport::remitente($tipo, $config)) {
                $mailable->from($from);
            }
            $envio = Mail::to($destinatarios);
            $cc = PrestamoAvisoPlantillaSupport::copiasAdicionales(
                $this->moduloAvisoService,
                $tipo,
                $config,
                $this->contextoFiltro($entityId),
                $destinatarios
            );
            if ($cc !== []) {
                $envio->cc($cc);
            }
            $envio->send($mailable);
        } catch (\Throwable $e) {
            Log::error('Prestamo aviso recordatorio: falló envío', [
                'prestamo_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function despacharCambioEstadoSolicitante(ModuloAvisoTipo $tipo, int $entityId, string $tipoCambio, ?string $mensaje): void
    {
        $prestamo = $this->prestamoRepository->findConRelaciones($entityId);
        $prestamo->loadMissing(['items.articulos:id,sku,descripcion', 'depositoOrigen', 'depositoDestino', 'aprobador', 'solicitante']);

        $solicitante = $prestamo->solicitante;
        if (! $solicitante || empty($solicitante->email)) {
            return;
        }

        $config = Configuracion_Prestamo::vigente();
        $placeholders = $this->placeholdersPrestamo($prestamo);

        try {
            $mailable = new PrestamoCambioEstado($prestamo, $tipoCambio, $mensaje, $config, $tipo, $placeholders);
            if ($from = PrestamoAvisoPlantillaSupport::remitente($tipo, $config)) {
                $mailable->from($from);
            }
            Mail::to($solicitante->email)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('Prestamo aviso cambio estado solicitante: falló envío', [
                'prestamo_id' => $entityId,
                'tipo' => $tipoCambio,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Aviso informativo a destinatarios estáticos del tipo (sin tokens de aprobación).
     *
     * @param  list<string>  $excluirEmails
     */
    private function enviarCopiaInformativa(
        ModuloAvisoTipo $tipo,
        Configuracion_Prestamo $config,
        Prestamo $prestamo,
        array $placeholders,
        array $excluirEmails
    ): void {
        $copias = PrestamoAvisoPlantillaSupport::copiasAdicionales(
            $this->moduloAvisoService,
            $tipo,
            $config,
            $this->contextoFiltro((int) $prestamo->id),
            $excluirEmails
        );
        if ($copias === []) {
            return;
        }

        $link = $tipo->incluir_link_consulta ? $this->linkConsulta((int) $prestamo->id) : null;
        $asunto = PrestamoAvisoPlantillaSupport::asunto(
            $tipo,
            $placeholders,
            $config,
            'mail_asunto_aprobacion',
            'Préstamo de materiales: pendiente de aprobación'
        );
        $texto = PrestamoAvisoPlantillaSupport::textoIntro($tipo, $placeholders, $config, 'mail_texto_aprobacion')
            ?? 'Se registró un préstamo de materiales pendiente de aprobación por el administrador del depósito destino.';

        foreach ($copias as $email) {
            try {
                $mailable = new ModuloAvisoMail($asunto, $texto, $tipo->nombre, $link, null);
                if ($from = PrestamoAvisoPlantillaSupport::remitente($tipo, $config)) {
                    $mailable->from($from);
                }
                Mail::to($email)->send($mailable);
            } catch (\Throwable $e) {
                Log::error('Prestamo aviso solicitud: falló copia informativa', [
                    'prestamo_id' => $prestamo->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array{aprobar: string, rechazar: string, visualizar: string}
     */
    private function linksAprobacion(Prestamo $prestamo, Usuario $usuario, $expira): array
    {
        $tokenAprobar = $this->crearToken($prestamo, Prestamo_Token::ACCION_APROBAR, (int) $usuario->id, $expira);
        $tokenRechazar = $this->crearToken($prestamo, Prestamo_Token::ACCION_RECHAZAR, (int) $usuario->id, $expira);
        $tokenVer = $this->crearToken($prestamo, Prestamo_Token::ACCION_VISUALIZAR, (int) $usuario->id, $expira);

        return [
            'aprobar' => route('prestamo_aprobar_publico', ['token' => $tokenAprobar->token]),
            'rechazar' => route('prestamo_rechazar_publico', ['token' => $tokenRechazar->token]),
            'visualizar' => route('prestamo_ver_publico', ['token' => $tokenVer->token]),
        ];
    }

    private function crearToken(Prestamo $prestamo, string $accion, int $usuarioId, $expira): Prestamo_Token
    {
        return Prestamo_Token::create([
            'prestamo_id' => $prestamo->id,
            'token' => Str::random(60),
            'accion' => $accion,
            'usuario_destino_id' => $usuarioId,
            'expira_el' => $expira,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function placeholdersPrestamo(Prestamo $prestamo): array
    {
        return [
            'codigo' => (string) ($prestamo->codigo ?? $prestamo->id),
            'numero' => (string) ($prestamo->codigo ?? $prestamo->id),
            'solicitante' => (string) (optional($prestamo->solicitante)->nombre ?? '—'),
            'deposito_origen' => (string) (optional($prestamo->depositoOrigen)->nombre ?? '—'),
            'deposito_destino' => (string) (optional($prestamo->depositoDestino)->nombre ?? '—'),
            'fecha_prestamo' => $prestamo->fecha_prestamo ? $prestamo->fecha_prestamo->format('d/m/Y') : '—',
            'fecha_devolucion' => $prestamo->fecha_devolucion_prometida ? $prestamo->fecha_devolucion_prometida->format('d/m/Y') : '—',
            'estado' => (string) ($prestamo->estado ?? '—'),
            'link_consulta' => $this->linkConsulta((int) $prestamo->id) ?? '',
        ];
    }
}
