<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencias del copiloto IA por usuario (digest / defaults FAB).
 * Sobrevive flush de cache; no SoftDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_copiloto_preferencia')) {
            return;
        }

        Schema::create('ai_copiloto_preferencia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('centrocosto_codigo', 32)->nullable();
            $table->string('deposito_codigo', 32)->nullable();
            $table->string('asistente_rol', 32)->default('auto');
            $table->boolean('auto_brief')->default(true);
            $table->boolean('notify_brief_campanita')->default(true);
            $table->boolean('notify_brief_mail')->default(false);
            $table->timestamps();

            $table->unique('usuario_id', 'uk_ai_copiloto_pref_usuario');
            $table->foreign('usuario_id', 'fk_ai_copiloto_pref_usuario')
                ->references('id')->on('usuario')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_copiloto_preferencia');
    }
};
