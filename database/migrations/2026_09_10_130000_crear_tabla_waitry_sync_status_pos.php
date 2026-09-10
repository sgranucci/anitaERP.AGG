<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitry_sync_status_pos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('venta_id');
            $table->foreign('venta_id', 'fk_waitry_sync_pos_venta')
                ->references('id')->on('venta')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('cuenta_gastronomia_id')->nullable();
            $table->foreign('cuenta_gastronomia_id', 'fk_waitry_sync_pos_cuenta')
                ->references('id')->on('cuenta_gastronomia')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('waitry_order_id');
            $table->unsignedTinyInteger('empresa_id');
            $table->unsignedInteger('place_id');
            $table->string('estado', 20)->default('pendiente');
            $table->boolean('sync_pos_ok')->default(false);
            $table->boolean('kds_ok')->default(false);
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->unsignedSmallInteger('max_intentos')->default(8);
            $table->text('ultimo_error')->nullable();
            $table->unsignedSmallInteger('ultimo_http_code')->nullable();
            $table->json('payload_json')->nullable();
            $table->json('respuesta_json')->nullable();
            $table->timestamp('proximo_reintento_at')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->unique('venta_id', 'uq_waitry_sync_pos_venta');
            $table->index(['estado', 'proximo_reintento_at'], 'idx_waitry_sync_pos_estado_reintento');
            $table->index(['waitry_order_id', 'empresa_id'], 'idx_waitry_sync_pos_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitry_sync_status_pos');
    }
};
