<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplazo por vacaciones de destinatarios de avisos (todas las instalaciones).
 * Misma idea que el reemplazo de firmante del árbol: usuario_orig_id + log con vence_el.
 */
return new class extends Migration
{
    private const HIJO_URL = 'configuracion/reemplazo-destinatario-aviso';

    private const HIJO_NOMBRE = 'Reemplazo destinatario aviso';

    private const HIJO_ICONO = 'fa-user-clock';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar reemplazo destinatario aviso', 'slug' => 'listar-reemplazo-destinatario-aviso'],
        ['nombre' => 'Ejecutar reemplazo destinatario aviso', 'slug' => 'ejecutar-reemplazo-destinatario-aviso'],
    ];

    public function up(): void
    {
        if (Schema::hasTable('modulo_aviso_destinatario')
            && ! Schema::hasColumn('modulo_aviso_destinatario', 'usuario_orig_id')) {
            Schema::table('modulo_aviso_destinatario', function (Blueprint $table) {
                $table->unsignedBigInteger('usuario_orig_id')->nullable()->after('usuario_id');
                $table->index('usuario_orig_id', 'modulo_aviso_dest_usuario_orig_idx');
            });
        }

        if (! Schema::hasTable('modulo_aviso_reemplazo_log')) {
            Schema::create('modulo_aviso_reemplazo_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('usuario_ejecutor_id')->nullable();
                $table->unsignedBigInteger('usuario_origen_id');
                $table->unsignedBigInteger('usuario_destino_id');
                $table->string('operacion', 20)->default('reemplazo');
                $table->json('modulos_json')->nullable();
                $table->date('vence_el')->nullable();
                $table->timestamp('restaurado_at')->nullable();
                $table->string('restaurado_modo', 20)->nullable();
                $table->unsignedBigInteger('restauracion_log_id')->nullable();
                $table->unsignedInteger('conteo_destinatarios')->default(0);
                $table->unsignedInteger('conteo_omitidos')->default(0);
                $table->json('detalle_json')->nullable();
                $table->timestamps();
                $table->index(['usuario_origen_id', 'usuario_destino_id'], 'aviso_reemplazo_origen_destino_idx');
                $table->index(['operacion', 'vence_el', 'restaurado_at'], 'aviso_reemplazo_vence_idx');
            });
        }

        $padreId = $this->resolverMenuPadreId();
        $ordenAviso = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('orden') ?? 11);
        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => self::HIJO_NOMBRE,
                'url' => self::HIJO_URL,
                'orden' => $ordenAviso + 1,
                'icono' => self::HIJO_ICONO,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $padreId,
                'nombre' => self::HIJO_NOMBRE,
                'icono' => self::HIJO_ICONO,
                'orden' => $ordenAviso + 1,
                'updated_at' => now(),
            ]);
        }

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
        }

        foreach ($this->resolverRolIds() as $rolId) {
            $this->asegurarMenuRol($menuId, $rolId);
            if ($padreId > 0) {
                $this->asegurarMenuRol($padreId, $rolId);
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoIds = DB::table('permiso')
            ->whereIn('slug', array_map(fn ($p) => $p['slug'], self::PERMISOS))
            ->pluck('id');

        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('modulo_aviso_reemplazo_log');

        if (Schema::hasTable('modulo_aviso_destinatario')
            && Schema::hasColumn('modulo_aviso_destinatario', 'usuario_orig_id')) {
            try {
                Schema::table('modulo_aviso_destinatario', function (Blueprint $table) {
                    $table->dropIndex('modulo_aviso_dest_usuario_orig_idx');
                });
            } catch (\Throwable) {
                //
            }
            Schema::table('modulo_aviso_destinatario', function (Blueprint $table) {
                $table->dropColumn('usuario_orig_id');
            });
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuPadreId(): int
    {
        $padreAviso = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('menu_id') ?? 0);
        if ($padreAviso > 0) {
            return $padreAviso;
        }

        foreach (['Configuración', 'Módulo Configuración', 'Configuracion'] as $nombre) {
            $id = (int) (DB::table('menu')->where('nombre', $nombre)->where('menu_id', 0)->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 33;
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = ['nombre' => $nombre, 'slug' => $slug, 'menu_id' => $menuId, 'updated_at' => now()];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = DB::table('rol')
            ->where(function ($q) {
                $q->whereIn('nombre', ['administrador', 'Enc-sistemas', 'Enc-admin', 'Ger-administracion'])
                    ->orWhere('nombre', 'like', '%dmin%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $menuAviso = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('id') ?? 0);
        if ($menuAviso > 0) {
            $extra = DB::table('menu_rol')->where('menu_id', $menuAviso)->pluck('rol_id')
                ->map(fn ($id) => (int) $id)->all();
            $ids = array_values(array_unique(array_merge($ids, $extra)));
        }

        return $ids;
    }
};
