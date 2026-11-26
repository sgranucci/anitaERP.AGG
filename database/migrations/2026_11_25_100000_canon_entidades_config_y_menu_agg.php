<?php

declare(strict_types=1);

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración canon entidades de bien público (una fila por empresa) + menú y permisos.
 * Solo AGG: BSA / KSA / RSA. Las filas se cargan desde la pantalla, no desde la migración.
 */
return new class extends Migration
{
    private const URL_CONFIG = 'contable/canon-entidades-config';

    private const MENU_CONFIG_MODULO = '#config-modulo-contable';

    private const SUBMENU_ORGANISMOS = 'Presentaciones a organismos';

    private const MENU_PADRE = 'Módulo Contable';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Enc-impuestos', 'Op-impuestos'];

    /** @var list<array{0: string, 1: string}> */
    private const PERMISOS = [
        ['Listar configuración canon entidades', 'listar-canon-entidades-config'],
        ['Crear configuración canon entidades', 'crear-canon-entidades-config'],
        ['Editar configuración canon entidades', 'editar-canon-entidades-config'],
        ['Actualizar configuración canon entidades', 'actualizar-canon-entidades-config'],
        ['Eliminar configuración canon entidades', 'eliminar-canon-entidades-config'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (! Schema::hasTable('canon_entidades_config')) {
            Schema::create('canon_entidades_config', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->unique();
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->unsignedBigInteger('sector_solicitudpago_id')->nullable();
                $table->unsignedBigInteger('concepto_maquinas_solicitudpago_id')->nullable();
                $table->unsignedBigInteger('concepto_bingo_solicitudpago_id')->nullable();
                $table->unsignedBigInteger('formapagosol_id')->nullable();
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->unsignedBigInteger('centrocosto_id')->nullable();
                $table->unsignedBigInteger('cuentacontable_debe_id')->nullable();
                $table->unsignedBigInteger('cuentacontable_haber_id')->nullable();
                $table->string('plantilla_maquinas', 180)->nullable();
                $table->string('plantilla_bingo', 180)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_canon_ent_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
            });
        }

        $this->altaMenuYPermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', array_column(self::PERMISOS, 1))
            ->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuIds = DB::table('menu')->where('url', self::URL_CONFIG)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        Schema::dropIfExists('canon_entidades_config');
        SuitecrmPermiso::flushCachePermisos();
    }

    private function altaMenuYPermisos(): void
    {
        $configModuloId = (int) (DB::table('menu')->where('url', self::MENU_CONFIG_MODULO)->value('id') ?? 0);
        $organismosId = $this->resolverSubmenuOrganismosId();
        if ($configModuloId === 0 || $organismosId === 0) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        $rolAdmin = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);

        // Canónico: Configuración por módulo → Contable (todos los roles que configuran).
        $ordenCanon = (int) (DB::table('menu')->where('menu_id', $configModuloId)->max('orden') ?? 0) + 1;
        $menuCanon = $this->upsertMenu(
            self::URL_CONFIG,
            'Configuración canon entidades',
            $configModuloId,
            $ordenCanon,
            'fa-cogs',
        );
        $this->propagarMenuRolAAncestros($menuCanon, $rolIds);

        // Atajo: Presentaciones a organismos, solo administrador (regla configuracion-menu-por-modulo).
        $ordenAtajo = (int) (DB::table('menu')->where('menu_id', $organismosId)->max('orden') ?? 0) + 1;
        $menuAtajo = $this->upsertMenu(
            self::URL_CONFIG,
            'Configuración canon entidades',
            $organismosId,
            $ordenAtajo,
            'fa-cogs',
        );
        if ($rolAdmin > 0) {
            DB::table('menu_rol')->updateOrInsert(
                ['menu_id' => $menuAtajo, 'rol_id' => $rolAdmin],
                []
            );
            $this->propagarMenuRolAAncestros($menuAtajo, [$rolAdmin]);
        }

        foreach (self::PERMISOS as [$nombre, $slug]) {
            $permisoId = $this->upsertPermiso($nombre, $slug, $menuCanon);
            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverSubmenuOrganismosId(): int
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
            ->where('nombre', self::SUBMENU_ORGANISMOS)
            ->where('url', '#')
            ->value('id') ?? 0);
    }

    /**
     * Sube por la cadena de padres y da acceso de menú a los roles indicados.
     *
     * @param  list<int>  $rolIds
     */
    private function propagarMenuRolAAncestros(int $menuId, array $rolIds): void
    {
        $actual = $menuId;
        $guardia = 0;
        while ($actual > 0 && $guardia < 10) {
            foreach ($rolIds as $rolId) {
                DB::table('menu_rol')->updateOrInsert(
                    ['menu_id' => $actual, 'rol_id' => $rolId],
                    []
                );
            }
            $actual = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
            $guardia++;
        }
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
        $id = (int) (DB::table('menu')->where('url', $url)->where('menu_id', $padreId)->value('id') ?? 0);
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
