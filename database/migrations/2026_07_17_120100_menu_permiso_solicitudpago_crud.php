<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Solicitudes de Pago';

    private const MENU_URL = 'solicitudpago/solicitudpago';

    private const MENU_NOMBRE = 'Solicitudes de pago';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-impuestos', 'Op-impuestos'];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar solicitud de pago', 'slug' => 'listar-solicitud-pago'],
        ['nombre' => 'Crear solicitud de pago', 'slug' => 'crear-solicitud-pago'],
        ['nombre' => 'Editar solicitud de pago', 'slug' => 'editar-solicitud-pago'],
        ['nombre' => 'Actualizar solicitud de pago', 'slug' => 'actualizar-solicitud-pago'],
        ['nombre' => 'Borrar solicitud de pago', 'slug' => 'borrar-solicitud-pago'],
    ];

    public function up(): void
    {
        $moduloId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_MODULO)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($moduloId === 0) {
            return;
        }

        $existenteId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $orden = $existenteId > 0
            ? (int) (DB::table('menu')->where('id', $existenteId)->value('orden') ?? 0)
            : 1;

        // Insertar como primer hijo del módulo (orden 1); desplazar el resto si es nuevo
        if ($existenteId === 0) {
            DB::table('menu')->where('menu_id', $moduloId)->where('orden', '>=', 1)->increment('orden');
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $menuId = $existenteId;
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $moduloId,
                'nombre' => self::MENU_NOMBRE,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $perm['nombre'],
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
            }
            $permisoIds[] = $permisoId;
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($rolIds as $rolId) {
            foreach ([$moduloId, $menuId] as $mid) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolId]);
                }
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
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
