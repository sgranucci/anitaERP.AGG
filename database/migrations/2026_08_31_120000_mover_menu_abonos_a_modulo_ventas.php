<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Abonos es un proceso de Ventas (no tabla maestra): menú propio bajo Módulo de Ventas.
 */
return new class extends Migration
{
    private const PARENT_MODULO = 'Módulo de Ventas';

    private const GRUPO_NOMBRE = 'Abonos';

    /** @var list<array{url: string, nombre: string, orden: int}> */
    private const HIJOS = [
        ['url' => 'ventas/contrato-venta', 'nombre' => 'Abonos / contratos', 'orden' => 1],
        ['url' => 'ventas/contrato-venta-cola', 'nombre' => 'Cola facturación abonos', 'orden' => 2],
        ['url' => 'ventas/concepto-venta', 'nombre' => 'Conceptos de venta', 'orden' => 3],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Ger-administracion',
        'Enc-contaduría',
        'Op-contaduria',
    ];

    public function up(): void
    {
        $moduloId = $this->resolverModuloVentasId();
        if ($moduloId === 0) {
            return;
        }

        // Insertar grupo Abonos justo después de Facturación (orden 3).
        DB::table('menu')
            ->where('menu_id', $moduloId)
            ->where('orden', '>=', 3)
            ->increment('orden');

        $grupoId = $this->resolverGrupoAbonosId($moduloId);
        if ($grupoId === 0) {
            $grupoId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloId,
                'nombre' => self::GRUPO_NOMBRE,
                'url' => '#',
                'orden' => 3,
                'icono' => 'fa fa-file-contract',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $grupoId)->update([
                'menu_id' => $moduloId,
                'nombre' => self::GRUPO_NOMBRE,
                'url' => '#',
                'orden' => 3,
                'icono' => 'fa fa-file-contract',
                'updated_at' => now(),
            ]);
        }

        foreach (self::HIJOS as $hijo) {
            $menuId = (int) (DB::table('menu')->where('url', $hijo['url'])->value('id') ?? 0);
            if ($menuId === 0) {
                continue;
            }
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $grupoId,
                'nombre' => $hijo['nombre'],
                'orden' => $hijo['orden'],
                'updated_at' => now(),
            ]);
        }

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $grupoId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $grupoId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $tablasId = (int) (DB::table('menu')
            ->where('nombre', 'Tablas de ventas')
            ->where('url', '#')
            ->value('id') ?? 0);

        $moduloId = $this->resolverModuloVentasId();
        $grupoId = $this->resolverGrupoAbonosId($moduloId);

        if ($tablasId > 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $tablasId)->max('orden') ?? 0);
            foreach (self::HIJOS as $hijo) {
                $orden++;
                DB::table('menu')->where('url', $hijo['url'])->update([
                    'menu_id' => $tablasId,
                    'orden' => $orden,
                    'updated_at' => now(),
                ]);
            }
        }

        if ($grupoId > 0) {
            DB::table('menu_rol')->where('menu_id', $grupoId)->delete();
            DB::table('menu')->where('id', $grupoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverModuloVentasId(): int
    {
        $moduloId = (int) (DB::table('menu')
            ->where('nombre', self::PARENT_MODULO)
            ->where(function ($q) {
                $q->where('url', '#')->orWhereNull('url')->orWhere('url', '');
            })
            ->value('id') ?? 0);

        if ($moduloId === 0) {
            $moduloId = (int) (DB::table('menu')->where('url', 'ventas/factura')->value('menu_id') ?? 0);
        }

        return $moduloId;
    }

    private function resolverGrupoAbonosId(int $moduloId): int
    {
        if ($moduloId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $moduloId)
            ->where('nombre', self::GRUPO_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
