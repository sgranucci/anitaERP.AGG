<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de reglas de aviso (WHEN/IF/THEN), activable por pantalla.
 * Sin SoftDeletes. Genérico a todos los clientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aviso_regla_config')) {
            Schema::create('aviso_regla_config', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->boolean('motor_habilitado')->default(false);
                $table->timestamps();
            });
            DB::table('aviso_regla_config')->insert([
                'motor_habilitado' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('aviso_regla')) {
            Schema::create('aviso_regla', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 80);
                $table->string('nombre', 160);
                $table->string('descripcion', 500)->nullable();
                $table->boolean('activo')->default(false);
                $table->unsignedSmallInteger('prioridad')->default(100);
                $table->string('evento', 120);
                $table->json('condiciones')->nullable();
                $table->json('acciones')->nullable();
                $table->timestamps();

                $table->unique('codigo', 'uk_aviso_regla_codigo');
                $table->index(['evento', 'activo', 'prioridad'], 'idx_aviso_regla_evento_activo');
            });
        }

        if (! Schema::hasTable('aviso_regla_log')) {
            Schema::create('aviso_regla_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('aviso_regla_id');
                $table->string('evento', 120);
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->boolean('ok')->default(true);
                $table->string('detalle', 500)->nullable();
                $table->timestamp('ejecutado_en')->useCurrent();
                $table->timestamps();

                $table->index(['aviso_regla_id', 'ejecutado_en'], 'idx_aviso_regla_log_regla');
                $table->index(['evento', 'ejecutado_en'], 'idx_aviso_regla_log_evento');
                $table->foreign('aviso_regla_id', 'fk_aviso_regla_log_regla')
                    ->references('id')->on('aviso_regla')->onDelete('cascade');
            });
        }

        $this->seedMenu();
    }

    public function down(): void
    {
        Schema::dropIfExists('aviso_regla_log');
        Schema::dropIfExists('aviso_regla');
        Schema::dropIfExists('aviso_regla_config');

        if (Schema::hasTable('menu')) {
            $menuId = (int) (DB::table('menu')->where('url', 'configuracion/aviso-regla')->value('id') ?? 0);
            if ($menuId > 0) {
                if (Schema::hasTable('menu_rol')) {
                    DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                }
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }
    }

    private function seedMenu(): void
    {
        if (! Schema::hasTable('menu')) {
            return;
        }
        $padreId = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('menu_id') ?? 0);
        if ($padreId <= 0) {
            return;
        }
        $orden = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso-evento')->value('orden') ?? 12);
        $rolAdmin = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        $url = 'configuracion/aviso-regla';
        $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $now = now();
        if ($menuId <= 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Reglas de aviso',
                'url' => $url,
                'orden' => $orden + 1,
                'icono' => 'fa-project-diagram',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if ($menuId > 0 && $rolAdmin > 0 && Schema::hasTable('menu_rol')) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolAdmin)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolAdmin]);
            }
        }
    }
};
