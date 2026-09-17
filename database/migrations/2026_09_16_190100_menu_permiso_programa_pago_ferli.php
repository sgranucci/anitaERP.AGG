<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: menú y permisos del Programa de pagos (cashflow).
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/programa-pago';

    private const MENU_NOMBRE = 'Programa de pagos';

    private const PADRE_URL = 'compras/propuesta-pago';

    /** @var list<string> */
    private const ROLES = ['administrador'];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar programa de pagos', 'slug' => 'listar-programa-pago'],
        ['nombre' => 'Crear programa de pagos', 'slug' => 'crear-programa-pago'],
        ['nombre' => 'Editar programa de pagos', 'slug' => 'editar-programa-pago'],
        ['nombre' => 'Actualizar programa de pagos', 'slug' => 'actualizar-programa-pago'],
        ['nombre' => 'Borrar programa de pagos', 'slug' => 'borrar-programa-pago'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $padreId = (int) (DB::table('menu')->where('url', self::PADRE_URL)->value('menu_id') ?? 0);
        if ($padreId <= 0) {
            $padreId = (int) (DB::table('menu')
                ->where('nombre', 'Cuentas a pagar')
                ->where('menu_id', 0)
                ->value('id') ?? 0);
        }
        if ($padreId <= 0) {
            return;
        }

        $ordenPropuesta = (int) (DB::table('menu')->where('url', self::PADRE_URL)->value('orden') ?? 6);
        $orden = $ordenPropuesta + 1;

        $existenteId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($existenteId > 0) {
            $menuId = $existenteId;
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'menu_id' => $padreId,
                'orden' => $orden,
                'icono' => 'fa-calendar-check',
                'updated_at' => now(),
            ]);
        } else {
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'menu_id' => $padreId,
                'orden' => $orden,
                'icono' => 'fa-calendar-check',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rolId]);
            }
        }

        foreach (self::PERMISOS as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $perm['nombre'],
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
            } else {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
