<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Movimientos devueltos por la API Interbanking (v2), persistidos para consulta offline.
     */
    public function up(): void
    {
        Schema::create('interbanking_movimiento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->string('bank_number', 8)->default('');
            $table->string('account_number', 64)->default('');
            $table->string('account_type', 8)->default('CC');
            $table->string('currency', 3);
            $table->string('movement_type', 16);
            $table->dateTime('process_date')->nullable();
            $table->char('debit_credit_type', 1)->default('');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('operation_code_ib', 64)->nullable();
            $table->string('operation_code_bank', 64)->nullable();
            $table->string('code_description_ib', 255)->nullable();
            $table->string('code_description_bank', 512)->nullable();
            $table->string('customer_cuit', 16)->nullable();
            $table->string('depositor_code', 64)->nullable();
            $table->string('depositor_description', 255)->nullable();
            $table->unsignedBigInteger('voucher_number')->nullable();
            $table->string('account_cbu', 32)->nullable();
            $table->string('grouping_code_ib', 64)->nullable();
            $table->string('branch_office_activity', 255)->nullable();
            $table->string('dedupe_hash', 64);
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_interbanking_movimiento_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->unique('dedupe_hash', 'uq_interbanking_movimiento_dedupe');
            $table->index(['empresa_id', 'process_date'], 'idx_interbanking_movimiento_empresa_fecha');
            $table->index(['empresa_id', 'account_number', 'currency'], 'idx_interbanking_movimiento_cuenta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interbanking_movimiento');
    }
};
