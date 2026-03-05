<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaProvinciaCuentacontableIibb extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('provincia_cuentacontableiibb', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('provincia_id');
            $table->foreign('provincia_id', 'fk_provincia_cuentacontableiibb_provincia')->references('id')->on('provincia')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_provincia_cuentacontableiibb_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('cuentacontable_id');
            $table->foreign('cuentacontable_id', 'fk_provincia_cuentacontableiibb_cuentacontable')->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_provincia_cuentacontableiibb_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('provincia_cuentacontableiibb');
    }
}
