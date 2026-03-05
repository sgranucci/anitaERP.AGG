<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarClienteEntregaPedido extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedido', function (Blueprint $table) {
			$table->unsignedBigInteger('cliente_entrega_id')->nullable()->after('lugarentrega');
            $table->foreign('cliente_entrega_id', 'fk_pedido_cliente_entrega')->references('id')->on('cliente_entrega')->onDelete('restrict')->onUpdate('restrict');
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
