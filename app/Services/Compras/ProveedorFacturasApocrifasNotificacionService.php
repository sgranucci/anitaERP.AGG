<?php

namespace App\Services\Compras;

use App\Mail\Compras\ProveedorFacturasApocrifasSuspensionMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ProveedorFacturasApocrifasNotificacionService
{
    /**
     * @param  array<string, mixed>  $informe
     * @return array{enviado: bool, motivo?: string, destinatarios?: list<string>, error?: string}
     */
    public function enviarSiCorresponde(array $informe): array
    {
        $config = config('arca_wsapoc.mail', []);

        if (! filter_var($config['habilitado'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return ['enviado' => false, 'motivo' => 'deshabilitado'];
        }

        $suspendidos = array_merge(
            $informe['proveedores_suspendidos'] ?? [],
            $informe['clientes_suspendidos'] ?? []
        );

        $soloSiSuspendidos = filter_var($config['solo_si_suspendidos'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if ($soloSiSuspendidos && $suspendidos === []) {
            return ['enviado' => false, 'motivo' => 'sin_suspensiones'];
        }

        $destinatarios = $this->resolverDestinatarios($config['destinatarios'] ?? '');
        if ($destinatarios === []) {
            Log::warning('arca_wsapoc.mail — sin destinatarios (ARCA_WSAPOC_MAIL_DESTINATARIOS vacío)');

            return ['enviado' => false, 'motivo' => 'sin_destinatarios'];
        }

        try {
            Mail::to($destinatarios)->send(new ProveedorFacturasApocrifasSuspensionMail($informe));

            return [
                'enviado' => true,
                'destinatarios' => $destinatarios,
            ];
        } catch (Throwable $e) {
            Log::error('arca_wsapoc.mail_fallo', [
                'error' => $e->getMessage(),
                'destinatarios' => $destinatarios,
                'suspendidos' => count($suspendidos),
            ]);

            return [
                'enviado' => false,
                'motivo' => 'error_envio',
                'destinatarios' => $destinatarios,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return list<string>
     */
    private function resolverDestinatarios(mixed $raw): array
    {
        if (is_array($raw)) {
            $lista = $raw;
        } else {
            $lista = explode(',', (string) $raw);
        }

        $out = [];
        foreach ($lista as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }
}
