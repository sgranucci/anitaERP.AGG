<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: Emely Sánchez y María Micaletti siguen en compras, pero cada una
 * necesita un acceso que Op-Compras no tiene y Enc-compras sí.
 *
 * El permiso vive en el rol activo de la sesión. Un segundo rol las obligaría
 * a elegir y perderían Compras. Por eso cada una pasa a un rol que copia
 * Op-Compras y solo suma lo que falta:
 * - esanchez → carga del árbol y reemplazo de firmante (lo de Enc-compras).
 * - mmicaletti → módulo de solicitudes de pago (listar, crear, editar, actualizar).
 *
 * El resto de Op-Compras no cambia.
 */
return new class extends Migration
{
    private const ROL_OP = 'Op-Compras';

    private const ROL_ENC = 'Enc-compras';

    private const ROL_EMELY = 'Op-Compras-arbol';

    private const ROL_MARIA = 'Op-Compras-SP';

    private const USUARIO_EMELY = 'esanchez';

    private const USUARIO_MARIA = 'mmicaletti';

    /** @var list<string> */
    private const MENUS_ARBOL = [
        'configuracion/arbolaprobacion',
        'configuracion/reemplazo-firmante-arbol',
    ];

    /** @var list<string> */
    private const MENUS_SP = [
        'solicitudpago/solicitudpago',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('rol') || ! Schema::hasTable('usuario_rol')) {
            return;
        }

        $rolOpId = $this->rolId(self::ROL_OP);
        $rolEncId = $this->rolId(self::ROL_ENC);
        if ($rolOpId <= 0 || $rolEncId <= 0) {
            return;
        }

        $rolEmelyId = $this->upsertRol(self::ROL_EMELY, $rolOpId);
        $rolMariaId = $this->upsertRol(self::ROL_MARIA, $rolOpId);

        $this->copiarRol($rolOpId, $rolEmelyId);
        $this->copiarRol($rolOpId, $rolMariaId);
        $this->copiarMenus($rolEncId, $rolEmelyId, $this->menuIds(self::MENUS_ARBOL, 'Árbol de aprobación'));
        $this->copiarMenus($rolEncId, $rolMariaId, $this->menuIds(self::MENUS_SP, 'Módulo Solicitudes de Pago'));

        $this->moverUsuario(self::USUARIO_EMELY, $rolOpId, $rolEmelyId);
        $this->moverUsuario(self::USUARIO_MARIA, $rolOpId, $rolMariaId);

        foreach ([$rolOpId, $rolEmelyId, $rolMariaId] as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('usuario_rol')) {
            return;
        }

        $rolOpId = $this->rolId(self::ROL_OP);
        $this->devolverAOp(self::USUARIO_EMELY, $rolOpId, $this->rolId(self::ROL_EMELY));
        $this->devolverAOp(self::USUARIO_MARIA, $rolOpId, $this->rolId(self::ROL_MARIA));
        $this->borrarRolSiQuedaVacio(self::ROL_EMELY);
        $this->borrarRolSiQuedaVacio(self::ROL_MARIA);

        PermisoCacheSupport::forgetRol($rolOpId);
        SuitecrmPermiso::flushCachePermisos();
    }

    private function rolId(string $nombre): int
    {
        if (! Schema::hasTable('rol')) {
            return 0;
        }

        return (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
    }

    private function upsertRol(string $nombre, int $rolBaseId): int
    {
        $id = $this->rolId($nombre);
        $centrocostoId = DB::table('rol')->where('id', $rolBaseId)->value('centrocosto_id');
        if ($id > 0) {
            DB::table('rol')->where('id', $id)->update([
                'centrocosto_id' => $centrocostoId,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('rol')->insertGetId([
            'nombre' => $nombre,
            'centrocosto_id' => $centrocostoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function copiarRol(int $desdeRolId, int $haciaRolId): void
    {
        if ($desdeRolId <= 0 || $haciaRolId <= 0) {
            return;
        }

        foreach (DB::table('menu_rol')->where('rol_id', $desdeRolId)->pluck('menu_id') as $menuId) {
            $this->asegurarMenuRol((int) $menuId, $haciaRolId);
        }

        foreach (DB::table('permiso_rol')->where('rol_id', $desdeRolId)->pluck('permiso_id') as $permisoId) {
            $this->asegurarPermisoRol((int) $permisoId, $haciaRolId);
        }
    }

    /**
     * @param  list<string>  $urls
     * @return list<int>
     */
    private function menuIds(array $urls, string $padreNombre): array
    {
        $ids = [];
        foreach ($urls as $url) {
            $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
                $padreId = (int) (DB::table('menu')->where('id', $id)->value('menu_id') ?? 0);
                if ($padreId > 0) {
                    $ids[] = $padreId;
                }
            }
        }

        $padre = (int) (DB::table('menu')
            ->where('nombre', $padreNombre)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($padre > 0) {
            $ids[] = $padre;
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /**
     * @param  list<int>  $menuIds
     */
    private function copiarMenus(int $desdeRolId, int $haciaRolId, array $menuIds): void
    {
        if ($desdeRolId <= 0 || $haciaRolId <= 0 || $menuIds === []) {
            return;
        }

        foreach ($menuIds as $menuId) {
            if (DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $desdeRolId)->exists()) {
                $this->asegurarMenuRol($menuId, $haciaRolId);
            }
        }

        $permisoIds = DB::table('permiso_rol')
            ->join('permiso', 'permiso.id', '=', 'permiso_rol.permiso_id')
            ->where('permiso_rol.rol_id', $desdeRolId)
            ->whereIn('permiso.menu_id', $menuIds)
            ->pluck('permiso_rol.permiso_id');

        foreach ($permisoIds as $permisoId) {
            $this->asegurarPermisoRol((int) $permisoId, $haciaRolId);
        }
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function asegurarPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function moverUsuario(string $login, int $rolOpId, int $rolNuevoId): void
    {
        $usuarioId = (int) (DB::table('usuario')->where('usuario', $login)->value('id') ?? 0);
        if ($usuarioId <= 0 || $rolNuevoId <= 0) {
            return;
        }

        if (! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolNuevoId)->exists()) {
            DB::table('usuario_rol')->insert([
                'usuario_id' => $usuarioId,
                'rol_id' => $rolNuevoId,
            ]);
        }

        if ($rolOpId > 0) {
            DB::table('usuario_rol')
                ->where('usuario_id', $usuarioId)
                ->where('rol_id', $rolOpId)
                ->delete();
        }
    }

    private function devolverAOp(string $login, int $rolOpId, int $rolNuevoId): void
    {
        $usuarioId = (int) (DB::table('usuario')->where('usuario', $login)->value('id') ?? 0);
        if ($usuarioId <= 0) {
            return;
        }

        if ($rolOpId > 0 && ! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolOpId)->exists()) {
            DB::table('usuario_rol')->insert([
                'usuario_id' => $usuarioId,
                'rol_id' => $rolOpId,
            ]);
        }

        if ($rolNuevoId > 0) {
            DB::table('usuario_rol')
                ->where('usuario_id', $usuarioId)
                ->where('rol_id', $rolNuevoId)
                ->delete();
        }
    }

    private function borrarRolSiQuedaVacio(string $nombre): void
    {
        $rolId = $this->rolId($nombre);
        if ($rolId <= 0) {
            return;
        }
        if (DB::table('usuario_rol')->where('rol_id', $rolId)->exists()) {
            return;
        }

        DB::table('permiso_rol')->where('rol_id', $rolId)->delete();
        DB::table('menu_rol')->where('rol_id', $rolId)->delete();
        DB::table('rol')->where('id', $rolId)->delete();
        PermisoCacheSupport::forgetRol($rolId);
    }
};
