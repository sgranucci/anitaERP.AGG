<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Solapa Fórmulas del ABM Artículos (peso, coeficiente, id fórmula, etc.).
 * Asigna permisos formula-articulo + menu_rol al rol Enc-produccion-surmar.
 * Solo EL BIERZO.
 */
return new class extends Migration
{
    private const ROL_ID = 10;

    private const MENU_FORMULA_URL = 'stock/formula-articulo';

    /** @var list<string> */
    private const SLUGS = [
        'listar-formula-articulo',
        'editar-formula-articulo',
        'actualizar-formula-articulo',
    ];

    public function up(): void
    {
        if (! $this->esElBierzo()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('id', self::ROL_ID)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_FORMULA_URL)->value('id') ?? 0);
        if ($menuId > 0 && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS)->pluck('id');
        foreach ($permisoIds as $permisoId) {
            $permisoId = (int) $permisoId;
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

        SuitecrmPermiso::flushCachePermisos();
        try {
            cache()->tags('Permiso')->forget('Permiso.rolid.'.$rolId);
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        if (! $this->esElBierzo()) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')
                ->where('rol_id', self::ROL_ID)
                ->whereIn('permiso_id', $permisoIds)
                ->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_FORMULA_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', self::ROL_ID)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
        try {
            cache()->tags('Permiso')->forget('Permiso.rolid.'.self::ROL_ID);
        } catch (\Throwable) {
        }
    }

    private function esElBierzo(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }
};
