<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Firma del recibo Anexo III por empresa (con fila global opcional).
 * Menú: Configuración → por módulo → Sueldos.
 */
return new class extends Migration
{
    private const MENU_URL = 'sueldos/recibo-firma';

    private const MENU_NOMBRE = 'Firma del recibo';

    private const MODULO_URL = '#config-modulo-sueldos';

    private const GRUPO_URL = '#configuracion-por-modulo';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Editar firma del recibo de sueldos', 'slug' => 'editar-recibo-firma-sueldos'],
        ['nombre' => 'Actualizar firma del recibo de sueldos', 'slug' => 'actualizar-recibo-firma-sueldos'],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'enc-Capital Humano',
        'op-Capital Humano',
        'ger-capitalhumano',
        'opcont-capitalhumano',
        'Enc-sistemas',
        'Tecnico de Tecnología',
        'op-Gerencia de Tecnologia',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('sueldos_recibo_firma')) {
            Schema::create('sueldos_recibo_firma', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->string('nombre', 120)->nullable();
                $table->string('cargo', 120)->nullable();
                $table->string('imagen', 255)->nullable();
                $table->timestamps();

                $table->unique('empresa_id', 'sueldos_recibo_firma_empresa_uq');
                $table->foreign('empresa_id')
                    ->references('id')
                    ->on('empresa')
                    ->nullOnDelete();
            });
        }

        // Semilla global con la firma actual (Erika / Capital Humano).
        if (! DB::table('sueldos_recibo_firma')->whereNull('empresa_id')->exists()) {
            DB::table('sueldos_recibo_firma')->insert([
                'empresa_id' => null,
                'nombre' => 'ERIKA PEREZ',
                'cargo' => 'CAPITAL HUMANO',
                'imagen' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Limpia claves viejas de Configuración general (eran globales).
        if (Schema::hasTable('parametro_sistema')) {
            DB::table('parametro_sistema')
                ->whereIn('clave', [
                    'sueldos_recibo_firma_nombre',
                    'sueldos_recibo_firma_cargo',
                    'sueldos_recibo_firma_imagen',
                ])
                ->delete();
        }

        $this->asegurarMenuYPermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('sueldos_recibo_firma');
        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarMenuYPermisos(): void
    {
        $configRootId = $this->resolverMenuConfiguracionId();
        $grupoId = (int) (DB::table('menu')->where('url', self::GRUPO_URL)->value('id') ?? 0);
        $moduloId = (int) (DB::table('menu')->where('url', self::MODULO_URL)->value('id') ?? 0);
        if ($configRootId === 0 || $grupoId === 0 || $moduloId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, self::MENU_NOMBRE, $moduloId, $orden, 'fa-pencil');

        $rolIds = $this->resolverRolIds(self::ROLES);
        $this->asignarRolesMenu($menuId, $rolIds);
        $this->asignarRolesMenu($moduloId, $rolIds);
        $this->asignarRolesMenu($grupoId, $rolIds);
        $this->asignarRolesMenu($configRootId, $rolIds);

        foreach (self::PERMISOS as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Configuración')
            ->value('id') ?? 0);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->where('menu_id', $padre)->value('id') ?? 0);
        if ($id === 0) {
            $id = (int) (DB::table('menu')->where('url', $url)->orderBy('id')->value('id') ?? 0);
        }
        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
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

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
