<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaVoucherGuia extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher_guia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('voucher_id');
            $table->foreign('voucher_id', 'fk_voucher_guia_voucher')->references('id')->on('voucher')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('guia_id');
            $table->foreign('guia_id', 'fk_voucher_guia_guia')->references('id')->on('guia')->onDelete('restrict')->onUpdate('restrict');
            $table->string('tipocomision', 2);
            $table->float('porcentajecomision',5,2);
            $table->decimal('montocomision',22,4); 
            $table->unsignedBigInteger('ordenservicio_id');
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
        Schema::dropIfExists('voucher_guia');
    }
}
