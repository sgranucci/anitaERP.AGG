<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F4 — gobierno BRE: versiones, permisos diseñar/activar.
 */
return new class extends Migration
{
    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Diseñar reglas de negocio', 'slug' => 'disenar-regla-negocio'],
        ['nombre' => 'Activar reglas de negocio', 'slug' => 'activar-regla-negocio'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('regla_negocio_version')) {
            Schema::create('regla_negocio_version', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('regla_negocio_id');
                $table->unsignedInteger('nro_version');
                $table->json('snapshot');
                $table->string('motivo', 40)->default('update');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->index(['regla_negocio_id', 'nro_version'], 'idx_regla_negocio_version_regla');
                $table->foreign('regla_negocio_id', 'fk_regla_negocio_version_regla')
                    ->references('id')->on('regla_negocio')->onDelete('cascade');
            });
        }

        $this->seedPermisos();
    }

    public function down(): void
    {
        Schema::dropIfExists('regla_negocio_version');

        if (! Schema::hasTable('permiso')) {
            return;
        }
        $permisoIds = DB::table('permiso')
            ->whereIn('slug', array_map(fn ($p) => $p['slug'], self::PERMISOS))
            ->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        if (class_exists(SuitecrmPermiso::class)) {
            SuitecrmPermiso::flushCachePermisos();
        }
    }

    private function seedPermisos(): void
    {
        if (! Schema::hasTable('permiso') || ! Schema::hasTable('regla_negocio')) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', 'configuracion/regla-negocio')->value('id') ?? 0);
        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
        }

        $rolIds = $this->resolverRolIds();
        $ref = (int) (DB::table('permiso')->where('slug', 'actualizar-regla-negocio')->value('id') ?? 0);
        if ($ref > 0) {
            $extra = DB::table('permiso_rol')->where('permiso_id', $ref)->pluck('rol_id')
                ->map(fn ($id) => (int) $id)->all();
            $rolIds = array_values(array_unique(array_merge($rolIds, $extra)));
        }

        foreach ($rolIds as $rolId) {
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

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        if (! Schema::hasTable('rol')) {
            return [];
        }

        return DB::table('rol')
            ->where(function ($q) {
                $q->whereIn('nombre', ['administrador', 'Enc-sistemas', 'Enc-admin', 'Ger-administracion'])
                    ->orWhere('nombre', 'like', '%dmin%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
