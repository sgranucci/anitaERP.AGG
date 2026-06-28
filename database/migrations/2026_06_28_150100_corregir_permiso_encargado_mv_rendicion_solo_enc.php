<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige slug truncado (varchar 50) y deja el permiso encargado solo en Enc-gastronomía.
 */
return new class extends Migration
{
    private const SLUG_NUEVO = 'actualizar-mv-rend-gastronomia-encargado';

    private const SLUG_VIEJO_TRUNCADO = 'actualizar-maquinavending-rendicion-gastronomia-en';

    /** @var list<string> */
    private const ROLES_EXCLUIR = [
        'Sup-Gastronomia',
    ];

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')
            ->whereIn('slug', [self::SLUG_NUEVO, self::SLUG_VIEJO_TRUNCADO])
            ->value('id') ?? 0);

        if ($permisoId <= 0) {
            return;
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'slug' => self::SLUG_NUEVO,
            'nombre' => 'Modificar rendición vending (jornada anterior)',
            'updated_at' => now(),
        ]);

        foreach ($this->resolverRolesExcluir() as $rolId) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolesExcluir(): array
    {
        $rolIds = [];
        foreach (self::ROLES_EXCLUIR as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $rolIds[] = $id;
            }
        }

        if ($rolIds !== []) {
            return array_values(array_unique($rolIds));
        }

        $supId = (int) (DB::table('rol')->where('nombre', 'like', 'Sup-Gastronom%')->orderBy('id')->value('id') ?? 0);

        return $supId > 0 ? [$supId] : [];
    }

    public function down(): void
    {
        // Sin reversión: el slug truncado era inválido.
    }
};
