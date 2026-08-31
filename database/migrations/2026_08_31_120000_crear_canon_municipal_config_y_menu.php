<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canon municipal bingo (4%): config por empresa + menú bajo Presentaciones ARCA.
 * Roles: administrador, Enc-contaduría, Enc-impuestos, Op-impuestos.
 */
return new class extends Migration
{
    private const SUBMENU_NOMBRE = 'Presentaciones ARCA';

    private const MENU_PADRE = 'Módulo Contable';

    private const URL_PROCESO = 'contable/canon-municipal';

    private const URL_CONFIG = 'contable/canon-municipal-config';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Enc-impuestos', 'Op-impuestos'];

    public function up(): void
    {
        if (! Schema::hasTable('canon_municipal_config')) {
            Schema::create('canon_municipal_config', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->unique();
                $table->string('municipio', 120);
                $table->string('legajo', 40);
                $table->string('periodicidad', 20); // semanal | quincenal
                $table->string('plantilla', 40); // biyemas | kandiko | rebisco
                $table->decimal('alicuota', 8, 4)->default(0.04);
                $table->string('firmante_nombre', 120)->default('Marisol Gonzalez');
                $table->string('firmante_cargo', 80)->default('Impuestos');
                $table->string('pie_razon_social', 120)->nullable();
                $table->string('direccion_extra', 255)->nullable();
                $table->string('telefono', 80)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_canon_mun_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
            });
        }

        $this->seedConfigs();
        $this->altaMenuYPermisos();
    }

    public function down(): void
    {
        $slugs = [
            'listar-canon-municipal',
            'exportar-canon-municipal',
            'listar-canon-municipal-config',
            'crear-canon-municipal-config',
            'editar-canon-municipal-config',
            'actualizar-canon-municipal-config',
            'eliminar-canon-municipal-config',
        ];
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        $menuIds = DB::table('menu')->whereIn('url', [self::URL_PROCESO, self::URL_CONFIG])->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        Schema::dropIfExists('canon_municipal_config');
        SuitecrmPermiso::flushCachePermisos();
    }

    private function seedConfigs(): void
    {
        $defaults = [
            [
                'empresa_id' => 1,
                'municipio' => 'Avellaneda',
                'legajo' => '45308',
                'periodicidad' => 'semanal',
                'plantilla' => 'biyemas',
                'alicuota' => 0.04,
                'firmante_nombre' => 'Marisol Gonzalez',
                'firmante_cargo' => 'Impuestos',
                'pie_razon_social' => 'Biyemas S.A.',
                'direccion_extra' => null,
                'telefono' => null,
            ],
            [
                'empresa_id' => 2,
                'municipio' => 'Avellaneda',
                'legajo' => '52.000',
                'periodicidad' => 'semanal',
                'plantilla' => 'kandiko',
                'alicuota' => 0.04,
                'firmante_nombre' => 'Marisol Gonzalez',
                'firmante_cargo' => 'Impuestos',
                'pie_razon_social' => 'Kandiko SA',
                'direccion_extra' => 'B1875ABF, Wilde, Buenos Aires',
                'telefono' => '(54 11) 4229-0900',
            ],
            [
                'empresa_id' => 3,
                'municipio' => 'Florencio Varela',
                'legajo' => '16550',
                'periodicidad' => 'quincenal',
                'plantilla' => 'rebisco',
                'alicuota' => 0.04,
                'firmante_nombre' => 'Marisol Gonzalez',
                'firmante_cargo' => 'Impuestos',
                'pie_razon_social' => 'Rebisco SA',
                'direccion_extra' => null,
                'telefono' => null,
            ],
        ];

        foreach ($defaults as $row) {
            if (! DB::table('empresa')->where('id', $row['empresa_id'])->exists()) {
                continue;
            }
            $existe = DB::table('canon_municipal_config')
                ->where('empresa_id', $row['empresa_id'])
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('canon_municipal_config')->insert(array_merge($row, [
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function altaMenuYPermisos(): void
    {
        $submenuId = $this->resolverSubmenuId();
        if ($submenuId === 0) {
            return;
        }

        $ordenProceso = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
        $menuProceso = $this->upsertMenu(
            self::URL_PROCESO,
            'Canon municipal bingo',
            $submenuId,
            $ordenProceso,
            'fa-landmark',
        );
        $menuConfig = $this->upsertMenu(
            self::URL_CONFIG,
            'Configuración canon municipal',
            $submenuId,
            $ordenProceso + 1,
            'fa-cogs',
        );

        $permisosProceso = [
            ['Listar canon municipal bingo', 'listar-canon-municipal'],
            ['Exportar nota canon municipal', 'exportar-canon-municipal'],
        ];
        $permisosConfig = [
            ['Listar configuración canon municipal', 'listar-canon-municipal-config'],
            ['Crear configuración canon municipal', 'crear-canon-municipal-config'],
            ['Editar configuración canon municipal', 'editar-canon-municipal-config'],
            ['Actualizar configuración canon municipal', 'actualizar-canon-municipal-config'],
            ['Eliminar configuración canon municipal', 'eliminar-canon-municipal-config'],
        ];

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            foreach ([$menuProceso, $menuConfig, $submenuId] as $mid) {
                DB::table('menu_rol')->updateOrInsert(
                    ['menu_id' => $mid, 'rol_id' => $rolId],
                    []
                );
            }
        }

        foreach ($permisosProceso as [$nombre, $slug]) {
            $permisoId = $this->upsertPermiso($nombre, $slug, $menuProceso);
            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }
        foreach ($permisosConfig as [$nombre, $slug]) {
            $permisoId = $this->upsertPermiso($nombre, $slug, $menuConfig);
            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverSubmenuId(): int
    {
        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($padreId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('nombre', self::SUBMENU_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function upsertMenu(string $url, string $nombre, int $padreId, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
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

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }
};
