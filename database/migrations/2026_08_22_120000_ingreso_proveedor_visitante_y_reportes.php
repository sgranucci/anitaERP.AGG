<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ingreso_proveedor') && ! Schema::hasColumn('ingreso_proveedor', 'visitante_tipo')) {
            Schema::table('ingreso_proveedor', function (Blueprint $table) {
                $table->dropForeign('fk_ingprov_proveedor');
            });
            DB::statement('ALTER TABLE ingreso_proveedor MODIFY proveedor_id BIGINT UNSIGNED NULL');
            Schema::table('ingreso_proveedor', function (Blueprint $table) {
                $table->foreign('proveedor_id', 'fk_ingprov_proveedor')
                    ->references('id')->on('proveedor')->onDelete('restrict');
                $table->string('visitante_tipo', 20)->default('PROVEEDOR')->after('proveedor_id');
                $table->string('visitante_nombre', 180)->nullable()->after('visitante_tipo');
                $table->index('visitante_tipo', 'idx_ingprov_visitante_tipo');
            });
        }

        $now = now();
        if (Schema::hasTable('ingreso_proveedor_motivo')
            && ! DB::table('ingreso_proveedor_motivo')->where('codigo', 'PRESUPUESTO')->exists()
        ) {
            DB::table('ingreso_proveedor_motivo')->insert([
                'codigo' => 'PRESUPUESTO',
                'nombre' => 'Presupuesto / cotización',
                'activo' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $moduloId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Seguridad')
            ->value('id') ?? 0);
        if ($moduloId <= 0) {
            return;
        }

        $menuKpi = $this->asegurarMenu($moduloId, 'Reporte tickets e ingresos', 'seguridad/reporte-tickets-ingreso', 'fa-chart-bar', 20);
        $menuPlanta = $this->asegurarMenu($moduloId, 'Ingresos de planta', 'seguridad/reporte-ingresos-planta', 'fa-clipboard-list', 21);

        $permisoKpi = $this->asegurarPermiso('listar-reporte-tickets-ingreso', 'Listar reporte tickets e ingresos', $menuKpi);
        $permisoPlanta = $this->asegurarPermiso('listar-reporte-ingresos-planta', 'Listar ingresos de planta (seguridad)', $menuPlanta);

        $this->asignar($menuKpi, $permisoKpi, ['administrador', 'Enc-admin', 'Enc-compras', 'Op-Compras']);
        $this->asignar($menuPlanta, $permisoPlanta, ['administrador', 'Enc-admin', 'enc-SEGURIDAD']);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $urls = ['seguridad/reporte-tickets-ingreso', 'seguridad/reporte-ingresos-planta'];
        $slugs = ['listar-reporte-tickets-ingreso', 'listar-reporte-ingresos-planta'];
        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        if (Schema::hasColumn('ingreso_proveedor', 'visitante_tipo')) {
            Schema::table('ingreso_proveedor', function (Blueprint $table) {
                $table->dropIndex('idx_ingprov_visitante_tipo');
                $table->dropColumn(['visitante_tipo', 'visitante_nombre']);
            });
        }
    }

    private function asegurarMenu(int $padreId, string $nombre, string $url, string $icono, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $padreId,
            'nombre' => $nombre,
            'url' => $url,
            'orden' => $orden,
            'icono' => $icono,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function asegurarPermiso(string $slug, string $nombre, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $roles
     */
    private function asignar(int $menuId, int $permisoId, array $roles): void
    {
        $rolIds = DB::table('rol')->whereIn('nombre', $roles)->pluck('id');
        $moduloId = (int) (DB::table('menu')->where('menu_id', 0)->where('nombre', 'Seguridad')->value('id') ?? 0);
        foreach ($rolIds as $rolId) {
            foreach (array_filter([$menuId, $moduloId]) as $mid) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolId]);
                }
            }
            if ($permisoId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }
    }
};
