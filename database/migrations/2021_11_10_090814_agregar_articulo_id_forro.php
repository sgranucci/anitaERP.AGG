<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarArticuloIdForro extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('forro', function (Blueprint $table) {
			$table->unsignedBigInteger('articulo_id')->after('nombre')->nullable();
            $table->foreign('articulo_id', 'fk_forro_articulo')->references('id')->on('articulo')->onDelete('set null')->onUpdate('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('forro', function (Blueprint $table) {
    		$table->dropForeign('fk_forro_articulo');        
    		$table->dropColumn('articulo_id');        
        });
    }
}
