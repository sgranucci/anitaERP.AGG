<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\RendicionRepararFechaAlfaAnitaService;
use Illuminate\Console\Command;

class RendicionRepararFechaAlfaAnita extends Command
{
    protected $signature = 'rendicion:reparar-fecha-alfa-anita
                            {--desde=2026-06-01 : Fecha mínima de jornada (rendg_fecha) Y-m-d}
                            {--empresa= : Filtrar por rendg_empresa (opcional)}
                            {--dry-run : Lista cambios sin escribir en Informix}';

    protected $description = 'Corrige rendg_fecha_alfa en Informix (Ymd → DD/MM/YY) para rendiciones desde la fecha indicada';

    public function handle(RendicionRepararFechaAlfaAnitaService $service): int
    {
        $desde = trim((string) $this->option('desde'));
        if ($desde === '') {
            $this->error('Indique --desde=Y-m-d.');

            return self::FAILURE;
        }

        $empresaOpt = $this->option('empresa');
        $empresaId = is_string($empresaOpt) && trim($empresaOpt) !== '' ? (int) $empresaOpt : null;
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'rendg_fecha >= %s%s%s',
            $desde,
            $empresaId !== null ? ' | empresa '.$empresaId : '',
            $dryRun ? ' | MODO SIMULACIÓN' : '',
        ));

        try {
            $resultados = $service->reparar($desde, $empresaId, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($resultados === []) {
            $this->info('No hay rendiciones con rendg_fecha_alfa en formato Ymd desde '.$desde.'.');

            return self::SUCCESS;
        }

        $this->table(
            ['nro_oper', 'tipo', 'empresa', 'fecha', 'suc', 'turno', 'alfa actual', 'alfa nueva', 'estado'],
            array_map(fn (array $r) => [
                $r['nro_oper'],
                $r['tipo_oper'],
                $r['empresa'],
                $r['fecha'],
                $r['sucursal'],
                $r['turno'],
                $r['alfa_actual'] !== '' ? $r['alfa_actual'] : '—',
                $r['alfa_nueva'],
                $r['estado'].($r['motivo'] !== '' ? ' ('.$r['motivo'].')' : ''),
            ], $resultados),
        );

        $actualizados = count(array_filter($resultados, fn (array $r) => $r['estado'] === 'actualizado'));
        $simulados = count(array_filter($resultados, fn (array $r) => $r['estado'] === 'simulado'));

        if ($dryRun) {
            $this->info($simulados.' registro(s) a actualizar. Ejecute sin --dry-run para aplicar.');
        } else {
            $this->info($actualizados.' registro(s) actualizados en Informix.');
        }

        return self::SUCCESS;
    }
}
