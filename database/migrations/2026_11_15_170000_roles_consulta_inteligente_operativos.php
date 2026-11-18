<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Abre Consulta Inteligente a roles operativos de Caja / Compras / Contaduría / Ventas
 * (además de administrador). El catálogo sigue filtrando entidades por permiso de módulo.
 */
return new class extends Migration
{
    private const MENU_URL = 'direccion/consulta-inteligente';

    private const PERMISO_SLUG = 'listar-consulta-inteligente';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Enc-compras',
        'Enc-contaduría',
        'Ger-administracion',
        'Op-Compras',
        'Op-contaduria',
        'Sup-contaduria',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($menuId === 0 || $permisoId === 0) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        // También quien ya lista cheques / facturas / pagos / IE / clientes
        $extra = DB::table('permiso_rol as pr')
            ->join('permiso as p', 'p.id', '=', 'pr.permiso_id')
            ->whereIn('p.slug', [
                'listar-cheque',
                'listar-comprobante-proveedor',
                'listar-pagoproveedor',
                'listar-proveedor',
                'listar-ingresos-egresos-caja',
                'listar-clientes',
            ])
            ->pluck('pr.rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rolIds = array_values(array_unique(array_merge($rolIds, $extra)));

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            // Padres del menú (Dirección) para que el árbol sea visible
            $vistos = [$menuId => true];
            $padreId = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0);
            $profundidad = 0;
            while ($padreId > 0 && $profundidad < 32) {
                if (isset($vistos[$padreId])) {
                    break;
                }
                $vistos[$padreId] = true;
                if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rolId]);
                }
                $padreId = (int) (DB::table('menu')->where('id', $padreId)->value('menu_id') ?? 0);
                $profundidad++;
            }

            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);

        if ($menuId > 0) {
            $q = DB::table('menu_rol')->where('menu_id', $menuId);
            if ($adminId > 0) {
                $q->where('rol_id', '!=', $adminId);
            }
            $q->delete();
        }
        if ($permisoId > 0) {
            $q = DB::table('permiso_rol')->where('permiso_id', $permisoId);
            if ($adminId > 0) {
                $q->where('rol_id', '!=', $adminId);
            }
            $q->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
