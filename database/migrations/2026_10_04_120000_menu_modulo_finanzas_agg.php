<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: módulo Finanzas.
 * Atajos (misma URL) de cuentas de caja, ingresos/egresos, pago a proveedores y cheques.
 * Mueve posición bancaria diaria y la carpeta API Interbanking desde Caja.
 * Alta de Precarga de movimientos.
 */
return new class extends Migration
{
    private const RAIZ = 'Módulo de Finanzas';

    private const CAJA = 'Módulo de Caja';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS_PRECARGA = [
        ['nombre' => 'Listar precargas de cash flow', 'slug' => 'listar-finanza-movimiento-precarga'],
        ['nombre' => 'Crear precargas de cash flow', 'slug' => 'crear-finanza-movimiento-precarga'],
        ['nombre' => 'Editar precargas de cash flow', 'slug' => 'editar-finanza-movimiento-precarga'],
        ['nombre' => 'Actualizar precargas de cash flow', 'slug' => 'actualizar-finanza-movimiento-precarga'],
        ['nombre' => 'Borrar precargas de cash flow', 'slug' => 'borrar-finanza-movimiento-precarga'],
        ['nombre' => 'Contabilizar precargas de cash flow', 'slug' => 'convertir-finanza-movimiento-precarga'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $cajaId = $this->raizId(self::CAJA);
        if ($cajaId <= 0) {
            return;
        }

        $finanzasId = $this->asegurarRaiz();
        $this->copiarRoles($cajaId, $finanzasId);

        $this->atajo($finanzasId, 'caja/cuentacaja', 1, 'fa-cash-register');
        $this->moverHijo($cajaId, $finanzasId, 'caja/posicion-bancaria-diaria', 2);
        $precargaId = $this->asegurarPrecarga($finanzasId);
        $this->atajo($finanzasId, 'caja/ingresoegreso', 4, 'fa-check');
        $this->atajo($finanzasId, 'compras/pagoproveedor', 5, 'fa-money');
        $this->duplicarCarpeta($cajaId, $finanzasId, 'Cheques', 6);
        $this->moverCarpeta($cajaId, $finanzasId, 'API Interbanking', 7);

        $rolIds = $this->rolesDe([$finanzasId, $cajaId, $precargaId]);
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($adminId > 0) {
            $rolIds[] = $adminId;
        }
        $rolIds = array_values(array_unique(array_filter($rolIds)));
        foreach ($rolIds as $rolId) {
            $this->vincularMenuRol($finanzasId, $rolId);
            $this->vincularMenuRol($precargaId, $rolId);
        }
        foreach (self::PERMISOS_PRECARGA as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $precargaId);
            foreach ($rolIds as $rolId) {
                $this->vincularPermisoRol($permisoId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $cajaId = $this->raizId(self::CAJA);
        $finanzasId = $this->raizId(self::RAIZ);
        if ($finanzasId <= 0) {
            return;
        }

        if ($cajaId > 0) {
            $this->moverHijo($finanzasId, $cajaId, 'caja/posicion-bancaria-diaria', 22);
            $this->moverCarpeta($finanzasId, $cajaId, 'API Interbanking', 9);
        }

        foreach (self::PERMISOS_PRECARGA as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $this->borrarDescendientes($finanzasId);
        DB::table('menu_rol')->where('menu_id', $finanzasId)->delete();
        DB::table('menu')->where('id', $finanzasId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarRaiz(): int
    {
        $id = $this->raizId(self::RAIZ);
        if ($id > 0) {
            return $id;
        }

        DB::table('menu')
            ->where('menu_id', 0)
            ->where('orden', '>=', 6)
            ->increment('orden');

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => 0,
            'nombre' => self::RAIZ,
            'url' => '#',
            'orden' => 6,
            'icono' => 'fa-line-chart',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function asegurarPrecarga(int $finanzasId): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $finanzasId)
            ->where('url', 'finanzas/movimiento-precarga')
            ->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'nombre' => 'Precarga de movimientos',
                'orden' => 3,
                'icono' => 'fa-exchange',
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $finanzasId,
            'nombre' => 'Precarga de movimientos',
            'url' => 'finanzas/movimiento-precarga',
            'orden' => 3,
            'icono' => 'fa-exchange',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function atajo(int $padreId, string $url, int $orden, string $icono): void
    {
        $canonico = DB::table('menu')->where('url', $url)->orderBy('id')->first();
        $id = (int) (DB::table('menu')->where('menu_id', $padreId)->where('url', $url)->value('id') ?? 0);
        $nombre = (string) ($canonico->nombre ?? $url);
        $iconoFinal = (string) ($canonico->icono ?? $icono);
        if ($iconoFinal === '') {
            $iconoFinal = $icono;
        }
        if ($id <= 0) {
            $id = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $iconoFinal,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $id)->update([
                'nombre' => $nombre,
                'orden' => $orden,
                'icono' => $iconoFinal,
                'updated_at' => now(),
            ]);
        }
        if ($canonico) {
            $this->copiarRoles((int) $canonico->id, $id);
            $this->copiarRoles((int) $canonico->id, $padreId);
        }
    }

    private function moverHijo(int $desdePadre, int $haciaPadre, string $url, int $orden): void
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $desdePadre)
            ->where('url', $url)
            ->value('id') ?? 0);
        if ($id <= 0) {
            $id = (int) (DB::table('menu')
                ->where('menu_id', $haciaPadre)
                ->where('url', $url)
                ->value('id') ?? 0);
        }
        if ($id <= 0) {
            return;
        }
        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $haciaPadre,
            'orden' => $orden,
            'updated_at' => now(),
        ]);
        $this->copiarRoles($id, $haciaPadre);
    }

    private function moverCarpeta(int $desdePadre, int $haciaPadre, string $nombre, int $orden): void
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $desdePadre)
            ->where('nombre', $nombre)
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($id <= 0) {
            return;
        }
        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $haciaPadre,
            'orden' => $orden,
            'updated_at' => now(),
        ]);
        $this->copiarRoles($id, $haciaPadre);
    }

    private function duplicarCarpeta(int $cajaId, int $finanzasId, string $nombre, int $orden): void
    {
        $origen = DB::table('menu')
            ->where('menu_id', $cajaId)
            ->where('nombre', $nombre)
            ->where('url', '#')
            ->first();
        if ($origen === null) {
            return;
        }
        $destinoId = (int) (DB::table('menu')
            ->where('menu_id', $finanzasId)
            ->where('nombre', $nombre)
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($destinoId <= 0) {
            $destinoId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $finanzasId,
                'nombre' => $nombre,
                'url' => '#',
                'orden' => $orden,
                'icono' => (string) ($origen->icono ?: 'fa-money-check'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $destinoId)->update([
                'orden' => $orden,
                'icono' => (string) ($origen->icono ?: 'fa-money-check'),
                'updated_at' => now(),
            ]);
        }
        $this->copiarRoles((int) $origen->id, $destinoId);
        $this->copiarRoles((int) $origen->id, $finanzasId);

        $hijos = DB::table('menu')->where('menu_id', (int) $origen->id)->orderBy('orden')->get();
        foreach ($hijos as $hijo) {
            if ((string) $hijo->url === '#') {
                continue;
            }
            $this->atajo($destinoId, (string) $hijo->url, (int) $hijo->orden, (string) $hijo->icono);
        }
    }

    private function raizId(string $nombre): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', $nombre)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
    }

    private function copiarRoles(int $desde, int $hacia): void
    {
        if ($desde <= 0 || $hacia <= 0) {
            return;
        }
        $roles = DB::table('menu_rol')->where('menu_id', $desde)->pluck('rol_id');
        foreach ($roles as $rolId) {
            $this->vincularMenuRol($hacia, (int) $rolId);
        }
    }

    /**
     * @param  list<int>  $menuIds
     * @return list<int>
     */
    private function rolesDe(array $menuIds): array
    {
        return DB::table('menu_rol')
            ->whereIn('menu_id', array_filter($menuIds))
            ->pluck('rol_id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => $nombre,
                'menu_id' => $menuId > 0 ? $menuId : null,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function vincularMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function vincularPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function borrarDescendientes(int $padreId): void
    {
        $hijos = DB::table('menu')->where('menu_id', $padreId)->pluck('id');
        foreach ($hijos as $hijoId) {
            $this->borrarDescendientes((int) $hijoId);
            DB::table('menu_rol')->where('menu_id', $hijoId)->delete();
            DB::table('menu')->where('id', $hijoId)->delete();
        }
    }
};
