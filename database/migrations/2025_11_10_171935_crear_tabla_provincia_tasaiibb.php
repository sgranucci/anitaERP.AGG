<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaProvinciaTasaiibb extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('provincia_tasaiibb', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('provincia_id');
            $table->foreign('provincia_id', 'fk_provincia_tasaiibb_provincia')->references('id')->on('provincia')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('condicioniibb_id');
            $table->foreign('condicioniibb_id', 'fk_provincia_tasaiibb_condicionIIBB')->references('id')->on('condicionIIBB')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('tasa',22,6);
            $table->decimal('minimoneto',22,2)->nullable();
            $table->decimal('minimopercepcion',22,6)->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_provincia_tasaiibb_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('provincia_tasaiibb');
    }
}
