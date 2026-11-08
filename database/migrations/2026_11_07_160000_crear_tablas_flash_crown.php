<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flash Crown (directivos): snapshot por sala/fecha + suscripciones de mail.
 * Solo schema en entorno CROWN.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('flash_crown')) {
            Schema::create('flash_crown', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sala_id');
                $table->unsignedBigInteger('empresa_id');
                $table->date('fecha');

                $table->unsignedInteger('att')->default(0);
                $table->decimal('cashdrop', 16, 2)->default(0);
                $table->decimal('soft_mesas', 16, 2)->default(0);
                $table->unsignedInteger('cant_mesas')->default(0);
                $table->decimal('caja_empleado', 16, 2)->default(0);

                $table->decimal('wheel_r', 16, 2)->default(0);
                $table->decimal('baccarat_r', 16, 2)->default(0);
                $table->decimal('blackjack_r', 16, 2)->default(0);
                $table->decimal('multi_r', 16, 2)->default(0);
                $table->decimal('crap_r', 16, 2)->default(0);
                $table->decimal('caribbean_r', 16, 2)->default(0);
                $table->decimal('poker_r', 16, 2)->default(0);
                $table->decimal('seven_r', 16, 2)->default(0);
                $table->decimal('super_peek_r', 16, 2)->default(0);

                $table->decimal('torneo_poker', 16, 2)->default(0);
                $table->decimal('master_cup', 16, 2)->default(0);
                $table->decimal('inscripcion', 16, 2)->default(0);
                $table->decimal('premio', 16, 2)->default(0);

                $table->decimal('slot_d', 16, 2)->default(0);
                $table->decimal('soft_count', 16, 2)->default(0);
                $table->decimal('hard_count', 16, 2)->default(0);
                $table->unsignedInteger('cant_slots')->default(0);
                $table->decimal('coin_in', 16, 2)->default(0);
                $table->decimal('coin_out', 16, 2)->default(0);
                $table->decimal('pago_manual', 16, 2)->default(0);
                $table->decimal('slot_r', 16, 2)->default(0);
                $table->decimal('cash_less', 16, 2)->default(0);

                $table->decimal('ruleta_el_d', 16, 2)->default(0);
                $table->decimal('ruleta_el_r', 16, 2)->default(0);
                $table->decimal('soft_rul_el', 16, 2)->default(0);
                $table->unsignedInteger('cant_rul_el')->default(0);
                $table->decimal('coin_in_rul', 16, 2)->default(0);

                $table->decimal('ayb', 16, 2)->default(0);
                $table->decimal('hotel', 16, 2)->default(0);

                $table->string('comentario', 80)->nullable();
                $table->json('overrides_json')->nullable();
                $table->json('detalle_json')->nullable();
                $table->string('origen', 20)->default('auto');
                $table->timestamp('calculado_en')->nullable();

                $table->boolean('validado')->default(false);
                $table->timestamp('validado_en')->nullable();
                $table->unsignedBigInteger('validado_usuario_id')->nullable();
                $table->unsignedBigInteger('creousuario_id')->nullable();
                $table->unsignedBigInteger('actualizousuario_id')->nullable();
                $table->timestamps();

                $table->unique(['sala_id', 'fecha'], 'flash_crown_sala_fecha_unique');
                $table->index(['fecha', 'sala_id'], 'idx_flash_crown_fecha_sala');
                $table->foreign('sala_id', 'fk_flash_crown_sala')
                    ->references('id')->on('sala')->onDelete('restrict');
                $table->foreign('empresa_id', 'fk_flash_crown_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('flash_crown_suscripcion')) {
            Schema::create('flash_crown_suscripcion', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 120);
                $table->boolean('activo')->default(true);
                $table->string('periodicidad', 20)->default('diaria');
                $table->unsignedTinyInteger('dia_mes')->nullable();
                $table->unsignedTinyInteger('dia_semana')->nullable();
                $table->string('hora', 5)->default('08:00');
                $table->string('periodo_relativo', 30)->default('mes_actual');
                $table->string('mes_fijo', 7)->nullable();
                $table->string('perfil_vista', 30)->default('completa');
                $table->boolean('solo_validados')->default(true);
                $table->text('destinatarios')->nullable();
                $table->text('mensaje')->nullable();
                $table->timestamp('ultima_ejecucion')->nullable();
                $table->string('ultimo_estado', 20)->nullable();
                $table->string('ultimo_mensaje', 500)->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('flash_crown_suscripcion');
        Schema::dropIfExists('flash_crown');
    }
};
