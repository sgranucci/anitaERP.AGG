<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaVoucher extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('voucher', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('talonariovoucher_id');
            $table->foreign('talonariovoucher_id', 'fk_voucher_talonariovoucher')->references('id')->on('talonariovoucher')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('numero');
            $table->date('fecha');
            $table->integer('pax');
            $table->integer('paxfree');
            $table->integer('incluido');
            $table->integer('opcional');
            $table->unsignedBigInteger('servicioterrestre_id');
            $table->foreign('servicioterrestre_id', 'fk_voucher_servicioterrestre')->references('id')->on('servicioterrestre')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_voucher_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('montovoucher',22,4);
            $table->decimal('montoempresa',22,4); 
            $table->decimal('montoproveedor',22,4); 
            $table->string('observacion',255)->nullable();
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
        Schema::dropIfExists('voucher');
    }
}
