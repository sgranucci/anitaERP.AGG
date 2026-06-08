<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recuento_archivo')) {
            return;
        }

        Schema::create('recuento_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recuento_id');
            $table->string('nombrearchivo', 255);
            $table->timestamps();

            $table->foreign('recuento_id', 'fk_recuentoarchivo_recuento')
                ->references('id')->on('recuento')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->index('recuento_id', 'ix_recuentoarchivo_recuento');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recuento_archivo');
    }
};
