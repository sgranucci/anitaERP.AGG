<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posicion_bancaria_cheque', function (Blueprint $table) {
            $table->dropUnique('uk_pos_banc_chq_emp_banco_nro');
            $table->unique(
                ['empresa_id', 'cuentacaja_id', 'numero_cheque'],
                'uk_pos_banc_chq_emp_cc_nro'
            );
        });
    }

    public function down(): void
    {
        Schema::table('posicion_bancaria_cheque', function (Blueprint $table) {
            $table->dropUnique('uk_pos_banc_chq_emp_cc_nro');
            $table->unique(
                ['empresa_id', 'banco_canonico', 'numero_cheque'],
                'uk_pos_banc_chq_emp_banco_nro'
            );
        });
    }
};
