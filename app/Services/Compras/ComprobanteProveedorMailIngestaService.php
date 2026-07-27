<?php

namespace App\Services\Compras;

use App\Jobs\Compras\ProcesarFacturaMailJob;
use App\Mail\Compras\FacturaMailIngestaErroresMail;
use App\Models\Compras\Precarga_Comprobante_Mail_Mensaje;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaProveedor\Mail\FacturaMailCandidatoFiltroSupport;
use App\Support\Compras\PrecargaProveedor\Mail\FacturaMailOcExtractorSupport;
use App\Support\Compras\PrecargaProveedor\Mail\MailboxLectorInterface;
use App\Support\Compras\PrecargaProveedor\Mail\MailFacturaMensaje;
use App\Support\Compras\PrecargaRecepcionErrorRegistrar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Ingesta de facturas de proveedor desde la casilla de correo.
 *
 * El comando programado lee mensajes no leídos, saca la OC del asunto/cuerpo
 * y encola un job por mensaje que corre el pipeline PDF+IA existente:
 * la precarga queda PENDIENTE en la grilla y el PDF en Facturas_scan con la
 * convención canónica (mismo camino que la carga PDF+IA manual y el portal).
 */
final class ComprobanteProveedorMailIngestaService
{
    private const DIR_ENTRADA = 'compras/factura_pdf_ia/mail_entrada';

    private const DIR_PENDIENTE = 'compras/factura_pdf_ia/mail_pendiente';

    public function __construct(
        private MailboxLectorInterface $mailbox,
        private FacturaMailOcExtractorSupport $ocExtractor,
        private FacturaMailCandidatoFiltroSupport $candidatoFiltro,
        private ComprobanteProveedorPdfIaService $pdfIaService,
    ) {}

    /**
     * Lee la casilla y encola un job por mensaje con adjuntos PDF.
     *
     * @return array{mensajes: int, encolados: int, ignorados: int, ya_procesados: int, detalle: list<array<string, mixed>>}
     */
    public function procesarCasilla(?int $limite = null, bool $dryRun = false): array
    {
        $limite = $limite ?? (int) config('precarga_comprobante_mail.max_mensajes', 25);
        $mensajes = $this->mailbox->mensajesNoLeidos($limite);

        $resumen = ['mensajes' => count($mensajes), 'encolados' => 0, 'ignorados' => 0, 'ya_procesados' => 0, 'detalle' => []];

        foreach ($mensajes as $mensaje) {
            $detalle = [
                'message_id' => $mensaje->messageId,
                'remitente' => $mensaje->remitente,
                'asunto' => $mensaje->asunto,
                'adjuntos_pdf' => count($mensaje->adjuntosPdf),
            ];

            if (! $mensaje->tieneAdjuntosPdf()) {
                // Casilla provisoria personal: no marcar ni registrar correos comunes.
                // Solo la presencia de un PDF convierte al mensaje en candidato.
                $detalle['accion'] = 'omitido_sin_pdf';
                $resumen['ignorados']++;
                $resumen['detalle'][] = $detalle;
                continue;
            }

            $filtro = $this->candidatoFiltro->evaluar($mensaje, $mensaje->adjuntosPdf);
            $detalle['filtro'] = $filtro;
            if (! ($filtro['ok'] ?? false)) {
                // Sin OC ni palabra clave: dejar el mail intacto (no leído / no mover).
                $detalle['accion'] = 'omitido_filtro_candidato';
                $resumen['ignorados']++;
                $resumen['detalle'][] = $detalle;
                continue;
            }

            $adjuntos = [];
            $pendientes = 0;
            foreach ($mensaje->adjuntosPdf as $adjunto) {
                $oc = $this->ocExtractor->extraer($mensaje, $adjunto['nombre']);
                $yaProcesado = Precarga_Comprobante_Mail_Mensaje::query()
                    ->where('message_id', $mensaje->messageId)
                    ->where('adjunto_hash', $adjunto['hash'])
                    ->exists();

                $adjuntos[] = [
                    'nombre' => $adjunto['nombre'],
                    'hash' => $adjunto['hash'],
                    'numero_oc' => $oc['numero'],
                    'numero_oc_origen' => $oc['origen'],
                    'ya_procesado' => $yaProcesado,
                ];
                if (! $yaProcesado) {
                    $pendientes++;
                }
            }
            $detalle['adjuntos'] = $adjuntos;

            if ($pendientes === 0) {
                $detalle['accion'] = 'ya_procesado';
                $resumen['ya_procesados']++;
                if (! $dryRun) {
                    $this->mailbox->marcarProcesado($mensaje);
                }
                $resumen['detalle'][] = $detalle;
                continue;
            }

            if ($dryRun) {
                $detalle['accion'] = 'dry_run';
                $resumen['detalle'][] = $detalle;
                continue;
            }

            $payload = $this->armarPayloadJob($mensaje, $adjuntos);
            $this->mailbox->marcarLeido($mensaje);
            ProcesarFacturaMailJob::dispatch($payload);

            $detalle['accion'] = 'encolado';
            $resumen['encolados']++;
            $resumen['detalle'][] = $detalle;
        }

        return $resumen;
    }

    /**
     * Procesa un mensaje encolado: cada adjunto pasa por preview/confirmar del
     * pipeline PDF+IA. Corre dentro del job (worker de cola).
     *
     * @param  array<string, mixed>  $payload
     */
    public function procesarMensajeEncolado(array $payload): void
    {
        $mensaje = $this->mensajeDesdePayload($payload);
        $errores = [];
        $exitos = 0;

        foreach ((array) ($payload['adjuntos'] ?? []) as $adjunto) {
            if (! empty($adjunto['ya_procesado'])) {
                continue;
            }

            $resultado = $this->procesarAdjunto($mensaje, $adjunto);
            if ($resultado['ok']) {
                $exitos++;
            } else {
                $errores[] = $resultado;
            }
        }

        try {
            if ($errores === []) {
                $this->mailbox->marcarProcesado($mensaje);
            } else {
                $this->mailbox->moverAError($mensaje);
            }
        } catch (Throwable $e) {
            Log::channel('ai')->warning('mail_ingesta.mover_mensaje_fallo', [
                'message_id' => $mensaje->messageId,
                'error' => $e->getMessage(),
            ]);
        }

        if ($errores !== []) {
            $this->avisarErrores($mensaje, $errores, $exitos);
        }
    }

    /**
     * @param  array<string, mixed>  $adjunto
     * @return array{ok: bool, adjunto: string, numero_oc: ?string, precarga_id?: int, error?: string}
     */
    private function procesarAdjunto(MailFacturaMensaje $mensaje, array $adjunto): array
    {
        $nombre = (string) ($adjunto['nombre'] ?? 'factura.pdf');
        $path = (string) ($adjunto['path'] ?? '');
        $numeroOcMail = $adjunto['numero_oc'] ?? null;

        try {
            if ($path === '' || ! is_file($path)) {
                throw new \RuntimeException('No se encontró el PDF preparado para procesar: '.$path);
            }

            $uploaded = new UploadedFile($path, $nombre, 'application/pdf', null, true);

            // 1) La OC del PDF manda; la del mail entra como fallback.
            $preview = $this->pdfIaService->preview($uploaded, null, true);
            $ocDesdeMail = false;

            if (empty($preview['ok'])) {
                $ocRequerida = ! empty($preview['oc_requerida']);
                if ($ocRequerida && $numeroOcMail !== null) {
                    $preview = $this->pdfIaService->resolverConOcManual(
                        (array) ($preview['extraccion'] ?? []),
                        (string) $numeroOcMail,
                    );
                    $ocDesdeMail = true;
                } else {
                    throw new \RuntimeException((string) ($preview['message'] ?? 'No se pudo interpretar el PDF.'));
                }
            }

            $resuelto = (array) ($preview['resuelto'] ?? []);
            $advertencias = (array) ($preview['advertencias'] ?? []);

            // 2) Revisión humana forzada cuando la extracción quedó floja
            //    o el score no alcanza el umbral de auto-aplicar.
            $motivos = [];
            $autoAplicable = ! empty($preview['ai_auto_aplicable']);
            if ($ocDesdeMail) {
                $motivos[] = 'OC tomada del mail ('.$adjunto['numero_oc_origen'].'), no del PDF';
                $autoAplicable = false;
            }
            if (! empty($resuelto['pararevisar'])) {
                $motivos[] = 'suma de conceptos difiere del total';
                $autoAplicable = false;
            }
            if (blank($resuelto['numerocae'] ?? null)) {
                $motivos[] = 'sin CAE detectado';
                $autoAplicable = false;
            }
            if ($advertencias !== []) {
                $motivos[] = 'advertencias de extracción';
                $autoAplicable = false;
            }
            if (! $autoAplicable && $motivos === []) {
                $motivos[] = 'score bajo umbral de auto-aplicar (HITL)';
            }

            // 3) Fecha real del mensaje como recepción por mail.
            if ($mensaje->fechaMensaje !== null) {
                $resuelto['fecha_recepcion_email'] = $mensaje->fechaMensaje->format('Y-m-d');
            }
            $preview['resuelto'] = $resuelto;
            $preview['ai_auto_aplicable'] = $autoAplicable;

            $confirmacion = $this->pdfIaService->confirmar(
                $preview,
                $uploaded,
                PrecargaComprobanteOrigenEntrada::MAIL,
                null,
                ! $autoAplicable,
            );

            $this->registrarResultado($mensaje, $adjunto, Precarga_Comprobante_Mail_Mensaje::ESTADO_PROCESADO, [
                'precarga_id' => (int) $confirmacion['precarga_id'],
                'mensaje_error' => $motivos !== [] ? 'Para revisar: '.implode('; ', $motivos) : null,
            ]);

            return [
                'ok' => true,
                'adjunto' => $nombre,
                'numero_oc' => $resuelto['numero_oc'] ?? $numeroOcMail,
                'precarga_id' => (int) $confirmacion['precarga_id'],
            ];
        } catch (Throwable $e) {
            $rutaCuarentena = $this->moverACuarentena($path, $nombre);

            $this->registrarResultado($mensaje, $adjunto, Precarga_Comprobante_Mail_Mensaje::ESTADO_ERROR, [
                'mensaje_error' => $e->getMessage().($rutaCuarentena !== null ? ' [PDF: '.$rutaCuarentena.']' : ''),
            ]);

            PrecargaRecepcionErrorRegistrar::registrarMail('procesar_adjunto', $e->getMessage(), [
                'numero_oc' => $numeroOcMail,
                'archivo_nombre' => $nombre,
                'remitente' => $mensaje->remitente,
                'asunto' => $mensaje->asunto,
                'message_id' => $mensaje->messageId,
                'ruta_cuarentena' => $rutaCuarentena,
            ]);

            return [
                'ok' => false,
                'adjunto' => $nombre,
                'numero_oc' => $numeroOcMail,
                'error' => $e->getMessage(),
            ];
        } finally {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $adjuntos
     * @return array<string, mixed>
     */
    private function armarPayloadJob(MailFacturaMensaje $mensaje, array $adjuntos): array
    {
        $dir = storage_path('app/'.self::DIR_ENTRADA);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $adjuntosPayload = [];
        foreach ($mensaje->adjuntosPdf as $i => $adjunto) {
            $meta = $adjuntos[$i] ?? [];
            if (! empty($meta['ya_procesado'])) {
                continue;
            }

            $path = $dir.'/'.$adjunto['hash'].'.pdf';
            file_put_contents($path, $adjunto['contenido']);

            $adjuntosPayload[] = [
                'nombre' => $adjunto['nombre'],
                'hash' => $adjunto['hash'],
                'path' => $path,
                'numero_oc' => $meta['numero_oc'] ?? null,
                'numero_oc_origen' => $meta['numero_oc_origen'] ?? null,
                'ya_procesado' => false,
            ];
        }

        return [
            'message_id' => $mensaje->messageId,
            'uid' => $mensaje->uid,
            'remitente' => $mensaje->remitente,
            'asunto' => $mensaje->asunto,
            'fecha_mensaje' => $mensaje->fechaMensaje?->format('Y-m-d H:i:s'),
            'adjuntos' => $adjuntosPayload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mensajeDesdePayload(array $payload): MailFacturaMensaje
    {
        $fecha = null;
        if (filled($payload['fecha_mensaje'] ?? null)) {
            $fecha = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $payload['fecha_mensaje']) ?: null;
        }

        return new MailFacturaMensaje(
            messageId: (string) ($payload['message_id'] ?? ''),
            uid: (string) ($payload['uid'] ?? ''),
            remitente: (string) ($payload['remitente'] ?? ''),
            asunto: (string) ($payload['asunto'] ?? ''),
            cuerpoTexto: '',
            fechaMensaje: $fecha,
            adjuntosPdf: [],
        );
    }

    private function registrarIgnorado(MailFacturaMensaje $mensaje): void
    {
        Precarga_Comprobante_Mail_Mensaje::query()->firstOrCreate(
            ['message_id' => $mensaje->messageId, 'adjunto_hash' => 'sin-adjunto'],
            [
                'uid' => $mensaje->uid,
                'carpeta' => (string) config('precarga_comprobante_mail.carpeta', 'INBOX'),
                'remitente' => $mensaje->remitente,
                'asunto' => mb_substr($mensaje->asunto, 0, 500),
                'fecha_mensaje' => $mensaje->fechaMensaje,
                'adjunto_nombre' => null,
                'estado' => Precarga_Comprobante_Mail_Mensaje::ESTADO_IGNORADO,
                'mensaje_error' => 'Mensaje sin adjuntos PDF.',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $adjunto
     * @param  array<string, mixed>  $extra
     */
    private function registrarResultado(
        MailFacturaMensaje $mensaje,
        array $adjunto,
        string $estado,
        array $extra = [],
    ): void {
        try {
            Precarga_Comprobante_Mail_Mensaje::query()->updateOrCreate(
                ['message_id' => $mensaje->messageId, 'adjunto_hash' => (string) ($adjunto['hash'] ?? '')],
                [
                    'uid' => $mensaje->uid,
                    'carpeta' => (string) config('precarga_comprobante_mail.carpeta', 'INBOX'),
                    'remitente' => $mensaje->remitente,
                    'asunto' => mb_substr($mensaje->asunto, 0, 500),
                    'fecha_mensaje' => $mensaje->fechaMensaje,
                    'adjunto_nombre' => (string) ($adjunto['nombre'] ?? ''),
                    'numero_oc' => $adjunto['numero_oc'] ?? null,
                    'estado' => $estado,
                    'precarga_id' => $extra['precarga_id'] ?? null,
                    'mensaje_error' => $extra['mensaje_error'] ?? null,
                ],
            );
        } catch (Throwable $e) {
            Log::channel('ai')->warning('mail_ingesta.registro_fallo', [
                'message_id' => $mensaje->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function moverACuarentena(string $path, string $nombre): ?string
    {
        if ($path === '' || ! is_file($path)) {
            return null;
        }

        try {
            $dir = storage_path('app/'.self::DIR_PENDIENTE);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $destino = $dir.'/'.now()->format('Ymd_His').'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre);
            copy($path, $destino);

            return $destino;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array{ok: bool, adjunto: string, numero_oc: ?string, error?: string}>  $errores
     */
    private function avisarErrores(MailFacturaMensaje $mensaje, array $errores, int $exitos): void
    {
        if (! config('precarga_comprobante_mail.aviso_errores.habilitado', true)) {
            return;
        }

        $destinatarios = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('precarga_comprobante_mail.aviso_errores.destinatarios', '')),
        )));
        if ($destinatarios === []) {
            return;
        }

        try {
            Mail::to($destinatarios)->send(new FacturaMailIngestaErroresMail($mensaje, $errores, $exitos));
        } catch (Throwable $e) {
            Log::channel('ai')->warning('mail_ingesta.aviso_errores_fallo', [
                'message_id' => $mensaje->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
