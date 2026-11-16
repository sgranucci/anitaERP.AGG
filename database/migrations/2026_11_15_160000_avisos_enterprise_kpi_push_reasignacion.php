<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise full: KPI SLA (solo pantallas), Web Push subscriptions, menús.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('web_push_subscription')) {
            Schema::create('web_push_subscription', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('usuario_id');
                $table->text('endpoint');
                $table->string('endpoint_hash', 64);
                $table->string('p256dh', 255);
                $table->string('auth', 255);
                $table->string('content_encoding', 40)->default('aesgcm');
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('ultima_ok_en')->nullable();
                $table->timestamp('ultimo_error_en')->nullable();
                $table->string('ultimo_error', 255)->nullable();
                $table->timestamps();

                $table->unique('endpoint_hash', 'uk_web_push_endpoint_hash');
                $table->index(['usuario_id'], 'idx_web_push_usuario');
                $table->foreign('usuario_id', 'fk_web_push_usuario')
                    ->references('id')->on('usuario')->onDelete('cascade');
            });
        }

        $this->seedMenus();
    }

    public function down(): void
    {
        foreach ([
            'configuracion/user-task-sla-kpi',
            'configuracion/user-task-reasignacion-masiva',
        ] as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }
        Schema::dropIfExists('web_push_subscription');
    }

    private function seedMenus(): void
    {
        if (! Schema::hasTable('menu')) {
            return;
        }
        $padreId = (int) (DB::table('menu')->where('url', 'configuracion/user-task-sla')->value('menu_id') ?? 0);
        if ($padreId <= 0) {
            $padreId = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('menu_id') ?? 0);
        }
        if ($padreId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('url', 'configuracion/user-task-sla')->value('orden') ?? 20);
        $rolAdmin = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        $now = now();

        foreach ([
            ['url' => 'configuracion/user-task-sla-kpi', 'nombre' => 'KPI SLA aprobaciones', 'icono' => 'fa-chart-bar', 'orden' => $orden + 1],
            ['url' => 'configuracion/user-task-reasignacion-masiva', 'nombre' => 'Reasignación masiva tareas', 'icono' => 'fa-people-arrows', 'orden' => $orden + 2],
        ] as $item) {
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
