<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Contaduría consulta el reporte de recepciones (solo listar / menú).
 * Daiana Sampleben y Eduardo Galarza (Enc-contaduría) ya ven el ABM;
 * faltaba el informe stock/reporte-recepcion-proveedor.
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/reporte-recepcion-proveedor';

    private const PERMISO_SLUG = 'listar-reporte-recepcion-proveedor';

    /** @var list<string> */
    private const ROLES = [
        'Enc-contaduría',
        'Op-contaduria',
        'Sup-contaduria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $this->invalidarCacheRoles($rolIds);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        $this->invalidarCacheRoles($rolIds);
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id <= 0) {
                $like = match (true) {
                    str_starts_with($nombre, 'Enc-') => 'Enc-contadur%',
                    str_starts_with($nombre, 'Op-') => 'Op-contadur%',
                    default => 'Sup-contadur%',
                };
                $id = (int) (DB::table('rol')->where('nombre', 'like', $like)->orderBy('id')->value('id') ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param  list<int>  $rolIds */
    private function invalidarCacheRoles(array $rolIds): void
    {
        SuitecrmPermiso::flushCachePermisos();
        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
    }
};
