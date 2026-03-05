<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaRendicionreceptivoVoucher extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rendicionreceptivo_voucher', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rendicionreceptivo_id');
            $table->foreign('rendicionreceptivo_id', 'fk_rendicionreceptivo_voucher_rendicionreceptivo')->references('id')->on('rendicionreceptivo')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('voucher_id');
            $table->foreign('voucher_id', 'fk_rendicionreceptivo_voucher_voucher')->references('id')->on('voucher')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('rendicionreceptivo_voucher');
    }
}
