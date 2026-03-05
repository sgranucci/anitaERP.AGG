<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaOrdenventaConcepto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ordenventa_concepto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ordenventa_id');
            $table->foreign('ordenventa_id', 'fk_ordenventa_concepto_ordenventa')->references('id')->on('ordenventa')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('concepto_ordenventa_id');
            $table->foreign('concepto_ordenventa_id', 'fk_ordenventa_concepto_concepto_ordenventa')->references('id')->on('concepto_ordenventa')->onDelete('restrict')->onUpdate('restrict');
            $table->float('cantidad');
            $table->text('detalle');
            $table->decimal('monto',22,4);
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
        Schema::dropIfExists('ordenventa_concepto');
    }
}
