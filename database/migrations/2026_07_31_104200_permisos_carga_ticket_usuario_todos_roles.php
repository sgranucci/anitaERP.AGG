<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga de Tickets: todos los roles ven el menú y pueden gestionar solo sus tickets
 * (perfil usuario-ticket). Corrige roles que tenían menú sin listar-ticket
 * (ej. enc-SEGURIDAD → macuna, pcastellani).
 *
 * Roles de tecnología quedan excluidos de usuario-ticket (ver migración
 * 2026_07_31_105500_quitar_usuario_ticket_roles_tecnologia).
 */
return new class extends Migration
{
    private const MENU_MODULO_URL = '#';

    private const MENU_MODULO_NOMBRE = 'Módulo de Tickets';

    private const MENU_CARGA_URL = 'ticket/ticket';

    private const MENU_CARGA_NOMBRE = 'Carga de Tickets';

    /** @var list<string> Roles IT: no reciben usuario-ticket en installs frescos. */
    private const ROLES_SIN_USUARIO_TICKET = [
        'Enc-sistemas',
        'Tecnico de Tecnología',
        'op-Gerencia de Tecnologia',
    ];

    /** @var list<string> */
    private const PERMISOS_USUARIO = [
        'listar-ticket',
        'crear-ticket',
        'editar-ticket',
        'actualizar-ticket',
        'usuario-ticket',
    ];

    public function up(): void
    {
        $menuModuloId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', self::MENU_MODULO_NOMBRE)
                    ->orWhere('nombre', 'like', '%Módulo de Tickets%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($menuModuloId === 0) {
            $menuModuloId = (int) DB::table('menu')->insertGetId([
                'menu_id' => 0,
                'nombre' => self::MENU_MODULO_NOMBRE,
                'url' => self::MENU_MODULO_URL,
                'orden' => (int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1,
                'icono' => 'fa-wrench',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $menuCargaId = (int) (DB::table('menu')
            ->where('url', self::MENU_CARGA_URL)
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($menuCargaId === 0) {
            $menuCargaId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $menuModuloId,
                'nombre' => self::MENU_CARGA_NOMBRE,
                'url' => self::MENU_CARGA_URL,
                'orden' => (int) (DB::table('menu')->where('menu_id', $menuModuloId)->max('orden') ?? 0) + 1,
                'icono' => 'fa-cog',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISOS_USUARIO)
            ->pluck('id', 'slug');

        foreach (self::PERMISOS_USUARIO as $slug) {
            if (! isset($permisoIds[$slug])) {
                throw new RuntimeException("Falta permiso slug={$slug} en tabla permiso");
            }
        }

        $rolesSinUsuario = DB::table('rol')
            ->whereIn('nombre', self::ROLES_SIN_USUARIO_TICKET)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rolIds = DB::table('rol')->orderBy('id')->pluck('id');

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            $omitirUsuarioTicket = in_array($rolId, $rolesSinUsuario, true);

            if (! DB::table('menu_rol')->where('menu_id', $menuModuloId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuModuloId,
                    'rol_id' => $rolId,
                ]);
            }

            if (! DB::table('menu_rol')->where('menu_id', $menuCargaId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuCargaId,
                    'rol_id' => $rolId,
                ]);
            }

            foreach (self::PERMISOS_USUARIO as $slug) {
                if ($slug === 'usuario-ticket' && $omitirUsuarioTicket) {
                    continue;
                }
                $permisoId = (int) $permisoIds[$slug];
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
        // No revierte: muchos roles ya tenían el subset parcial antes de esta migración.
        SuitecrmPermiso::flushCachePermisos();
    }
};
