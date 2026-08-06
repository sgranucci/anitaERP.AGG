<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_SUBMENU = 'Tablas de liquidación';

    private const HIJO_URL = 'sueldos/antiguedad-tabla';

    private const HIJO_NOMBRE = 'Tablas de antigüedad';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar antiguedad tabla sueldos', 'slug' => 'listar-antiguedad-tabla-sueldos'],
        ['nombre' => 'Crear antiguedad tabla sueldos', 'slug' => 'crear-antiguedad-tabla-sueldos'],
        ['nombre' => 'Editar antiguedad tabla sueldos', 'slug' => 'editar-antiguedad-tabla-sueldos'],
        ['nombre' => 'Actualizar antiguedad tabla sueldos', 'slug' => 'actualizar-antiguedad-tabla-sueldos'],
        ['nombre' => 'Borrar antiguedad tabla sueldos', 'slug' => 'borrar-antiguedad-tabla-sueldos'],
    ];

    public function up(): void
    {
        Schema::create('antiguedad_tabla_sueldos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->unsignedSmallInteger('codigo');
            $table->string('descripcion', 80);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'codigo'], 'antiguedad_tabla_emp_codigo_uq');
        });

        Schema::create('antiguedad_tabla_tramo_sueldos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('antiguedad_tabla_id');
            $table->unsignedSmallInteger('anio');
            $table->decimal('porcentaje', 12, 6)->default(0);
            $table->decimal('cantidad', 18, 6)->default(0);
            $table->unsignedSmallInteger('nro_linea')->default(1);
            $table->timestamps();
            $table->foreign('antiguedad_tabla_id', 'antiguedad_tramo_tabla_fk')
                ->references('id')->on('antiguedad_tabla_sueldos')->cascadeOnDelete();
            $table->unique(['antiguedad_tabla_id', 'anio'], 'antiguedad_tramo_anio_uq');
        });

        $this->sembrarDesdeAnitaJson();
        $this->instalarMenus();
    }

    public function down(): void
    {
        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('antiguedad_tabla_tramo_sueldos');
        Schema::dropIfExists('antiguedad_tabla_sueldos');
        SuitecrmPermiso::flushCachePermisos();
    }

    private function sembrarDesdeAnitaJson(): void
    {
        $path = database_path('data/antmov_anita.json');
        if (! is_readable($path)) {
            return;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return;
        }

        $ahora = now();
        foreach ($data as $codigo => $tramos) {
            if (! is_array($tramos)) {
                continue;
            }
            $tablaId = (int) DB::table('antiguedad_tabla_sueldos')->insertGetId([
                'empresa_id' => null,
                'codigo' => (int) $codigo,
                'descripcion' => 'Tabla antigüedad '.(int) $codigo,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            $nro = 0;
            foreach ($tramos as $t) {
                $anio = (int) ($t['anio'] ?? 0);
                if ($anio <= 0) {
                    continue;
                }
                $nro++;
                DB::table('antiguedad_tabla_tramo_sueldos')->insert([
                    'antiguedad_tabla_id' => $tablaId,
                    'anio' => $anio,
                    'porcentaje' => (float) ($t['pct'] ?? $t['porcentaje'] ?? 0),
                    'cantidad' => (float) ($t['cant'] ?? $t['cantidad'] ?? 0),
                    'nro_linea' => $nro,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    private function instalarMenus(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        if ($moduloId === 0) {
            return;
        }

        $submenuId = (int) (DB::table('menu')->where('nombre', self::MENU_SUBMENU)->where('menu_id', $moduloId)->value('id') ?? 0);
        if ($submenuId === 0) {
            $submenuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_SUBMENU,
                'url' => '#',
                'menu_id' => $moduloId,
                'orden' => 3,
                'icono' => 'fa-calculator',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')
            ->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%apital%umano%')
            ->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($moduloId, $rolId);
            $this->asegurarMenuRol($submenuId, $rolId);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenuHijo(self::HIJO_URL, self::HIJO_NOMBRE, $submenuId, $orden);
        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
        }
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($menuId, $rolId);
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
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
