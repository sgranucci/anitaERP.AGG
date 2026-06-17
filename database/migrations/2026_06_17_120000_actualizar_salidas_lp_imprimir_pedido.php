<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $script = base_path('bin/imprimir-pedido.sh');

        foreach (DB::table('salida')->get(['id', 'comando']) as $salida) {
            $comando = trim((string) $salida->comando);
            $cola = null;

            if (preg_match('/^lp\s+-d\s+(\S+)\s+%s$/i', $comando, $m)) {
                $cola = $m[1];
            } elseif (preg_match('/^lp\s+-d(\S+)\s+%s$/i', $comando, $m)) {
                $cola = $m[1];
            }

            if ($cola === null) {
                continue;
            }

            DB::table('salida')->where('id', $salida->id)->update([
                'comando' => $script.' "%s" '.$cola,
            ]);
        }
    }

    public function down(): void
    {
        $script = base_path('bin/imprimir-pedido.sh');

        foreach (DB::table('salida')->get(['id', 'comando']) as $salida) {
            $comando = trim((string) $salida->comando);

            if (! str_starts_with($comando, $script.' "%s" ')) {
                continue;
            }

            $cola = trim(substr($comando, strlen($script.' "%s" ')));
            if ($cola === '') {
                continue;
            }

            DB::table('salida')->where('id', $salida->id)->update([
                'comando' => 'lp -d '.$cola.' %s',
            ]);
        }
    }
};
