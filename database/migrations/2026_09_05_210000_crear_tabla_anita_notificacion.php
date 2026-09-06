<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de notificaciones in-app (campanita / chat operativo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('anita_notificacion')) {
            return;
        }

        Schema::create('anita_notificacion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id')->index();
            $table->string('tipo', 40)->default('sistema')->index();
            $table->string('titulo', 180);
            $table->string('cuerpo', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('leida_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'leida_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anita_notificacion');
    }
};
