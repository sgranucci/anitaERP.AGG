<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lotes de envío on-line de transferencias Interbanking (Confección a CBU).
 * Anti-duplicado por dedupe_key en líneas con estado ok.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('interbanking_envio')) {
            Schema::create('interbanking_envio', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('cuentacaja_id');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('fuente', 32);
                $table->string('debit_account_number', 64)->nullable();
                $table->string('debit_bank_number', 8)->nullable();
                $table->string('debit_account_type', 8)->nullable();
                $table->string('debit_currency', 3)->nullable();
                $table->unsignedInteger('cantidad_total')->default(0);
                $table->unsignedInteger('cantidad_ok')->default(0);
                $table->unsignedInteger('cantidad_error')->default(0);
                $table->decimal('total_importe', 18, 2)->default(0);
                $table->string('estado', 20)->default('parcial');
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_ib_envio_empresa')
                    ->references('id')->on('empresa')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('cuentacaja_id', 'fk_ib_envio_cuentacaja')
                    ->references('id')->on('cuentacaja')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->index(['empresa_id', 'created_at'], 'idx_ib_envio_empresa_fecha');
            });
        }

        if (! Schema::hasTable('interbanking_envio_linea')) {
            Schema::create('interbanking_envio_linea', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('interbanking_envio_id');
                $table->string('origen_tipo', 32);
                $table->unsignedBigInteger('origen_id')->nullable();
                $table->string('origen_clave', 128)->nullable();
                $table->string('dedupe_key', 160)->nullable();
                $table->string('beneficiario', 255)->nullable();
                $table->string('credit_cbu', 22);
                $table->decimal('amount', 18, 2);
                $table->string('concept', 255)->nullable();
                $table->string('referencia', 255)->nullable();
                $table->string('estado', 20)->default('pendiente');
                $table->unsignedSmallInteger('http_code')->nullable();
                $table->json('api_response')->nullable();
                $table->text('error_mensaje')->nullable();
                $table->timestamps();

                $table->foreign('interbanking_envio_id', 'fk_ib_envio_linea_envio')
                    ->references('id')->on('interbanking_envio')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->unique('dedupe_key', 'uq_ib_envio_linea_dedupe');
                $table->index(['origen_tipo', 'origen_id'], 'idx_ib_envio_linea_origen');
                $table->index(['origen_tipo', 'origen_clave'], 'idx_ib_envio_linea_clave');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interbanking_envio_linea');
        Schema::dropIfExists('interbanking_envio');
    }
};
