<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Griselda Dominick (gdominick, única en Enc-admin) puede operar y confirmar cobranzas.
 * Asigna menú Cobranzas + permisos del ABM (incluye confirmar-cobranza).
 */
return new class extends Migration
{
    private const ROL = 'Enc-admin';

    private const USUARIO = 'gdominick';

    private const MENU_URL = 'caja/cobranza';

    /** @var list<string> */
    private const SLUGS = [
        'listar-cobranza',
        'crear-cobranza',
        'editar-cobranza',
        'actualizar-cobranza',
        'borrar-cobranza',
        'emitir-cobranza',
        'confirmar-cobranza',
        'revertir-cobranza',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $usuarioId = (int) (DB::table('usuario')->where('usuario', self::USUARIO)->value('id') ?? 0);
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($usuarioId <= 0 || $rolId <= 0) {
            return;
        }

        // Solo si sigue siendo la única del rol (evita ampliar el alcance sin querer).
        $otros = (int) DB::table('usuario_rol')
            ->where('rol_id', $rolId)
            ->where('usuario_id', '!=', $usuarioId)
            ->count();
        if ($otros > 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            $this->asegurarMenuRol($menuId, $rolId);
            $this->propagarMenuPadres($menuId, $rolId);
        }

        foreach (self::SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        PermisoCacheSupport::forgetRol($rolId);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->where('rol_id', $rolId)->whereIn('permiso_id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->delete();
        }

        PermisoCacheSupport::forgetRol($rolId);
        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function propagarMenuPadres(int $menuId, int $rolId): void
    {
        $actual = $menuId;
        for ($guard = 0; $guard < 8; $guard++) {
            $padre = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
            if ($padre <= 0) {
                break;
            }
            $this->asegurarMenuRol($padre, $rolId);
            $actual = $padre;
        }
    }
};
