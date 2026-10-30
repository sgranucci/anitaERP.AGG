<?php

namespace App\Console\Commands;

use App\Services\Caja\TesoreriaCrownAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarTesoreriaCrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:sincronizar-anita
                            {--dry-run : Cuenta altas y cambios sin grabar}';

    protected $description = 'Lee fichas, denominaciones, cajas, mesas, bóveda, tipos de transacción, bancas y cotizaciones de Cipolletti hacia la tesorería de esa sala.';

    public function handle(TesoreriaCrownAnitaSyncService $sync): int
    {
        try {
            $plan = $sync->armarPlan();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sala '.$plan['sala_codigo'].' (id '.$plan['sala_id'].').');
        $this->table(
            ['Origen', 'Leídas', 'Crear', 'Actualizar', 'Omitir'],
            [
                $this->fila('Fichas', $plan['fichas']),
                $this->fila('Denominaciones', $plan['denominaciones']),
                $this->fila('Cajas', $plan['cajas']),
                $this->fila('Mesas', $plan['mesas']),
                $this->fila('Tipos de transacción', $plan['transacciones']),
                $this->fila('Bancas y fichas', $plan['bancas']),
                $this->fila('Banca de cada mesa', $plan['mesas_banca']),
                $this->fila('Bóveda', $plan['boveda']),
                $this->fila('Cotización shared', $plan['cotizaciones']),
            ]
        );
        foreach (['fichas', 'denominaciones', 'cajas', 'mesas', 'transacciones', 'bancas', 'mesas_banca', 'boveda', 'cotizaciones'] as $grupo) {
            foreach ($plan[$grupo]['avisos'] as $aviso) {
                $this->warn($aviso);
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: no se grabó nada.');

            return self::SUCCESS;
        }

        $resultado = $sync->aplicar($plan);
        $this->info('Altas: '.$resultado['altas'].'. Cambios: '.$resultado['cambios'].'.');

        return self::SUCCESS;
    }

    /**
     * @param  array{leer: int, crear: int, actualizar: int, omitir: int}  $grupo
     * @return list<int|string>
     */
    private function fila(string $origen, array $grupo): array
    {
        return [$origen, $grupo['leer'], $grupo['crear'], $grupo['actualizar'], $grupo['omitir']];
    }
}
