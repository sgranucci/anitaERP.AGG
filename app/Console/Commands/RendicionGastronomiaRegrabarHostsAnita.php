<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\RendicionGastronomiaRegrabarHostsAnitaService;
use Illuminate\Console\Command;

class RendicionGastronomiaRegrabarHostsAnita extends Command
{
    protected $signature = 'rendicion-gastronomia:regrabar-hosts-anita
                            {--empresas=1,2 : IDs empresa separados por coma}
                            {--fecha-jornada-hasta=2026-06-30 : Fecha de jornada final Y-m-d}
                            {--dry-run : Simula sin borrar ni regrabar}
                            {--sin-post-cierre : No re-graba CIERRE-WAITRY}';

    protected $description = 'Borra rendgastro de hosts gastronomía por fecha de jornada y re-sincroniza desde ERP (rendg_estado=F)';

    public function handle(RendicionGastronomiaRegrabarHostsAnitaService $service): int
    {
        $empresas = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('empresas')))));
        $fechaHasta = trim((string) $this->option('fecha-jornada-hasta'));
        $dryRun = (bool) $this->option('dry-run');
        $regrabarPostCierre = ! (bool) $this->option('sin-post-cierre');

        if ($empresas === []) {
            $this->error('Indique al menos una empresa en --empresas');

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Empresas %s | fecha jornada hasta %s%s%s',
            implode(', ', $empresas),
            $fechaHasta,
            $dryRun ? ' | MODO SIMULACIÓN' : '',
            $regrabarPostCierre ? '' : ' | sin post-cierre',
        ));

        try {
            $informe = $service->ejecutar(
                $empresas,
                $fechaHasta,
                $dryRun,
                $regrabarPostCierre,
                fn (string $msg) => $this->info($msg),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($informe['empresas'] ?? [] as $empresaId => $bloque) {
            $this->newLine();
            $this->info('Empresa '.$empresaId.' | jornada '.$bloque['fecha_desde'].' → '.$bloque['fecha_hasta']);
            $this->line('Hosts salón: '.implode(', ', $bloque['hosts'] ?? []));

            $bor = $bloque['borrado'] ?? [];
            $this->table(
                ['Concepto', 'Valor'],
                [
                    ['Jornadas ERP en rango', (string) ($bor['jornadas'] ?? 0)],
                    ['Cabeceras eliminadas (rendg_fecha=jornada)', (string) ($bor['eliminadas'] ?? 0)],
                    ['Omitidas estacionamiento', (string) ($bor['omitidas_estacionamiento'] ?? 0)],
                    ['Omitidas CIERRE-WAITRY', (string) ($bor['omitidas_waitry'] ?? 0)],
                    ['Omitidas otro host', (string) ($bor['omitidas_host'] ?? 0)],
                ],
            );

            $res = $bloque['resync'] ?? [];
            $this->table(
                ['Concepto', 'Valor'],
                [
                    ['Rendiciones turno (por jornada)', (string) ($res['rendiciones'] ?? 0)],
                    ['Re-sincronizadas', (string) ($res['replicadas'] ?? 0)],
                    ['Jornadas con Z reaplicado', (string) count($res['jornadas'] ?? [])],
                    ['Errores', (string) count($res['errores'] ?? [])],
                ],
            );

            $aud = $bloque['auditoria'] ?? [];
            if (! $dryRun && ($aud['dias_total'] ?? 0) > 0) {
                $this->line(sprintf(
                    'Conciliación jornada: %d/%d días OK',
                    max(0, (int) ($aud['dias_total'] ?? 0) - (int) ($aud['dias_dif'] ?? 0)),
                    (int) ($aud['dias_total'] ?? 0),
                ));
                foreach ($aud['detalle_dif'] ?? [] as $dif) {
                    $this->warn(sprintf(
                        '  %s %s | ERP %s | Anita %s | rendg %s | Δ rendg %s',
                        $dif['fecha'] ?? '—',
                        $dif['estado'] ?? '',
                        isset($dif['erp']) ? number_format((float) $dif['erp'], 2, '.', '') : '—',
                        isset($dif['anita']) ? number_format((float) $dif['anita'], 2, '.', '') : '—',
                        isset($dif['rendg']) ? number_format((float) $dif['rendg'], 2, '.', '') : '—',
                        isset($dif['diff_erp_rendg']) ? number_format((float) $dif['diff_erp_rendg'], 2, '.', '') : '—',
                    ));
                }
            }

            if (($res['errores'] ?? []) !== []) {
                foreach ($res['errores'] as $err) {
                    $this->error('Rendición #'.($err['rendicion_id'] ?? '?').': '.($err['mensaje'] ?? ''));
                }

                return self::FAILURE;
            }
        }

        if ($dryRun) {
            $this->comment('Simulación lista. Ejecute sin --dry-run para aplicar.');
        } else {
            $this->info('Regrabado rendgastro por fecha de jornada completado.');
        }

        return self::SUCCESS;
    }
}
