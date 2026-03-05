<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaVentaImpuesto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('venta_impuesto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('concepto',255);
            $table->decimal('baseimponible',20,6)->nullable();
            $table->decimal('tasa',10,6);
            $table->decimal('importe',20,6);
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_venta_impuesto_provincia')->nullable()->references('id')->on('provincia')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('impuesto_id')->nullable();
            $table->foreign('impuesto_id', 'fk_venta_impuesto_impuesto')->nullable()->references('id')->on('impuesto');
            $table->timestamps();
            $table->softDeletes();
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
        Schema::dropIfExists('venta_impuesto');
    }
}
