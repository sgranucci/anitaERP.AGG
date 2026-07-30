<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asegura menú y permisos del CRUD de solicitudes de pago
 * (incluye carga masiva CSV, gated por crear-solicitud-pago)
 * a Impuestos y Capital Humano.
 */
return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Solicitudes de Pago';

    private const MENU_URL = 'solicitudpago/solicitudpago';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-impuestos',
        'Op-impuestos',
        'enc-Capital Humano',
        'op-Capital Humano',
        'ger-capitalhumano',
        'opcont-capitalhumano',
    ];

    /** Permisos operativos para listar / alta (carga masiva) / edición. */
    /** @var list<string> */
    private const PERMISO_SLUGS = [
        'listar-solicitud-pago',
        'crear-solicitud-pago',
        'editar-solicitud-pago',
        'actualizar-solicitud-pago',
    ];

    public function up(): void
    {
        $moduloId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_MODULO)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($moduloId <= 0 || $menuId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISO_SLUGS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($permisoIds === []) {
            return;
        }

        foreach ($this->resolverRolIds(self::ROLES) as $rolId) {
            foreach ([$moduloId, $menuId] as $mid) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $mid,
                        'rol_id' => $rolId,
                    ]);
                }
            }
            foreach ($permisoIds as $permisoId) {
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
        // No revierte: Impuestos ya tenía estos permisos desde migraciones previas
        // y Capital Humano puede haberlos recibido por otras vías.
        SuitecrmPermiso::flushCachePermisos();
    }

    /** @param list<string> $nombres @return list<int> */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
