<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sueldos\ConfidencialConfMigracionService;
use Illuminate\Console\Command;

/**
 * Migración de la nómina confidencial desde Anita /usr2/conf.
 *
 * Sin --confirmar: solo dry-run (no escribe).
 * Con --confirmar=HASH: graba solo si el plan coincide con el dry-run revisado.
 */
class SueldosMigrarConfidencialConfCommand extends Command
{
    protected $signature = 'sueldos:migrar-confidencial-conf
        {--confirmar= : Hash del plan revisado en el dry-run. Sin esta opción no se escribe nada}';

    protected $description = 'Migra la nómina confidencial desde Anita /usr2/conf (dry-run por defecto).';

    public function handle(ConfidencialConfMigracionService $servicio): int
    {
        $plan = $servicio->planificar();
        $this->mostrarResumen($plan['resumen']);
        $this->line('Hash del plan: '.$plan['hash']);

        $confirmar = (string) $this->option('confirmar');
        if ($confirmar === '') {
            $this->warn('DRY-RUN: no se escribió nada en el ERP ni en Anita.');

            return self::SUCCESS;
        }

        if (! hash_equals($plan['hash'], $confirmar)) {
            $this->error('El plan cambió desde el dry-run revisado. No se escribió nada. Vuelva a correr el dry-run.');

            return self::FAILURE;
        }

        $res = $servicio->ejecutar($plan['ops']);
        $this->info('Grabado: altas '.$res['altas'].' · actualizados '.$res['actualizados']
            .' · familiares '.$res['familiares'].' · ingresos '.$res['ingresos'].' · leyendas '.$res['leyendas']);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $e
     */
    private function mostrarResumen(array $e): void
    {
        $this->info('Legajos en conf: '.$e['conf_legajos'].' · por empresa: '.json_encode($e['por_empresa_conf']));
        $this->line('Altas nuevas: '.$e['altas'].' · por empresa: '.json_encode($e['altas_por_empresa']));
        $this->line('Marcar como confidencial: '.($e['altas'] + $e['marcar_confidencial_existentes'])
            .' (existentes sin marcar: '.$e['marcar_confidencial_existentes'].', ya confidenciales: '.$e['ya_confidenciales'].')');
        $this->line('CBU a actualizar (existentes): '.$e['cbu_actualizar']);
        $this->line('Banco a actualizar (existentes): '.$e['banco_actualizar']);
        $this->line('Código de banco sin nombre en el ERP: '.json_encode($e['banco_codigo_sin_nombre']));
        $this->line('CBU omitido por pertenecer a DNI distintos (legajos): '.($e['cbu_omitido_por_conflicto'] ?? 0));
        $this->line('CBU compartido por más de un legajo (mismo titular): '.count($e['cbu_compartido']));
        foreach ($e['cbu_compartido'] as $linea) {
            $this->line('  '.$linea);
        }
        $this->line('DNI distinto entre conf y ERP (no se modifica): '.count($e['documento_distinto']));
        foreach ($e['documento_distinto'] as $linea) {
            $this->line('  '.$linea);
        }
        $this->line('Familiares por tipo: '.json_encode($e['familiares_por_tipo']).' · sin tipo (omitidos): '.json_encode($e['familiares_sin_tipo']));
        $this->line('Ingresos a crear: '.$e['ingresos']);
        $this->line('Leyendas a crear: '.$e['leyendas_lineas'].' líneas en '.$e['leyendas_legajos'].' legajos');
        $this->line('Fuera de alcance: acumulados (acuempl, novacum) y vacaciones.');
    }
}
