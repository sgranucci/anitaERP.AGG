<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige nombres genéricos cajero / cajero_externo → cajero_crown_* (módulo caja Crown).
 * Las tablas anteriores estaban vacías (recién creadas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cajero_externo');
        Schema::dropIfExists('cajero');

        if (! Schema::hasTable('cajero_crown')) {
            Schema::create('cajero_crown', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('codigo');
                $table->string('nombre', 120);
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('sala_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->unsignedInteger('legajo')->nullable();
                $table->unsignedBigInteger('caja_id')->nullable();
                $table->unsignedBigInteger('mozo_gastronomia_id')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique(['empresa_id', 'codigo'], 'uq_cajero_crown_empresa_codigo');
                $table->index(['empresa_id', 'sala_id'], 'idx_cajero_crown_empresa_sala');
                $table->index('legajo', 'idx_cajero_crown_legajo');

                $table->foreign('empresa_id', 'fk_cajero_crown_empresa')
                    ->references('id')->on('empresa')->restrictOnDelete();
                $table->foreign('sala_id', 'fk_cajero_crown_sala')
                    ->references('id')->on('sala')->nullOnDelete();
                $table->foreign('usuario_id', 'fk_cajero_crown_usuario')
                    ->references('id')->on('usuario')->nullOnDelete();
                $table->foreign('caja_id', 'fk_cajero_crown_caja')
                    ->references('id')->on('caja')->nullOnDelete();
                $table->foreign('mozo_gastronomia_id', 'fk_cajero_crown_mozo')
                    ->references('id')->on('mozo_gastronomia')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('cajero_crown_externo')) {
            Schema::create('cajero_crown_externo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('cajero_crown_id');
                $table->string('sistema', 30);
                $table->string('codigo_externo', 30)->nullable();
                $table->unsignedBigInteger('mozo_gastronomia_id')->nullable();
                $table->timestamps();

                $table->unique(['cajero_crown_id', 'sistema'], 'uq_cajero_crown_ext_cajero_sistema');
                $table->index(['sistema', 'codigo_externo'], 'idx_cajero_crown_ext_sistema_codigo');
                $table->index(['sistema', 'mozo_gastronomia_id'], 'idx_cajero_crown_ext_sistema_mozo');

                $table->foreign('cajero_crown_id', 'fk_cajero_crown_ext_cajero')
                    ->references('id')->on('cajero_crown')->cascadeOnDelete();
                $table->foreign('mozo_gastronomia_id', 'fk_cajero_crown_ext_mozo')
                    ->references('id')->on('mozo_gastronomia')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // No recrea cajero/cajero_externo genéricos: el nombre correcto es cajero_crown_*.
        Schema::dropIfExists('cajero_crown_externo');
        Schema::dropIfExists('cajero_crown');
    }
};
