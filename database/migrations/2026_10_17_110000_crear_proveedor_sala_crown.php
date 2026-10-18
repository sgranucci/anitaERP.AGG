<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: proveedor ↔ sala (Anita procasino / casino).
 * En otros entornos la tabla puede existir vacía; la solapa solo se muestra si esCrown().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proveedor_sala')) {
            return;
        }

        Schema::create('proveedor_sala', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proveedor_id');
            $table->unsignedBigInteger('sala_id');
            $table->timestamps();

            $table->foreign('proveedor_id', 'fk_proveedor_sala_proveedor')
                ->references('id')->on('proveedor')
                ->cascadeOnDelete();
            $table->foreign('sala_id', 'fk_proveedor_sala_sala')
                ->references('id')->on('sala')
                ->restrictOnDelete();
            $table->unique(['proveedor_id', 'sala_id'], 'uk_proveedor_sala');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_sala');
    }
};
