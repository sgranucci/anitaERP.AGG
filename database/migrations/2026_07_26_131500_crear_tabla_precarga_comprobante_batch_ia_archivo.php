<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('precarga_comprobante_batch_ia_archivo')) {
            return;
        }

        Schema::create('precarga_comprobante_batch_ia_archivo', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_nombre', 255);
            $table->string('archivo_hash', 64)->unique('uk_batch_ia_archivo_hash');
            $table->string('ruta_procesando', 1000)->nullable();
            $table->string('ruta_archivo', 1000)->nullable();
            $table->string('estado', 20)->default('ENCOLADO');
            $table->string('numero_oc', 10)->nullable();
            $table->unsignedBigInteger('precarga_id')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->timestamps();

            $table->index('estado', 'ix_batch_ia_archivo_estado');
            $table->index('created_at', 'ix_batch_ia_archivo_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precarga_comprobante_batch_ia_archivo');
    }
};
