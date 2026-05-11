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
        Schema::create('ordencompra_historia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ordencompra_id');
            $table->foreign('ordencompra_id', 'fk_ordencompra_historia_ordencompra')->references('id')->on('ordencompra')->onDelete('cascade');
            $table->unsignedBigInteger('sector_legajocompra_id');
            $table->foreign('sector_legajocompra_id', 'fk_ordencompra_historia_sector_legajocompra')->references('id')->on('sector_legajocompra')->onDelete('cascade');
            $table->datetime('fecha');
            $table->string('observacion', 255)->nullable();
            $table->text('leyenda')->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_ordencompra_historia_usuario')->references('id')->on('usuario')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordencompra_historia');
    }
};
