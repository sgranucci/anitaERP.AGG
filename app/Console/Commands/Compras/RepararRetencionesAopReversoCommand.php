<?php

declare(strict_types=1);

namespace App\Console\Commands\Compras;

use App\Support\Compras\PagoproveedorAopReversoRetencionRepairSupport;
use Illuminate\Console\Command;

/**
 * Completa retenciones negativas en AOP de reversión que quedaron sin filas en ERP
 * (antes solo se escribía el AOP en Anita/retmov).
 */
final class RepararRetencionesAopReversoCommand extends Command
{
    protected $signature = 'compras:reparar-retenciones-aop-reverso
                            {--desde='.PagoproveedorAopReversoRetencionRepairSupport::DESDE_DEFAULT.' : Fecha desde (Y-m-d)}
                            {--empresa= : Filtrar empresa_id}
                            {--dry-run : Solo lista AOPs a reparar}';

    protected $description = 'Clona retenciones de la OPP en el AOP compensatorio cuando el AOP no tiene filas';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $empresaId = (int) ($this->option('empresa') ?: 0);
        $desde = trim((string) $this->option('desde'));
        if ($desde === '') {
            $desde = PagoproveedorAopReversoRetencionRepairSupport::DESDE_DEFAULT;
        }

        $resultado = PagoproveedorAopReversoRetencionRepairSupport::reparar(
            $desde,
            $empresaId > 0 ? $empresaId : null,
            $dry,
        );

        if ($resultado['aops'] === 0) {
            $this->info('No hay AOP sin retenciones para reparar desde '.$desde.'.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'AOP a reparar desde %s: %d (empresas con movimiento)',
            $desde,
            $resultado['aops'],
        ));
        foreach ($resultado['detalle'] as $item) {
            $this->line(sprintf(
                'emp=%d AOP id=%d nro=%s origen_opp=%s retenciones=%d',
                $item['empresa_id'],
                $item['aop_id'],
                $item['numero'],
                $item['origen_numero'],
                $item['retenciones'],
            ));
        }

        if ($dry) {
            $this->warn('Dry-run: no se grabó nada.');
        } else {
            $this->info('Retenciones negativas creadas: '.$resultado['retenciones']);
        }

        return self::SUCCESS;
    }
}
