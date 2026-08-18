<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos premium: tokens API, PII, confidencial.
 * Convención: nombre = etiqueta, slug = clave can().
 */
return new class extends Migration
{
    /** @var list<array{nombre:string,slug:string}> */
    private const PERMISOS = [
        ['nombre' => 'Administrar tokens API Sanctum', 'slug' => 'administrar-api-tokens'],
        ['nombre' => 'Ver PII en reportes definibles de sueldos', 'slug' => 'ver-pii-reporte-sueldos-definible'],
        ['nombre' => 'Incluir nómina confidencial en reportes definibles', 'slug' => 'ver-confidencial-reporte-sueldos-definible'],
    ];

    public function up(): void
    {
        $now = now();
        $menuId = (int) (DB::table('menu')->where('url', 'sueldos/reporte-definible')->value('id') ?? 0);
        $permisoIds = [];

        foreach (self::PERMISOS as $perm) {
            $id = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            $payload = [
                'nombre' => $perm['nombre'],
                'slug' => $perm['slug'],
                'menu_id' => $menuId > 0 ? $menuId : null,
                'updated_at' => $now,
            ];
            if ($id > 0) {
                DB::table('permiso')->where('id', $id)->update($payload);
            } else {
                $payload['created_at'] = $now;
                $id = (int) DB::table('permiso')->insertGetId($payload);
            }
            $permisoIds[] = $id;
        }

        $adminRol = DB::table('rol')->where('nombre', 'administrador')->value('id')
            ?? DB::table('rol')->where('nombre', 'Administrador')->value('id');
        if ($adminRol) {
            foreach ($permisoIds as $permisoId) {
                $ya = DB::table('permiso_rol')
                    ->where('rol_id', $adminRol)
                    ->where('permiso_id', $permisoId)
                    ->exists();
                if (! $ya) {
                    DB::table('permiso_rol')->insert([
                        'rol_id' => $adminRol,
                        'permiso_id' => $permisoId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $slugs = array_column(self::PERMISOS, 'slug');
        $ids = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $ids)->delete();
            DB::table('permiso')->whereIn('id', $ids)->delete();
        }
    }
};
