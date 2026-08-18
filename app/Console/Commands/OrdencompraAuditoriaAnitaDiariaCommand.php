<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Compras\OrdencompraAnitaAuditoriaDiariaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class OrdencompraAuditoriaAnitaDiariaCommand extends Command
{
    protected $signature = 'ordencompra:auditoria-anita-diaria
                            {--desde= : Desde Y-m-d inclusive (default: ventana config)}
                            {--hasta= : Hasta Y-m-d inclusive (default: hoy)}
                            {--sin-mail : No envía correo}
                            {--reparar : Repara gaps detectados (override config)}
                            {--sin-reparar : Solo diagnostica, no repara}';

    protected $description = 'Audita OC ERP ↔ Anita (cabecera, pad, pendmovp cobertura, aplicped), repara y notifica';

    public function handle(OrdencompraAnitaAuditoriaDiariaService $service): int
    {
        if (! config('ordencompra_anita.auditoria_diaria.habilitada', true)) {
            $this->warn('Auditoría deshabilitada (ordencompra_anita.auditoria_diaria.habilitada).');

            return self::SUCCESS;
        }

        $desde = trim((string) ($this->option('desde') ?? ''));
        $hasta = trim((string) ($this->option('hasta') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        $autoReparar = null;
        if ((bool) $this->option('reparar')) {
            $autoReparar = true;
        } elseif ((bool) $this->option('sin-reparar')) {
            $autoReparar = false;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $alcance = ($desde !== '' || $hasta !== '')
            ? 'fecha '.($desde ?: '…').' → '.($hasta ?: '…')
            : 'ventana diaria';
        $modoReparar = $autoReparar === null
            ? (filter_var(config('ordencompra_anita.auditoria_diaria.auto_reparar', true), FILTER_VALIDATE_BOOLEAN) ? 'auto' : 'off')
            : ($autoReparar ? 'on' : 'off');
        $this->line(sprintf(
            'Auditoría OC ERP ↔ Anita · %s · reparar=%s%s',
            $alcance,
            $modoReparar,
            $enviarMail ? '' : ' · sin mail',
        ));

        try {
            $informe = $service->ejecutar(
                $desde !== '' ? $desde : null,
                $hasta !== '' ? $hasta : null,
                $enviarMail,
                $autoReparar,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['OC en alcance', (string) ($informe['total_oc'] ?? 0)],
                ['OK', (string) ($informe['ok'] ?? 0)],
                ['Reparadas', (string) ($informe['reparadas'] ?? 0)],
                ['Pendmovp cobertura detectada', (string) ($informe['pendmovp_cobertura_detectadas'] ?? 0)],
                ['Pendmovp cobertura reparada', (string) ($informe['pendmovp_cobertura_reparadas'] ?? 0)],
                ['Discrepancias', (string) count($informe['discrepancias'] ?? [])],
                ['Errores', (string) count($informe['errores'] ?? [])],
                ['Proveedor mal pad (scan Anita)', (string) count($informe['proveedores_mal_pad_anita'] ?? [])],
            ],
        );

        foreach ($informe['filas_reparadas'] ?? [] as $fila) {
            $this->info(sprintf(
                'OC %s reparada%s: %s',
                (string) ($fila['numero'] ?? '?'),
                ! empty($fila['pendmovp_cobertura']) ? ' (pendmovp)' : '',
                implode('; ', $fila['acciones'] ?? []),
            ));
        }

        foreach ($informe['discrepancias'] ?? [] as $fila) {
            $this->warn(sprintf(
                'OC %s: %s',
                (string) ($fila['numero'] ?? '?'),
                implode('; ', $fila['problemas'] ?? []),
            ));
            foreach ($fila['acciones'] ?? [] as $acc) {
                $this->line('  · acción: '.$acc);
            }
        }

        foreach ($informe['errores'] ?? [] as $error) {
            $this->error(sprintf(
                'OC %s: %s',
                (string) ($error['numero'] ?? '—'),
                (string) ($error['mensaje'] ?? ''),
            ));
        }

        if (! empty($informe['mail_enviado'])) {
            $this->info('Correo enviado a '.$informe['mail_destino']);
        } elseif (! empty($informe['mail_error'])) {
            $this->error('Fallo al enviar correo: '.$informe['mail_error']);
        }

        return ($informe['requiere_alerta'] ?? false) ? self::FAILURE : self::SUCCESS;
    }
}
