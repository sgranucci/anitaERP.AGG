<?php

namespace App\Services\Configuracion;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoDestinatario;
use App\Models\Configuracion\ModuloAvisoTipo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ModuloAvisoService
{
    /** @var array<string, ModuloAvisoHandlerInterface> */
    private array $handlersResueltos = [];

    /**
     * Dispara el aviso configurado para un evento de módulo.
     * No lanza excepción al caller: errores se registran en log.
     *
     * @param  array<string, mixed>  $opciones
     */
    public function enviar(string $modulo, string $codigo, int $entityId, array $opciones = []): void
    {
        try {
            $tipo = ModuloAvisoTipo::query()
                ->where('modulo', $modulo)
                ->where('codigo', $codigo)
                ->where('activo', true)
                ->first();

            if (! $tipo) {
                return;
            }

            $handler = $this->handler($modulo, $codigo);
            if (! $handler) {
                Log::warning('ModuloAvisoService: sin handler registrado', [
                    'modulo' => $modulo,
                    'codigo' => $codigo,
                    'entity_id' => $entityId,
                ]);

                return;
            }

            if ($handler instanceof ModuloAvisoDespachoHandlerInterface) {
                $handler->despachar($tipo, $entityId, $opciones);

                return;
            }

            $filtro = $handler->contextoFiltro($entityId);
            $destinatarios = $this->resolverEmailsDestinatarios($tipo, $filtro);
            if ($destinatarios === []) {
                Log::info('ModuloAvisoService: sin destinatarios activos', [
                    'tipo_id' => $tipo->id,
                    'modulo' => $modulo,
                    'codigo' => $codigo,
                ]);

                return;
            }

            $placeholders = $handler->placeholders($entityId);
            $linkConsulta = $tipo->incluir_link_consulta ? $handler->linkConsulta($entityId) : null;
            $pdfAdjunto = $tipo->adjuntar_pdf ? $handler->generarPdf($entityId) : null;

            $asunto = $this->aplicarPlaceholders($tipo->mail_asunto, $placeholders, $linkConsulta);
            $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);

            foreach ($destinatarios as $email) {
                try {
                    $mailable = new ModuloAvisoMail(
                        $asunto,
                        $texto,
                        $tipo->nombre,
                        $linkConsulta,
                        $pdfAdjunto
                    );
                    if (! empty($tipo->mail_remitente)) {
                        $mailable->from($tipo->mail_remitente);
                    }
                    Mail::to($email)->send($mailable);
                } catch (\Throwable $e) {
                    Log::error('ModuloAvisoService: falló envío a destinatario', [
                        'email' => $email,
                        'modulo' => $modulo,
                        'codigo' => $codigo,
                        'entity_id' => $entityId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('ModuloAvisoService::enviar', [
                'modulo' => $modulo,
                'codigo' => $codigo,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{empresa_id?: int|null, centrocosto_id?: int|null}  $filtro
     * @return list<string>
     */
    public function resolverEmailsDestinatarios(ModuloAvisoTipo $tipo, array $filtro): array
    {
        $empresaDoc = $filtro['empresa_id'] ?? null;
        $ccDoc = $filtro['centrocosto_id'] ?? null;

        $emails = [];
        /** @var ModuloAvisoDestinatario $dest */
        foreach ($tipo->destinatarios()->where('activo', true)->with('usuarios')->get() as $dest) {
            if ($dest->empresa_id && $empresaDoc && (int) $dest->empresa_id !== (int) $empresaDoc) {
                continue;
            }
            if ($dest->centrocosto_id && $ccDoc && (int) $dest->centrocosto_id !== (int) $ccDoc) {
                continue;
            }
            $email = $dest->emailResuelto();
            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($email);
            }
        }

        return array_values(array_unique($emails));
    }

    private function handler(string $modulo, string $codigo): ?ModuloAvisoHandlerInterface
    {
        $clave = $modulo.'.'.$codigo;
        if (isset($this->handlersResueltos[$clave])) {
            return $this->handlersResueltos[$clave];
        }

        $handlers = config('modulo_aviso.handlers', []);
        if (! is_array($handlers)) {
            return null;
        }

        $clase = $handlers[$clave] ?? null;
        if (! $clase || ! class_exists($clase)) {
            return null;
        }

        $instancia = app($clase);
        if (! $instancia instanceof ModuloAvisoHandlerInterface) {
            return null;
        }

        $this->handlersResueltos[$clave] = $instancia;

        return $instancia;
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function aplicarPlaceholders(string $texto, array $placeholders, ?string $linkConsulta): string
    {
        $mapa = $placeholders;
        $mapa['link_consulta'] = $linkConsulta ?? '';

        $resultado = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($mapa) {
            $clave = strtolower($m[1]);

            return $mapa[$clave] ?? $m[0];
        }, $texto);

        return is_string($resultado) ? $resultado : $texto;
    }
}
