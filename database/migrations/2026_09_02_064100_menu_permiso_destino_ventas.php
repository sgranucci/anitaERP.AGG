<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa el proceso de certificados SENASA y el ABM de destinos
 * bajo un submenú "Certificados sanitarios" en Módulo de Ventas.
 */
return new class extends Migration
{
    private const SUBMENU_NOMBRE = 'Certificados sanitarios';

    private const CERT_URL = 'ventas/certificado-sanitario';

    private const DESTINO_URL = 'ventas/destino';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS_DESTINO = [
        ['nombre' => 'Listar destinos SENASA', 'slug' => 'listar-destino'],
        ['nombre' => 'Crear destinos SENASA', 'slug' => 'crear-destino'],
        ['nombre' => 'Editar destinos SENASA', 'slug' => 'editar-destino'],
        ['nombre' => 'Actualizar destinos SENASA', 'slug' => 'actualizar-destino'],
        ['nombre' => 'Borrar destinos SENASA', 'slug' => 'borrar-destino'],
    ];

    public function up(): void
    {
        $moduloId = $this->resolverModuloVentasId();
        if ($moduloId === 0) {
            return;
        }

        $submenuId = $this->upsertSubmenu($moduloId);
        $this->moverCertificadoBajoSubmenu($submenuId);
        $destinoId = $this->upsertMenuDestino($submenuId);
        $rolIds = $this->resolverRolIds();

        foreach ([$submenuId, $destinoId] as $menuId) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        $certId = (int) (DB::table('menu')->where('url', self::CERT_URL)->value('id') ?? 0);
        if ($certId > 0) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $certId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $certId, 'rol_id' => $rolId]);
                }
            }
        }

        foreach (self::PERMISOS_DESTINO as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $destinoId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $destinoId,
                    'nombre' => $perm['nombre'],
                    'updated_at' => now(),
                ]);
            }
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $moduloId = $this->resolverModuloVentasId();
        $submenuId = $this->resolverSubmenuId($moduloId);
        $destinoId = (int) (DB::table('menu')->where('url', self::DESTINO_URL)->value('id') ?? 0);
        $rolIds = $this->resolverRolIds();

        $slugs = array_column(self::PERMISOS_DESTINO, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        foreach ($permisoIds as $permisoId) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);
        }

        if ($destinoId > 0) {
            DB::table('menu_rol')->where('menu_id', $destinoId)->delete();
            DB::table('menu')->where('id', $destinoId)->delete();
        }

        if ($moduloId > 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
            DB::table('menu')->where('url', self::CERT_URL)->update([
                'menu_id' => $moduloId,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        if ($submenuId > 0) {
            DB::table('menu_rol')->where('menu_id', $submenuId)->delete();
            DB::table('menu')->where('id', $submenuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverModuloVentasId(): int
    {
        $id = (int) (DB::table('menu')->where('url', self::CERT_URL)->value('menu_id') ?? 0);
        if ($id > 0) {
            $urlPadre = (string) (DB::table('menu')->where('id', $id)->value('url') ?? '');
            if ($urlPadre === '#') {
                $nombre = (string) (DB::table('menu')->where('id', $id)->value('nombre') ?? '');
                if ($nombre === self::SUBMENU_NOMBRE) {
                    return (int) (DB::table('menu')->where('id', $id)->value('menu_id') ?? 0);
                }

                return $id;
            }
        }

        $pedidoPadre = (int) (DB::table('menu')->where('url', 'ventas/pedido')->value('menu_id') ?? 0);
        if ($pedidoPadre > 0) {
            return $pedidoPadre;
        }

        return (int) (DB::table('menu')->where('nombre', 'Módulo de Ventas')->where('url', '#')->value('id') ?? 0);
    }

    private function resolverSubmenuId(int $moduloId): int
    {
        if ($moduloId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $moduloId)
            ->where('nombre', self::SUBMENU_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);
    }

    private function upsertSubmenu(int $moduloId): int
    {
        $id = $this->resolverSubmenuId($moduloId);
        $ordenCert = (int) (DB::table('menu')->where('url', self::CERT_URL)->value('orden') ?? 0);
        $orden = $ordenCert > 0 ? $ordenCert : ((int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1);

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloId,
                'nombre' => self::SUBMENU_NOMBRE,
                'url' => '#',
                'orden' => $orden,
                'icono' => 'fa fa-certificate',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $moduloId,
            'nombre' => self::SUBMENU_NOMBRE,
            'url' => '#',
            'orden' => $orden,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function moverCertificadoBajoSubmenu(int $submenuId): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::CERT_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $submenuId,
            'nombre' => 'Certificado sanitario SENASA',
            'orden' => 1,
            'updated_at' => now(),
        ]);
    }

    private function upsertMenuDestino(int $submenuId): int
    {
        $menuId = (int) (DB::table('menu')->where('url', self::DESTINO_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $submenuId,
                'nombre' => 'Destinos SENASA',
                'url' => self::DESTINO_URL,
                'orden' => 2,
                'icono' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $submenuId,
            'nombre' => 'Destinos SENASA',
            'orden' => 2,
            'updated_at' => now(),
        ]);

        return $menuId;
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $desdeCertificado = DB::table('permiso_rol')
            ->join('permiso', 'permiso.id', '=', 'permiso_rol.permiso_id')
            ->whereIn('permiso.slug', [
                'listar-certificado-sanitario',
                'crear-certificado-sanitario',
            ])
            ->pluck('permiso_rol.rol_id');

        $admin = DB::table('rol')->where('nombre', 'administrador')->pluck('id');

        return $desdeCertificado
            ->concat($admin)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
