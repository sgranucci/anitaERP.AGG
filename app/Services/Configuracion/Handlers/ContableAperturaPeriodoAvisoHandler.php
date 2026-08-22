<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Contable\AperturaPeriodoContable;
use App\Support\Contable\AperturaPeriodoContablePermiso;
use App\Support\Contable\PeriodoContableCierreSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContableAperturaPeriodoAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        if ($tipo->codigo === 'apertura_periodo_solicitud_pendiente') {
            $this->despacharSolicitudPendiente($tipo, $entityId);

            return;
        }

        $this->despacharAvisoUsuarioHabilitado($tipo, $entityId);
    }

    public function contextoFiltro(int $entityId): array
    {
        $apertura = AperturaPeriodoContable::query()->find($entityId);

        return [
            'empresa_id' => $apertura ? (int) $apertura->empresa_id : null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        return $this->placeholdersApertura($entityId);
    }

    public function linkConsulta(int $entityId): ?string
    {
        return url('contable/apertura-periodo');
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    private function despacharSolicitudPendiente(ModuloAvisoTipo $tipo, int $entityId): void
    {
        if (AperturaPeriodoContablePermiso::esModoInmediata()) {
            Log::info('ContableAperturaPeriodoAvisoHandler: omitido solicitud_pendiente (modo inmediata)', [
                'apertura_id' => $entityId,
            ]);

            return;
        }

        $destinatarios = AperturaPeriodoContablePermiso::emailsEncargadosHabilitacion();
        if ($destinatarios === []) {
            Log::info('ContableAperturaPeriodoAvisoHandler: sin encargados con permiso habilitar', [
                'apertura_id' => $entityId,
            ]);

            return;
        }

        $placeholders = $this->placeholdersApertura($entityId);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null;
        $asunto = $this->aplicarPlaceholders($tipo->mail_asunto, $placeholders, $linkConsulta);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);

        foreach ($destinatarios as $email) {
            $this->enviarCorreo($tipo, $email, $asunto, $texto, $linkConsulta, $entityId, $tipo->codigo);
        }
    }

    private function despacharAvisoUsuarioHabilitado(ModuloAvisoTipo $tipo, int $entityId): void
    {
        $apertura = AperturaPeriodoContable::query()
            ->with([
                'empresa:id,nombre',
                'habilitado:id,nombre,email,usuario',
                'solicitante:id,nombre,email,usuario',
            ])
            ->find($entityId);

        if (! $apertura) {
            return;
        }

        $emails = $this->emailsAvisoOperativo($apertura, $tipo->codigo);
        if ($emails === []) {
            Log::warning('ContableAperturaPeriodoAvisoHandler: sin destinatarios con email válido', [
                'apertura_id' => $entityId,
                'codigo' => $tipo->codigo,
                'usuario_habilitado_id' => $apertura->usuario_habilitado_id,
                'usuario_solicitante_id' => $apertura->usuario_solicitante_id,
            ]);

            return;
        }

        $placeholders = $this->placeholdersApertura($entityId);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null;
        $asunto = $this->aplicarPlaceholders($tipo->mail_asunto, $placeholders, $linkConsulta);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);

        foreach ($emails as $email) {
            $this->enviarCorreo($tipo, $email, $asunto, $texto, $linkConsulta, $entityId, $tipo->codigo);
        }

        match ($tipo->codigo) {
            'apertura_periodo_habilitada' => $apertura->update(['aviso_habilitacion_enviado_en' => now()]),
            'apertura_periodo_recordatorio' => $apertura->update(['recordatorio_vencimiento_enviado_en' => now()]),
            'apertura_periodo_cerrada' => $apertura->update(['aviso_cierre_enviado_en' => now()]),
            default => null,
        };
    }

    /**
     * Destinatarios del aviso operativo (habilitada / recordatorio / cerrada).
     * En modo inmediata, la confirmación de habilitación prioriza al solicitante.
     *
     * @return list<string>
     */
    private function emailsAvisoOperativo(AperturaPeriodoContable $apertura, string $codigo): array
    {
        $emails = [];
        $agregar = static function (?string $email) use (&$emails): void {
            $email = strtolower(trim((string) $email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        };

        if ($codigo === 'apertura_periodo_habilitada' && AperturaPeriodoContablePermiso::esModoInmediata()) {
            $agregar($apertura->solicitante?->email);
            $agregar($apertura->habilitado?->email);
        } else {
            $agregar($apertura->habilitado?->email);
        }

        return array_values(array_unique($emails));
    }

    private function enviarCorreo(
        ModuloAvisoTipo $tipo,
        string $email,
        string $asunto,
        string $texto,
        ?string $linkConsulta,
        int $entityId,
        string $codigo
    ): void {
        try {
            $mailable = new ModuloAvisoMail($asunto, $texto, $tipo->nombre, $linkConsulta, null);
            if (! empty($tipo->mail_remitente)) {
                $mailable->from($tipo->mail_remitente);
            }
            Mail::to($email)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('ContableAperturaPeriodoAvisoHandler: fallo envío', [
                'apertura_id' => $entityId,
                'email' => $email,
                'codigo' => $codigo,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string, string> */
    private function placeholdersApertura(int $entityId): array
    {
        $apertura = AperturaPeriodoContable::query()
            ->with([
                'empresa:id,nombre',
                'habilitado:id,nombre,usuario',
                'solicitante:id,nombre,usuario',
            ])
            ->findOrFail($entityId);

        return [
            'empresa' => (string) ($apertura->empresa?->nombre ?? ''),
            'solicitante' => (string) ($apertura->solicitante?->nombre ?? ''),
            'usuario' => (string) ($apertura->habilitado?->nombre ?? ''),
            'alcance' => PeriodoContableCierreSupport::etiquetaAlcance((string) $apertura->alcance),
            'fecha_desde' => optional($apertura->fecha_operacion_desde)->format('d/m/Y') ?? '',
            'fecha_hasta' => optional($apertura->fecha_operacion_hasta)->format('d/m/Y') ?? '',
            'vence_en' => optional($apertura->vence_en)->format('d/m/Y H:i') ?? '',
            'duracion' => $apertura->etiquetaDuracion(),
            'motivo' => (string) ($apertura->motivo ?? ''),
            'link_habilitar' => $apertura->estado === 'pendiente'
                ? AperturaPeriodoContablePermiso::urlHabilitacionFirmada($entityId)
                : '',
        ];
    }

    /** @param  array<string, string>  $placeholders */
    private function aplicarPlaceholders(string $plantilla, array $placeholders, ?string $linkConsulta): string
    {
        $texto = $plantilla;
        foreach ($placeholders as $clave => $valor) {
            $texto = str_replace('{'.$clave.'}', $valor, $texto);
        }
        if ($linkConsulta !== null) {
            $texto = str_replace('{link_consulta}', $linkConsulta, $texto);
        }

        return $texto;
    }
}
