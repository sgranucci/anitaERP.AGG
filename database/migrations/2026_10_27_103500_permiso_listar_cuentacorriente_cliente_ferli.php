<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: el botón de cuenta corriente del index de clientes
 * usa listar-cuentacorriente-cliente, que no estaba creado.
 * Lo ven Laura (rol Ventas) y el rol Oficina.
 */
return new class extends Migration
{
    private const PERMISO = 'listar-cuentacorriente-cliente';

    private const NOMBRE = 'Listar cuenta corriente de cliente';

    private const MENU_URL = 'ventas/cliente';

    /** @var list<string> */
    private const ROLES = [
        'Oficina',
        'Ventas',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => self::NOMBRE,
                'menu_id' => $menuId > 0 ? $menuId : null,
                'updated_at' => now(),
            ]);
        } else {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::NOMBRE,
                'slug' => self::PERMISO,
                'menu_id' => $menuId > 0 ? $menuId : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        $this->invalidarCache($rolIds);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        $rolIds = $this->resolverRolIds();
        if ($permisoId > 0 && $rolIds !== []) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        if ($permisoId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->exists()) {
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $this->invalidarCache($rolIds);
    }

    /**
     * @return list<int>
     */
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

    /**
     * @param  list<int>  $rolIds
     */
    private function invalidarCache(array $rolIds): void
    {
        SuitecrmPermiso::flushCachePermisos();
        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
    }
};
