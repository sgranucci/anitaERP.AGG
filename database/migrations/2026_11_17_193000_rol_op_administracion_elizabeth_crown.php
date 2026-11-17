<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: rol op-administracion = Enc-cuentasapagar + módulo de Stock completo
 * (menús de administrador bajo Stock, sin tablas Ferli). Se asigna a eflores
 * (Elizabeth Flores) en lugar de Enc-cuentasapagar.
 */
return new class extends Migration
{
    private const ROL_NUEVO = 'op-administracion';

    private const ROL_BASE = 'Enc-cuentasapagar';

    private const ROL_STOCK_REF = 'administrador';

    private const ROL_STOCK_PERM_REF = 'Enc-logistica';

    private const USUARIO = 'eflores';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('rol') || ! Schema::hasTable('menu_rol') || ! Schema::hasTable('permiso_rol')) {
            return;
        }

        $rolBaseId = $this->rolId(self::ROL_BASE);
        if ($rolBaseId <= 0) {
            return;
        }

        $rolId = $this->asegurarRol($rolBaseId);
        if ($rolId <= 0) {
            return;
        }

        $this->copiarMenuYPermisoDesde($rolBaseId, $rolId);
        $this->agregarModuloStockCompleto($rolId);
        $this->asignarUsuario($rolId, $rolBaseId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('rol') || ! Schema::hasTable('menu_rol') || ! Schema::hasTable('permiso_rol')) {
            return;
        }

        $rolId = $this->rolId(self::ROL_NUEVO);
        if ($rolId <= 0) {
            return;
        }

        $usuarioId = $this->usuarioId(self::USUARIO);
        $rolBaseId = $this->rolId(self::ROL_BASE);

        if ($usuarioId > 0) {
            DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolId)->delete();
            if ($rolBaseId > 0
                && ! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolBaseId)->exists()
            ) {
                DB::table('usuario_rol')->insert([
                    'usuario_id' => $usuarioId,
                    'rol_id' => $rolBaseId,
                ]);
            }
        }

        DB::table('menu_rol')->where('rol_id', $rolId)->delete();
        DB::table('permiso_rol')->where('rol_id', $rolId)->delete();
        DB::table('usuario_rol')->where('rol_id', $rolId)->delete();
        DB::table('rol')->where('id', $rolId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarRol(int $rolBaseId): int
    {
        $existente = $this->rolId(self::ROL_NUEVO);
        if ($existente > 0) {
            return $existente;
        }

        $centrocostoId = DB::table('rol')->where('id', $rolBaseId)->value('centrocosto_id');
        $now = now();

        return (int) DB::table('rol')->insertGetId([
            'nombre' => self::ROL_NUEVO,
            'centrocosto_id' => $centrocostoId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function copiarMenuYPermisoDesde(int $desdeRolId, int $haciaRolId): void
    {
        foreach (DB::table('menu_rol')->where('rol_id', $desdeRolId)->pluck('menu_id') as $menuId) {
            $this->asegurarMenuRol($haciaRolId, (int) $menuId);
        }

        foreach (DB::table('permiso_rol')->where('rol_id', $desdeRolId)->pluck('permiso_id') as $permisoId) {
            $this->asegurarPermisoRol($haciaRolId, (int) $permisoId);
        }
    }

    private function agregarModuloStockCompleto(int $rolId): void
    {
        $stockMenuIds = $this->menusStockCompleto();
        foreach ($stockMenuIds as $menuId) {
            $this->asegurarMenuRol($rolId, $menuId);
            $this->asegurarAncestrosMenu($rolId, $menuId);
        }

        foreach ($this->permisosStockCompleto($stockMenuIds) as $permisoId) {
            $this->asegurarPermisoRol($rolId, $permisoId);
        }

        // Categoría/subcategoría de artículos: permiso.menu_id apunta a ticket (167), no a stock.
        foreach ([
            'listar-categorias', 'crear-categorias', 'editar-categorias', 'actualizar-categorias',
            'listar-subcategorias', 'crear-subcategorias', 'editar-subcategorias', 'actualizar-subcategorias',
        ] as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            $this->asegurarPermisoRol($rolId, $permisoId);
        }
    }

    /**
     * Menús Stock de administrador + carpeta Informes (649) si existe.
     *
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

    private function asignarUsuario(int $rolNuevoId, int $rolBaseId): void
    {
        if (! Schema::hasTable('usuario_rol')) {
            return;
        }

        $usuarioId = $this->usuarioId(self::USUARIO);
        if ($usuarioId <= 0) {
            return;
        }

        if (! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolNuevoId)->exists()) {
            DB::table('usuario_rol')->insert([
                'usuario_id' => $usuarioId,
                'rol_id' => $rolNuevoId,
            ]);
        }

        DB::table('usuario_rol')
            ->where('usuario_id', $usuarioId)
            ->where('rol_id', $rolBaseId)
            ->delete();
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
        if ($menuId <= 0) {
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
        if ($permisoId <= 0) {
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
};
