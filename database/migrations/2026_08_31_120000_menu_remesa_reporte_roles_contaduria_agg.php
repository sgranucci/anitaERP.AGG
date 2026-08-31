<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asigna menú padre Remesas + hijo Reporte + permiso listar-remesa-reporte
 * a administrador y roles de contaduría. Solo AGG.
 */
return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Remesas';

    private const MENU_REPORTE_URL = 'caja/remesa-reporte';

    private const PERMISO_SLUG = 'listar-remesa-reporte';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-contaduría',
        'Op-contaduria',
        'Sup-contaduria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $cajaId = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Caja')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 104);

        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $cajaId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        $reporteId = (int) (DB::table('menu')->where('url', self::MENU_REPORTE_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($rolIds as $rolId) {
            if ($padreId > 0) {
                $this->vincularMenuRol($padreId, $rolId);
            }
            if ($reporteId > 0) {
                $this->vincularMenuRol($reporteId, $rolId);
            }
            if ($permisoId > 0) {
                $this->vincularPermisoRol($permisoId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No revierte: la asignación es aditiva y puede coexistir con tesorería.
    }

    private function vincularMenuRol(int $menuId, int $rolId): void
    {
        $exists = DB::table('menu_rol')
            ->where('menu_id', $menuId)
            ->where('rol_id', $rolId)
            ->exists();
        if (! $exists) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    private function vincularPermisoRol(int $permisoId, int $rolId): void
    {
        $exists = DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->where('rol_id', $rolId)
            ->exists();
        if (! $exists) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }
};
