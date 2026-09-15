<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Emisión OT: salidas a JetDirect (IP:9100) sin colas CUPS en el L12.
 * IPs tomadas del host Ferli 160.132.0.254 (lpstat -v).
 */
return new class extends Migration
{
    public function up(): void
    {
        $script = base_path('bin/imprimir-pdf-laser.sh');

        $map = [
            4 => '160.132.0.203', // OT Laser Diego (hp-diego)
            5 => '160.132.0.183', // OT Laser Gabriela (HP4250GABY)
            6 => '160.132.0.183', // Prueba OT Stock
            8 => '160.132.0.201', // OT Laser Monica (P1)
        ];

        foreach ($map as $id => $ip) {
            if (! DB::table('salida')->where('id', $id)->exists()) {
                continue;
            }
            DB::table('salida')->where('id', $id)->update([
                'comando' => $script.' "%s" '.$ip,
            ]);
        }
    }

    public function down(): void
    {
        $scriptCups = base_path('bin/imprimir-pedido.sh');

        $map = [
            4 => 'hp-diego',
            5 => 'HP4250GABY',
            6 => 'HP4250GABY',
            8 => 'P1',
        ];

        foreach ($map as $id => $cola) {
            if (! DB::table('salida')->where('id', $id)->exists()) {
                continue;
            }
            DB::table('salida')->where('id', $id)->update([
                'comando' => $scriptCups.' "%s" '.$cola,
            ]);
        }
    }
};
