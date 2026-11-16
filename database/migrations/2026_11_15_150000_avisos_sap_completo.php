<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAP/Oracle completo: suscripciones UI, prefs por tipo, escaladores, auditoría WA, config bandeja.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usuario_aviso_preferencia_tipo')) {
            Schema::create('usuario_aviso_preferencia_tipo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedBigInteger('modulo_aviso_tipo_id');
                $table->string('canal', 20);
                $table->boolean('habilitado')->default(true);
                $table->timestamps();

                $table->unique(
                    ['usuario_id', 'modulo_aviso_tipo_id', 'canal'],
                    'uk_usuario_aviso_pref_tipo_canal'
                );
                $table->foreign('usuario_id', 'fk_usuario_aviso_pref_tipo_usu')
                    ->references('id')->on('usuario')->onDelete('cascade');
                $table->foreign('modulo_aviso_tipo_id', 'fk_usuario_aviso_pref_tipo_tipo')
                    ->references('id')->on('modulo_aviso_tipo')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('user_task_escalador')) {
            Schema::create('user_task_escalador', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('usuario_id');
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique('usuario_id', 'uk_user_task_escalador_usuario');
                $table->foreign('usuario_id', 'fk_user_task_escalador_usuario')
                    ->references('id')->on('usuario')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('user_task_config')) {
            Schema::create('user_task_config', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedSmallInteger('sla_horas')->default(48);
                $table->boolean('bandeja_desde_tabla')->default(false);
                $table->timestamps();
            });
            DB::table('user_task_config')->insert([
                'sla_horas' => max(1, (int) env('USER_TASK_SLA_HORAS', 48)),
                'bandeja_desde_tabla' => filter_var(env('USER_TASK_BANDEJA_DESDE_TABLA', false), FILTER_VALIDATE_BOOLEAN),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('whatsapp_envio_log')) {
            Schema::create('whatsapp_envio_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('celular', 30)->nullable();
                $table->string('modulo_aviso', 120)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('estado', 20)->default('ok');
                $table->string('provider_id', 120)->nullable();
                $table->string('error', 255)->nullable();
                $table->string('driver', 40)->nullable();
                $table->unsignedSmallInteger('chars')->default(0);
                $table->boolean('uso_plantilla')->default(false);
                $table->timestamp('enviado_en')->useCurrent();
                $table->timestamps();

                $table->index(['enviado_en'], 'idx_wa_log_enviado');
                $table->index(['empresa_id', 'estado'], 'idx_wa_log_empresa_estado');
                $table->index(['usuario_id'], 'idx_wa_log_usuario');
            });
        }

        $this->seedMenus();
    }

    public function down(): void
    {
        foreach ([
            'configuracion/modulo-aviso-evento',
            'configuracion/user-task-sla',
            'configuracion/whatsapp-envio-log',
            'configuracion/usuarios-celular-masivo',
        ] as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        Schema::dropIfExists('whatsapp_envio_log');
        Schema::dropIfExists('user_task_config');
        Schema::dropIfExists('user_task_escalador');
        Schema::dropIfExists('usuario_aviso_preferencia_tipo');
    }

    private function seedMenus(): void
    {
        if (! Schema::hasTable('menu')) {
            return;
        }

        $padreId = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('menu_id') ?? 0);
        if ($padreId <= 0) {
            return;
        }

        $ordenBase = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('orden') ?? 10);
        $items = [
            ['url' => 'configuracion/modulo-aviso-evento', 'nombre' => 'Suscripciones de eventos', 'icono' => 'fa-bolt', 'orden' => $ordenBase + 2],
            ['url' => 'configuracion/user-task-sla', 'nombre' => 'SLA y escalamiento', 'icono' => 'fa-hourglass-half', 'orden' => $ordenBase + 3],
            ['url' => 'configuracion/whatsapp-envio-log', 'nombre' => 'Auditoría WhatsApp', 'icono' => 'fa-list-alt', 'orden' => $ordenBase + 4],
            ['url' => 'configuracion/usuarios-celular-masivo', 'nombre' => 'Celulares masivo', 'icono' => 'fa-mobile', 'orden' => $ordenBase + 5],
        ];

        $rolAdmin = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        $now = now();

        foreach ($items as $item) {
            $menuId = (int) (DB::table('menu')->where('url', $item['url'])->value('id') ?? 0);
            if ($menuId <= 0) {
                $menuId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => $padreId,
                    'nombre' => $item['nombre'],
                    'url' => $item['url'],
                    'orden' => $item['orden'],
                    'icono' => $item['icono'],
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
    }
};
