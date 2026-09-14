<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: el mapper Anita↔ERP estaba invertido (1=título, 2=imputable).
 * Tras corregir el mapper, se intercambian tipocuenta 1↔2 para alinear con AGG
 * (1=imputable, 2=título, 3=totalizadora).
 *
 * tipocuenta es varchar(1): un solo UPDATE con CASE (sin valor temporal largo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('cuentacontable')) {
            return;
        }

        DB::update(
            "UPDATE cuentacontable
             SET tipocuenta = CASE tipocuenta
                WHEN '1' THEN '2'
                WHEN '2' THEN '1'
                ELSE tipocuenta
             END
             WHERE tipocuenta IN ('1', '2')"
        );
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('cuentacontable')) {
            return;
        }

        DB::update(
            "UPDATE cuentacontable
             SET tipocuenta = CASE tipocuenta
                WHEN '1' THEN '2'
                WHEN '2' THEN '1'
                ELSE tipocuenta
             END
             WHERE tipocuenta IN ('1', '2')"
        );
    }
};
