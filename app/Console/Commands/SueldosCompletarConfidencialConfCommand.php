<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sueldos\ConfidencialConfCompletarService;
use Illuminate\Console\Command;

/**
 * Completa campos vacíos de legajos confidenciales desde conf (/usr2/conf).
 *
 * Sin --confirmar: solo dry-run (no escribe).
 * Con --confirmar=HASH: graba solo si el plan coincide con el dry-run revisado.
 */
class SueldosCompletarConfidencialConfCommand extends Command
{
    protected $signature = 'sueldos:completar-confidencial-conf
        {--confirmar= : Hash del plan revisado en el dry-run. Sin esta opción no se escribe nada}';

    protected $description = 'Completa campos vacíos de la nómina confidencial desde Anita /usr2/conf (dry-run por defecto).';

    public function handle(ConfidencialConfCompletarService $servicio): int
    {
        $plan = $servicio->planificar();
        $r = $plan['resumen'];

        $this->info('Filas conf: '.$r['filas_conf'].' · sin fila en ERP: '.$r['sin_fila_erp']
            .' · legajos con cambios: '.$r['legajos_con_cambios']);

        $this->line('Documentos a corregir: '.count($r['documento_corregido']));
        foreach ($r['documento_corregido'] as $linea) {
            $this->line('  '.$linea);
        }

        $this->line('Campos a completar (cantidad de legajos):');
        foreach ($r['completados'] as $campo => $n) {
            $this->line('  '.str_pad($campo, 26).$n);
        }

        if ($r['sin_mapeo'] !== []) {
            $this->warn('Valores de conf sin equivalente en el ERP (no se cargan):');
            foreach ($r['sin_mapeo'] as $campo => $n) {
                $this->line('  '.str_pad($campo, 26).$n);
            }
        }

        $this->line('Hash del plan: '.$plan['hash']);

        $confirmar = (string) $this->option('confirmar');
        if ($confirmar === '') {
            $this->warn('DRY-RUN: no se escribió nada en el ERP.');

            return self::SUCCESS;
        }

        if (! hash_equals($plan['hash'], $confirmar)) {
            $this->error('El plan cambió desde el dry-run revisado. No se escribió nada. Vuelva a correr el dry-run.');

            return self::FAILURE;
        }

        $n = $servicio->ejecutar($plan['updates']);
        $this->info('Grabado: '.$n.' legajos actualizados.');

        return self::SUCCESS;
    }
}
