<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaVoucherReserva extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_reserva', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('voucher_id');
            $table->foreign('voucher_id', 'fk_voucher_reserva_voucher')->references('id')->on('voucher')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('reserva_id');
            $table->unsignedBigInteger('pasajero_id');
            $table->string('nombrepasajero',255);
            $table->date('fechaarribo');
            $table->date('fechapartida');
            $table->integer('pax');
            $table->integer('limitepax');
            $table->integer('paxfree');
            $table->integer('limitefree');
            $table->integer('incluido');
            $table->integer('opcional');
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
        Schema::dropIfExists('voucher_reserva');
    }
}
