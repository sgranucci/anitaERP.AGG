<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/rendicionbingo';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $permisoDiaId = $this->upsertPermiso(
            'Borrar rendición bingo caja en el día',
            'borrar-rendicion-bingo-caja-dia',
            $menuId,
        );
        $permisoEncargadoId = $this->upsertPermiso(
            'Borrar rendición bingo caja sin restricción de fecha',
            'borrar-rendicion-bingo-caja-encargado',
            $menuId,
        );

        // El permiso "del día" acompaña a los roles que ya pueden borrar bingo en caja.
        $borrarId = (int) (DB::table('permiso')->where('slug', 'borrar-rendicion-bingo-caja')->value('id') ?? 0);
        if ($borrarId > 0) {
            foreach (DB::table('permiso_rol')->where('permiso_id', $borrarId)->pluck('rol_id') as $rolId) {
                $this->asignarPermisoRol($permisoDiaId, (int) $rolId);
            }
        }

        // El permiso sin restricción de fecha queda para encargados/gerencia de tesorería.
        $rolesEncargado = DB::table('rol')
            ->whereIn('nombre', ['Enc-tesorería', 'Enc-tesoreria', 'enc-Tesoreria Operativa', 'Ger-Tesoreria', 'Sup-tesoreria', 'Sup-Tesoreria'])
            ->pluck('id')
            ->all();
        foreach ($rolesEncargado as $rolId) {
            $this->asignarPermisoRol($permisoEncargadoId, (int) $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);

        if ($permisoId === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'menu_id' => $menuId,
            'nombre' => $nombre,
            'updated_at' => now(),
        ]);

        return $permisoId;
    }

    private function asignarPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
        }
    }

    public function down(): void
    {
        $slugs = [
            'borrar-rendicion-bingo-caja-dia',
            'borrar-rendicion-bingo-caja-encargado',
        ];

        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
