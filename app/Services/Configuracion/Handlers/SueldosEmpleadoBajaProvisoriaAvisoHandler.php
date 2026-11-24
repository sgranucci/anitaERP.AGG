<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SueldosEmpleadoBajaProvisoriaAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        // entityId 0 = digest del robot (listado en opciones).
        if ($entityId <= 0 && ! empty($opciones['lineas']) && is_array($opciones['lineas'])) {
            $this->despacharDigest($tipo, $opciones['lineas']);

            return;
        }

        $filtro = $this->contextoFiltro($entityId);
        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $filtro);
        $emails = array_values(array_unique(array_filter($emails)));

        if ($emails === []) {
            Log::info('SueldosEmpleadoBajaProvisoriaAviso: sin destinatarios', ['empleado_id' => $entityId]);

            return;
        }

        $placeholders = $this->placeholders($entityId);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null;
        $placeholders['link_consulta'] = $linkConsulta ?? '';

        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders);

        foreach ($emails as $email) {
            try {
                $mailable = new ModuloAvisoMail(
                    $asunto,
                    $texto,
                    $tipo->nombre,
                    $linkConsulta,
                    null
                );
                if (! empty($tipo->mail_remitente)) {
                    $mailable->from($tipo->mail_remitente);
                }
                Mail::to($email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::warning('SueldosEmpleadoBajaProvisoriaAviso: error envío', [
                    'empleado_id' => $entityId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  list<array{empresa?:string, legajo?:int|string, nombre?:string, fecha_egreso?:string, vencida?:bool}>  $lineas
     */
    private function despacharDigest(ModuloAvisoTipo $tipo, array $lineas): void
    {
        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, []);
        $emails = array_values(array_unique(array_filter($emails)));
        if ($emails === [] || $lineas === []) {
            return;
        }

        $filas = [];
        foreach ($lineas as $l) {
            $marca = ! empty($l['vencida']) ? ' [VENCIDA]' : '';
            $filas[] = sprintf(
                '- %s · leg. %s · %s · egreso %s%s',
                $l['empresa'] ?? '—',
                $l['legajo'] ?? '—',
                $l['nombre'] ?? '—',
                $l['fecha_egreso'] ?? '—',
                $marca
            );
        }
        $listado = implode("\n", $filas);
        $asunto = 'Bajas provisorias pendientes ('.count($lineas).')';
        $texto = "Hay bajas provisorias pendientes de confirmar como definitiva.\n"
            ."La efectivización es manual en el legajo.\n\n"
            .$listado."\n";

        foreach ($emails as $email) {
            try {
                $mailable = new ModuloAvisoMail($asunto, $texto, $tipo->nombre, null, null);
                if (! empty($tipo->mail_remitente)) {
                    $mailable->from($tipo->mail_remitente);
                }
                Mail::to($email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::warning('SueldosEmpleadoBajaProvisoriaAviso digest: error envío', [
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
            ->with(['empresa:id,nombre', 'motivoegreso:id,codigo,descripcion,nombre'])
            ->findOrFail($entityId);

        $motivo = optional($emp->motivoegreso)->descripcion
            ?? optional($emp->motivoegreso)->nombre
            ?? '—';

        return [
            'id' => (string) $emp->id,
            'legajo' => (string) $emp->legajo,
            'nombre' => (string) ($emp->nombre ?? '—'),
            'empresa' => (string) (optional($emp->empresa)->nombre ?? '—'),
            'fecha_egreso' => $emp->fecha_egreso ? $emp->fecha_egreso->format('d/m/Y') : '—',
            'motivo' => (string) $motivo,
            'comentario' => (string) ($emp->comentario_baja ?? '—'),
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
