<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permite abrir consulta de tipo de comprobante / PV desde el reporte analítico.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES = [
        'Sup-Gastronomia',
        'Enc-gastronomía',
        'Ger-Gastronomia',
        'Enc-Control de Gestión',
        'Op-Control de Gestión',
    ];

    /** @var list<string> */
    private const PERMISOS = [
        'listar-tipos-transacciones',
        'listar-puntos-de-venta',
    ];

    public function up(): void
    {
        $rolIds = $this->resolverRolIds();
        foreach (self::PERMISOS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            foreach ($rolIds as $rolId) {
                if ($rolId <= 0) {
                    continue;
                }
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
        // No revoca: puede haber sido asignado por otra vía.
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id <= 0 && str_starts_with($nombre, 'Sup-')) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Sup-Gastronom%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id <= 0 && str_starts_with($nombre, 'Enc-gastronom')) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id <= 0 && str_starts_with($nombre, 'Ger-')) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Ger-Gastronom%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id <= 0 && str_contains($nombre, 'Control de Gestión')) {
                $like = str_starts_with($nombre, 'Enc-')
                    ? 'Enc-Control de Gesti%'
                    : 'Op-Control de Gesti%';
                $id = (int) (DB::table('rol')->where('nombre', 'like', $like)->orderBy('id')->value('id') ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        foreach (DB::table('rol')->where('nombre', 'like', '%Control de Gesti%')->pluck('id') as $cgId) {
            $ids[] = (int) $cgId;
        }

        return array_values(array_unique($ids));
    }
};
