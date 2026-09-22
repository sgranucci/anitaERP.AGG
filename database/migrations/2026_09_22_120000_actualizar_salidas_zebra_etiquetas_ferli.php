<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli L12: Zebras de etiquetas OT por JetDirect (puerto 9100), sin CUPS local.
 * IPs tomadas de /etc/hosts de L8 (userver): zebra1/2/arriba.
 */
return new class extends Migration
{
    private const SCRIPT = 'bin/imprimir-etiqueta-zebra.sh';

    /** @var array<string, array{ip: string, nombre: string, comando_legacy: string}> */
    private const ZEBRAS = [
        'zebra1' => [
            'ip' => '160.132.0.230',
            'nombre' => 'Zebra 1 Etiquetas',
            'comando_legacy' => 'lp -dzebra1 %s',
        ],
        'zebra2' => [
            'ip' => '160.132.0.235',
            'nombre' => 'Zebra 2 Etiquetas',
            'comando_legacy' => 'lp -dzebra2 %s',
        ],
        'zebraarriba' => [
            'ip' => '160.132.0.240',
            'nombre' => 'Zebra Arriba Etiquetas',
            'comando_legacy' => 'lp -dzebraarriba %s',
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

        foreach (self::ZEBRAS as $clave => $cfg) {
            $comandoNuevo = $script.' "%s" '.$cfg['ip'];

            $existente = DB::table('salida')
                ->where(function ($q) use ($cfg, $clave) {
                    $q->where('nombre', $cfg['nombre'])
                        ->orWhere('comando', $cfg['comando_legacy'])
                        ->orWhere('comando', 'like', '%-d'.$clave.' %')
                        ->orWhere('comando', 'like', '% '.$clave);
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

        $script = base_path(self::SCRIPT);

        foreach (self::ZEBRAS as $cfg) {
            $comandoNuevo = $script.' "%s" '.$cfg['ip'];
            DB::table('salida')
                ->where('comando', $comandoNuevo)
                ->update([
                    'comando' => $cfg['comando_legacy'],
                    'updated_at' => now(),
                ]);
        }
    }
};
