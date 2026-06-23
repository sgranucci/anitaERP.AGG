<?php

use App\Support\Stock\ArticuloEtiquetaZplSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $scriptZebra = base_path('bin/imprimir-etiqueta-zebra.sh');
        $scriptPedido = base_path('bin/imprimir-pedido.sh');

        foreach (DB::table('salida')->get(['id', 'comando']) as $salida) {
            $comando = trim((string) $salida->comando);

            if (! str_starts_with($comando, $scriptPedido.' "%s" imp-labo')) {
                continue;
            }

            $cola = trim(substr($comando, strlen($scriptPedido.' "%s" ')));
            if ($cola === '') {
                continue;
            }

            DB::table('salida')->where('id', $salida->id)->update([
                'comando' => $scriptZebra.' "%s" '.$cola,
            ]);
        }

        $modelo = DB::table('modeloetiqueta')->where('id', 1)->first();
        if ($modelo === null) {
            return;
        }

        $codigo = ArticuloEtiquetaZplSupport::normalizarPlantilla((string) $modelo->codigoetiqueta);

        DB::table('modeloetiqueta')->where('id', 1)->update([
            'codigoetiqueta' => $codigo,
        ]);
    }

    public function down(): void
    {
        $scriptZebra = base_path('bin/imprimir-etiqueta-zebra.sh');
        $scriptPedido = base_path('bin/imprimir-pedido.sh');

        foreach (DB::table('salida')->get(['id', 'comando']) as $salida) {
            $comando = trim((string) $salida->comando);

            if (! str_starts_with($comando, $scriptZebra.' "%s" imp-labo')) {
                continue;
            }

            $cola = trim(substr($comando, strlen($scriptZebra.' "%s" ')));
            if ($cola === '') {
                continue;
            }

            DB::table('salida')->where('id', $salida->id)->update([
                'comando' => $scriptPedido.' "%s" '.$cola,
            ]);
        }
    }
};
