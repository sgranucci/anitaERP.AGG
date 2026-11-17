<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de reglas de negocio transversal (sync decidir).
 * Genérico a todos los clientes. Sin SoftDeletes.
 * Acciones tipadas de catálogo cerrado (sin script libre).
 */
return new class extends Migration
{
    private const HIJO_URL = 'configuracion/regla-negocio';

    private const HIJO_NOMBRE = 'Reglas de negocio';

    private const HIJO_ICONO = 'fa-balance-scale';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar reglas de negocio', 'slug' => 'listar-regla-negocio'],
        ['nombre' => 'Actualizar reglas de negocio', 'slug' => 'actualizar-regla-negocio'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('regla_negocio_config')) {
            Schema::create('regla_negocio_config', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->boolean('motor_habilitado')->default(false);
                $table->timestamps();
            });
            DB::table('regla_negocio_config')->insert([
                'motor_habilitado' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('regla_negocio')) {
            Schema::create('regla_negocio', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 80);
                $table->string('nombre', 160);
                $table->string('descripcion', 500)->nullable();
                $table->string('dominio', 40)->default('configuracion');
                $table->string('punto', 120);
                $table->boolean('activo')->default(false);
                $table->unsignedSmallInteger('prioridad')->default(100);
                $table->boolean('stop_on_match')->default(true);
                $table->json('condiciones')->nullable();
                $table->json('acciones')->nullable();
                $table->timestamps();

                $table->unique('codigo', 'uk_regla_negocio_codigo');
                $table->index(['punto', 'activo', 'prioridad'], 'idx_regla_negocio_punto_activo');
                $table->index(['dominio', 'activo'], 'idx_regla_negocio_dominio');
            });
        }

        if (! Schema::hasTable('regla_negocio_log')) {
            Schema::create('regla_negocio_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('regla_negocio_id')->nullable();
                $table->string('punto', 120);
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->boolean('ok')->default(true);
                $table->boolean('permitido')->default(true);
                $table->string('detalle', 500)->nullable();
                $table->timestamp('ejecutado_en')->useCurrent();
                $table->timestamps();

                $table->index(['regla_negocio_id', 'ejecutado_en'], 'idx_regla_negocio_log_regla');
                $table->index(['punto', 'ejecutado_en'], 'idx_regla_negocio_log_punto');
                $table->foreign('regla_negocio_id', 'fk_regla_negocio_log_regla')
                    ->references('id')->on('regla_negocio')->onDelete('set null');
            });
        }

        $this->seedMenuPermisos();
    }

    public function down(): void
    {
        Schema::dropIfExists('regla_negocio_log');
        Schema::dropIfExists('regla_negocio');
        Schema::dropIfExists('regla_negocio_config');

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', array_map(fn ($p) => $p['slug'], self::PERMISOS))
            ->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            if (Schema::hasTable('menu_rol')) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            }
            DB::table('menu')->where('id', $menuId)->delete();
        }

        if (class_exists(SuitecrmPermiso::class)) {
            SuitecrmPermiso::flushCachePermisos();
        }
    }

    private function seedMenuPermisos(): void
    {
        if (! Schema::hasTable('menu')) {
            return;
        }

        $padreId = $this->resolverMenuPadreId();
        $ordenRef = (int) (DB::table('menu')->where('url', 'configuracion/aviso-regla')->value('orden') ?? 0);
        if ($ordenRef <= 0) {
            $ordenRef = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('orden') ?? 11);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        $now = now();
        if ($menuId <= 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => self::HIJO_NOMBRE,
                'url' => self::HIJO_URL,
                'orden' => $ordenRef + 1,
                'icono' => self::HIJO_ICONO,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $padreId,
                'nombre' => self::HIJO_NOMBRE,
                'icono' => self::HIJO_ICONO,
                'orden' => $ordenRef + 1,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasTable('permiso')) {
            return;
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

        if (class_exists(SuitecrmPermiso::class)) {
            SuitecrmPermiso::flushCachePermisos();
        }
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
        if (! Schema::hasTable('menu_rol') || $menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        if (! Schema::hasTable('rol')) {
            return [];
        }

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

        $menuAviso = (int) (DB::table('menu')->where('url', 'configuracion/aviso-regla')->value('id') ?? 0);
        if ($menuAviso <= 0) {
            $menuAviso = (int) (DB::table('menu')->where('url', 'configuracion/modulo-aviso')->value('id') ?? 0);
        }
        if ($menuAviso > 0 && Schema::hasTable('menu_rol')) {
            $extra = DB::table('menu_rol')->where('menu_id', $menuAviso)->pluck('rol_id')
                ->map(fn ($id) => (int) $id)->all();
            $ids = array_values(array_unique(array_merge($ids, $extra)));
        }

        return $ids;
    }
};
