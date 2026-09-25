<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli L12: térmicas calidad/armado por IPP al CUPS del L8 (sin lp local).
 */
return new class extends Migration
{
    private const SCRIPT = 'bin/imprimir-cups-remoto.sh';

    /** @var array<string, array{cola: string, nombre: string, comando_legacy: string}> */
    private const TERMICAS = [
        'calidad' => [
            'cola' => 'calidad',
            'nombre' => 'Termica calidad',
            'comando_legacy' => 'lp -dcalidad %s',
        ],
        'armado' => [
            'cola' => 'armado',
            'nombre' => 'Termica Armado',
            'comando_legacy' => 'lp -darmado %s',
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('salida')) {
            return;
        }

        $script = base_path(self::SCRIPT);

        foreach (self::TERMICAS as $cfg) {
            $comandoNuevo = $script.' "%s" '.$cfg['cola'];

            $existente = DB::table('salida')
                ->where(function ($q) use ($cfg) {
                    $q->where('nombre', $cfg['nombre'])
                        ->orWhere('comando', $cfg['comando_legacy'])
                        ->orWhere('comando', 'like', '%-d'.$cfg['cola'].' %');
                })
                ->orderBy('id')
                ->first();

            if ($existente) {
                DB::table('salida')->where('id', $existente->id)->update([
                    'nombre' => $cfg['nombre'],
                    'comando' => $comandoNuevo,
                    'updated_at' => now(),
                ]);
                continue;
            }

            DB::table('salida')->insert([
                'nombre' => $cfg['nombre'],
                'ubicacion_impresora_id' => null,
                'comando' => $comandoNuevo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('salida')) {
            return;
        }

        foreach (self::TERMICAS as $cfg) {
            DB::table('salida')
                ->where('nombre', $cfg['nombre'])
                ->update([
                    'comando' => $cfg['comando_legacy'],
                    'updated_at' => now(),
                ]);
        }
    }
};
