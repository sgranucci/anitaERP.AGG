<?php

namespace App\Services\Compras;

use App\Mail\Compras\PagoproveedorOrdenPago;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Proveedor;
use App\Support\Mail\EmailsMultiplesSupport;
use Auth;
use Illuminate\Support\Facades\Mail;

class PagoproveedorEnvioProveedorService
{
    public function __construct(
        private PagoproveedorComprobantePdfService $pagoproveedorComprobantePdfService,
    ) {}

    /**
     * @return array{
     *     puede_enviar: bool,
     *     email: string,
     *     emails: list<string>,
     *     proveedor_nombre: string,
     *     etiqueta_op: string,
     *     estado: string,
     *     advertencia_estado: string|null,
     *     mensaje: string|null
     * }
     */
    public function datosEnvio(int $pagoproveedorId): array
    {
        $pago = Pagoproveedor::query()
            ->select(['id', 'tipocomprobante', 'sucursal', 'numerotransaccion', 'proveedor_id', 'estado'])
            ->with(['proveedores:id,nombre,email,emailoc'])
            ->find($pagoproveedorId);

        if (! $pago) {
            return [
                'puede_enviar' => false,
                'email' => '',
                'emails' => [],
                'proveedor_nombre' => '',
                'etiqueta_op' => '',
                'estado' => '',
                'advertencia_estado' => null,
                'mensaje' => 'La orden de pago no existe.',
            ];
        }

        $etiqueta = $pago->etiquetaComprobante();
        $proveedor = $pago->proveedores;
        if (! $proveedor) {
            return [
                'puede_enviar' => false,
                'email' => '',
                'emails' => [],
                'proveedor_nombre' => '',
                'etiqueta_op' => $etiqueta,
                'estado' => (string) ($pago->estado ?? ''),
                'advertencia_estado' => null,
                'mensaje' => 'La orden de pago no tiene proveedor asignado.',
            ];
        }

        $emails = self::emailsProveedor($proveedor);
        $sinEmailProveedor = $emails === [];

        return [
            'puede_enviar' => true,
            'email' => implode(', ', $emails),
            'emails' => $emails,
            'proveedor_nombre' => (string) ($proveedor->nombre ?? ''),
            'etiqueta_op' => $etiqueta,
            'estado' => (string) ($pago->estado ?? ''),
            'advertencia_estado' => self::advertenciaEstadoParaEnvio((string) ($pago->estado ?? '')),
            'mensaje' => $sinEmailProveedor
                ? 'El proveedor no tiene email configurado. Ingrese uno o más destinatarios.'
                : null,
        ];
    }

    /**
     * @return array{mensaje: string, errores?: string}
     */
    public function enviar(int $pagoproveedorId, ?string $emailOverride = null, ?string $mensajeAdicional = null): array
    {
        $pagoExiste = Pagoproveedor::query()->whereKey($pagoproveedorId)->exists();
        if (! $pagoExiste) {
            return ['mensaje' => 'error', 'errores' => 'La orden de pago no existe.'];
        }

        $datos = $this->datosEnvio($pagoproveedorId);
        $emails = $emailOverride !== null && trim($emailOverride) !== ''
            ? self::parseEmails($emailOverride)
            : ($datos['emails'] ?? []);

        if ($emails === []) {
            $invalidos = self::emailsInvalidos((string) ($emailOverride ?? ''));
            if ($invalidos !== []) {
                return [
                    'mensaje' => 'error',
                    'errores' => 'Email(s) inválido(s): '.implode(', ', $invalidos),
                ];
            }

            return ['mensaje' => 'error', 'errores' => 'Indique al menos un email de destino válido.'];
        }

        $invalidosOverride = self::emailsInvalidos((string) ($emailOverride ?? ''));
        if ($emailOverride !== null && trim($emailOverride) !== '' && $invalidosOverride !== []) {
            return [
                'mensaje' => 'error',
                'errores' => 'Email(s) inválido(s): '.implode(', ', $invalidosOverride),
            ];
        }

        $pago = Pagoproveedor::query()
            ->with(['proveedores', 'empresas'])
            ->find($pagoproveedorId);
        if (! $pago) {
            return ['mensaje' => 'error', 'errores' => 'La orden de pago no existe.'];
        }

        $pdf = null;
        try {
            $pdf = $this->pagoproveedorComprobantePdfService->generarArchivo($pagoproveedorId);
            $mailable = new PagoproveedorOrdenPago($pago, $mensajeAdicional);
            Mail::to($emails)->send($mailable->attach($pdf['ruta'], [
                'as' => $pdf['nombre'],
                'mime' => 'application/pdf',
            ]));
        } catch (\Throwable $e) {
            report($e);

            return ['mensaje' => 'error', 'errores' => 'No se pudo enviar el correo: '.$e->getMessage()];
        } finally {
            if ($pdf !== null && is_file($pdf['ruta'])) {
                @unlink($pdf['ruta']);
            }
        }

        $uid = Auth::id();
        if ($uid) {
            Pagoproveedor_Estado::query()->create([
                'pagoproveedor_id' => $pago->id,
                'fecha' => now(),
                'estado' => (string) ($pago->estado ?? ''),
                'usuario_id' => $uid,
                'observacion' => 'OP enviada por correo ('.implode(', ', $emails).')',
            ]);
        }

        return ['mensaje' => 'ok'];
    }

    /**
     * @return list<string>
     */
    public static function emailsProveedor(Proveedor $proveedor): array
    {
        $raw = trim((string) ($proveedor->email ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($proveedor->emailoc ?? ''));
        } elseif (trim((string) ($proveedor->emailoc ?? '')) !== '') {
            $raw .= ', '.(string) $proveedor->emailoc;
        }

        return self::parseEmails($raw);
    }

    /**
     * @return list<string>
     */
    public static function parseEmails(string $raw): array
    {
        return EmailsMultiplesSupport::parse($raw);
    }

    /**
     * Tokens no vacíos que no pasan validación de email.
     *
     * @return list<string>
     */
    public static function emailsInvalidos(string $raw): array
    {
        return EmailsMultiplesSupport::invalidos($raw);
    }

    public static function advertenciaEstadoParaEnvio(string $estado): ?string
    {
        $estado = strtoupper(trim($estado));
        if (in_array($estado, ['REVERTIDA', 'BAJA'], true)) {
            return 'La OP está en estado '.$estado.'. Revise si corresponde enviarla.';
        }
        if ($estado === 'PRE CARGA') {
            return 'La OP aún está en Pre carga. Puede enviarla igualmente si el proveedor debe recibirla.';
        }

        return null;
    }
}
