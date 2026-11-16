<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de disparos a webhooks allowlist (reglas / acciones tipadas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modulo_aviso_webhook_log')) {
            return;
        }

        Schema::create('modulo_aviso_webhook_log', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80);
            $table->string('origen', 80)->nullable();
            $table->unsignedBigInteger('aviso_regla_id')->nullable();
            $table->string('url', 500);
            $table->string('metodo', 10)->default('POST');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('ok')->default(false);
            $table->string('detalle', 500)->nullable();
            $table->timestamp('ejecutado_en');
            $table->index(['clave', 'ejecutado_en'], 'idx_ma_webhook_log_clave');
            $table->index(['ok', 'ejecutado_en'], 'idx_ma_webhook_log_ok');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modulo_aviso_webhook_log');
    }
};
