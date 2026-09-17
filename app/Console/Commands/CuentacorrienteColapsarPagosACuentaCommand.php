<?php

namespace App\Console\Commands;

use App\Support\Cuentacorriente\CuentacorrientePagoACuentaColapsarSupport;
use Illuminate\Console\Command;

class CuentacorrienteColapsarPagosACuentaCommand extends Command
{
    protected $signature = 'cuentacorriente:colapsar-pagos-a-cuenta
                            {--lado=ambos : cliente|proveedor|ambos}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste: CC.total = saldo y borra aplicaciones parciales}';

    protected $description = 'Facturas con pago a cuenta: deja el saldo en CC y elimina aplicaciones parciales (clientes y proveedores)';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }
        $dryRun = ! $ejecutar;
        $lado = strtolower(trim((string) $this->option('lado')));
        if (! in_array($lado, ['ambos', 'cliente', 'proveedor'], true)) {
            $this->error('lado inválido: use cliente|proveedor|ambos');

            return self::FAILURE;
        }

        $this->line($dryRun ? 'DRY-RUN' : 'EJECUTAR');

        if ($lado === 'ambos' || $lado === 'cliente') {
            $cli = CuentacorrientePagoACuentaColapsarSupport::colapsarClientes($dryRun);
            $this->table(['Clientes', 'Cantidad'], [
                ['CC parciales', $cli['candidatos']],
                ['Aplicaciones a borrar', $cli['aplicaciones']],
                ['CC actualizados', $cli['actualizados']],
                ['Aplicaciones borradas', $cli['aplicaciones_borradas']],
            ]);
            if ($cli['muestra'] !== []) {
                $this->line('Muestra clientes:');
                $this->table(
                    ['cc_id', 'total_actual', 'aplicado', 'saldo_nuevo', 'n_apl'],
                    array_map(static fn (array $r) => [
                        $r['cc_id'],
                        number_format($r['total_actual'], 2, ',', '.'),
                        number_format($r['aplicado'], 2, ',', '.'),
                        number_format($r['saldo_nuevo'], 2, ',', '.'),
                        $r['n_apl'],
                    ], $cli['muestra'])
                );
            }
        }

        if ($lado === 'ambos' || $lado === 'proveedor') {
            $prov = CuentacorrientePagoACuentaColapsarSupport::colapsarProveedores($dryRun);
            $this->table(['Proveedores', 'Cantidad'], [
                ['CC parciales', $prov['candidatos']],
                ['Aplicaciones a borrar', $prov['aplicaciones']],
                ['CC actualizados', $prov['actualizados']],
                ['Aplicaciones borradas', $prov['aplicaciones_borradas']],
            ]);
            if ($prov['muestra'] !== []) {
                $this->line('Muestra proveedores:');
                $this->table(
                    ['cc_id', 'total_actual', 'aplicado', 'saldo_nuevo', 'n_apl'],
                    array_map(static fn (array $r) => [
                        $r['cc_id'],
                        number_format($r['total_actual'], 2, ',', '.'),
                        number_format($r['aplicado'], 2, ',', '.'),
                        number_format($r['saldo_nuevo'], 2, ',', '.'),
                        $r['n_apl'],
                    ], $prov['muestra'])
                );
            }
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada. Para persistir: --ejecutar.');
        }

        return self::SUCCESS;
    }
}
