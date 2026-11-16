<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencias de canal por usuario, SLA/escalamiento en user_task, SLA en tipos de aviso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usuario_aviso_preferencia')) {
            Schema::create('usuario_aviso_preferencia', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('usuario_id');
                $table->string('canal', 20);
                $table->boolean('habilitado')->default(true);
                $table->timestamps();

                $table->unique(['usuario_id', 'canal'], 'uk_usuario_aviso_pref_canal');
                $table->foreign('usuario_id', 'fk_usuario_aviso_pref_usuario')
                    ->references('id')->on('usuario')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('user_task')) {
            Schema::table('user_task', function (Blueprint $table) {
                if (! Schema::hasColumn('user_task', 'vence_en')) {
                    $table->timestamp('vence_en')->nullable()->after('abierta_en');
                }
                if (! Schema::hasColumn('user_task', 'prioridad')) {
                    $table->unsignedTinyInteger('prioridad')->default(0)->after('estado');
                }
                if (! Schema::hasColumn('user_task', 'sla_horas')) {
                    $table->unsignedSmallInteger('sla_horas')->nullable()->after('prioridad');
                }
                if (! Schema::hasColumn('user_task', 'escalado_en')) {
                    $table->timestamp('escalado_en')->nullable()->after('vence_en');
                }
                if (! Schema::hasColumn('user_task', 'escalado_a_usuario_id')) {
                    $table->unsignedBigInteger('escalado_a_usuario_id')->nullable()->after('escalado_en');
                }
                if (! Schema::hasColumn('user_task', 'reasignado_de_usuario_id')) {
                    $table->unsignedBigInteger('reasignado_de_usuario_id')->nullable()->after('escalado_a_usuario_id');
                }
            });

            if (Schema::hasColumn('user_task', 'escalado_a_usuario_id')) {
                $fk = collect(DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_task'
                    AND CONSTRAINT_NAME = 'fk_user_task_escalado_usuario'"))->isNotEmpty();
                if (! $fk) {
                    Schema::table('user_task', function (Blueprint $table) {
                        $table->foreign('escalado_a_usuario_id', 'fk_user_task_escalado_usuario')
                            ->references('id')->on('usuario')->onDelete('set null');
                    });
                }
            }
        }

        if (Schema::hasTable('modulo_aviso_tipo') && ! Schema::hasColumn('modulo_aviso_tipo', 'sla_horas')) {
            Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
                $table->unsignedSmallInteger('sla_horas')->nullable()->after('parametro_dias');
            });
        }

        $this->seedMenuPreferencias();
    }

    public function down(): void
    {
        if (Schema::hasTable('menu')) {
            $menuId = (int) (DB::table('menu')->where('url', 'configuracion/mis-preferencias-aviso')->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        if (Schema::hasTable('modulo_aviso_tipo') && Schema::hasColumn('modulo_aviso_tipo', 'sla_horas')) {
            Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
                $table->dropColumn('sla_horas');
            });
        }

        if (Schema::hasTable('user_task')) {
            Schema::table('user_task', function (Blueprint $table) {
                foreach (['fk_user_task_escalado_usuario'] as $fk) {
                    try {
                        $table->dropForeign($fk);
                    } catch (\Throwable) {
                    }
                }
                foreach (['vence_en', 'prioridad', 'sla_horas', 'escalado_en', 'escalado_a_usuario_id', 'reasignado_de_usuario_id'] as $col) {
                    if (Schema::hasColumn('user_task', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('usuario_aviso_preferencia');
    }

    private function seedMenuPreferencias(): void
    {
        if (! Schema::hasTable('menu')) {
            return;
        }

        $padreId = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('menu_id') ?? 0);
        if ($padreId <= 0) {
            $padreId = (int) (DB::table('menu')->where('url', 'mis-aprobaciones')->value('menu_id') ?? 0);
        }
        if ($padreId <= 0) {
            return;
        }

        $url = 'configuracion/mis-preferencias-aviso';
        if (DB::table('menu')->where('url', $url)->exists()) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('url', 'mis-aprobaciones')->value('orden') ?? 5) + 1;
        $menuId = (int) DB::table('menu')->insertGetId([
            'menu_id' => $padreId,
            'nombre' => 'Mis preferencias de avisos',
            'url' => $url,
            'orden' => $orden,
            'icono' => 'fa-sliders',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($menuId <= 0 || ! Schema::hasTable('menu_rol') || ! Schema::hasTable('rol')) {
            return;
        }

        $rolIds = DB::table('rol')->pluck('id');
        foreach ($rolIds as $rolId) {
            $exists = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $exists) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
