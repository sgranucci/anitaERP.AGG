<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Ventas\JornadaGastronomia;
use App\Services\Caja\RendicionGastronomiaRepararJornadaAnitaService;
use Illuminate\Console\Command;

class RendicionGastronomiaRepararJornadaAnita extends Command
{
    protected $signature = 'rendicion-gastronomia:reparar-jornada-anita
                            {--jornada= : ID jornada_gastronomia (si no, se busca por --fecha)}
                            {--fecha=2026-06-01 : Fecha de jornada Y-m-d}
                            {--empresa=1 : empresa_id}
                            {--pc= : IP de la PC opcional (ej. 192.168.20.152)}
                            {--puntoventa= : Alias legacy de --pc}
                            {--dry-run : Simula sin escribir en Anita}';

    protected $description = 'Repara rendg_total_z y rendg_tot_nc en Anita por PC (rendg_host): Z = CAE+CAEA del día';

    public function handle(RendicionGastronomiaRepararJornadaAnitaService $service): int
    {
        $jornada = $this->resolverJornada();
        if ($jornada === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $pcFiltro = $this->option('pc') ?? $this->option('puntoventa');
        $pcFiltro = is_string($pcFiltro) && trim($pcFiltro) !== '' ? trim($pcFiltro) : null;

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Jornada #%d | empresa %d | fecha jornada %s%s',
            $jornada->id,
            $jornada->empresa_id,
            $jornada->fecha_jornada?->format('Y-m-d') ?? '—',
            $dryRun ? ' | MODO SIMULACIÓN' : '',
        ));

        try {
            $resultados = $service->reparar($jornada, $pcFiltro, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($resultados === []) {
            $this->warn('No hay PCs con rendiciones de turno en esta jornada.');

            return self::SUCCESS;
        }

        $filasResumen = [];
        foreach ($resultados as $r) {
            $filasResumen[] = [
                $r['puntoventa'],
                $r['estado'],
                $r['cabeceras'],
                $r['portadora_turno'] ?? '—',
                $r['portadora_nro_oper'] ?? '—',
                $r['portadora_hora'] ?? '—',
                $r['total_z'] !== null ? number_format((float) $r['total_z'], 2, '.', '') : '—',
                $r['tot_nc'] !== null ? number_format((float) $r['tot_nc'], 2, '.', '') : '—',
            ];

            if (! empty($r['detalle'])) {
                $this->newLine();
                $this->comment('PC '.$r['identificador_pc'].' — portadora turno '.($r['portadora_turno'] ?? '—'));
                if (($r['identificador_pc'] ?? '') === 'LEGACY-HUERFANAS') {
                    $this->table(
                        ['nro_oper', 'host', 'suc', 'Z legacy', 'fc_caea'],
                        array_map(fn (array $d) => [
                            $d['nro_oper'] ?? '—',
                            $d['host'] ?? '—',
                            $d['suc'] ?? '—',
                            number_format((float) ($d['z'] ?? 0), 2, '.', ''),
                            number_format((float) ($d['fc_caea'] ?? 0), 2, '.', ''),
                        ], $r['detalle']),
                    );
                } elseif (($r['identificador_pc'] ?? '') === 'NORMALIZAR-CAEA') {
                    $this->table(
                        ['nro_oper', 'host', 'Z antes / fc_caea antes', 'valor'],
                        array_map(function (array $d): array {
                            if (isset($d['z_antes'])) {
                                return [
                                    $d['nro_oper'] ?? '—',
                                    $d['host'] ?? '—',
                                    'Z→0',
                                    number_format((float) ($d['z_antes'] ?? 0), 2, '.', ''),
                                ];
                            }

                            return [
                                $d['nro_oper'] ?? '—',
                                $d['host'] ?? '—',
                                'fc_caea→0',
                                number_format((float) ($d['fc_caea_antes'] ?? 0), 2, '.', ''),
                            ];
                        }, $r['detalle']),
                    );
                } else {
                    $this->table(
                        ['nro_oper', 'turno', 'hora', 'turno ERP', 'Z', 'NC', 'portadora'],
                        array_map(fn (array $d) => [
                            $d['nro_oper'],
                            $d['turno'],
                            $d['hora'],
                            $d['turno_erp'],
                            number_format((float) $d['z'], 2, '.', ''),
                            number_format((float) $d['tot_nc'], 2, '.', ''),
                            ! empty($d['portadora']) ? 'sí' : 'no',
                        ], $r['detalle']),
                    );
                }
            }
        }

        $this->newLine();
        $this->table(
            ['PC', 'Estado', 'Cabeceras', 'Turno Z', 'Portadora nro_oper', 'Hora', 'Z día (CAE+CAEA)', 'NC día'],
            $filasResumen,
        );

        if ($dryRun) {
            $this->info('Simulación lista. Ejecute sin --dry-run para aplicar en Anita.');
        } else {
            $this->info('Reparación aplicada en Anita.');
        }

        return self::SUCCESS;
    }

    private function resolverJornada(): ?JornadaGastronomia
    {
        $jornadaId = (int) $this->option('jornada');
        if ($jornadaId > 0) {
            $jornada = JornadaGastronomia::query()->find($jornadaId);
            if ($jornada === null) {
                $this->error('No existe jornada #'.$jornadaId.'.');

                return null;
            }

            return $jornada;
        }

        $empresaId = (int) $this->option('empresa');
        $fecha = trim((string) $this->option('fecha'));
        if ($fecha === '') {
            $this->error('Indique --jornada=ID o --fecha=Y-m-d.');

            return null;
        }

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fecha)
            ->orderByDesc('id')
            ->first();

        if ($jornada === null) {
            $this->error('No hay jornada para empresa '.$empresaId.' y fecha '.$fecha.'.');

            return null;
        }

        $this->comment('Jornada #'.$jornada->id.' (fecha '.$fecha.').');

        return $jornada;
    }
}
