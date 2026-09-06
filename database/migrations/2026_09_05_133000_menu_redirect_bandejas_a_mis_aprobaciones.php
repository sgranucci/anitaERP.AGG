<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Apunta menús de bandejas legacy a Mis aprobaciones (entrada única).
 */
return new class extends Migration
{
    /** @var array<string, array{url: string, nombre: string}> */
    private const MAPA = [
        'sueldos/indumentaria/bandeja' => [
            'url' => 'mis-aprobaciones?fuente=indumentaria',
            'nombre' => 'Mis aprobaciones · Indumentaria',
        ],
        'contable/aprobacion-asientos' => [
            'url' => 'mis-aprobaciones?fuente=asiento',
            'nombre' => 'Mis aprobaciones · Asientos',
        ],
        'seguridad/ingreso-proveedor-pendientes' => [
            'url' => 'mis-aprobaciones?fuente=ingreso_proveedor',
            'nombre' => 'Mis aprobaciones · Ingresos',
        ],
    ];

    public function up(): void
    {
        foreach (self::MAPA as $urlVieja => $nuevo) {
            DB::table('menu')->where('url', $urlVieja)->update([
                'url' => $nuevo['url'],
                'nombre' => $nuevo['nombre'],
                'icono' => 'fa-inbox',
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $revert = [
            'mis-aprobaciones?fuente=indumentaria' => [
                'url' => 'sueldos/indumentaria/bandeja',
                'nombre' => 'Bandeja de aprobación',
            ],
            'mis-aprobaciones?fuente=asiento' => [
                'url' => 'contable/aprobacion-asientos',
                'nombre' => 'Asientos pendientes',
            ],
            'mis-aprobaciones?fuente=ingreso_proveedor' => [
                'url' => 'seguridad/ingreso-proveedor-pendientes',
                'nombre' => 'Pendientes de autorizar',
            ],
        ];

        foreach ($revert as $urlNueva => $viejo) {
            DB::table('menu')->where('url', $urlNueva)->update([
                'url' => $viejo['url'],
                'nombre' => $viejo['nombre'],
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
