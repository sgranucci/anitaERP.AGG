<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CambiarFkMaterialCapeart2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('capeart', function (Blueprint $table) {
			$table->dropForeign('fk_capeart_material_articulo');
        });
        Schema::table('capeart', function (Blueprint $table) {
            $table->foreign('material_id', 'fk_capeart_materialcapellada')->references('id')->on('materialcapellada')->onDelete('restrict')->onUpdate('restrict');
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
