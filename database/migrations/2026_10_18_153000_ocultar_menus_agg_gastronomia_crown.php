<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: quita del menú (menu_rol) las pantallas AGG que no se usan.
 * No borra filas de `menu` (siguen existiendo para AGG / restores).
 *
 * Se ocultan: proceso facturación Waitry, turnos (ABM/habilitación/cierres/saneamiento/central),
 * tótems Waitry, canjes marketing, viandas y vending.
 * Quedan: tablas (mesas/mozos/descuentos/áreas/planos), POS/KDS Crown, facturas del día,
 * jornada, reportes genéricos.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const URLS_HOJA = [
        'ventas/gastronomia/proceso-facturacion',
        'ventas/gastronomia/habilitacion-turno',
        'ventas/gastronomia/cierres-turno',
        'ventas/gastronomia/saneamiento-turno',
        'ventas/gastronomia/cierre-turno-central',
        'ventas/turno-gastronomia',
        'ventas/totem-waitry-gastronomia',
    ];

    /** @var list<string> padres cuyo subárbol entero se oculta */
    private const URLS_PADRE_SUBARBOL = [
        // Canjes / Viandas / Vending usan url "#" — se resuelven por id conocido o nombre
    ];

    /** @var list<int> ids fijos del árbol Crown actual (padres de submenú) */
    private const IDS_PADRE_SUBARBOL = [
        291, // Canjes
        372, // Viandas
        366, // Vending
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ids = $this->idsAOcultar();
        if ($ids === []) {
            return;
        }

        DB::table('menu_rol')->whereIn('menu_id', $ids)->delete();

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        // Restaura solo a administrador / Enc-sistemas (no se puede reconstruir el mapa previo).
        $ids = $this->idsAOcultar();
        if ($ids === []) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', ['administrador', 'Enc-sistemas'])
            ->pluck('id');

        foreach ($rolIds as $rolId) {
            foreach ($ids as $menuId) {
                $existe = DB::table('menu_rol')
                    ->where('rol_id', $rolId)
                    ->where('menu_id', $menuId)
                    ->exists();
                if (! $existe) {
                    DB::table('menu_rol')->insert([
                        'rol_id' => $rolId,
                        'menu_id' => $menuId,
                    ]);
                }
            }
        }

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }

    /**
     * @return list<int>
     */
    private function idsAOcultar(): array
    {
        $ids = [];

        foreach (self::URLS_HOJA as $url) {
            $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        foreach (self::IDS_PADRE_SUBARBOL as $padreId) {
            if (DB::table('menu')->where('id', $padreId)->exists()) {
                $ids[] = $padreId;
                foreach ($this->descendientes($padreId) as $hijoId) {
                    $ids[] = $hijoId;
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return list<int>
     */
    private function descendientes(int $padreId): array
    {
        $out = [];
        $hijos = DB::table('menu')->where('menu_id', $padreId)->pluck('id');
        foreach ($hijos as $hijoId) {
            $hijoId = (int) $hijoId;
            $out[] = $hijoId;
            foreach ($this->descendientes($hijoId) as $nieto) {
                $out[] = $nieto;
            }
        }

        return $out;
    }
};
