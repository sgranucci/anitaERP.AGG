<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega entrada de menú "Préstamos" bajo el módulo Stock con sus permisos.
 * También registra el menú de Configuración del módulo de préstamos.
 */
return new class extends Migration
{
    private const MENU_URL_PRESTAMO = 'stock/prestamo';
    private const MENU_URL_CONFIG = 'stock/configuracion-prestamo';
    private const MENU_URL_DEPADM = 'stock/deposito-administrador';

    public function up(): void
    {
        $stockMenuId = $this->resolverMenuStockId();

        $refMenuId = (int) (DB::table('menu')->where('url', 'stock/movimientostock')->value('id') ?? $stockMenuId);

        // Menú principal: Préstamos
        $orden = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->max('orden') ?? 0) + 1;
        $menuPrestamoId = $this->upsertMenu(self::MENU_URL_PRESTAMO, 'Préstamos', $stockMenuId, $orden, 'fa-handshake-o');

        $slugsPrestamo = [
            ['nombre' => 'Listar préstamos', 'slug' => 'listar-prestamo'],
            ['nombre' => 'Ingresar préstamo', 'slug' => 'crear-prestamo'],
            ['nombre' => 'Editar préstamo', 'slug' => 'editar-prestamo'],
            ['nombre' => 'Actualizar préstamo', 'slug' => 'actualizar-prestamo'],
            ['nombre' => 'Borrar préstamo', 'slug' => 'borrar-prestamo'],
            ['nombre' => 'Confirmar envío de préstamo', 'slug' => 'confirmar-envio-prestamo'],
            ['nombre' => 'Aprobar recepción de préstamo', 'slug' => 'aprobar-recepcion-prestamo'],
            ['nombre' => 'Rechazar recepción de préstamo', 'slug' => 'rechazar-recepcion-prestamo'],
            ['nombre' => 'Registrar devolución de préstamo', 'slug' => 'devolver-prestamo'],
            ['nombre' => 'Cancelar préstamo', 'slug' => 'cancelar-prestamo'],
            ['nombre' => 'Reenviar correo de préstamo', 'slug' => 'reenviar-correo-prestamo'],
            ['nombre' => 'Ver saldos por depósito', 'slug' => 'ver-saldo-deposito'],
        ];
        $this->upsertPermisos($slugsPrestamo, $menuPrestamoId, $refMenuId);

        // Menú: Configuración de préstamos
        $orden++;
        $menuConfigId = $this->upsertMenu(self::MENU_URL_CONFIG, 'Configuración préstamos', $stockMenuId, $orden, 'fa-cog');
        $this->upsertPermisos([
            ['nombre' => 'Editar configuración de préstamos', 'slug' => 'editar-configuracion-prestamo'],
            ['nombre' => 'Actualizar configuración de préstamos', 'slug' => 'actualizar-configuracion-prestamo'],
        ], $menuConfigId, $refMenuId);

        // Menú: Administradores de depósito
        $orden++;
        $menuDepadmId = $this->upsertMenu(self::MENU_URL_DEPADM, 'Administradores de depósito', $stockMenuId, $orden, 'fa-users');
        $this->upsertPermisos([
            ['nombre' => 'Listar administradores de depósito', 'slug' => 'listar-deposito-administrador'],
            ['nombre' => 'Ingresar administrador de depósito', 'slug' => 'crear-deposito-administrador'],
            ['nombre' => 'Editar administrador de depósito', 'slug' => 'editar-deposito-administrador'],
            ['nombre' => 'Actualizar administrador de depósito', 'slug' => 'actualizar-deposito-administrador'],
            ['nombre' => 'Borrar administrador de depósito', 'slug' => 'borrar-deposito-administrador'],
        ], $menuDepadmId, $refMenuId);
    }

    private function resolverMenuStockId(): int
    {
        $id = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Stock')
                    ->orWhere('nombre', 'like', '%Stock%')
                    ->orWhere('nombre', 'like', '%stock%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        // Fallback: tomar el menú padre del primer item conocido bajo stock
        $padreFallback = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 0);

        return $padreFallback > 0 ? $padreFallback : 10;
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  array<int, array{nombre:string, slug:string}>  $slugs
     */
    private function upsertPermisos(array $slugs, int $menuId, int $refMenuId): void
    {
        $rolIdsMenuRef = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all()
            : [];

        foreach ($slugs as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);

            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $row['nombre'],
                    'updated_at' => now(),
                ]);
            }

            foreach ($rolIdsMenuRef as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'listar-prestamo', 'crear-prestamo', 'editar-prestamo',
            'actualizar-prestamo', 'borrar-prestamo',
            'confirmar-envio-prestamo', 'aprobar-recepcion-prestamo',
            'rechazar-recepcion-prestamo', 'devolver-prestamo',
            'cancelar-prestamo', 'reenviar-correo-prestamo',
            'ver-saldo-deposito',
            'editar-configuracion-prestamo', 'actualizar-configuracion-prestamo',
            'listar-deposito-administrador', 'crear-deposito-administrador',
            'editar-deposito-administrador', 'actualizar-deposito-administrador',
            'borrar-deposito-administrador',
        ];

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        foreach ($permisoIds as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        foreach ([self::MENU_URL_PRESTAMO, self::MENU_URL_CONFIG, self::MENU_URL_DEPADM] as $url) {
            $menuId = DB::table('menu')->where('url', $url)->value('id');
            if ($menuId) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }
    }
};
