<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaAnitaAuditoriaDiariaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GastronomiaAuditoriaAnitaDiaria extends Command
{
    protected $signature = 'gastronomia:auditoria-anita-diaria
                            {--fecha= : Fecha calendario Y-m-d (default: ayer)}
                            {--empresa= : Override empresa_id}
                            {--dry-run : Audita y simula replicación, sin escribir ni enviar mail}
                            {--sin-mail : No envía correo aunque haya alertas}';

    protected $description = 'Audita ventas gastronomía del día calendario anterior, replica faltantes en Anita y alerta por mail';

    public function handle(GastronomiaAnitaAuditoriaDiariaService $service): int
    {
        if (! config('gastronomia.auditoria_anita_diaria.habilitada', true)) {
            $this->warn('Auditoría Anita diaria deshabilitada (gastronomia.auditoria_anita_diaria.habilitada).');

            return self::SUCCESS;
        }

        $fechaOpt = trim((string) ($this->option('fecha') ?? ''));
        $fecha = $fechaOpt !== '' ? $fechaOpt : Carbon::yesterday()->toDateString();
        $empresaId = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;
        $dryRun = (bool) $this->option('dry-run');
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Fecha calendario %s%s%s',
            $fecha,
            $dryRun ? ' | MODO SIMULACIÓN' : '',
            $enviarMail ? '' : ' | sin mail',
        ));

        try {
            $informe = $service->ejecutar($fecha, $dryRun, $enviarMail, $empresaId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $pre = $informe['pre']['resumen_global'] ?? [];
        $post = $informe['post']['resumen_global'] ?? [];
        $rep = $informe['replicacion'] ?? [];

        $this->newLine();
        $this->info('Resumen');
        $this->table(
            ['Concepto', 'Antes', 'Después'],
            [
                ['Sin cabecera Anita', (string) ($pre['conteo']['solo_erp'] ?? 0), (string) ($post['conteo']['solo_erp'] ?? 0)],
                ['Diferencia importes', (string) ($pre['conteo']['diferencia'] ?? 0), (string) ($post['conteo']['diferencia'] ?? 0)],
                ['Replicadas', '—', (string) ($rep['replicadas'] ?? 0)],
                ['Delta total ERP−Anita', (string) ($pre['delta_totales']['total'] ?? 0), (string) ($post['delta_totales']['total'] ?? 0)],
            ],
        );

        if (! empty($informe['mail_enviado'])) {
            $this->info('Correo enviado a '.$informe['mail_destino']);
        } elseif (! empty($informe['mail_error'])) {
            $this->error('Fallo al enviar correo: '.$informe['mail_error']);
        } elseif ($informe['requiere_alerta'] ?? false) {
            $this->comment('Alerta detectada; revise mail o ejecute sin --sin-mail.');
        } else {
            $this->info('Sin alertas; no se envió correo.');
        }

        $hayProblemas = (int) ($post['conteo']['solo_erp'] ?? 0) > 0
            || (int) ($post['conteo']['diferencia'] ?? 0) > 0
            || ($rep['errores'] ?? []) !== [];

        return $hayProblemas ? self::FAILURE : self::SUCCESS;
    }
}
