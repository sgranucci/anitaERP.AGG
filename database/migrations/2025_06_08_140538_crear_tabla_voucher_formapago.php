<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaVoucherFormapago extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_formapago', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('voucher_id');
            $table->foreign('voucher_id', 'fk_voucher_formapago_voucher')->references('id')->on('voucher')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->foreign('cuentacaja_id', 'fk_voucher_formapago_cuentacaja')->references('id')->on('cuentacaja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_voucher_formapago_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict'); 
            $table->decimal('monto',22,4);
            $table->decimal('cotizacion',22,4);
            $table->softDeletes();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_formapago');
    }
}
