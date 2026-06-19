<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TicketSubcategoriaTicketIdNullable extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropForeign('fk_ticket_subcategoria_ticket');
        });

        Schema::table('ticket', function (Blueprint $table) {
            $table->unsignedBigInteger('subcategoria_ticket_id')->nullable()->change();
            $table->foreign('subcategoria_ticket_id', 'fk_ticket_subcategoria_ticket')
                ->references('id')->on('subcategoria_ticket')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropForeign('fk_ticket_subcategoria_ticket');
        });

        Schema::table('ticket', function (Blueprint $table) {
            $table->unsignedBigInteger('subcategoria_ticket_id')->nullable(false)->change();
            $table->foreign('subcategoria_ticket_id', 'fk_ticket_subcategoria_ticket')
                ->references('id')->on('subcategoria_ticket')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }
}
