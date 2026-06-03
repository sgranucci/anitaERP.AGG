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
                            {--puntoventa= : Código PV CAE opcional (ej. 00004)}
                            {--dry-run : Simula sin escribir en Anita}';

    protected $description = 'Repara rendg_total_z y rendg_tot_nc en Anita por fecha de jornada, empresa y PV (portadora: turno N, si no T, si no M)';

    public function handle(RendicionGastronomiaRepararJornadaAnitaService $service): int
    {
        $jornada = $this->resolverJornada();
        if ($jornada === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $pvFiltro = $this->option('puntoventa');
        $pvFiltro = is_string($pvFiltro) && trim($pvFiltro) !== '' ? trim($pvFiltro) : null;

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Jornada #%d | empresa %d | fecha jornada %s%s',
            $jornada->id,
            $jornada->empresa_id,
            $jornada->fecha_jornada?->format('Y-m-d') ?? '—',
            $dryRun ? ' | MODO SIMULACIÓN' : '',
        ));

        try {
            $resultados = $service->reparar($jornada, $pvFiltro, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($resultados === []) {
            $this->warn('No hay puntos de venta con rendiciones de turno en esta jornada.');

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
                $this->comment('PV '.$r['puntoventa'].' (sucursal '.$r['sucursal'].') — portadora turno '.($r['portadora_turno'] ?? '—'));
                $this->table(
                    ['nro_oper', 'turno', 'hora', 'turno ERP', 'Z', 'NC', 'portadora'],
                    array_map(fn (array $d) => [
                        $d['nro_oper'],
                        $d['turno'],
                        $d['hora'],
                        $d['turno_erp'],
                        number_format((float) $d['z'], 2, '.', ''),
                        number_format((float) $d['tot_nc'], 2, '.', ''),
                        $d['portadora'] ? 'sí' : 'no',
                    ], $r['detalle']),
                );
            }
        }

        $this->newLine();
        $this->table(
            ['PV', 'Estado', 'Cabeceras', 'Turno Z', 'Portadora nro_oper', 'Hora', 'Z día', 'NC día'],
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
