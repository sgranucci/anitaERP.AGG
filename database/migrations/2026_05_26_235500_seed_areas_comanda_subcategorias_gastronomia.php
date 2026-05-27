<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Áreas de comanda (KDS) y asignación a subcategorías según
 * listado kds y horario (1).xlsx
 * - BIYEMAS (Avellaneda, emp. 1): 3 KDS — cocina, tomasso, pizzeria
 * - KANDIKO (Wilde, emp. 2): 2 KDS — cocina, mostrador
 * - REBISCO (Varela, emp. 3): 2 KDS — cocina, mostrador
 *
 * Columnas Excel: 3–5 → emp. 1; col. 6 → emp. 2 cocina; col. 7 → emp. 3 mostrador.
 */
return new class extends Migration
{
    /** @var array<int, array{empresa_id: int, area_codigo: string}> */
    private array $columnaExcel = [
        3 => ['empresa_id' => 1, 'area_codigo' => 'kds_cocina'],
        4 => ['empresa_id' => 1, 'area_codigo' => 'kds_tomasso'],
        5 => ['empresa_id' => 1, 'area_codigo' => 'kds_pizzeria'],
        6 => ['empresa_id' => 2, 'area_codigo' => 'kds_cocina'],
        7 => ['empresa_id' => 3, 'area_codigo' => 'kds_mostrador'],
    ];

    /** @var array<int, array{codigo: string, nombre: string}> */
    private array $areasPorEmpresa = [
        1 => [
            ['codigo' => 'kds_cocina', 'nombre' => 'KDS Cocina'],
            ['codigo' => 'kds_tomasso', 'nombre' => 'KDS Tomasso'],
            ['codigo' => 'kds_pizzeria', 'nombre' => 'KDS Pizzeria'],
        ],
        2 => [
            ['codigo' => 'kds_cocina', 'nombre' => 'KDS Cocina'],
            ['codigo' => 'kds_mostrador', 'nombre' => 'KDS Mostrador'],
        ],
        3 => [
            ['codigo' => 'kds_cocina', 'nombre' => 'KDS Cocina'],
            ['codigo' => 'kds_mostrador', 'nombre' => 'KDS Mostrador'],
        ],
    ];

    /**
     * Sección del menú (Excel) → nombres de subcategoría en ERP.
     * Varias filas del Excel pueden apuntar a la misma subcategoría (p. ej. EMPANADAS).
     *
     * @var array<string, list<string>>
     */
    private array $subcategoriasPorSeccionExcel = [
        'MENU SALUDABLE' => ['MENU SALUDABLE'],
        'PANCHOS' => ['PANCHOS'],
        'SANDWICH GOURMET' => ['SANDWICH GOURMET'],
        'SANDWICHES' => ['SANDWICHES'],
        'HAMBURGUESAS' => ['HAMBURGUESAS'],
        'EMPANADAS LINEA TOMASSO' => ['EMPANADAS'],
        'EMPANADAS CASERAS' => ['EMPANADAS'],
        'PIZZAS' => ['PIZZAS'],
        'MENU ESPECIALES' => ['MENUES (ESPECIALES)'],
        'FRITOS' => ['FRITOS'],
        'PROMOS' => ['PROMOS'],
        'PICOTEO Y GOLOSINAS' => ['PICOTEO Y GOLOSINAS'],
        'CAFETERIA' => ['CAFETERIA'],
        'PASTELERIA' => ['PASTELERIA'],
        'HELADOS' => ['HELADOS MUNCHIS'],
        'POSTRES' => ['POSTRES'],
        'GASEOSAS' => ['BEBIDAS'],
        'AGUAS SABORIZADAS' => ['BEBIDAS'],
        'CERVEZAS' => ['BEBIDAS'],
        'ESPUMANTE' => ['ESPUMANTES'],
        'APERITIVOS' => ['APERITIVOS'],
        'LICORES' => ['LICORES'],
        'WHISKYS' => ['WHISKYS'],
        'TRAGOS Y COCTELES' => ['TRAGOS Y COCTELES'],
        'VINOS' => ['VINOS BLANCOS', 'VINOS TINTOS'],
    ];

    /** @var array<string, list<int>> */
    private array $columnasMarcadasExcel = [
        'MENU SALUDABLE' => [3, 5, 6, 7],
        'PANCHOS' => [3, 5, 6, 7],
        'SANDWICH GOURMET' => [3, 5, 6, 7],
        'SANDWICHES' => [3, 4, 6, 7],
        'HAMBURGUESAS' => [3, 5, 6, 7],
        'EMPANADAS LINEA TOMASSO' => [4, 6, 7],
        'EMPANADAS CASERAS' => [5, 6, 7],
        'PIZZAS' => [5, 6, 7],
        'MENU ESPECIALES' => [3, 5, 6, 7],
        'FRITOS' => [3, 5, 6, 7],
        'PROMOS' => [3, 4, 5, 6, 7],
        'PICOTEO Y GOLOSINAS' => [4, 7],
        'CAFETERIA' => [4, 7],
        'PASTELERIA' => [4, 7],
        'HELADOS' => [4, 7],
        'POSTRES' => [4, 7],
        'GASEOSAS' => [4, 5, 7],
        'AGUAS SABORIZADAS' => [4, 5, 7],
        'CERVEZAS' => [4, 5, 7],
        'ESPUMANTE' => [5, 7],
        'APERITIVOS' => [4, 7],
        'LICORES' => [4, 7],
        'WHISKYS' => [4, 7],
        'TRAGOS Y COCTELES' => [4, 7],
        'VINOS' => [5, 7],
    ];

    public function up(): void
    {
        $now = now();
        $areaIds = [];

        foreach ($this->areasPorEmpresa as $empresaId => $areas) {
            foreach ($areas as $area) {
                $existente = DB::table('area_comanda_gastronomia')
                    ->where('empresa_id', $empresaId)
                    ->where('codigo', $area['codigo'])
                    ->value('id');

                if ($existente) {
                    DB::table('area_comanda_gastronomia')->where('id', $existente)->update([
                        'nombre' => $area['nombre'],
                        'updated_at' => $now,
                    ]);
                    $areaIds[$empresaId][$area['codigo']] = (int) $existente;
                } else {
                    $areaIds[$empresaId][$area['codigo']] = (int) DB::table('area_comanda_gastronomia')->insertGetId([
                        'nombre' => $area['nombre'],
                        'codigo' => $area['codigo'],
                        'empresa_id' => $empresaId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        $subcategoriasPorNombre = DB::table('subcategoria')
            ->pluck('id', 'nombre')
            ->mapWithKeys(fn ($id, $nombre) => [mb_strtoupper(trim($nombre)) => (int) $id])
            ->all();

        /** @var array<int, list<int>> $areasPorSubcategoria */
        $areasPorSubcategoria = [];

        foreach ($this->columnasMarcadasExcel as $seccionExcel => $columnas) {
            $nombresSub = $this->subcategoriasPorSeccionExcel[$seccionExcel] ?? [];
            foreach ($nombresSub as $nombreSub) {
                $subcategoriaId = $subcategoriasPorNombre[mb_strtoupper(trim($nombreSub))] ?? null;
                if ($subcategoriaId === null) {
                    continue;
                }
                foreach ($columnas as $col) {
                    $marca = $this->columnaExcel[$col] ?? null;
                    if ($marca === null) {
                        continue;
                    }
                    $areaId = $areaIds[$marca['empresa_id']][$marca['area_codigo']] ?? null;
                    if ($areaId === null) {
                        continue;
                    }
                    $areasPorSubcategoria[$subcategoriaId] ??= [];
                    $areasPorSubcategoria[$subcategoriaId][$areaId] = $areaId;
                }
            }
        }

        foreach ($areasPorSubcategoria as $subcategoriaId => $areaIdList) {
            foreach ($areaIdList as $areaId) {
                $existe = DB::table('subcategoria_area_comanda')
                    ->where('subcategoria_id', $subcategoriaId)
                    ->where('area_comanda_gastronomia_id', $areaId)
                    ->exists();

                if (! $existe) {
                    DB::table('subcategoria_area_comanda')->insert([
                        'subcategoria_id' => $subcategoriaId,
                        'area_comanda_gastronomia_id' => $areaId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $codigosArea = ['kds_cocina', 'kds_tomasso', 'kds_pizzeria', 'kds_mostrador'];
        $empresaIds = [1, 2, 3];

        $areaIds = DB::table('area_comanda_gastronomia')
            ->whereIn('empresa_id', $empresaIds)
            ->whereIn('codigo', $codigosArea)
            ->pluck('id')
            ->all();

        if ($areaIds !== []) {
            DB::table('subcategoria_area_comanda')
                ->whereIn('area_comanda_gastronomia_id', $areaIds)
                ->delete();

            DB::table('area_comanda_gastronomia')
                ->whereIn('id', $areaIds)
                ->delete();
        }
    }
};
