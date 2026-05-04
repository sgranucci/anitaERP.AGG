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
        Schema::create('requisicion_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('requisicion_id');
            $table->foreign('requisicion_id', 'fk_requisicion_archivo_requisicion')->references('id')->on('requisicion')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombrearchivo',255);
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
        Schema::dropIfExists('requisicion_archivo');
    }
};
