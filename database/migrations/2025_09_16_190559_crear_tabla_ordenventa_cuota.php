<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaOrdenventaCuota extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ordenventa_cuota', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('ordenventa_id');
            $table->foreign('ordenventa_id', 'fk_ordenventa_cuota_ordenventa')->references('id')->on('ordenventa')->onDelete('cascade')->onUpdate('cascade');
            $table->date('fechafactura');
            $table->decimal('montofactura', 22, 4);            
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
        Schema::dropIfExists('ordenventa_cuota');
    }
}
