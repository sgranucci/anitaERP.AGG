<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use App\Support\Sueldos\EmpleadoAltaAutorizacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SueldosEmpleadoAltaProvisoriaAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $filtro = $this->contextoFiltro($entityId);
        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $filtro);
        $emails = array_values(array_unique(array_filter($emails)));

        if ($emails === []) {
            Log::info('SueldosEmpleadoAltaProvisoriaAviso: sin destinatarios', ['empleado_id' => $entityId]);

            return;
        }

        $placeholders = $this->placeholders($entityId);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null;
        $placeholders['link_consulta'] = $linkConsulta ?? '';
        $placeholders['link_autorizar'] = EmpleadoAltaAutorizacion::urlAutorizacionFirmada($entityId);

        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders);

        foreach ($emails as $email) {
            try {
                $mailable = new ModuloAvisoMail(
                    $asunto,
                    $texto,
                    $tipo->nombre,
                    $placeholders['link_autorizar'],
                    null
                );
                if (! empty($tipo->mail_remitente)) {
                    $mailable->from($tipo->mail_remitente);
                }
                Mail::to($email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::warning('SueldosEmpleadoAltaProvisoriaAviso: error envío', [
                    'empleado_id' => $entityId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function contextoFiltro(int $entityId): array
    {
        $emp = Empleado_Sueldos::query()->find($entityId);

        return [
            'empresa_id' => $emp ? (int) $emp->empresa_id : null,
            'centrocosto_id' => $emp && $emp->centrocosto_id ? (int) $emp->centrocosto_id : null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $emp = Empleado_Sueldos::query()
            ->with(['empresa:id,nombre', 'usuarioAlta:id,nombre,usuario'])
            ->findOrFail($entityId);

        return [
            'id' => (string) $emp->id,
            'legajo' => (string) $emp->legajo,
            'nombre' => (string) ($emp->nombre ?? '—'),
            'cuil' => (string) ($emp->cuil ?? '—'),
            'empresa' => (string) (optional($emp->empresa)->nombre ?? '—'),
            'fecha_ingreso' => $emp->fecha_ingreso ? $emp->fecha_ingreso->format('d/m/Y') : '—',
            'usuario_alta' => (string) (optional($emp->usuarioAlta)->nombre
                ?? optional($emp->usuarioAlta)->usuario
                ?? '—'),
            'fecha' => $emp->created_at ? $emp->created_at->format('d/m/Y H:i') : '—',
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return ModoConsultaUrlSupport::urlAbsolutaConConsulta(
            'sueldos/empleado/'.$entityId.'/editar'
        );
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    /** @param  array<string, string>  $placeholders */
    private function aplicarPlaceholders(string $plantilla, array $placeholders): string
    {
        $resultado = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($placeholders) {
            $clave = strtolower($m[1]);

            return $placeholders[$clave] ?? $m[0];
        }, $plantilla);

        return is_string($resultado) ? $resultado : $plantilla;
    }
}
