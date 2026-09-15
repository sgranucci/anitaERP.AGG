<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Emisión OT: salidas a JetDirect (IP:9100) sin colas CUPS en el L12.
 * Solo Calzados Ferli. IPs del host Ferli 160.132.0.254 (lpstat -v).
 * Los IDs 4/5/6/8 son OT Laser en Ferli; en AGG son gastronomía — no correr sin gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

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
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

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
