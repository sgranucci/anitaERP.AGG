<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\RendicionGastronomiaRenumerarResincronizarAnitaService;
use Illuminate\Console\Command;

class RendicionGastronomiaRenumerarResincronizarAnita extends Command
{
    protected $signature = 'rendicion-gastronomia:renumerar-resincronizar-anita
                            {--empresa= : empresa_id (2=Kandiko, 3=Rebisco)}
                            {--fecha-desde= : Fecha jornada inicial Y-m-d}
                            {--dry-run : Simula sin borrar ni regrabar}';

    protected $description = 'Borra rendgastro legacy, renumerar desde piso por empresa y re-sincroniza rendiciones de turno';

    public function handle(RendicionGastronomiaRenumerarResincronizarAnitaService $service): int
    {
        $empresaId = (int) $this->option('empresa');
        $fechaDesde = trim((string) $this->option('fecha-desde'));
        $dryRun = (bool) $this->option('dry-run');

        if ($empresaId <= 0 || $fechaDesde === '') {
            $this->error('Indique --empresa=ID y --fecha-desde=Y-m-d');

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Empresa %d | jornada desde %s%s',
            $empresaId,
            $fechaDesde,
            $dryRun ? ' | MODO SIMULACIÓN' : '',
        ));

        try {
            $resultado = $service->ejecutar($empresaId, $fechaDesde, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Resumen');
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Piso nro_oper', (string) ($resultado['piso'] ?? '—')],
                ['Rendiciones turno', (string) ($resultado['rendiciones'] ?? 0)],
                ['Jornadas afectadas', (string) ($resultado['jornadas'] ?? 0)],
                ['Errores', (string) count($resultado['errores'] ?? [])],
            ],
        );

        $filas = [];
        foreach ($resultado['detalle'] ?? [] as $fila) {
            $filas[] = [
                $fila['rendicion_id'] ?? '',
                $fila['pv'] ?? '',
                $fila['jornada'] ?? '',
                $fila['nro_oper_anterior'] ?? '—',
                $fila['nro_oper_nuevo'] ?? '—',
                $fila['estado'] ?? '',
            ];
        }

        if ($filas !== []) {
            $this->table(
                ['Rendición', 'PV', 'Jornada', 'Nro ant.', 'Nro nuevo', 'Estado'],
                $filas,
            );
        }

        if ($dryRun) {
            $this->comment('Simulación lista. Ejecute sin --dry-run para aplicar.');
        } elseif (($resultado['errores'] ?? []) !== []) {
            foreach ($resultado['errores'] as $err) {
                $this->error('Rendición #'.($err['rendicion_id'] ?? '?').': '.($err['mensaje'] ?? ''));
            }

            return self::FAILURE;
        } else {
            $this->info('Renumeración y re-sincronización completadas.');
        }

        return self::SUCCESS;
    }
}
