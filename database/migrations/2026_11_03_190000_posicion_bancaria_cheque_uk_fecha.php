<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un mismo cheque puede tener el crédito y la baja en fechas distintas.
 * Cada fecha es una pata del portfolio de la posición.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posicion_bancaria_cheque', function (Blueprint $table) {
            $table->dropUnique('uk_pos_banc_chq_emp_cc_nro');
            $table->unique(
                ['empresa_id', 'cuentacaja_id', 'numero_cheque', 'fecha_cheque'],
                'uk_pos_banc_chq_emp_cc_nro_fecha'
            );
        });
    }

    public function down(): void
    {
        Schema::table('posicion_bancaria_cheque', function (Blueprint $table) {
            $table->dropUnique('uk_pos_banc_chq_emp_cc_nro_fecha');
            $table->unique(
                ['empresa_id', 'cuentacaja_id', 'numero_cheque'],
                'uk_pos_banc_chq_emp_cc_nro'
            );
        });
    }
};
