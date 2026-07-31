<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Por ahora solo tecnología (y administrador) administra tickets de todos.
 * Quita Adm. de Tickets y encargado-ticket a logística / mantenimiento.
 */
return new class extends Migration
{
    private const MENU_ADM_URL = 'ticket/administracion_ticket';

    /** @var list<string> */
    private const ROLES_ADM_TICKET = [
        'administrador',
        'Enc-sistemas',
        'Tecnico de Tecnología',
        'op-Gerencia de Tecnologia',
    ];

    /** @var list<string> */
    private const ROLES_QUITAR_ADM = [
        'Enc-logistica',
        'Enc-mantenimiento',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_ADM_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $rolesAdmIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_ADM_TICKET)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rolesAdmIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        $rolesQuitarIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_QUITAR_ADM)
            ->pluck('id');

        if ($rolesQuitarIds->isNotEmpty()) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->whereIn('rol_id', $rolesQuitarIds)
                ->delete();

            $encargadoId = (int) (DB::table('permiso')->where('slug', 'encargado-ticket')->value('id') ?? 0);
            if ($encargadoId > 0) {
                DB::table('permiso_rol')
                    ->where('permiso_id', $encargadoId)
                    ->whereIn('rol_id', $rolesQuitarIds)
                    ->delete();
            }
        }

        // Cualquier otro rol fuera de la lista no debe ver Adm. de Tickets
        if ($rolesAdmIds !== []) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->whereNotIn('rol_id', $rolesAdmIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_ADM_URL)->value('id') ?? 0);
        $encargadoId = (int) (DB::table('permiso')->where('slug', 'encargado-ticket')->value('id') ?? 0);

        foreach (self::ROLES_QUITAR_ADM as $nombre) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($rolId === 0) {
                continue;
            }
            if ($menuId > 0 && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
            if ($encargadoId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $encargadoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $encargadoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
