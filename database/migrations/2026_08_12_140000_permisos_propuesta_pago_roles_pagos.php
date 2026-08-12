<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asigna el circuito de Propuesta de pagos a los roles de pagos (Enc-pagos, Op-Pagos)
 * además de administrador: menú, listado/ABM, aprobación, ejecución y configuración.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/propuesta-pago';

    /** @var list<string> */
    private const PERMISOS = [
        'listar-propuesta-pago',
        'crear-propuesta-pago',
        'editar-propuesta-pago',
        'actualizar-propuesta-pago',
        'borrar-propuesta-pago',
        'enviar-aprobacion-propuesta-pago',
        'ejecutar-propuesta-pago',
        'editar-configuracion-propuesta-pago',
        'actualizar-configuracion-propuesta-pago',
    ];

    public function up(): void
    {
        $rolIds = $this->rolesPagos();
        if ($rolIds === []) {
            return;
        }

        foreach (self::PERMISOS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId === 0) {
                continue;
            }

            foreach ($rolIds as $rolId) {
                $existe = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();

                if (! $existe) {
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
                $existe = DB::table('menu_rol')
                    ->where('menu_id', $menuId)
                    ->where('rol_id', $rolId)
                    ->exists();

                if (! $existe) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $contenedorId = (int) (DB::table('menu')
            ->where('nombre', 'Cuentas a pagar')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($contenedorId > 0) {
            foreach ($rolIds as $rolId) {
                $existe = DB::table('menu_rol')
                    ->where('menu_id', $contenedorId)
                    ->where('rol_id', $rolId)
                    ->exists();

                if (! $existe) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $contenedorId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $this->invalidarCachePermisos();
    }

    public function down(): void
    {
        $rolIds = $this->rolesPagos(false);
        if ($rolIds === []) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISOS)
            ->pluck('id')
            ->all();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->whereIn('permiso_id', $permisoIds)
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

        $this->invalidarCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function rolesPagos(bool $conAdministrador = true): array
    {
        $query = DB::table('rol')->where('nombre', 'like', '%pagos%');

        if ($conAdministrador) {
            $query->orWhere('nombre', 'administrador');
        }

        return $query->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function invalidarCachePermisos(): void
    {
        try {
            cache()->tags('Permiso')->flush();
        } catch (\Throwable) {
            cache()->flush();
        }
    }
};
