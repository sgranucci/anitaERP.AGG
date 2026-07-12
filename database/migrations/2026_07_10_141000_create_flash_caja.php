<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_caja', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->date('fecha');
            $table->integer('att')->nullable();
            $table->decimal('ayb', 16, 2)->default(0);
            $table->decimal('slot_coin_in', 16, 2)->default(0);
            $table->decimal('slot_d', 16, 2)->default(0);
            $table->decimal('slot_r', 16, 2)->default(0);
            $table->decimal('soft_count', 16, 2)->default(0);
            $table->decimal('hard_count', 16, 2)->default(0);
            $table->unsignedInteger('cant_slots')->default(0);
            $table->decimal('rul_coin_in', 16, 2)->default(0);
            $table->decimal('rul_d', 16, 2)->default(0);
            $table->decimal('rul_r', 16, 2)->default(0);
            $table->decimal('soft_rul', 16, 2)->default(0);
            $table->decimal('hard_rul', 16, 2)->default(0);
            $table->unsignedInteger('cant_rul')->default(0);
            $table->decimal('cotizacion', 12, 4)->nullable();
            $table->string('comentario', 30)->nullable();
            $table->unsignedInteger('bingo_cant_carton')->default(0);
            $table->decimal('bingo_total_venta', 16, 2)->default(0);
            $table->decimal('bingo_resultado', 16, 2)->default(0);
            $table->unsignedInteger('pos_online')->default(0);
            $table->decimal('poker_coin_in', 16, 2)->default(0);
            $table->decimal('poker_d', 16, 2)->default(0);
            $table->decimal('poker_r', 16, 2)->default(0);
            $table->decimal('poker_soft_count', 16, 2)->default(0);
            $table->decimal('poker_hard_count', 16, 2)->default(0);
            $table->unsignedInteger('cant_poker')->default(0);
            $table->decimal('win_ol_slot', 16, 2)->default(0);
            $table->decimal('win_ol_rul', 16, 2)->default(0);
            $table->decimal('win_ol_poker', 16, 2)->default(0);
            $table->decimal('estac', 16, 2)->default(0);
            $table->unsignedInteger('cant_vehic')->default(0);
            $table->decimal('show', 16, 2)->default(0);
            $table->timestamp('calculado_en')->nullable();
            $table->unsignedBigInteger('creousuario_id')->nullable();
            $table->unsignedBigInteger('actualizousuario_id')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'fecha'], 'flash_caja_empresa_fecha_unique');
            $table->index(['fecha', 'empresa_id'], 'idx_flash_caja_fecha_empresa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_caja');
    }
};
