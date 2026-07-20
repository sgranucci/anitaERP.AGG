<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_parametro', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->char('periodo', 6); // YYYYMM
            $table->decimal('budget_total', 16, 2)->default(0);
            $table->decimal('budget_slot', 16, 2)->default(0);
            $table->decimal('budget_rul', 16, 2)->default(0);
            $table->decimal('budget_poker', 16, 2)->default(0);
            $table->decimal('budget_bingo', 16, 2)->default(0);
            $table->decimal('budget_f_b', 16, 2)->default(0);
            $table->unsignedInteger('budget_pos')->default(0);
            $table->decimal('budget_estac', 16, 2)->default(0);
            $table->decimal('total_season', 16, 6)->default(0); // gastro
            $table->decimal('total_sbingo', 16, 6)->default(0);
            $table->decimal('total_sslot', 16, 6)->default(0);
            $table->decimal('total_srul', 16, 6)->default(0);
            $table->decimal('total_spoker', 16, 6)->default(0);
            $table->decimal('total_s_estac', 16, 6)->default(0);
            $table->unsignedBigInteger('creousuario_id')->nullable();
            $table->unsignedBigInteger('actualizousuario_id')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'periodo'], 'flash_parametro_empresa_periodo_unique');
            $table->index(['periodo', 'empresa_id'], 'idx_flash_parametro_periodo_empresa');
        });

        Schema::create('flash_parametro_indice', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flash_parametro_id');
            $table->unsignedBigInteger('empresa_id');
            $table->date('fecha');
            $table->unsignedInteger('customer')->default(0);
            $table->decimal('season_index', 12, 6)->default(0); // gastro
            $table->decimal('sindex_bingo', 12, 6)->default(0);
            $table->decimal('sindex_slot', 12, 6)->default(0);
            $table->decimal('sindex_rul', 12, 6)->default(0);
            $table->decimal('sindex_poker', 12, 6)->default(0);
            $table->decimal('sindex_estac', 12, 6)->default(0);
            $table->unsignedInteger('vehiculos')->default(0);
            $table->timestamps();

            $table->unique(['flash_parametro_id', 'fecha'], 'flash_parametro_indice_param_fecha_unique');
            $table->unique(['empresa_id', 'fecha'], 'flash_parametro_indice_empresa_fecha_unique');
            $table->index('flash_parametro_id', 'idx_flash_parametro_indice_param');

            $table->foreign('flash_parametro_id', 'fk_flash_parametro_indice_param')
                ->references('id')
                ->on('flash_parametro')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_parametro_indice');
        Schema::dropIfExists('flash_parametro');
    }
};
