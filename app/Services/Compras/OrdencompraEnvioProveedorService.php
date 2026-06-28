<?php

namespace App\Services\Compras;

use App\Mail\Compras\OrdencompraProveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use App\Repositories\Compras\Ordencompra_EstadoRepositoryInterface;
use App\Support\Compras\OrdencompraEstados;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class OrdencompraEnvioProveedorService
{
    public function __construct(
        private OrdencompraPdfService $ordencompraPdfService,
        private Ordencompra_EstadoRepositoryInterface $ordencompraEstadoRepository,
    ) {}

    /**
     * @return array{
     *     puede_enviar: bool,
     *     email: string,
     *     emails: list<string>,
     *     proveedor_nombre: string,
     *     numeroordencompra: string|int|null,
     *     estadoordencompra: string,
     *     advertencia_estado: string|null,
     *     mensaje: string|null
     * }
     */
    public function datosEnvio(int $ordencompraId): array
    {
        $oc = Ordencompra::query()
            ->select(['id', 'numeroordencompra', 'proveedor_id', 'estadoordencompra'])
            ->with(['proveedores:id,nombre,emailoc'])
            ->find($ordencompraId);

        if (! $oc) {
            return [
                'puede_enviar' => false,
                'email' => '',
                'emails' => [],
                'proveedor_nombre' => '',
                'numeroordencompra' => null,
                'estadoordencompra' => '',
                'advertencia_estado' => null,
                'mensaje' => 'La orden de compra no existe.',
            ];
        }

        $proveedor = $oc->proveedores;
        if (! $proveedor) {
            return [
                'puede_enviar' => false,
                'email' => '',
                'emails' => [],
                'proveedor_nombre' => '',
                'numeroordencompra' => $oc->numeroordencompra,
                'estadoordencompra' => (string) ($oc->estadoordencompra ?? ''),
                'advertencia_estado' => null,
                'mensaje' => 'La orden de compra no tiene proveedor asignado.',
            ];
        }

        $emails = self::parseEmails((string) ($proveedor->emailoc ?? ''));
        if ($emails === []) {
            return [
                'puede_enviar' => false,
                'email' => '',
                'emails' => [],
                'proveedor_nombre' => (string) ($proveedor->nombre ?? ''),
                'numeroordencompra' => $oc->numeroordencompra,
                'estadoordencompra' => (string) ($oc->estadoordencompra ?? ''),
                'advertencia_estado' => null,
                'mensaje' => 'El proveedor no tiene configurado el email de envío de OC (Email OC).',
            ];
        }

        $advertencia = self::advertenciaEstadoParaEnvio((string) ($oc->estadoordencompra ?? ''));

        return [
            'puede_enviar' => true,
            'email' => implode(', ', $emails),
            'emails' => $emails,
            'proveedor_nombre' => (string) ($proveedor->nombre ?? ''),
            'numeroordencompra' => $oc->numeroordencompra,
            'estadoordencompra' => (string) ($oc->estadoordencompra ?? ''),
            'advertencia_estado' => $advertencia,
            'mensaje' => null,
        ];
    }

    /**
     * @return array{mensaje: string, errores?: string}
     */
    public function enviar(int $ordencompraId, ?string $emailOverride = null, ?string $mensajeAdicional = null): array
    {
        $datos = $this->datosEnvio($ordencompraId);
        if (! ($datos['puede_enviar'] ?? false)) {
            return ['mensaje' => 'error', 'errores' => $datos['mensaje'] ?? 'No se puede enviar la orden al proveedor.'];
        }

        $emails = $emailOverride !== null && trim($emailOverride) !== ''
            ? self::parseEmails($emailOverride)
            : ($datos['emails'] ?? []);

        if ($emails === []) {
            return ['mensaje' => 'error', 'errores' => 'Indique al menos un email de destino válido.'];
        }

        $oc = Ordencompra::query()
            ->with(['proveedores', 'empresas'])
            ->find($ordencompraId);
        if (! $oc) {
            return ['mensaje' => 'error', 'errores' => 'La orden de compra no existe.'];
        }

        $pdf = null;
        try {
            $pdf = $this->ordencompraPdfService->generarArchivo($ordencompraId, false);
            $mailable = new OrdencompraProveedor($oc, $mensajeAdicional);
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

        $uid = Auth::id() ?? (int) ($oc->creousuario_id ?? 0);
        if ($uid > 0) {
            $destinos = implode(', ', $emails);
            $this->ordencompraEstadoRepository->creaEstado(
                $ordencompraId,
                Carbon::now()->toDateTimeString(),
                (string) ($oc->estadoordencompra ?? OrdencompraEstados::PENDIENTE),
                $uid,
                'OC enviada al proveedor por correo ('.$destinos.')'
            );
        }

        return ['mensaje' => 'ok'];
    }

    public static function proveedorTieneEmailOc(?int $proveedorId): bool
    {
        if ($proveedorId === null || $proveedorId <= 0) {
            return false;
        }

        $email = Proveedor::query()->whereKey($proveedorId)->value('emailoc');

        return self::parseEmails((string) ($email ?? '')) !== [];
    }

    /**
     * @return list<string>
     */
    public static function parseEmails(string $raw): array
    {
        $partes = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $emails = [];
        foreach ($partes as $p) {
            $p = trim($p);
            if ($p !== '' && filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $p;
            }
        }

        return array_values(array_unique($emails));
    }

    public static function advertenciaEstadoParaEnvio(string $estado): ?string
    {
        if ($estado === OrdencompraEstados::APROBADA) {
            return null;
        }
        if (in_array($estado, [OrdencompraEstados::SUSPENDIDA, OrdencompraEstados::CERRADA, OrdencompraEstados::CUMPLIDA], true)) {
            return 'La orden está en estado '.$estado.'. Revise si corresponde enviarla al proveedor.';
        }

        return 'La orden aún no está APROBADA. Puede enviarla igualmente si el proveedor debe recibirla en este estado.';
    }
}
