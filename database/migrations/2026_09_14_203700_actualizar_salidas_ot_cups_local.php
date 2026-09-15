<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Emisión OT: pasar salidas IFPU (imp_otr / imp_otrS) a PDF → cola CUPS local.
 * No modifica salidas JetDirect (imprimir-pdf-laser.sh) usadas por otros programas.
 * Solo Calzados Ferli.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $script = base_path('bin/imprimir-pedido.sh');

        foreach (DB::table('salida')->get(['id', 'comando']) as $salida) {
            $comando = trim((string) $salida->comando);
            if (preg_match('/imp_otrS?\s+%s\s+%s\s+(\S+)/i', $comando, $m) !== 1) {
                continue;
            }

            $cola = $this->normalizarCola($m[1]);
            DB::table('salida')->where('id', $salida->id)->update([
                'comando' => $script.' "%s" '.$cola,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $script = base_path('bin/imprimir-pedido.sh');

        $reversa = [
            'hp-diego' => './bin/imp_otr %s %s hp-diego',
            'HP4250GABY' => './bin/imp_otr %s %s HP4250GABY',
            'P1' => './bin/imp_otr %s %s P1',
            'hp1300' => './bin/imp_otr %s %s hp1300',
        ];

        foreach (DB::table('salida')->get(['id', 'comando']) as $salida) {
            $comando = trim((string) $salida->comando);
            if (! str_starts_with($comando, $script.' "%s" ')) {
                continue;
            }
            $cola = trim(substr($comando, strlen($script.' "%s" ')));
            if ($cola === '' || ! isset($reversa[$cola])) {
                continue;
            }
            // Solo revertir si el nombre sugiere OT (evita tocar salidas de pedido ya migradas).
            $nombre = (string) DB::table('salida')->where('id', $salida->id)->value('nombre');
            if (stripos($nombre, 'OT') === false && stripos($nombre, 'Stock') === false) {
                continue;
            }
            DB::table('salida')->where('id', $salida->id)->update([
                'comando' => $reversa[$cola],
            ]);
        }
    }

    private function normalizarCola(string $destino): string
    {
        return match (strtolower(trim($destino))) {
            '160.132.0.201', 'pserver', 'p1' => 'P1',
            '160.132.0.200', 'hp1300' => 'hp1300',
            'hp4250gaby' => 'HP4250GABY',
            'hp-diego' => 'hp-diego',
            default => trim($destino),
        };
    }
};
