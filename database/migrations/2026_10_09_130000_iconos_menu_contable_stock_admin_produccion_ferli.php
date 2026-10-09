<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: icono en los cuatro menús de primer nivel.
 * Se buscan por nombre, no por id: el id del menú cambia en cada instalación.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const ICONOS = [
        'Módulo Contable' => 'fa-book',
        'Módulo de Stock' => 'fa-boxes',
        'Administrador' => 'fa-user-shield',
        'Módulo de Producción' => 'fa-industry',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        foreach (self::ICONOS as $nombre => $icono) {
            DB::table('menu')
                ->where('menu_id', 0)
                ->where('nombre', $nombre)
                ->where(function ($query) {
                    $query->whereNull('icono')
                        ->orWhere('icono', '')
                        ->orWhere('icono', 'NULL')
                        ->orWhere('icono', 'fa-factory');
                })
                ->update([
                    'icono' => $icono,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        foreach (self::ICONOS as $nombre => $icono) {
            DB::table('menu')
                ->where('menu_id', 0)
                ->where('nombre', $nombre)
                ->where('icono', $icono)
                ->update([
                    'icono' => null,
                    'updated_at' => now(),
                ]);
        }
    }
};
