<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Services\Caja\RendicionEstacionamientoLimpiarRendgastroAnitaService;
use Illuminate\Console\Command;

class RendicionEstacionamientoLimpiarRendgastroAnita extends Command
{
    protected $signature = 'rendicion-estacionamiento:limpiar-rendgastro-anita
                            {--jornada= : ID jornada_estacionamiento}
                            {--fecha= : Fecha de jornada Y-m-d (si no hay --jornada)}
                            {--empresa=1 : empresa_id}
                            {--dry-run : Simula sin escribir en Anita}';

    protected $description = 'Limpia Z/NC en rendgastro solo para PV estacionamiento de una jornada (totales ERP, ignora gastronomía/Waitry)';

    public function handle(RendicionEstacionamientoLimpiarRendgastroAnitaService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $jornada = $this->resolverJornada();

        if ($jornada === null) {
            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Jornada #%d | empresa %d | fecha %s%s',
            $jornada->id,
            $jornada->empresa_id,
            $jornada->fecha_jornada?->format('Y-m-d') ?? '—',
            $dryRun ? ' | MODO SIMULACIÓN' : '',
        ));

        try {
            $resultados = $service->limpiarJornada($jornada, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($resultados === []) {
            $this->warn('No hay rendiciones de turno en esta jornada.');

            return self::SUCCESS;
        }

        $filasResumen = [];
        foreach ($resultados as $r) {
            $filasResumen[] = [
                $r['puntoventa'],
                $r['estado'],
                $r['cabeceras_estacionamiento'],
                $r['cabeceras_ignoradas'] ?? 0,
                $r['portadora_nro_oper'] ?? '—',
                $r['erp_z'] !== null ? number_format((float) $r['erp_z'], 2, '.', '') : '—',
                $r['erp_nc'] !== null ? number_format((float) $r['erp_nc'], 2, '.', '') : '—',
            ];

            if (! empty($r['detalle'])) {
                $this->newLine();
                $this->comment('PV '.$r['puntoventa'].' — cabeceras estacionamiento');
                $this->table(
                    ['nro_oper', 'turno', 'hora', 'host', 'Z', 'NC', 'portadora'],
                    array_map(fn (array $d) => [
                        $d['nro_oper'],
                        $d['turno'],
                        $d['hora'],
                        $d['host'],
                        number_format((float) $d['z'], 2, '.', ''),
                        number_format((float) $d['tot_nc'], 2, '.', ''),
                        ! empty($d['portadora']) ? 'sí' : 'no',
                    ], $r['detalle']),
                );
            }
        }

        $this->newLine();
        $this->table(
            ['PV', 'Estado', 'Cab. estac.', 'Ignoradas', 'Portadora', 'Z ERP', 'NC ERP'],
            $filasResumen,
        );

        if ($dryRun) {
            $this->info('Simulación lista. Ejecute sin --dry-run para aplicar.');
        } else {
            $this->info('Limpieza aplicada en Anita.');
        }

        return self::SUCCESS;
    }

    private function resolverJornada(): ?JornadaEstacionamiento
    {
        $jornadaId = (int) $this->option('jornada');
        if ($jornadaId > 0) {
            $jornada = JornadaEstacionamiento::query()->find($jornadaId);
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

        $jornada = JornadaEstacionamiento::query()
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
