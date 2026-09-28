<?php

namespace App\Console\Commands\Compras;

use App\Support\Compras\PagoproveedorCuentacorrienteVinculoBackfillSupport;
use Illuminate\Console\Command;

/**
 * Completa el FK del crédito sintético de CC hacia la OP importada.
 * Default dry-run. No modifica totales ni asientos.
 */
class PagoproveedorCuentacorrienteVinculoBackfillCommand extends Command
{
    protected $signature = 'compras:vincular-pagoproveedor-cuentacorriente
                            {--ejecutar : Graba solo pagoproveedor_id; sin esto dry-run}';

    protected $description = 'Vincula créditos de CC importados a la OP (solo FK). No altera saldos ni asientos.';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN').' vínculo OP ↔ crédito de cuenta corriente');
        $this->comment('Solo escribe pagoproveedor_id en el crédito sintético y en la aplicación que nombra la OP.');

        $stats = PagoproveedorCuentacorrienteVinculoBackfillSupport::ejecutar(! $ejecutar);

        $this->table(['Métrica', 'Cantidad'], [
            ['Créditos con etiqueta OP', $stats['creditos_candidatos']],
            ['Créditos a vincular', $stats['a_vincular']],
            ['Aplicaciones a vincular', $stats['apps_a_vincular']],
            ['Créditos vinculados', $stats['vinculados_cc']],
            ['Aplicaciones vinculadas', $stats['vinculados_app']],
            ['Sin OP importada', $stats['sin_op']],
            ['Etiqueta ambigua', $stats['ambiguo_etiqueta']],
            ['Más de una OP con la misma clave', $stats['ambiguo_op']],
            ['OP que ya tenía movimiento de CC', $stats['omitidos_op_ya_tiene_cc']],
        ]);

        if ($stats['muestra'] !== []) {
            $this->line('Muestra (hasta 25):');
            $this->table(
                ['CC crédito', 'OP id', 'Aplicaciones'],
                array_map(static fn (array $fila) => [
                    $fila['cc_id'],
                    $fila['pago_id'],
                    $fila['apps'],
                ], $stats['muestra'])
            );
        }

        if (! $ejecutar) {
            $this->comment('Dry-run: no se grabó nada. Relanzá con --ejecutar para persistir.');
        }

        return self::SUCCESS;
    }
}
