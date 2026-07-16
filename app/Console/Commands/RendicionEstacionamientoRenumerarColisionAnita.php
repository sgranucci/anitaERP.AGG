<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\RendicionEstacionamientoRenumerarColisionAnitaService;
use Illuminate\Console\Command;

class RendicionEstacionamientoRenumerarColisionAnita extends Command
{
    protected $signature = 'rendicion-estacionamiento:renumerar-colision-anita
                            {nro_oper : nro_oper Anita colisionado (ej. 644640)}
                            {--dry-run : Simula sin mover ERP ni Anita}';

    protected $description = 'Mueve una rendición estacionamiento fuera de un nro_oper compartido con rendmaquina/rendbingo';

    public function handle(RendicionEstacionamientoRenumerarColisionAnitaService $service): int
    {
        $nro = (int) $this->argument('nro_oper');
        $dryRun = (bool) $this->option('dry-run');

        if ($nro <= 0) {
            $this->error('Indique un nro_oper válido.');

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(($dryRun ? 'SIMULACIÓN' : 'APLICAR').' nro_oper '.$nro);

        try {
            $r = $service->ejecutarPorNroOper($nro, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Campo', 'Valor'],
            [
                ['Rendición ERP', (string) ($r['rendicion_id'] ?? '')],
                ['Empresa', (string) ($r['empresa_id'] ?? '')],
                ['Nro anterior', (string) ($r['nro_oper_anterior'] ?? '')],
                ['Nro nuevo', (string) ($r['nro_oper_nuevo'] ?? '')],
                ['Fecha jornada', (string) ($r['fecha_jornada'] ?? '')],
                ['Colisión máquina', ! empty($r['colision_maquina']) ? 'sí' : 'no'],
                ['Colisión bingo', ! empty($r['colision_bingo']) ? 'sí' : 'no'],
                ['Valores estac movidos', (string) ($r['valores_estac'] ?? 0)],
                ['Valores otras fechas', (string) ($r['valores_otras_fechas_conservados'] ?? 0)],
                ['Estado', (string) ($r['estado'] ?? '')],
            ],
        );

        if ($dryRun) {
            $this->comment('Simulación OK. Ejecute sin --dry-run para aplicar.');
        } else {
            $this->info('Renumeración aplicada.');
        }

        return self::SUCCESS;
    }
}
