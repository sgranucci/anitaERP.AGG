<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: rol admin-casino para operadores de casino/sala.
 *
 * Alcance:
 * - Proveedores: solo listar (sin alta ni modificación).
 * - Requisiciones y OC: operan; el listado se recorta por usuario_sala.
 * - Stock completo; recepciones sin “ver todas” (recortadas por CC de la sala).
 *
 * Crea los 8 usuarios de Catriel del relevamiento, con sala CAT, empresa Crown
 * y centros de costo de la planilla. Corrige depmae Catriel (códigos 1800–1899)
 * a sala CAT (estaban en Administración central).
 */
return new class extends Migration
{
    private const ROL = 'admin-casino';

    private const ROL_STOCK_REF = 'administrador';

    private const ROL_STOCK_PERM_REF = 'Enc-logistica';

    private const SALA_CODIGO = 'CAT';

    private const EMPRESA_ID = 1;

    private const PASSWORD = '12345';

    private const EMAIL_DOMINIO = '@casinosdelrio.com.ar';

    /** @var list<array{usuario: string, nombre: string, centrocosto_codigo: string}> */
    private const USUARIOS = [
        ['usuario' => 'ikunusch', 'nombre' => 'KUNUSCH IVANA MARIELA', 'centrocosto_codigo' => '1303'],
        ['usuario' => 'vsantander', 'nombre' => 'SANTANDER VERONICA NATALIA', 'centrocosto_codigo' => '1310'],
        ['usuario' => 'nmanchini', 'nombre' => 'MANCHINI NICOLAS MAURICIO', 'centrocosto_codigo' => '1303'],
        ['usuario' => 'ddominguez', 'nombre' => 'DOMINGUEZ DARDO ALEJANDRO', 'centrocosto_codigo' => '1310'],
        ['usuario' => 'spacheco', 'nombre' => 'PACHECO SERGIO EDGARDO', 'centrocosto_codigo' => '1307'],
        ['usuario' => 'arivas', 'nombre' => 'RIVAS AMELIA BEATRIZ', 'centrocosto_codigo' => '1312'],
        ['usuario' => 'sguardia', 'nombre' => 'GUARDIA SOL MILAGROS', 'centrocosto_codigo' => '1312'],
        ['usuario' => 'exflores', 'nombre' => 'FLORES EXEQUIEL NICOLAS', 'centrocosto_codigo' => '1310'],
    ];

    /** @var list<string> */
    private const PERMISOS_PROVEEDOR = [
        'listar-proveedor',
    ];

    /** @var list<string> */
    private const PERMISOS_REQUISICION = [
        'listar-requisicion',
        'crear-requisicion',
        'editar-requisicion',
        'actualizar-requisicion',
        'borrar-requisicion',
        'usuario-requisicion-compras',
        'listar-reporte-requisicion-compras',
        'listar-requisicion-proveedor',
    ];

    /** @var list<string> */
    private const PERMISOS_ORDENCOMPRA = [
        'listar-ordencompra',
        'crear-ordencompra',
        'editar-ordencompra',
        'actualizar-ordencompra',
        'borrar-ordencompra',
        'listar-ordencompra-proveedor',
        'listar-reporte-ordencompra',
        'listar-todos-sector-legajo-compra',
    ];

    /** @var list<string> */
    private const PERMISOS_STOCK_QUITAR = [
        'listar-todas-recepciones-proveedor',
        'listar-todas-requisicion',
    ];

    /** @var list<string> */
    private const MENUS_COMPRAS_URL = [
        'compras/proveedor',
        'compras/requisicion',
        'compras/ordencompra',
        'compras/requisicion-reporte',
        'compras/ordencompra-reporte',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('rol') || ! Schema::hasTable('usuario')) {
            return;
        }

        $this->corregirDepositosCatriel();

        $rolId = $this->asegurarRol();
        if ($rolId <= 0) {
            return;
        }

        $this->asignarMenusYPermisos($rolId);
        $this->crearUsuarios($rolId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $this->revertirDepositosCatriel();

        $rolId = $this->rolId(self::ROL);
        foreach (self::USUARIOS as $fila) {
            $usuarioId = $this->usuarioId($fila['usuario']);
            if ($usuarioId <= 0) {
                continue;
            }
            if (Schema::hasTable('usuario_rol')) {
                DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolId)->delete();
            }
            if (Schema::hasTable('usuario_sala')) {
                DB::table('usuario_sala')->where('usuario_id', $usuarioId)->delete();
            }
            if (Schema::hasTable('usuario_empresa')) {
                DB::table('usuario_empresa')->where('usuario_id', $usuarioId)->delete();
            }
            if (Schema::hasTable('usuario_deposito')) {
                DB::table('usuario_deposito')->where('usuario_id', $usuarioId)->delete();
            }
            DB::table('usuario')->where('id', $usuarioId)->where('usuario', $fila['usuario'])->delete();
        }

        if ($rolId > 0) {
            if (Schema::hasTable('menu_rol')) {
                DB::table('menu_rol')->where('rol_id', $rolId)->delete();
            }
            if (Schema::hasTable('permiso_rol')) {
                DB::table('permiso_rol')->where('rol_id', $rolId)->delete();
            }
            if (Schema::hasTable('usuario_rol')) {
                DB::table('usuario_rol')->where('rol_id', $rolId)->delete();
            }
            DB::table('rol')->where('id', $rolId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarRol(): int
    {
        $existente = $this->rolId(self::ROL);
        if ($existente > 0) {
            return $existente;
        }

        $now = now();

        return (int) DB::table('rol')->insertGetId([
            'nombre' => self::ROL,
            'centrocosto_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function asignarMenusYPermisos(int $rolId): void
    {
        foreach ($this->menusCompras() as $menuId) {
            $this->asegurarMenuRol($rolId, $menuId);
            $this->asegurarAncestrosMenu($rolId, $menuId);
        }

        $stockMenuIds = $this->menusStockCompleto();
        foreach ($stockMenuIds as $menuId) {
            $this->asegurarMenuRol($rolId, $menuId);
            $this->asegurarAncestrosMenu($rolId, $menuId);
        }

        foreach (array_merge(
            self::PERMISOS_PROVEEDOR,
            self::PERMISOS_REQUISICION,
            self::PERMISOS_ORDENCOMPRA,
        ) as $slug) {
            $this->asegurarPermisoRol($rolId, $this->permisoId($slug));
        }

        foreach ($this->permisosStockCompleto($stockMenuIds) as $permisoId) {
            $this->asegurarPermisoRol($rolId, $permisoId);
        }

        foreach ([
            'listar-categorias', 'crear-categorias', 'editar-categorias', 'actualizar-categorias',
            'listar-subcategorias', 'crear-subcategorias', 'editar-subcategorias', 'actualizar-subcategorias',
        ] as $slug) {
            $this->asegurarPermisoRol($rolId, $this->permisoId($slug));
        }

        foreach (self::PERMISOS_STOCK_QUITAR as $slug) {
            $permisoId = $this->permisoId($slug);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->delete();
            }
        }
    }

    /**
     * @return list<int>
     */
    private function menusCompras(): array
    {
        $ids = [];
        foreach (self::MENUS_COMPRAS_URL as $url) {
            foreach (DB::table('menu')->where('url', $url)->pluck('id') as $id) {
                $ids[] = (int) $id;
            }
        }

        $modulo = (int) (DB::table('menu')->where('nombre', 'Módulo de Compras')->where('url', '#')->value('id') ?? 0);
        if ($modulo > 0) {
            $ids[] = $modulo;
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /**
     * @return list<int>
     */
    private function menusStockCompleto(): array
    {
        $rolAdminId = $this->rolId(self::ROL_STOCK_REF);
        if ($rolAdminId <= 0) {
            return [];
        }

        $ids = DB::table('menu as m')
            ->join('menu_rol as mr', 'mr.menu_id', '=', 'm.id')
            ->where('mr.rol_id', $rolAdminId)
            ->where(function ($q) {
                $q->where('m.id', 10)
                    ->orWhere('m.menu_id', 10)
                    ->orWhere('m.url', 'like', 'stock/%')
                    ->orWhereIn('m.id', [11, 260, 513, 645, 649, 704]);
            })
            ->pluck('m.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $informesId = (int) (DB::table('menu')->where('id', 649)->value('id') ?? 0);
        if ($informesId > 0 && ! in_array($informesId, $ids, true)) {
            $ids[] = $informesId;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $stockMenuIds
     * @return list<int>
     */
    private function permisosStockCompleto(array $stockMenuIds): array
    {
        $ids = collect();

        if ($stockMenuIds !== []) {
            $ids = $ids->merge(
                DB::table('permiso')->whereIn('menu_id', $stockMenuIds)->pluck('id')
            );
        }

        $rolLogisticaId = $this->rolId(self::ROL_STOCK_PERM_REF);
        if ($rolLogisticaId > 0) {
            $ids = $ids->merge(
                DB::table('permiso as p')
                    ->join('permiso_rol as pr', 'pr.permiso_id', '=', 'p.id')
                    ->where('pr.rol_id', $rolLogisticaId)
                    ->where(function ($q) use ($stockMenuIds) {
                        if ($stockMenuIds !== []) {
                            $q->whereIn('p.menu_id', $stockMenuIds);
                        }
                        $q->orWhere('p.slug', 'like', '%articulo%')
                            ->orWhere('p.slug', 'like', '%deposito%')
                            ->orWhere('p.slug', 'like', '%depmae%')
                            ->orWhere('p.slug', 'like', '%stock%')
                            ->orWhere('p.slug', 'like', '%recepcion%')
                            ->orWhere('p.slug', 'like', '%recuento%')
                            ->orWhere('p.slug', 'like', '%transferencia%')
                            ->orWhere('p.slug', 'like', '%movimientos-de-stock%')
                            ->orWhere('p.slug', 'like', '%movimientostock%')
                            ->orWhere('p.slug', 'like', '%prestamo%')
                            ->orWhere('p.slug', 'like', '%salida-bien%')
                            ->orWhere('p.slug', 'like', '%npu%')
                            ->orWhere('p.slug', 'like', '%existencias%')
                            ->orWhere('p.slug', 'like', '%formula%')
                            ->orWhere('p.slug', 'like', '%categoria%')
                            ->orWhere('p.slug', 'like', '%subcategoria%')
                            ->orWhere('p.slug', 'like', '%unidadmedida%')
                            ->orWhere('p.slug', 'like', '%mventa%')
                            ->orWhere('p.slug', 'like', '%listaprecio%')
                            ->orWhere('p.slug', 'like', '%tipoarticulo%')
                            ->orWhere('p.slug', 'like', '%usoarticulo%')
                            ->orWhere('p.slug', 'like', '%codigobarra%')
                            ->orWhere('p.slug', 'like', '%tipotransaccion-stock%')
                            ->orWhere('p.slug', 'like', '%tipotransaccion_stock%');
                    })
                    ->pluck('p.id')
            );
        }

        return $ids->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
    }

    private function crearUsuarios(int $rolId): void
    {
        $salaId = (int) (DB::table('sala')->where('codigo', self::SALA_CODIGO)->value('id') ?? 0);
        $now = now();
        $passwordHash = Hash::make(self::PASSWORD);

        foreach (self::USUARIOS as $fila) {
            $centrocostoId = (int) (DB::table('centrocosto')
                ->where('codigo', $fila['centrocosto_codigo'])
                ->value('id') ?? 0);

            $usuarioId = $this->usuarioId($fila['usuario']);
            if ($usuarioId <= 0) {
                $usuarioId = (int) DB::table('usuario')->insertGetId([
                    'usuario' => $fila['usuario'],
                    'nombre' => $fila['nombre'],
                    'email' => $fila['usuario'].self::EMAIL_DOMINIO,
                    'password' => $passwordHash,
                    'centrocosto_id' => $centrocostoId > 0 ? $centrocostoId : null,
                    'suspendido' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('usuario')->where('id', $usuarioId)->update([
                    'nombre' => $fila['nombre'],
                    'email' => $fila['usuario'].self::EMAIL_DOMINIO,
                    'centrocosto_id' => $centrocostoId > 0 ? $centrocostoId : null,
                    'suspendido' => 0,
                    'updated_at' => $now,
                ]);
            }

            if ($usuarioId <= 0) {
                continue;
            }

            if (Schema::hasTable('usuario_rol')
                && ! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolId)->exists()
            ) {
                DB::table('usuario_rol')->insert([
                    'usuario_id' => $usuarioId,
                    'rol_id' => $rolId,
                ]);
            }

            if ($salaId > 0 && Schema::hasTable('usuario_sala')
                && ! DB::table('usuario_sala')->where('usuario_id', $usuarioId)->where('sala_id', $salaId)->exists()
            ) {
                DB::table('usuario_sala')->insert([
                    'usuario_id' => $usuarioId,
                    'sala_id' => $salaId,
                ]);
            }

            if (Schema::hasTable('usuario_empresa')
                && ! DB::table('usuario_empresa')->where('usuario_id', $usuarioId)->where('empresa_id', self::EMPRESA_ID)->exists()
            ) {
                DB::table('usuario_empresa')->insert([
                    'usuario_id' => $usuarioId,
                    'empresa_id' => self::EMPRESA_ID,
                ]);
            }
        }
    }

    private function corregirDepositosCatriel(): void
    {
        if (! Schema::hasTable('depmae') || ! Schema::hasColumn('depmae', 'sala_id')) {
            return;
        }

        $salaId = (int) (DB::table('sala')->where('codigo', self::SALA_CODIGO)->value('id') ?? 0);
        if ($salaId <= 0) {
            return;
        }

        DB::table('depmae')
            ->where(function ($q) {
                $q->whereBetween('codigo', ['1800', '1899'])
                    ->orWhere('nombre', 'like', 'CATRIEL%');
            })
            ->where('sala_id', '!=', $salaId)
            ->update([
                'sala_id' => $salaId,
                'updated_at' => now(),
            ]);
    }

    private function revertirDepositosCatriel(): void
    {
        if (! Schema::hasTable('depmae') || ! Schema::hasColumn('depmae', 'sala_id')) {
            return;
        }

        $salaAc = (int) (DB::table('sala')->where('codigo', 'AC')->value('id') ?? 0);
        $salaCat = (int) (DB::table('sala')->where('codigo', self::SALA_CODIGO)->value('id') ?? 0);
        if ($salaAc <= 0 || $salaCat <= 0) {
            return;
        }

        DB::table('depmae')
            ->where(function ($q) {
                $q->whereBetween('codigo', ['1800', '1899'])
                    ->orWhere('nombre', 'like', 'CATRIEL%');
            })
            ->where('sala_id', $salaCat)
            ->update([
                'sala_id' => $salaAc,
                'updated_at' => now(),
            ]);
    }

    private function asegurarAncestrosMenu(int $rolId, int $menuId): void
    {
        $padreId = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0);
        while ($padreId > 0) {
            $this->asegurarMenuRol($rolId, $padreId);
            $padreId = (int) (DB::table('menu')->where('id', $padreId)->value('menu_id') ?? 0);
        }
    }

    private function asegurarMenuRol(int $rolId, int $menuId): void
    {
        if ($menuId <= 0 || ! Schema::hasTable('menu_rol')) {
            return;
        }
        if (DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $menuId)->exists()) {
            return;
        }
        DB::table('menu_rol')->insert([
            'rol_id' => $rolId,
            'menu_id' => $menuId,
        ]);
    }

    private function asegurarPermisoRol(int $rolId, int $permisoId): void
    {
        if ($permisoId <= 0 || ! Schema::hasTable('permiso_rol')) {
            return;
        }
        if (DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
            return;
        }
        DB::table('permiso_rol')->insert([
            'rol_id' => $rolId,
            'permiso_id' => $permisoId,
        ]);
    }

    private function rolId(string $nombre): int
    {
        return (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
    }

    private function usuarioId(string $usuario): int
    {
        return (int) (DB::table('usuario')->where('usuario', $usuario)->value('id') ?? 0);
    }

    private function permisoId(string $slug): int
    {
        return (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
    }
};
