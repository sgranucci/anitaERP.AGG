<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp por cuenta-empresa, user_task persistida, suscripciones de eventos ModuloAviso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuario') && ! Schema::hasColumn('usuario', 'celular')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->string('celular', 30)->nullable()->after('email');
            });
        }

        if (Schema::hasTable('modulo_aviso_tipo') && ! Schema::hasColumn('modulo_aviso_tipo', 'notificar_whatsapp')) {
            Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
                $table->boolean('notificar_whatsapp')->default(false)->after('notificar_in_app');
            });
        }

        if (! Schema::hasTable('empresa_whatsapp_cuenta')) {
            Schema::create('empresa_whatsapp_cuenta', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->string('proveedor', 40)->default('meta_cloud');
                $table->string('nombre', 120)->nullable();
                $table->string('phone_number_id', 80)->nullable();
                $table->string('waba_id', 80)->nullable();
                $table->text('access_token')->nullable();
                $table->string('api_version', 20)->default('v21.0');
                $table->boolean('activo')->default(false);
                $table->timestamps();

                $table->unique('empresa_id', 'uk_empresa_whatsapp_cuenta_empresa');
                $table->foreign('empresa_id', 'fk_empresa_whatsapp_cuenta_empresa')
                    ->references('id')->on('empresa')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('user_task')) {
            Schema::create('user_task', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('fuente', 60);
                $table->string('entity_type', 80)->nullable();
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('usuario_id');
                $table->string('titulo', 255);
                $table->string('subtitulo', 255)->nullable();
                $table->string('url', 500)->nullable();
                $table->string('estado', 20)->default('pendiente');
                $table->json('meta')->nullable();
                $table->timestamp('abierta_en')->nullable();
                $table->timestamp('cerrada_en')->nullable();
                $table->timestamps();

                $table->unique(['fuente', 'entity_id', 'usuario_id'], 'uk_user_task_fuente_entity_usuario');
                $table->index(['usuario_id', 'estado'], 'idx_user_task_usuario_estado');
                $table->index(['fuente', 'entity_id'], 'idx_user_task_fuente_entity');
                $table->foreign('usuario_id', 'fk_user_task_usuario')
                    ->references('id')->on('usuario')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('modulo_aviso_evento_suscripcion')) {
            Schema::create('modulo_aviso_evento_suscripcion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('evento', 120);
                $table->unsignedBigInteger('modulo_aviso_tipo_id');
                $table->boolean('activo')->default(true);
                $table->json('condiciones')->nullable();
                $table->timestamps();

                $table->unique(['evento', 'modulo_aviso_tipo_id'], 'uk_aviso_evento_tipo');
                $table->foreign('modulo_aviso_tipo_id', 'fk_aviso_evento_tipo')
                    ->references('id')->on('modulo_aviso_tipo')->onDelete('cascade');
            });
        }

        $this->seedMenuPermisos();
    }

    public function down(): void
    {
        Schema::dropIfExists('modulo_aviso_evento_suscripcion');
        Schema::dropIfExists('user_task');
        Schema::dropIfExists('empresa_whatsapp_cuenta');

        if (Schema::hasTable('modulo_aviso_tipo') && Schema::hasColumn('modulo_aviso_tipo', 'notificar_whatsapp')) {
            Schema::table('modulo_aviso_tipo', function (Blueprint $table) {
                $table->dropColumn('notificar_whatsapp');
            });
        }
        if (Schema::hasTable('usuario') && Schema::hasColumn('usuario', 'celular')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->dropColumn('celular');
            });
        }

        if (Schema::hasTable('menu')) {
            $menuId = (int) (DB::table('menu')->where('url', 'configuracion/empresa-whatsapp')->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }
        if (Schema::hasTable('permiso')) {
            $slugs = [
                'listar-empresa-whatsapp',
                'editar-empresa-whatsapp',
                'actualizar-empresa-whatsapp',
            ];
            $ids = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
            if ($ids->isNotEmpty() && Schema::hasTable('permiso_rol')) {
                DB::table('permiso_rol')->whereIn('permiso_id', $ids)->delete();
            }
            DB::table('permiso')->whereIn('slug', $slugs)->delete();
        }
    }

    private function seedMenuPermisos(): void
    {
        if (! Schema::hasTable('permiso') || ! Schema::hasTable('menu')) {
            return;
        }

        $now = now();
        $padreId = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('menu_id') ?? 0);
        if ($padreId <= 0) {
            $padreId = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        }
        if ($padreId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', 'configuracion/empresa-whatsapp')->value('id') ?? 0);
        if ($menuId <= 0) {
            $orden = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('orden') ?? 11) + 1;
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'WhatsApp por empresa',
                'url' => 'configuracion/empresa-whatsapp',
                'orden' => $orden,
                'icono' => 'fa-whatsapp',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permisos = [
            ['slug' => 'listar-empresa-whatsapp', 'nombre' => 'Listar cuentas WhatsApp por empresa'],
            ['slug' => 'editar-empresa-whatsapp', 'nombre' => 'Editar cuentas WhatsApp por empresa'],
            ['slug' => 'actualizar-empresa-whatsapp', 'nombre' => 'Actualizar cuentas WhatsApp por empresa'],
        ];
        $permisoIds = [];
        foreach ($permisos as $p) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $p['slug'])->value('id') ?? 0);
            if ($permisoId <= 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'slug' => $p['slug'],
                    'nombre' => $p['nombre'],
                    'menu_id' => $menuId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $p['nombre'],
                    'updated_at' => $now,
                ]);
            }
            $permisoIds[] = $permisoId;
        }

        $rolAdmin = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($menuId > 0 && $rolAdmin > 0 && Schema::hasTable('menu_rol')) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolAdmin)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolAdmin]);
            }
        }
        if ($rolAdmin > 0 && Schema::hasTable('permiso_rol')) {
            foreach ($permisoIds as $permisoId) {
                if ($permisoId <= 0) {
                    continue;
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolAdmin)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolAdmin]);
                }
            }
        }
    }
};

