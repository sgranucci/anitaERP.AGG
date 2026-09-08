<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posicion_bancaria_cheque', function (Blueprint $table) {
            $table->boolean('en_portfolio_posicion')->default(false)->after('activo')
                ->comment('true = cuenta para Disponible HOY de tesorería (planilla Posición)');
            $table->index(['en_portfolio_posicion', 'activo'], 'idx_pos_banc_chq_portfolio');
        });
    }

    public function down(): void
    {
        Schema::table('posicion_bancaria_cheque', function (Blueprint $table) {
            $table->dropIndex('idx_pos_banc_chq_portfolio');
            $table->dropColumn('en_portfolio_posicion');
        });
    }
};
