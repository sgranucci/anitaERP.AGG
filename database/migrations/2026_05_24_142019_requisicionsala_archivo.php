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
        if (Schema::hasTable('requisicion_sala_archivo')) {
            return;
        }

        Schema::create('requisicion_sala_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('requisicion_sala_id');
            $table->foreign('requisicion_sala_id', 'fk_requisicion_sala_archivo_requisicion_sala')->references('id')->on('requisicion_sala')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombrearchivo', 255);
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
        Schema::dropIfExists('requisicion_sala_archivo');
    }
};
