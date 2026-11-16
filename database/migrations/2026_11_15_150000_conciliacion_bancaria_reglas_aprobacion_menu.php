<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas editables, aprobación de cierre, menú config/aging/tablero.
 */
return new class extends Migration
{
    private const MENU_CONFIG = 'contable/configuracion-conciliacion-bancaria';

    private const MENU_AGING = 'contable/conciliacion-bancaria-aging';

    private const MENU_TABLERO = 'contable/conciliacion-bancaria-tablero';

    /** @var list<array{0: string, 1: string}> */
    private const PERMISOS = [
        ['editar-configuracion-conciliacion-bancaria', 'Editar configuración conciliación bancaria'],
        ['actualizar-configuracion-conciliacion-bancaria', 'Actualizar configuración conciliación bancaria'],
        ['listar-conciliacion-bancaria-aging', 'Listar aging conciliación bancaria'],
        ['listar-conciliacion-bancaria-tablero', 'Listar tablero conciliación bancaria'],
        ['aprobar-conciliacion-bancaria', 'Aprobar cierre conciliación bancaria'],
        ['alinear-conciliacion-bancaria-excel', 'Alinear Excel Contaduría conciliación bancaria'],
    ];

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Ger-administracion', 'Enc-admin'];

    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_regla')) {
            Schema::create('conciliacion_bancaria_regla', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('clave', 80)->unique();
                $table->text('valor')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('conciliacion_bancaria_ejecucion')) {
            Schema::table('conciliacion_bancaria_ejecucion', function (Blueprint $table) {
                if (! Schema::hasColumn('conciliacion_bancaria_ejecucion', 'aprobado_at')) {
                    $table->timestamp('aprobado_at')->nullable()->after('cierre_snapshot_json');
                }
                if (! Schema::hasColumn('conciliacion_bancaria_ejecucion', 'aprobado_por_usuario_id')) {
                    $table->unsignedBigInteger('aprobado_por_usuario_id')->nullable()->after('aprobado_at');
                }
            });
        }

        $this->seedMenusYPermisos();
    }

    public function down(): void
    {
        foreach ([self::MENU_CONFIG, self::MENU_AGING, self::MENU_TABLERO] as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        $slugs = array_column(self::PERMISOS, 0);
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        if (Schema::hasTable('conciliacion_bancaria_ejecucion')) {
            Schema::table('conciliacion_bancaria_ejecucion', function (Blueprint $table) {
                if (Schema::hasColumn('conciliacion_bancaria_ejecucion', 'aprobado_por_usuario_id')) {
                    $table->dropColumn('aprobado_por_usuario_id');
                }
                if (Schema::hasColumn('conciliacion_bancaria_ejecucion', 'aprobado_at')) {
                    $table->dropColumn('aprobado_at');
                }
            });
        }

        Schema::dropIfExists('conciliacion_bancaria_regla');
        SuitecrmPermiso::flushCachePermisos();
    }

    private function seedMenusYPermisos(): void
    {
        $grupoId = (int) (DB::table('menu')->where('url', '#configuracion-por-modulo')->value('id') ?? 0);
        $moduloContableId = $this->resolverModuloContableId($grupoId);
        $padreOperativoId = (int) (DB::table('menu')
            ->where('nombre', 'Módulo Contable')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        if ($moduloContableId > 0) {
            $configId = $this->upsertMenu(
                self::MENU_CONFIG,
                'Conciliación bancaria (reglas)',
                $moduloContableId,
                'fa-sliders-h',
            );
            $this->asignarMenuRoles($configId, $rolIds);
            $this->asignarMenuRoles($moduloContableId, $rolIds);
            if ($grupoId > 0) {
                $this->asignarMenuRoles($grupoId, $rolIds);
            }
        }

        if ($padreOperativoId > 0) {
            $agingId = $this->upsertMenu(
                self::MENU_AGING,
                'Aging conciliación bancaria',
                $padreOperativoId,
                'fa-hourglass-half',
            );
            $tableroId = $this->upsertMenu(
                self::MENU_TABLERO,
                'Tablero conciliación bancaria',
                $padreOperativoId,
                'fa-tachometer-alt',
            );
            $this->asignarMenuRoles($agingId, $rolIds);
            $this->asignarMenuRoles($tableroId, $rolIds);
            $this->asignarMenuRoles($padreOperativoId, $rolIds);
        }

        $menuConfigId = (int) (DB::table('menu')->where('url', self::MENU_CONFIG)->value('id') ?? 0);
        $menuAgingId = (int) (DB::table('menu')->where('url', self::MENU_AGING)->value('id') ?? 0);
        $menuTableroId = (int) (DB::table('menu')->where('url', self::MENU_TABLERO)->value('id') ?? 0);
        $menuConcId = (int) (DB::table('menu')->where('url', 'contable/conciliacion-bancaria')->value('id') ?? 0);

        foreach (self::PERMISOS as [$slug, $nombre]) {
            $menuId = match (true) {
                str_contains($slug, 'configuracion') => $menuConfigId,
                str_contains($slug, 'aging') => $menuAgingId,
                str_contains($slug, 'tablero') => $menuTableroId,
                default => $menuConcId > 0 ? $menuConcId : $menuConfigId,
            };
            $permisoId = $this->upsertPermiso($slug, $nombre, $menuId);
            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverModuloContableId(int $grupoId): int
    {
        $id = (int) (DB::table('menu')->where('url', '#config-modulo-contable')->value('id') ?? 0);
        if ($id > 0 || $grupoId <= 0) {
            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $grupoId,
            'nombre' => 'Contable',
            'url' => '#config-modulo-contable',
            'orden' => 40,
            'icono' => 'fa-balance-scale',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->orWhereRaw('LOWER(nombre) LIKE ?', ['%contadur%'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function upsertMenu(string $url, string $nombre, int $padre, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $padre)->max('orden') ?? 0) + 1;
        $payload = [
            'menu_id' => $padre,
            'nombre' => $nombre,
            'url' => $url,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function upsertPermiso(string $slug, string $nombre, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
    }

    /** @param  list<int>  $rolIds */
    private function asignarMenuRoles(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->updateOrInsert(
                ['menu_id' => $menuId, 'rol_id' => $rolId],
                []
            );
        }
    }
};
