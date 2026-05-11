<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ordencompra_articulo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ordencompra_id');
            $table->foreign('ordencompra_id', 'fk_ordencompra_articulo_ordencompra')->references('id')->on('ordencompra')->onDelete('cascade')->onUpdate('cascade');
            $table->date('fechaentrega');
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->foreign('articulo_id', 'fk_ordencompra_articulo_articulo')->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
            $table->double('cantidad');
            $table->decimal('precio',22,4);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_ordencompra_articulo_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('cotizacion',22,4);
            $table->decimal('descuento',5,2)->nullable();
            $table->double('cantidadalternativa');
            $table->text('detalle');
            $table->unsignedBigInteger('centrocostodestino_id');
            $table->foreign('centrocostodestino_id', 'fk_ordencompra_centrocostodestino')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('partidagasto_id')->nullable();
            $table->foreign('partidagasto_id', 'fk_ordencompra_articulo_partidagasto')->references('id')->on('partidagasto')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('capex_id')->nullable();
            $table->foreign('capex_id', 'fk_ordencompra_articulo_capex')->references('id')->on('capex')->onDelete('restrict')->onUpdate('restrict');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });  
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordencompra_articulo');
    }
};
