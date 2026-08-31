<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_ABONO = 'ventas/contrato-venta';

    private const MENU_COLA = 'ventas/contrato-venta-cola';

    private const PARENT_MENU_NAME = 'Tablas de ventas';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Ger-administracion',
        'Enc-contaduría',
        'Op-contaduria',
    ];

    /** @var list<array{nombre: string, slug: string, menu: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar abonos / contratos de venta', 'slug' => 'listar-contratos-venta', 'menu' => self::MENU_ABONO],
        ['nombre' => 'Crear abonos / contratos de venta', 'slug' => 'crear-contratos-venta', 'menu' => self::MENU_ABONO],
        ['nombre' => 'Editar abonos / contratos de venta', 'slug' => 'editar-contratos-venta', 'menu' => self::MENU_ABONO],
        ['nombre' => 'Actualizar abonos / contratos de venta', 'slug' => 'actualizar-contratos-venta', 'menu' => self::MENU_ABONO],
        ['nombre' => 'Borrar abonos / contratos de venta', 'slug' => 'borrar-contratos-venta', 'menu' => self::MENU_ABONO],
        ['nombre' => 'Cola de facturación de abonos', 'slug' => 'listar-contrato-venta-cola', 'menu' => self::MENU_COLA],
        ['nombre' => 'Facturar abonos desde cola', 'slug' => 'facturar-contrato-venta-cola', 'menu' => self::MENU_COLA],
    ];

    public function up(): void
    {
        $parentMenuId = $this->resolverParentMenuId();
        $menuAbonoId = $this->asegurarMenu(
            self::MENU_ABONO,
            'Abonos / contratos',
            $parentMenuId,
            null
        );
        $this->asegurarMenu(
            self::MENU_COLA,
            'Cola facturación abonos',
            $parentMenuId,
            null
        );

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $menuId = (int) (DB::table('menu')->where('url', $perm['menu'])->value('id') ?? $menuAbonoId);
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $perm['nombre'],
                    'updated_at' => now(),
                ]);
            }
            $permisoIds[] = $permisoId;
        }

        $rolIds = $this->resolverRolIds();
        $menuIds = array_filter([
            $parentMenuId,
            (int) (DB::table('menu')->where('url', self::MENU_ABONO)->value('id') ?? 0),
            (int) (DB::table('menu')->where('url', self::MENU_COLA)->value('id') ?? 0),
        ]);

        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
            foreach ($menuIds as $menuId) {
                if ($menuId <= 0) {
                    continue;
                }
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        $this->sembrarCatalogoTags();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        $rolIds = $this->resolverRolIds();

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

        foreach ([self::MENU_COLA, self::MENU_ABONO] as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId === 0) {
                continue;
            }
            DB::table('menu_rol')->where('menu_id', $menuId)->whereIn('rol_id', $rolIds)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverParentMenuId(): int
    {
        $parentMenuId = (int) (DB::table('menu')
            ->where('nombre', self::PARENT_MENU_NAME)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/vendedor')->value('menu_id') ?? 53);
        }

        return $parentMenuId;
    }

    private function asegurarMenu(string $url, string $nombre, int $parentMenuId, ?string $icono): int
    {
        $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $orden = $menuId > 0
            ? (int) (DB::table('menu')->where('id', $menuId)->value('orden') ?? 0)
            : (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        if ($menuId === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $parentMenuId,
            'nombre' => $nombre,
            'updated_at' => now(),
        ]);

        return $menuId;
    }

    private function sembrarCatalogoTags(): void
    {
        $ahora = now();
        $filas = [
            ['clave' => 'periodo', 'etiqueta' => 'Período a facturar', 'tipo' => 'periodo', 'es_sistema' => false, 'largo_max' => 40],
            ['clave' => 'dominio', 'etiqueta' => 'Dominio / patente', 'tipo' => 'texto', 'es_sistema' => false, 'largo_max' => 20],
            ['clave' => 'referencia', 'etiqueta' => 'Referencia', 'tipo' => 'texto', 'es_sistema' => false, 'largo_max' => 80],
            ['clave' => 'cliente', 'etiqueta' => 'Nombre del cliente', 'tipo' => 'texto', 'es_sistema' => true, 'largo_max' => 80],
            ['clave' => 'cuit', 'etiqueta' => 'CUIT / documento', 'tipo' => 'texto', 'es_sistema' => true, 'largo_max' => 20],
            ['clave' => 'fecha_factura', 'etiqueta' => 'Fecha de factura', 'tipo' => 'fecha', 'es_sistema' => true, 'largo_max' => 10],
            ['clave' => 'empresa', 'etiqueta' => 'Empresa', 'tipo' => 'texto', 'es_sistema' => true, 'largo_max' => 80],
            ['clave' => 'codigo_concepto', 'etiqueta' => 'Código concepto', 'tipo' => 'texto', 'es_sistema' => true, 'largo_max' => 20],
            ['clave' => 'nombre_concepto', 'etiqueta' => 'Nombre concepto', 'tipo' => 'texto', 'es_sistema' => true, 'largo_max' => 80],
        ];

        foreach ($filas as $fila) {
            $existe = DB::table('concepto_venta_tag_catalogo')->where('clave', $fila['clave'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('concepto_venta_tag_catalogo')->insert([
                'clave' => $fila['clave'],
                'etiqueta' => $fila['etiqueta'],
                'tipo' => $fila['tipo'],
                'es_sistema' => $fila['es_sistema'],
                'largo_max' => $fila['largo_max'],
                'opciones' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
