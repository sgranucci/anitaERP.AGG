<?php

namespace App\Console\Commands;

use App\Support\Stock\RecepcionProveedorAnitaNumeracionSupport;
use Illuminate\Console\Command;

class RecepcionProveedorSincronizarNumeradorComAnitaCommand extends Command
{
    protected $signature = 'recepcion-proveedor:sincronizar-numerador-com-anita';

    protected $description = 'Alinea numerador ventas num_clave 120 (COM) con max(ERP, recepmae) para Anita desktop';

    public function handle(): int
    {
        $resultado = RecepcionProveedorAnitaNumeracionSupport::sincronizarNumeradorComGlobal();

        $this->info('Numerador COM Anita (t_comp COM → num_clave '.$resultado['num_clave'].')');
        $this->table(['Fuente', 'Valor'], [
            ['max numerorecepcion ERP', $resultado['max_erp']],
            ['max recm_nro recepmae', $resultado['max_recepmae']],
            ['num_ult_numero antes', $resultado['antes']],
            ['num_ult_numero después', $resultado['despues']],
            ['siguiente COM (Anita/ERP)', $resultado['despues'] + 1],
        ]);

        if ($resultado['despues'] > $resultado['antes']) {
            $this->info('Numerador actualizado en Anita.');
        } else {
            $this->comment('Numerador ya estaba al día; no se modificó Anita.');
        }

        return self::SUCCESS;
    }
}
