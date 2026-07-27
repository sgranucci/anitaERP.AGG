<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const HIJO_URL = 'configuracion/reemplazo-firmante-arbol';

    private const HIJO_NOMBRE = 'Reemplazo firmante árbol';

    private const HIJO_ICONO = 'fa-exchange-alt';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar reemplazo firmante árbol', 'slug' => 'listar-reemplazo-firmante-arbol'],
        ['nombre' => 'Ejecutar reemplazo firmante árbol', 'slug' => 'ejecutar-reemplazo-firmante-arbol'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('arbol_reemplazo_firmante_log')) {
            Schema::create('arbol_reemplazo_firmante_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('usuario_ejecutor_id')->nullable();
                $table->unsignedBigInteger('usuario_origen_id');
                $table->unsignedBigInteger('usuario_destino_id');
                $table->boolean('incluir_globales')->default(true);
                $table->boolean('incluir_conceptos_sp')->default(true);
                $table->boolean('actualizar_pendientes')->default(true);
                $table->boolean('reenviar_correo')->default(true);
                $table->json('tipos_json')->nullable();
                $table->unsignedInteger('conteo_niveles')->default(0);
                $table->unsignedInteger('conteo_conceptos_sp')->default(0);
                $table->unsignedInteger('conteo_pendientes')->default(0);
                $table->unsignedInteger('conteo_correos')->default(0);
                $table->json('detalle_json')->nullable();
                $table->timestamps();
                $table->index(['usuario_origen_id', 'usuario_destino_id'], 'arbol_reemplazo_origen_destino_idx');
            });
        }

        $padreId = $this->resolverMenuConfiguracionId();
        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $ordenArbol = (int) (DB::table('menu')->where('url', 'configuracion/arbolaprobacion')->value('orden') ?? 13);
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => self::HIJO_NOMBRE,
                'url' => self::HIJO_URL,
                'orden' => $ordenArbol + 1,
                'icono' => self::HIJO_ICONO,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::HIJO_NOMBRE,
                'icono' => self::HIJO_ICONO,
                'updated_at' => now(),
            ]);
            $padreId = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? $padreId);
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

        Schema::dropIfExists('arbol_reemplazo_firmante_log');
        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuConfiguracionId(): int
    {
        $padreArbol = (int) (DB::table('menu')->where('url', 'configuracion/arbolaprobacion')->value('menu_id') ?? 0);
        if ($padreArbol > 0) {
            return $padreArbol;
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
                $q->where('nombre', 'administrador')
                    ->orWhere('nombre', 'like', '%dmin%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        // Quienes ya tienen el menú de árbol de aprobación.
        $menuArbol = (int) (DB::table('menu')->where('url', 'configuracion/arbolaprobacion')->value('id') ?? 0);
        if ($menuArbol > 0) {
            $extra = DB::table('menu_rol')->where('menu_id', $menuArbol)->pluck('rol_id')
                ->map(fn ($id) => (int) $id)->all();
            $ids = array_values(array_unique(array_merge($ids, $extra)));
        }

        return $ids;
    }
};
