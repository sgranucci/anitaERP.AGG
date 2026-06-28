<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaProcesoAutomaticoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GastronomiaCierreJornadaWaitryAutomatico extends Command
{
    protected $signature = 'gastronomia:cierre-jornada-waitry-automatico
                            {--empresa= : ID empresa (default: todas las habilitadas en config)}
                            {--fecha-jornada= : Y-m-d (default: última jornada cerrada pendiente por empresa)}
                            {--sin-mail : No envía correo con el resultado}
                            {--enviar-mail : Envía correo (default en schedule)}';

    protected $description = 'Ejecuta el proceso completo de cierre Waitry (recalcular %, facturas post-cierre y asientos) sin intervención';

    public function handle(GastronomiaCierreJornadaProcesoAutomaticoService $service): int
    {
        @ini_set('memory_limit', (string) config('gastronomia.cierre_jornada_proceso_memory_limit', '1024M'));
        @set_time_limit(0);

        $enviarMail = ! (bool) $this->option('sin-mail');
        if ((bool) $this->option('enviar-mail')) {
            $enviarMail = true;
        }

        $empresaOpt = trim((string) ($this->option('empresa') ?? ''));
        $fechaJornada = trim((string) ($this->option('fecha-jornada') ?? ''));
        $fechaJornada = $fechaJornada !== '' ? $fechaJornada : null;

        Log::info('gastronomia.cierre_jornada_automatico.inicio', [
            'empresa' => $empresaOpt !== '' ? (int) $empresaOpt : null,
            'fecha_jornada' => $fechaJornada,
            'enviar_mail' => $enviarMail,
        ]);

        try {
            if ($empresaOpt !== '') {
                $informe = [
                    'ejecutado_en' => now()->toIso8601String(),
                    'empresas' => [
                        $service->ejecutarEmpresa((int) $empresaOpt, $fechaJornada),
                    ],
                    'resumen' => ['procesadas' => 0, 'omitidas' => 0, 'errores' => 0],
                ];
                foreach ($informe['empresas'] as $r) {
                    $estado = (string) ($r['estado'] ?? '');
                    if ($estado === 'completado') {
                        $informe['resumen']['procesadas']++;
                    } elseif (in_array($estado, ['omitido', 'sin_pendiente'], true)) {
                        $informe['resumen']['omitidas']++;
                    } else {
                        $informe['resumen']['errores']++;
                    }
                }
                $informe['ok'] = $informe['resumen']['errores'] === 0;
                if ($enviarMail) {
                    $service->enviarMailInforme($informe);
                }
            } else {
                $informe = $service->ejecutarTodasEmpresas($enviarMail);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($informe['empresas'] ?? [] as $r) {
            $this->line(sprintf(
                'Empresa %d (%s) — %s — %s',
                $r['empresa_id'] ?? 0,
                $r['empresa_nombre'] ?? '?',
                $r['fecha_jornada'] ?? '—',
                strtoupper((string) ($r['estado'] ?? '')),
            ));
            if (! empty($r['error'])) {
                $this->error('  → '.$r['error']);
            } elseif (! empty($r['mensaje'])) {
                $this->comment('  → '.$r['mensaje']);
            }
        }

        $resumen = $informe['resumen'] ?? [];
        $this->newLine();
        $this->info(sprintf(
            'Resumen: %d procesada(s), %d omitida(s), %d error(es).',
            (int) ($resumen['procesadas'] ?? 0),
            (int) ($resumen['omitidas'] ?? 0),
            (int) ($resumen['errores'] ?? 0),
        ));

        return ($resumen['errores'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
