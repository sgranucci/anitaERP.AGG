<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bandeja de tickets (modo claim / Mantenimiento):
 * menú + permisos listar/tomar/liberar.
 * Restaura encargado-ticket a Enc-mantenimiento para tab Todos.
 */
return new class extends Migration
{
    private const MENU_URL = 'ticket/bandeja';

    private const MENU_NOMBRE = 'Bandeja de tickets';

    private const MENU_ADM_URL = 'ticket/administracion_ticket';

    /** @var list<array{slug: string, nombre: string}> */
    private const PERMISOS = [
        ['slug' => 'listar-bandeja-ticket', 'nombre' => 'Listar bandeja de tickets'],
        ['slug' => 'tomar-ticket', 'nombre' => 'Tomar ticket de la cola'],
        ['slug' => 'liberar-ticket', 'nombre' => 'Liberar ticket a la cola'],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-mantenimiento',
        'op-Obras y Mantenimiento',
        'Enc-sistemas',
        'Tecnico de Tecnología',
        'op-Gerencia de Tecnologia',
    ];

    public function up(): void
    {
        $padreId = (int) (DB::table('menu')->where('url', self::MENU_ADM_URL)->value('menu_id') ?? 0);
        if ($padreId === 0) {
            $padreId = (int) (DB::table('menu')
                ->where('menu_id', 0)
                ->where(function ($q) {
                    $q->where('nombre', 'Módulo de Tickets')
                        ->orWhere('nombre', 'like', '%Módulo de Tickets%');
                })
                ->orderBy('id')
                ->value('id') ?? 0);
        }
        if ($padreId === 0) {
            return;
        }

        $ordenCarga = (int) (DB::table('menu')->where('url', 'ticket/ticket')->value('orden') ?? 2);
        $orden = $ordenCarga > 0 ? $ordenCarga + 1 : ((int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1);

        // Desplaza hermanos con orden >= nuevo para insertar entre Carga y Adm.
        DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('orden', '>=', $orden)
            ->where('url', '!=', self::MENU_URL)
            ->update([
                'orden' => DB::raw('orden + 1'),
                'updated_at' => now(),
            ]);

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-inbox',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $padreId,
                'nombre' => self::MENU_NOMBRE,
                'orden' => $orden,
                'icono' => 'fa-inbox',
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds(self::ROLES);

        foreach (self::PERMISOS as $def) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $def['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $def['nombre'],
                    'slug' => $def['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $def['nombre'],
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

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        // Enc-mantenimiento: encargado para tab Todos + atajo de asignación.
        $encMantId = (int) (DB::table('rol')->where('nombre', 'Enc-mantenimiento')->value('id') ?? 0);
        $encargadoPermisoId = (int) (DB::table('permiso')->where('slug', 'encargado-ticket')->value('id') ?? 0);
        if ($encMantId > 0 && $encargadoPermisoId > 0) {
            if (! DB::table('permiso_rol')->where('permiso_id', $encargadoPermisoId)->where('rol_id', $encMantId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $encargadoPermisoId,
                    'rol_id' => $encMantId,
                ]);
            }
        }

        // Técnicos operativos de obras: permiso tecnico-ticket para alcance.
        $opObrasId = (int) (DB::table('rol')->where('nombre', 'op-Obras y Mantenimiento')->value('id') ?? 0);
        $tecnicoPermisoId = (int) (DB::table('permiso')->where('slug', 'tecnico-ticket')->value('id') ?? 0);
        if ($opObrasId > 0 && $tecnicoPermisoId > 0) {
            if (! DB::table('permiso_rol')->where('permiso_id', $tecnicoPermisoId)->where('rol_id', $opObrasId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $tecnicoPermisoId,
                    'rol_id' => $opObrasId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (self::PERMISOS as $def) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $def['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
