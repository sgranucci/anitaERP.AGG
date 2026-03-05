<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CambiarFkMaterialAvioart2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('avioart', function (Blueprint $table) {
			$table->dropForeign('fk_avioart_material_articulo');
        });
        Schema::table('avioart', function (Blueprint $table) {
            $table->foreign('material_id', 'fk_avioart_materialavio')->references('id')->on('materialavio')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
