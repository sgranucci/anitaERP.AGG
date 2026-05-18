<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprobantes de transferencias devueltos por la API Interbanking (v1 transfers/vouchers).
     */
    public function up(): void
    {
        Schema::create('interbanking_transferencia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->string('debit_bank_number', 8)->nullable();
            $table->string('debit_account_number', 64)->nullable();
            $table->string('debit_account_type', 8)->nullable();
            $table->string('debit_currency', 3)->nullable();
            $table->dateTime('request_date')->nullable();
            $table->string('transfer_type_description', 512)->nullable();
            $table->string('transfer_type_code', 64)->nullable();
            $table->bigInteger('transfer_id')->nullable();
            $table->unsignedSmallInteger('network_number')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->string('debit_account', 64)->nullable();
            $table->string('credit_account', 64)->nullable();
            $table->string('validation_code', 64)->nullable();
            $table->json('afip_json')->nullable();
            $table->string('dedupe_hash', 64);
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_interbanking_transferencia_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->unique('dedupe_hash', 'uq_interbanking_transferencia_dedupe');
            $table->index(['empresa_id', 'request_date'], 'idx_interbanking_transferencia_empresa_fecha');
            $table->index(['empresa_id', 'debit_account_number', 'debit_currency'], 'idx_interbanking_transferencia_cuenta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interbanking_transferencia');
    }
};
