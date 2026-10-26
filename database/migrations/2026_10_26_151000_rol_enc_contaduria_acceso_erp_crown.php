<?php

use App\Models\Admin\Menu_Rol;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Enc-contaduría queda con el menú del ERP salvo Administrador,
 * caja de AGG, suscripciones, órdenes de venta, solicitudes de pago,
 * dirección, logística, sala y producción.
 * El rol lo usa solo cchiappero.
 */
return new class extends Migration
{
    private const ROL = 'Enc-contaduría';

    /** @var list<string> */
    private const RAICES_POR_NOMBRE = [
        'Administrador',
        'Módulo de Caja',
        'Módulo Ordenes de Vta.',
        'Módulo de Sala',
        'Módulo de Producción',
        'Módulo Solicitudes de Pago',
    ];

    /** @var list<string> */
    private const RAICES_POR_URL = [
        '#config-modulo-caja',
        '#config-modulo-logistica',
        '#direccion',
        '#logistica',
    ];

    /** @var list<int> */
    private const MENUS_ANTERIORES = [
        6, 10, 33, 34, 39, 40, 41, 42, 43, 47, 48, 49, 50, 51, 53, 57, 59, 104, 105, 110,
        119, 121, 123, 139, 142, 143, 144, 147, 148, 149, 150, 154, 157, 160, 168, 191, 198, 199, 200, 204,
        207, 208, 209, 210, 211, 212, 224, 225, 227, 230, 231, 233, 234, 235, 236, 244, 249, 268, 271, 274,
        276, 277, 278, 279, 280, 281, 282, 283, 284, 285, 286, 287, 296, 297, 298, 299, 320, 337, 338, 339,
        340, 341, 342, 343, 344, 345, 347, 351, 353, 354, 355, 357, 362, 367, 371, 379, 380, 381, 382, 383,
        384, 385, 386, 387, 388, 389, 390, 391, 393, 394, 395, 398, 399, 400, 401, 403, 408, 459, 460, 462,
        468, 469, 476, 477, 478, 479, 480, 483, 488, 490, 491, 493, 496, 497, 501, 509, 510, 511, 512, 513,
        514, 516, 522, 523, 524, 529, 531, 538, 543, 544, 546, 547, 548, 549, 550, 553, 554, 555, 556, 557,
        558, 559, 560, 561, 562, 563, 564, 565, 567, 570, 571, 573, 575, 576, 577, 578, 579, 580, 581, 582,
        587, 595, 596, 598, 599, 601, 602, 604, 606, 607, 608, 609, 612, 616, 617, 618, 619, 621, 625, 626,
        634, 635, 636, 637, 638, 640, 646, 647, 652, 654, 655, 659, 663, 665, 667, 668, 669, 673, 674, 675,
        676, 677, 678,
    ];

    /** @var list<int> */
    private const PERMISOS_ANTERIORES = [
        1, 2, 3, 4, 6, 7, 8, 9, 17, 18, 19, 86, 87, 88, 91, 92, 93, 94, 106, 107,
        108, 109, 110, 111, 112, 113, 114, 115, 131, 132, 133, 134, 135, 136, 137, 138, 139, 140, 141, 142,
        143, 144, 145, 146, 147, 148, 149, 150, 157, 159, 164, 165, 166, 171, 172, 173, 174, 175, 176, 177,
        178, 179, 180, 181, 182, 183, 184, 185, 186, 187, 188, 189, 191, 192, 193, 194, 195, 196, 197, 198,
        199, 200, 201, 202, 203, 204, 205, 206, 207, 209, 210, 268, 269, 271, 286, 287, 288, 289, 290, 291,
        292, 293, 294, 295, 296, 297, 298, 299, 300, 301, 302, 303, 304, 305, 306, 307, 308, 309, 310, 312,
        313, 331, 332, 333, 334, 335, 336, 337, 338, 339, 341, 342, 343, 344, 346, 347, 348, 349, 351, 352,
        353, 354, 356, 357, 358, 359, 371, 372, 373, 374, 401, 402, 403, 407, 408, 411, 412, 413, 414, 415,
        416, 417, 418, 419, 421, 422, 423, 424, 441, 450, 451, 452, 453, 455, 456, 457, 458, 460, 461, 462,
        463, 465, 466, 467, 468, 485, 486, 487, 488, 490, 491, 492, 493, 495, 496, 497, 500, 501, 502, 503,
        504, 505, 506, 507, 508, 509, 515, 516, 517, 518, 519, 520, 521, 522, 523, 525, 526, 530, 531, 532,
        533, 534, 545, 546, 547, 548, 549, 551, 552, 555, 556, 557, 558, 559, 631, 632, 633, 634, 640, 701,
        702, 703, 704, 705, 732, 733, 811, 813, 815, 917, 918, 930, 931, 932, 933, 934, 953, 954, 955, 971,
        972, 1006, 1007, 1008, 1009, 1010, 1011, 1012, 1013, 1014, 1020, 1021, 1022, 1023, 1026, 1040, 1041, 2031, 2032, 2033,
        2034, 2111, 2113, 4002, 4003, 4021, 4022, 4023, 4024, 4025, 5011, 5012, 10006, 10007, 10008, 10009, 10010, 10011, 10012, 10013,
        10014, 10015, 10016, 10017, 10018, 10019, 10020, 10021, 10022, 10023, 10034, 10035, 10036, 10037, 10038, 10047, 10048, 10049, 10064, 10065,
        10066, 10067, 10077, 10078, 10079, 10140, 10173, 10174, 10175, 10176, 10177, 10178, 10179, 10180, 10182, 10183, 10184, 10185, 10186, 10187,
        10188, 10189, 10190, 10191, 10192, 10193, 10194, 10195, 10196, 10197, 10198, 10199, 10200, 10201, 10202, 10203, 10204, 10205, 10206, 10207,
        10208, 10209, 10210, 10211, 10212, 10213, 10214, 10215, 10216, 10217, 10218, 10219, 10220, 10221, 10222, 10223, 10224, 10225, 10226, 10227,
        10238, 10259, 10260, 10261, 10263, 10264, 10265, 10266, 10267, 10269, 10272, 10273, 10274, 10275, 10276, 10277, 10278, 10279, 10280, 10281,
        10282, 10283, 10284, 10285, 10287, 10291, 10292, 10293, 10294, 10301, 10302, 10303, 10304, 10306, 10307, 10308, 10309, 10310, 10316, 10328,
        10329, 10345, 10348, 10349, 10362, 10366, 10376, 10377, 10378, 10379, 10380, 10381, 10382, 10383, 10384, 10385, 10386, 10387, 10388, 10389,
        10390, 10391, 10392, 10393, 10394, 10395, 10396, 10397, 10398, 10399, 10400, 10401, 10402, 10403, 10404, 10405, 10406, 10407, 10408, 10409,
        10410, 10411, 10412, 10413, 10414, 10415, 10416, 10422, 10423, 10424, 10425, 10427, 10429, 10430, 10431, 10432, 10433, 10440, 10441, 10442,
        10443, 10444, 10445, 10446, 10447, 10448, 10449, 10450, 10451, 10452, 10458, 10459, 10460, 10461, 10462, 10463, 10464, 10465, 10466, 10467,
        10468, 10474, 10475, 10500, 10502, 10505, 10506, 10637, 10641, 10642, 10643, 10644, 10645, 10646, 10647, 10648, 10653, 10654, 10658, 10659,
        10660, 10661, 10662, 10663, 10664, 10688, 10689, 10690, 10691, 10695, 10696, 10697, 10698, 10699, 10701, 10702, 10703, 10704, 10705, 10706,
        10707, 10708, 10709, 10710, 10733, 10735, 10737, 10745, 10747, 10748, 10749, 10750, 10751, 10752, 10753, 10754, 10756, 10757, 10758, 10771,
        10772, 10773, 10774, 10775, 10776, 10777, 10812, 10813, 10814, 10815, 10818, 10819, 10836, 10837, 10838, 10839, 10849, 10850, 10853, 10854,
        10855, 10856, 10857, 10859, 10860, 10862, 10863, 10865, 10866, 10867, 10868, 10869, 10870, 10871, 10872, 10873, 10874, 10875, 10888, 10889,
        10890, 10891, 10892, 10893, 10894, 10895, 10896, 10897, 10898, 10899, 10900, 10901, 10902, 10903, 10904, 10905, 10922, 10923, 10924, 10925,
        10926, 10927, 10928, 10929, 10930, 10933, 10934, 10935, 10937, 10938, 10939, 10940, 10941, 10942, 10949, 10962, 10963, 10964, 10965, 10966,
        10969, 10970, 10971, 10972, 10976, 10978, 10984, 10986, 10992, 10993, 10994, 10995, 10996, 10997, 10998, 11006, 11007, 11008, 11009, 11010,
        11014, 11015, 11016, 11017, 11033, 11034, 11035, 11036, 11037, 11038, 11039, 11040, 11041, 11042, 11043, 11044, 11045, 11046, 11047, 11048,
        11049, 11050, 11061,
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = $this->rolId();
        if ($rolId <= 0) {
            return;
        }

        [$menus, $permisos] = $this->asignacionObjetivo();
        $this->sincronizarMenus($rolId, $menus);
        $this->sincronizarPermisos($rolId, $permisos);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = $this->rolId();
        if ($rolId <= 0) {
            return;
        }

        $this->sincronizarMenus($rolId, self::MENUS_ANTERIORES);
        $this->sincronizarPermisos($rolId, self::PERMISOS_ANTERIORES);
        SuitecrmPermiso::flushCachePermisos();
    }

    private function rolId(): int
    {
        return (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
    }

    /**
     * @return array{0: list<int>, 1: list<int>}
     */
    private function asignacionObjetivo(): array
    {
        $menus = DB::table('menu')->get(['id', 'menu_id', 'nombre', 'url']);
        $porPadre = [];
        $porId = [];
        foreach ($menus as $menu) {
            $id = (int) $menu->id;
            $porId[$id] = $menu;
            $porPadre[(int) $menu->menu_id][] = $id;
        }

        $excluidos = [];
        foreach ($this->raicesExcluidas($porId) as $raizId) {
            $excluidos = array_merge($excluidos, $this->descendientes($porPadre, $raizId));
        }
        $excluidos = array_values(array_unique($excluidos));

        $urlsConservadas = [];
        $menusObjetivo = [];
        foreach ($menus as $menu) {
            $id = (int) $menu->id;
            if (in_array($id, $excluidos, true)) {
                continue;
            }
            $menusObjetivo[] = $id;
            $url = trim((string) $menu->url);
            if ($url !== '' && $url !== '#' && ! str_starts_with($url, '#')) {
                $urlsConservadas[$url] = true;
            }
        }

        $reincorporar = [];
        foreach ($excluidos as $id) {
            $url = trim((string) ($porId[$id]->url ?? ''));
            if (isset($urlsConservadas[$url])) {
                $reincorporar[] = $id;
            }
        }

        $permisosObjetivo = [];
        foreach (DB::table('permiso')->get(['id', 'slug', 'menu_id']) as $permiso) {
            $slug = (string) $permiso->slug;
            if (str_contains($slug, 'movimientos-caja') || str_contains($slug, 'orden-de-venta')) {
                continue;
            }
            $menuId = (int) ($permiso->menu_id ?? 0);
            if ($menuId > 0 && in_array($menuId, $excluidos, true) && ! in_array($menuId, $reincorporar, true)) {
                continue;
            }
            $permisosObjetivo[] = (int) $permiso->id;
        }

        return [$menusObjetivo, $permisosObjetivo];
    }

    /**
     * @param  array<int, object>  $porId
     * @return list<int>
     */
    private function raicesExcluidas(array $porId): array
    {
        $ids = [];
        foreach (self::RAICES_POR_NOMBRE as $nombre) {
            foreach ($porId as $id => $menu) {
                if ((int) $menu->menu_id === 0 && (string) $menu->nombre === $nombre) {
                    $ids[] = $id;
                }
            }
        }
        foreach (self::RAICES_POR_URL as $url) {
            foreach ($porId as $id => $menu) {
                if ((string) $menu->url === $url) {
                    $ids[] = $id;
                }
            }
        }
        foreach ($porId as $id => $menu) {
            if ((string) $menu->nombre !== 'Módulo de Compras' || (int) $menu->menu_id !== 0) {
                continue;
            }
            foreach ($porId as $hijoId => $hijo) {
                if ((int) $hijo->menu_id === $id && (string) $hijo->nombre === 'Suscripciones') {
                    $ids[] = $hijoId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, list<int>>  $porPadre
     * @return list<int>
     */
    private function descendientes(array $porPadre, int $id): array
    {
        $ids = [$id];
        foreach ($porPadre[$id] ?? [] as $hijoId) {
            $ids = array_merge($ids, $this->descendientes($porPadre, $hijoId));
        }

        return $ids;
    }

    /**
     * @param  list<int>  $objetivo
     */
    private function sincronizarMenus(int $rolId, array $objetivo): void
    {
        $actuales = DB::table('menu_rol')->where('rol_id', $rolId)->pluck('menu_id')->map(fn ($id) => (int) $id)->all();
        $quitar = array_values(array_diff($actuales, $objetivo));
        $poner = array_values(array_diff($objetivo, $actuales));

        foreach (array_chunk($quitar, 200) as $lote) {
            EloquentAuditDeleteSupport::each(
                Menu_Rol::query()->where('rol_id', $rolId)->whereIn('menu_id', $lote)
            );
        }

        foreach (array_chunk($poner, 200) as $lote) {
            DB::table('menu_rol')->insert(array_map(
                fn (int $menuId) => ['rol_id' => $rolId, 'menu_id' => $menuId],
                $lote
            ));
        }
    }

    /**
     * permiso_rol no tiene id: el borrado Eloquent no puede identificar la fila.
     *
     * @param  list<int>  $objetivo
     */
    private function sincronizarPermisos(int $rolId, array $objetivo): void
    {
        $actuales = DB::table('permiso_rol')->where('rol_id', $rolId)->pluck('permiso_id')->map(fn ($id) => (int) $id)->all();
        $quitar = array_values(array_diff($actuales, $objetivo));
        $poner = array_values(array_diff($objetivo, $actuales));

        foreach (array_chunk($quitar, 200) as $lote) {
            DB::table('permiso_rol')->where('rol_id', $rolId)->whereIn('permiso_id', $lote)->delete();
        }

        foreach (array_chunk($poner, 200) as $lote) {
            DB::table('permiso_rol')->insert(array_map(
                fn (int $permisoId) => ['rol_id' => $rolId, 'permiso_id' => $permisoId],
                $lote
            ));
        }
    }
};