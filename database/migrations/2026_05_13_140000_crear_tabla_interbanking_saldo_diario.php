<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saldos diarios persistidos desde el endpoint de balances de Interbanking.
     * Un registro por cuenta (empresa + banco + nro cuenta + moneda) y fecha calendario.
     */
    public function up(): void
    {
        Schema::create('interbanking_saldo_diario', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->string('bank_number', 32)->default('');
            $table->string('account_number', 64)->default('');
            $table->string('currency', 3);
            $table->date('fecha');
            $table->decimal('total_debits', 18, 2)->default(0);
            $table->decimal('total_credits', 18, 2)->default(0);
            $table->decimal('day_balance', 18, 2)->default(0);
            $table->decimal('countable_balance', 18, 2)->nullable();
            $table->decimal('initial_operating_balance', 18, 2)->nullable();
            $table->decimal('current_operating_balance', 18, 2)->nullable();
            $table->decimal('projected_balance_24hs', 18, 2)->nullable();
            $table->decimal('projected_balance_48hs', 18, 2)->nullable();
            $table->string('account_name', 255)->nullable();
            $table->string('account_type', 64)->nullable();
            $table->string('account_label', 255)->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_interbanking_saldo_diario_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->unique(
                ['empresa_id', 'bank_number', 'account_number', 'currency', 'fecha'],
                'uq_interbanking_saldo_diario_cuenta_fecha'
            );
            $table->index(['empresa_id', 'fecha'], 'idx_interbanking_saldo_diario_empresa_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interbanking_saldo_diario');
    }
};
