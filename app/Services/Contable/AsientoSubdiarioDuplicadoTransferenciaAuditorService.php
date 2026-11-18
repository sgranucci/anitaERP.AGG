<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Mail\Contable\AsientoSubdiarioDuplicadoTransferenciaMail;
use App\Models\Configuracion\Empresa;
use App\Support\Contable\AsientoSubdiarioDuplicadoTransferenciaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class AsientoSubdiarioDuplicadoTransferenciaAuditorService
{
    /**
     * @return array<string, mixed>
     */
    public function ejecutar(?string $fechaCorte = null, bool $enviarMail = true): array
    {
        $this->asegurarVistaCompilable();

        $config = (array) config('contable.asiento_subdiario_duplicado_auditor', []);
        $empresaId = max(1, (int) ($config['empresa_id'] ?? 1));
        $ventanaDias = max(1, (int) ($config['ventana_dias'] ?? 60));
        $limiteMail = max(10, (int) ($config['limite_filas_mail'] ?? 40));
        $mailSiempre = (bool) ($config['mail_siempre'] ?? false);

        $hasta = $fechaCorte !== null && trim($fechaCorte) !== ''
            ? Carbon::createFromFormat('Y-m-d', trim($fechaCorte))->startOfDay()
            : Carbon::today();
        $desde = $hasta->copy()->subDays($ventanaDias - 1);

        $duplicados = AsientoSubdiarioDuplicadoTransferenciaSupport::detectar(
            $empresaId,
            $desde->toDateString(),
            $hasta->toDateString(),
        );

        $importeTotal = 0.0;
        foreach ($duplicados as $fila) {
            $importeTotal += (float) ($fila['importe'] ?? 0);
        }

        $empresaNombre = (string) (Empresa::query()->whereKey($empresaId)->value('nombre') ?? '');
        $requiereAlerta = $duplicados !== [];

        $informe = [
            'empresa_id' => $empresaId,
            'empresa_nombre' => $empresaNombre !== '' ? $empresaNombre : 'Empresa #'.$empresaId,
            'fecha' => $hasta->toDateString(),
            'fecha_fmt' => $hasta->format('d/m/Y'),
            'desde' => $desde->toDateString(),
            'desde_fmt' => $desde->format('d/m/Y'),
            'hasta' => $hasta->toDateString(),
            'hasta_fmt' => $hasta->format('d/m/Y'),
            'ventana_dias' => $ventanaDias,
            'requiere_alerta' => $requiereAlerta,
            'cantidad' => count($duplicados),
            'importe_total' => round($importeTotal, 2),
            'duplicados' => array_slice($duplicados, 0, $limiteMail),
            'duplicados_omitidos' => max(0, count($duplicados) - $limiteMail),
            'comando_limpieza' => sprintf(
                'php artisan contable:eliminar-asientos-subdiario-duplicados-transferencia --empresa-id=%d --desde=%s --hasta=%s --dry-run',
                $empresaId,
                $desde->toDateString(),
                $hasta->toDateString(),
            ),
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
        ];

        if ($enviarMail && ($requiereAlerta || $mailSiempre)) {
            $informe = array_merge($informe, $this->enviarMail($informe, $config));
        } elseif (! $enviarMail) {
            $informe['mail_error'] = 'omitido (--sin-mail)';
        }

        return $informe;
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     * @return array{mail_enviado?: bool, mail_destino?: string, mail_error?: string}
     */
    private function enviarMail(array $informe, array $config): array
    {
        $destinos = $this->resolverDestinatarios($this->emailConfigurado($config));
        if ($destinos === []) {
            return ['mail_error' => 'Sin destinatario (CONTABLE_ASIENTO_SUBDIARIO_DUP_AUDITOR_EMAIL).'];
        }

        try {
            Mail::to($destinos)->send(new AsientoSubdiarioDuplicadoTransferenciaMail($informe));

            return [
                'mail_enviado' => true,
                'mail_destino' => implode(', ', $destinos),
            ];
        } catch (Throwable $e) {
            Log::error('Auditor asientos subdiario duplicados: mail falló', [
                'empresa_id' => $informe['empresa_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return ['mail_error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function emailConfigurado(array $config): string
    {
        $email = trim((string) ($config['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        return trim((string) config('compras.deuda_ficha_auditor.email', ''));
    }

    /**
     * @return list<string>
     */
    private function resolverDestinatarios(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[;,]+/', $raw) ?: [] as $parte) {
            $mail = strtolower(trim((string) $parte));
            if ($mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $out[$mail] = $mail;
            }
        }

        return array_values($out);
    }

    private function asegurarVistaCompilable(): void
    {
        $compiled = (string) config('view.compiled');
        if ($compiled !== '' && is_dir($compiled) && is_writable($compiled)) {
            return;
        }

        $fallback = rtrim(sys_get_temp_dir(), '/').'/anitaERP-views';
        if (! is_dir($fallback)) {
            @mkdir($fallback, 0775, true);
        }
        if (is_dir($fallback) && is_writable($fallback)) {
            config(['view.compiled' => $fallback]);
        }
    }
}
