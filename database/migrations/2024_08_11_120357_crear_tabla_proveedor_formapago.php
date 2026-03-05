<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaProveedorFormapago extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proveedor_formapago', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_proveedor_formapago_proveedor')->references('id')->on('proveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombre',255);
            $table->unsignedBigInteger('formapago_id');
            $table->foreign('formapago_id', 'fk_proveedor_formapago_formapago')->references('id')->on('formapago')->onDelete('restrict')->onUpdate('restrict');
            $table->string('cbu',50);
            $table->unsignedBigInteger('tipocuentacaja_id');
            $table->foreign('tipocuentacaja_id', 'fk_proveedor_formapago_tipocuentacaja')->references('id')->on('tipocuentacaja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_proveedor_formapago_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->string('numerocuenta',50);
            $table->string('nroinscripcion',100);
            $table->unsignedBigInteger('banco_id')->nullable();
            $table->foreign('banco_id', 'fk_proveedor_formapago_banco')->references('id')->on('banco')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('mediopago_id');
            $table->foreign('mediopago_id', 'fk_proveedor_formapago_mediopago')->references('id')->on('mediopago')->onDelete('restrict')->onUpdate('restrict');
            $table->string('email',255);
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
        Schema::dropIfExists('proveedor_formapago');
    }
}
