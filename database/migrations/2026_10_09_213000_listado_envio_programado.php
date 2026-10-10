<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('listado_envio_programado')) {
            return;
        }

        Schema::create('listado_envio_programado', function (Blueprint $table) {
            $table->id();
            $table->string('recurso', 120);
            $table->unsignedBigInteger('usuario_id');
            $table->string('email', 190);
            $table->string('frecuencia', 20);
            $table->json('filtros_json');
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_envio_at')->nullable();
            $table->timestamps();
            $table->index(['activo', 'frecuencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listado_envio_programado');
    }
};
