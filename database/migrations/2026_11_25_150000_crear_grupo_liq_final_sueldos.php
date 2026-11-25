<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mapeo sindicato (+ motivo de egreso) → grupo de conceptos para liquidación final.
 * Evita asignar a mano el grupo 15/16/… en cada legajo como en Anita.
 */
return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_SUBMENU = 'Tablas de liquidación';

    private const HIJO_URL = 'sueldos/grupo-liq-final';

    private const HIJO_NOMBRE = 'Grupos liq. final';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar grupos liq. final sueldos', 'slug' => 'listar-grupo-liq-final-sueldos'],
        ['nombre' => 'Crear grupos liq. final sueldos', 'slug' => 'crear-grupo-liq-final-sueldos'],
        ['nombre' => 'Editar grupos liq. final sueldos', 'slug' => 'editar-grupo-liq-final-sueldos'],
        ['nombre' => 'Actualizar grupos liq. final sueldos', 'slug' => 'actualizar-grupo-liq-final-sueldos'],
        ['nombre' => 'Borrar grupos liq. final sueldos', 'slug' => 'borrar-grupo-liq-final-sueldos'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('grupo_liq_final_sueldos')) {
            Schema::create('grupo_liq_final_sueldos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('sindicato_id');
                // null = cualquier motivo (fallback del sindicato en esa empresa)
                $table->unsignedBigInteger('motivoegreso_id')->nullable();
                // Espejo de motivoegreso_id (0 si null) para unique estable en MySQL.
                $table->unsignedInteger('motivo_key')->default(0);
                $table->unsignedBigInteger('grupo_concepto_id');
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresa')->cascadeOnDelete();
                $table->foreign('sindicato_id')->references('id')->on('sindicato_sueldos')->cascadeOnDelete();
                $table->foreign('motivoegreso_id')->references('id')->on('motivoegreso_sueldos')->nullOnDelete();
                $table->foreign('grupo_concepto_id', 'glf_grupo_fk')
                    ->references('id')->on('grupo_concepto_sueldos')->cascadeOnDelete();

                $table->unique(['empresa_id', 'sindicato_id', 'motivo_key'], 'glf_emp_sind_motivo_uq');
                $table->index(['grupo_concepto_id'], 'glf_grupo_ix');
            });
        } elseif (! Schema::hasColumn('grupo_liq_final_sueldos', 'motivo_key')) {
            Schema::table('grupo_liq_final_sueldos', function (Blueprint $table) {
                $table->unsignedInteger('motivo_key')->default(0)->after('motivoegreso_id');
                $table->unique(['empresa_id', 'sindicato_id', 'motivo_key'], 'glf_emp_sind_motivo_uq');
            });
        }

        $this->asegurarMenuYPermisos();
    }

    public function down(): void
    {
        $slugs = array_map(fn ($p) => $p['slug'], self::PERMISOS);
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            $rolIds = DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->pluck('rol_id')->unique();
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
            foreach ($rolIds as $rolId) {
                PermisoCacheSupport::forgetRol((int) $rolId);
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('grupo_liq_final_sueldos');
        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarMenuYPermisos(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        if ($moduloId === 0) {
            return;
        }

        $submenuId = (int) (DB::table('menu')->where('nombre', self::MENU_SUBMENU)->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($submenuId === 0) {
            $ordenSubmenu = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
            $submenuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_SUBMENU, 'url' => '#', 'menu_id' => $moduloId,
                'orden' => $ordenSubmenu, 'icono' => 'fa-table', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $existente = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        $orden = $existente > 0
            ? (int) (DB::table('menu')->where('id', $existente)->value('orden') ?? 0)
            : (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenuHijo(self::HIJO_URL, self::HIJO_NOMBRE, $submenuId, $orden);

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
        }

        $rolIds = DB::table('rol')
            ->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%apital%umano%')
            ->orWhere('nombre', 'like', '%capitalhumano%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($moduloId, $rolId);
            $this->asegurarMenuRol($submenuId, $rolId);
            $this->asegurarMenuRol($menuId, $rolId);
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
            PermisoCacheSupport::forgetRol($rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenuHijo(string $url, string $nombre, int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre, 'url' => $url, 'menu_id' => $padreId,
            'orden' => $orden, 'icono' => null, 'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
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
};
