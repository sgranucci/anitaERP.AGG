<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCategoria extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('categoria', function (Blueprint $table) {
            $table->bigIncrements('id');
    		$table->string('nombre', 100);
    		$table->string('codigo', 50);
    		$table->unsignedInteger('copiaot');
			$table->unsignedBigInteger('tipoarticulo_id');
            $table->foreign('tipoarticulo_id', 'fk_categoria_tipoarticulo')->references('id')->on('tipoarticulo')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('categoria');
    }
}
