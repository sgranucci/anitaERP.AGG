<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\RendicionEstacionamientoMigrarRangoAnitaService;
use Illuminate\Console\Command;

class RendicionEstacionamientoMigrarRangoAnita extends Command
{
    protected $signature = 'rendicion-estacionamiento:migrar-rango-anita
                            {--fecha-desde=2026-07-01 : Fecha jornada inicial Y-m-d}
                            {--fecha-hasta=2026-07-31 : Fecha jornada final Y-m-d}
                            {--empresas= : IDs empresa separados por coma (default: todas)}
                            {--dry-run : Simula sin mover ERP ni Anita}';

    protected $description = 'Mueve rendiciones estacionamiento al rango nro_oper dedicado (piso 850000+); rendvalor filtrado por fecha';

    public function handle(RendicionEstacionamientoMigrarRangoAnitaService $service): int
    {
        $fechaDesde = trim((string) $this->option('fecha-desde'));
        $fechaHasta = trim((string) $this->option('fecha-hasta'));
        $dryRun = (bool) $this->option('dry-run');
        $empresasOpt = trim((string) $this->option('empresas'));
        $empresaIds = $empresasOpt === ''
            ? null
            : array_values(array_filter(array_map('intval', explode(',', $empresasOpt))));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Jornada %s → %s | piso %d%s',
            $fechaDesde,
            $fechaHasta,
            (int) config('rendicion_estacionamiento_anita.nro_oper_piso', 850000),
            $dryRun ? ' | SIMULACIÓN' : ' | APLICAR',
        ));

        try {
            $r = $service->ejecutar($fechaDesde, $fechaHasta, $dryRun, $empresaIds);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Piso', (string) ($r['piso'] ?? '')],
                ['Primer nro asignable', (string) ($r['primer_nro'] ?? '')],
                ['Total', (string) ($r['total'] ?? 0)],
                ['OK / simuladas', (string) ($r['ok'] ?? 0)],
                ['Ya en rango', (string) ($r['omitidas'] ?? 0)],
                ['Errores', (string) count($r['errores'] ?? [])],
            ],
        );

        $filas = [];
        foreach (array_slice($r['detalle'] ?? [], 0, 40) as $d) {
            $filas[] = [
                $d['rendicion_id'] ?? '',
                $d['empresa_id'] ?? '',
                $d['fecha_jornada'] ?? '',
                $d['nro_oper_anterior'] ?? '—',
                $d['nro_oper_nuevo'] ?? '—',
                $d['estado'] ?? '',
            ];
        }
        if ($filas !== []) {
            $this->table(['ID', 'Emp', 'Jornada', 'Nro ant.', 'Nro nuevo', 'Estado'], $filas);
            $rest = count($r['detalle'] ?? []) - count($filas);
            if ($rest > 0) {
                $this->comment("… y {$rest} más (ver log / detalle completo en retorno del service).");
            }
        }

        foreach ($r['errores'] ?? [] as $err) {
            $this->error($err);
        }

        if ($dryRun) {
            $this->comment('Simulación OK. Ejecute sin --dry-run para aplicar.');
        } else {
            $this->info('Migración finalizada.');
        }

        return ($r['errores'] ?? []) === [] ? self::SUCCESS : self::FAILURE;
    }
}
