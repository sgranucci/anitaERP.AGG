<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renombra menú Préstamos → Salida de bienes y crea permisos nuevos
 * copiando asignaciones de roles desde los slugs *-prestamo.
 * Sin gate de entorno (AGG + Bierzo + resto).
 */
return new class extends Migration
{
    private const MENU_URL_OLD = 'stock/prestamo';

    private const MENU_URL_NEW = 'stock/salida-bienes';

    private const CONFIG_URL_OLD = 'stock/configuracion-prestamo';

    private const CONFIG_URL_NEW = 'stock/configuracion-salida-bienes';

    /** @var list<array{old:string, new:string, nombre:string}> */
    private const PERMISOS = [
        ['old' => 'listar-prestamo', 'new' => 'listar-salida-bienes', 'nombre' => 'Listar salida de bienes'],
        ['old' => 'crear-prestamo', 'new' => 'crear-salida-bienes', 'nombre' => 'Ingresar salida de bienes'],
        ['old' => 'editar-prestamo', 'new' => 'editar-salida-bienes', 'nombre' => 'Editar salida de bienes'],
        ['old' => 'actualizar-prestamo', 'new' => 'actualizar-salida-bienes', 'nombre' => 'Actualizar salida de bienes'],
        ['old' => 'borrar-prestamo', 'new' => 'borrar-salida-bienes', 'nombre' => 'Borrar salida de bienes'],
        ['old' => 'confirmar-envio-prestamo', 'new' => 'confirmar-envio-salida-bienes', 'nombre' => 'Confirmar envío salida de bienes'],
        ['old' => 'aprobar-recepcion-prestamo', 'new' => 'aprobar-recepcion-salida-bienes', 'nombre' => 'Aprobar recepción salida de bienes'],
        ['old' => 'rechazar-recepcion-prestamo', 'new' => 'rechazar-recepcion-salida-bienes', 'nombre' => 'Rechazar recepción salida de bienes'],
        ['old' => 'devolver-prestamo', 'new' => 'devolver-salida-bienes', 'nombre' => 'Registrar devolución salida de bienes'],
        ['old' => 'cancelar-prestamo', 'new' => 'cancelar-salida-bienes', 'nombre' => 'Cancelar salida de bienes'],
        ['old' => 'reenviar-correo-prestamo', 'new' => 'reenviar-correo-salida-bienes', 'nombre' => 'Reenviar correo salida de bienes'],
        ['old' => 'editar-configuracion-prestamo', 'new' => 'editar-configuracion-salida-bienes', 'nombre' => 'Editar configuración salida de bienes'],
        ['old' => 'actualizar-configuracion-prestamo', 'new' => 'actualizar-configuracion-salida-bienes', 'nombre' => 'Actualizar configuración salida de bienes'],
    ];

    public function up(): void
    {
        $menuId = $this->renombrarMenu(self::MENU_URL_OLD, self::MENU_URL_NEW, 'Salida de bienes', 'fa-truck');
        $configId = $this->renombrarMenu(self::CONFIG_URL_OLD, self::CONFIG_URL_NEW, 'Configuración salida de bienes', 'fa-cog');

        foreach (self::PERMISOS as $row) {
            $menuDestino = str_contains($row['new'], 'configuracion') ? $configId : $menuId;
            if ($menuDestino <= 0) {
                continue;
            }
            $this->upsertPermisoYCopiarRoles($row['old'], $row['new'], $row['nombre'], $menuDestino);
        }

        // Extra: cerrar sin devolución (premium)
        if ($menuId > 0) {
            $this->upsertPermisoYCopiarRoles(
                'devolver-prestamo',
                'cerrar-salida-bienes',
                'Cerrar salida de bienes sin devolución',
                $menuId
            );
        }
    }

    public function down(): void
    {
        $this->renombrarMenu(self::MENU_URL_NEW, self::MENU_URL_OLD, 'Préstamos', 'fa-handshake-o');
        $this->renombrarMenu(self::CONFIG_URL_NEW, self::CONFIG_URL_OLD, 'Configuración préstamos', 'fa-cog');

        foreach (array_merge(self::PERMISOS, [[
            'old' => 'devolver-prestamo',
            'new' => 'cerrar-salida-bienes',
            'nombre' => 'Cerrar salida de bienes sin devolución',
        ]]) as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['new'])->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
    }

    private function renombrarMenu(string $urlOld, string $urlNew, string $nombre, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $urlOld)->value('id') ?? 0);
        if ($id <= 0) {
            $id = (int) (DB::table('menu')->where('url', $urlNew)->value('id') ?? 0);
        }
        if ($id <= 0) {
            return 0;
        }

        DB::table('menu')->where('id', $id)->update([
            'url' => $urlNew,
            'nombre' => $nombre,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertPermisoYCopiarRoles(string $slugOld, string $slugNew, string $nombre, int $menuId): void
    {
        $oldId = (int) (DB::table('permiso')->where('slug', $slugOld)->value('id') ?? 0);
        $newId = (int) (DB::table('permiso')->where('slug', $slugNew)->value('id') ?? 0);

        if ($newId === 0) {
            $newId = (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slugNew,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $newId)->update([
                'nombre' => $nombre,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        // Roles del permiso viejo
        if ($oldId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $oldId)->pluck('rol_id')->all();
            foreach ($rolIds as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('permiso_rol')->where('permiso_id', $newId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $newId, 'rol_id' => $rid]);
                }
            }
        }

        // Menú en roles que ya tenían el menú del permiso viejo / nuevo
        $menuRolIds = DB::table('menu_rol')->where('menu_id', $menuId)->pluck('rol_id')->all();
        if ($menuRolIds === [] && $oldId > 0) {
            $oldMenuId = (int) (DB::table('permiso')->where('id', $oldId)->value('menu_id') ?? 0);
            if ($oldMenuId > 0) {
                $menuRolIds = DB::table('menu_rol')->where('menu_id', $oldMenuId)->pluck('rol_id')->all();
            }
        }
        foreach ($menuRolIds as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
            }
        }
    }
};
