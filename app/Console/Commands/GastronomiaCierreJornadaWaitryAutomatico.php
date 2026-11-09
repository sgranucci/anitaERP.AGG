<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaProcesoAutomaticoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GastronomiaCierreJornadaWaitryAutomatico extends Command
{
    protected $signature = 'gastronomia:cierre-jornada-waitry-automatico
                            {--empresa= : ID empresa (default: todas las habilitadas en config)}
                            {--fecha-jornada= : Y-m-d (default: jornada pendiente mas antigua por empresa)}
                            {--sin-mail : No envia correo OK; el URGENTE por fallo se manda igual}
                            {--enviar-mail : Envia correo (default en schedule)}';

    protected $description = 'Ejecuta el proceso completo de cierre Waitry (recalcular %, facturas post-cierre y asientos) sin intervencion';

    public function handle(GastronomiaCierreJornadaProcesoAutomaticoService $service): int
    {
        @ini_set('memory_limit', (string) config('gastronomia.cierre_jornada_proceso_memory_limit', '1024M'));
        @set_time_limit(0);

        if (EntornoEmpresaSupport::esFerli()) {
            $this->warn('El cierre de jornada Waitry está desconectado en Ferli.');
            Log::info('gastronomia.cierre_jornada_automatico.omitido_ferli');

            return self::SUCCESS;
        }

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
                $this->enviarMailSegunResultado($service, $informe, $enviarMail);
            } else {
                $informe = $service->ejecutarTodasEmpresas(false);
                $this->enviarMailSegunResultado($service, $informe, $enviarMail);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $informeFallo = [
                'ejecutado_en' => now()->toIso8601String(),
                'empresas' => [[
                    'empresa_id' => $empresaOpt !== '' ? (int) $empresaOpt : null,
                    'estado' => 'error',
                    'fecha_jornada' => $fechaJornada,
                    'error' => $e->getMessage(),
                ]],
                'resumen' => ['procesadas' => 0, 'omitidas' => 0, 'errores' => 1],
                'ok' => false,
            ];
            $service->enviarMailInforme($informeFallo, true);

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

    /**
     * OK: mail solo si --enviar-mail.
     * Error: mail URGENTE siempre (salvo que config lo apague), aunque vaya --sin-mail.
     *
     * @param  array<string, mixed>  $informe
     */
    private function enviarMailSegunResultado(
        GastronomiaCierreJornadaProcesoAutomaticoService $service,
        array $informe,
        bool $enviarMail,
    ): void {
        $hayError = \App\Support\Ventas\Gastronomia\CierreJornadaProcesoAutomaticoSupport::informeTieneErrores($informe);
        $urgenteSiempre = (bool) config('gastronomia.cierre_jornada_automatico.mail_urgente_siempre_en_error', true);

        if ($hayError && $urgenteSiempre) {
            $service->enviarMailInforme($informe, true);
            $this->warn('Mail URGENTE enviado por fallo del proceso.');

            return;
        }

        if ($enviarMail) {
            $service->enviarMailInforme($informe);
        }
    }
}
