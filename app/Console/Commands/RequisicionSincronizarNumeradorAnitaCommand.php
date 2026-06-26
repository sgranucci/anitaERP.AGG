<?php

namespace App\Console\Commands;

use App\Support\Compras\RequisicionAnitaNumeracionSupport;
use Illuminate\Console\Command;

class RequisicionSincronizarNumeradorAnitaCommand extends Command
{
    protected $signature = 'requisicion:sincronizar-numerador-anita';

    protected $description = 'Alinea numabm shared (código 21, a-reqmae.c) con max(ERP, reqmae) para Anita desktop';

    public function handle(): int
    {
        $resultado = RequisicionAnitaNumeracionSupport::sincronizarNumeradorGlobal();

        $this->info('Numerador requisición Anita (shared numabm código '.$resultado['numa_codigo'].')');
        $this->table(['Fuente', 'Valor'], [
            ['max numerorequisicion ERP', $resultado['max_erp']],
            ['max reqm_nro reqmae', $resultado['max_reqmae']],
            ['numa_ult_numero antes', $resultado['antes']],
            ['numa_ult_numero después', $resultado['despues']],
            ['siguiente requisición (Anita/ERP)', $resultado['despues'] + 1],
        ]);

        if ($resultado['despues'] > $resultado['antes']) {
            $this->info('Numerador actualizado en Anita.');
        } else {
            $this->comment('Numerador ya estaba al día; no se modificó Anita.');
        }

        return self::SUCCESS;
    }
}
