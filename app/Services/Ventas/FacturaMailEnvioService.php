<?php

namespace App\Services\Ventas;

use App\Mail\Ventas\FacturaClienteMail;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Venta;
use App\Support\Ventas\FacturaMailConfiguracionSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class FacturaMailEnvioService
{
    public function __construct(
        private FacturacionService $facturacionService,
        private EnvioComprobantePdfService $envioComprobantePdfService,
    ) {
    }

    /**
     * @return array{ok: bool, mensaje: string, destinatarios?: list<string>}
     */
    public function enviarAutomaticoSiCorresponde(int $ventaId): array
    {
        $ctx = $this->evaluar($ventaId, automatico: true);
        if (! ($ctx['puede'] ?? false)) {
            return ['ok' => false, 'mensaje' => $ctx['motivo'] ?? 'No corresponde envío automático'];
        }

        return $this->enviar($ventaId, null, null);
    }

    /**
     * @return array{ok: bool, mensaje: string, destinatarios?: list<string>}
     */
    public function enviar(int $ventaId, ?string $emailOverride = null, ?string $mensajeExtra = null): array
    {
        $ctx = $this->evaluar($ventaId, automatico: false, emailOverride: $emailOverride);
        if (! ($ctx['puede'] ?? false)) {
            return ['ok' => false, 'mensaje' => $ctx['motivo'] ?? 'No se puede enviar'];
        }

        /** @var Venta $venta */
        $venta = $ctx['venta'];
        $cfg = $ctx['config'];
        $emails = $ctx['emails'];

        try {
            $adjuntos = $this->armarAdjuntos($venta, (bool) $cfg->incluir_remito, (bool) $cfg->incluir_envio);
            if ($adjuntos === []) {
                return ['ok' => false, 'mensaje' => 'No se pudo generar el PDF de la factura'];
            }

            $cuerpo = $this->renderCuerpo($cfg->cuerpo ?? '', $venta, $mensajeExtra);
            $asunto = $this->renderPlantilla($cfg->asunto ?: 'Comprobante {codigo}', $venta);

            $mailable = (new FacturaClienteMail($venta, $cuerpo, $adjuntos))->subject($asunto);
            $pending = Mail::to($emails);
            $bcc = self::parseEmails((string) ($cfg->bcc ?? ''));
            if ($bcc !== []) {
                $pending->bcc($bcc);
            }
            $pending->send($mailable);

            return [
                'ok' => true,
                'mensaje' => 'Factura enviada a '.implode(', ', $emails),
                'destinatarios' => $emails,
            ];
        } catch (\Throwable $e) {
            Log::warning('factura.mail.envio_fallo', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'mensaje' => 'Error al enviar mail: '.$e->getMessage()];
        }
    }

    /**
     * @return array{
     *     puede: bool,
     *     motivo?: string,
     *     emails?: list<string>,
     *     venta?: Venta,
     *     config?: \App\Models\Ventas\Factura_Mail_Configuracion
     * }
     */
    public function evaluar(int $ventaId, bool $automatico = false, ?string $emailOverride = null): array
    {
        if (! Schema::hasTable('factura_mail_configuracion')) {
            return ['puede' => false, 'motivo' => 'Configuración de mail de facturas no disponible'];
        }

        $venta = Venta::query()
            ->with(['clientes', 'puntoventas.empresas'])
            ->find($ventaId);
        if (! $venta) {
            return ['puede' => false, 'motivo' => 'Venta inexistente'];
        }

        $empresaId = (int) ($venta->puntoventas?->empresa_id ?? 0);
        if ($empresaId <= 0) {
            return ['puede' => false, 'motivo' => 'La venta no tiene empresa'];
        }

        $cfg = FacturaMailConfiguracionSupport::paraEmpresa($empresaId);
        if (! $cfg->habilitado) {
            return ['puede' => false, 'motivo' => 'El envío de facturas por mail no está habilitado para la empresa'];
        }
        if ($automatico && ! $cfg->envio_automatico) {
            return ['puede' => false, 'motivo' => 'El envío automático está desactivado'];
        }

        /** @var Cliente|null $cliente */
        $cliente = $venta->clientes;
        if ($automatico && $cfg->exigir_flag_cliente) {
            $flag = Schema::hasColumn('cliente', 'enviar_factura_mail')
                ? (bool) ($cliente?->enviar_factura_mail ?? false)
                : false;
            if (! $flag) {
                return ['puede' => false, 'motivo' => 'El cliente no tiene activado el envío de factura por mail'];
            }
        }

        $emails = $emailOverride !== null && trim($emailOverride) !== ''
            ? self::parseEmails($emailOverride)
            : self::parseEmails((string) ($cliente?->email ?? $venta->email ?? ''));

        if ($emails === []) {
            return ['puede' => false, 'motivo' => 'El cliente no tiene un email válido'];
        }

        return [
            'puede' => true,
            'emails' => $emails,
            'venta' => $venta,
            'config' => $cfg,
        ];
    }

    /**
     * Envío forzado al email del comprador (canal Tiendanube): no exige flag del cliente contado
     * ni que la empresa tenga habilitación global de mail automático.
     *
     * @return array{ok: bool, mensaje: string, destinatarios?: list<string>}
     */
    public function enviarDesdeTiendanube(int $ventaId, string $email): array
    {
        $emails = self::parseEmails($email);
        if ($emails === []) {
            return ['ok' => false, 'mensaje' => 'Email del comprador Tiendanube inválido'];
        }

        $venta = Venta::query()
            ->with(['clientes', 'puntoventas.empresas'])
            ->find($ventaId);
        if (! $venta) {
            return ['ok' => false, 'mensaje' => 'Venta inexistente'];
        }

        $empresaId = (int) ($venta->puntoventas?->empresa_id ?? config('tiendanube.empresa_id', 1));
        $cfg = FacturaMailConfiguracionSupport::paraEmpresa($empresaId);
        if (! $cfg->exists) {
            $cfg = FacturaMailConfiguracionSupport::defaults($empresaId);
        }
        // TN siempre intenta enviar al mail del comprador (no depende del flag del cliente maestro CF).
        $cfg->habilitado = true;
        $cfg->envio_automatico = true;
        $cfg->exigir_flag_cliente = false;

        try {
            $adjuntos = $this->armarAdjuntos($venta, (bool) $cfg->incluir_remito, (bool) $cfg->incluir_envio);
            if ($adjuntos === []) {
                return ['ok' => false, 'mensaje' => 'No se pudo generar el PDF de la factura'];
            }

            $cuerpo = $this->renderCuerpo($cfg->cuerpo ?? '', $venta, null);
            $asunto = $this->renderPlantilla($cfg->asunto ?: 'Comprobante {codigo}', $venta);

            $mailable = (new FacturaClienteMail($venta, $cuerpo, $adjuntos))->subject($asunto);
            $pending = Mail::to($emails);
            $bcc = self::parseEmails((string) ($cfg->bcc ?? ''));
            if ($bcc !== []) {
                $pending->bcc($bcc);
            }
            $pending->send($mailable);

            return [
                'ok' => true,
                'mensaje' => 'Factura enviada a '.implode(', ', $emails),
                'destinatarios' => $emails,
            ];
        } catch (\Throwable $e) {
            Log::warning('factura.mail.tiendanube_fallo', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'mensaje' => 'Error al enviar mail: '.$e->getMessage()];
        }
    }

    /**
     * @return list<array{path: string, name: string}>
     */
    private function armarAdjuntos(Venta $venta, bool $incluirRemito, bool $incluirEnvio): array
    {
        $adjuntos = [];
        $rutaFac = $this->facturacionService->generarPdfFacturaArchivo(
            (int) $venta->id,
            'ORIGINAL',
            ! $incluirRemito,
            false
        );
        if ($rutaFac !== '' && is_file($rutaFac)) {
            $adjuntos[] = [
                'path' => $rutaFac,
                'name' => 'factura-'.preg_replace('/[^\w\-]+/', '_', (string) $venta->codigo).'.pdf',
            ];
        }

        if ($incluirRemito && (int) ($venta->numeroremito ?? 0) > 0 && (int) ($venta->remito_id ?? 0) <= 0) {
            // La hoja remito ya va en el PDF FAC si incluir_remito; no duplicar.
        }

        if ($incluirEnvio) {
            try {
                $rutaEnv = $this->envioComprobantePdfService->generarPdfDesdeVenta((int) $venta->id);
                if ($rutaEnv !== '' && is_file($rutaEnv)) {
                    $adjuntos[] = [
                        'path' => $rutaEnv,
                        'name' => 'envio-'.preg_replace('/[^\w\-]+/', '_', (string) $venta->codigo).'.pdf',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('factura.mail.envio_pdf_fallo', ['venta_id' => $venta->id, 'error' => $e->getMessage()]);
            }
        }

        return $adjuntos;
    }

    private function renderCuerpo(string $plantilla, Venta $venta, ?string $extra): string
    {
        $texto = $this->renderPlantilla($plantilla, $venta);
        if ($extra !== null && trim($extra) !== '') {
            $texto .= "\n\n".trim($extra);
        }

        return nl2br(e($texto));
    }

    private function renderPlantilla(string $plantilla, Venta $venta): string
    {
        $fecha = $venta->fecha;
        $fechaStr = $fecha instanceof \DateTimeInterface
            ? $fecha->format('d/m/Y')
            : (string) $fecha;

        return strtr($plantilla, [
            '{codigo}' => (string) ($venta->codigo ?? $venta->id),
            '{cliente}' => (string) ($venta->nombre ?? $venta->clientes?->nombre ?? ''),
            '{empresa}' => (string) ($venta->puntoventas?->empresas?->nombre ?? ''),
            '{fecha}' => $fechaStr,
        ]);
    }

    /**
     * @return list<string>
     */
    public static function parseEmails(string $raw): array
    {
        $parts = preg_split('/[;,]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $email = strtolower(trim($p));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }
}
