<?php

use App\Support\Configuracion\CondicionivaLetraComprasSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('condicioniva', 'letra_compras')) {
            Schema::table('condicioniva', function (Blueprint $table) {
                $table->string('letra_compras', 1)->nullable()->after('letra');
            });
        }

        $filas = DB::table('condicioniva')->orderBy('id')->get(['id', 'nombre', 'letra', 'letra_compras']);
        foreach ($filas as $fila) {
            if (trim((string) ($fila->letra_compras ?? '')) !== '') {
                continue;
            }

            DB::table('condicioniva')->where('id', $fila->id)->update([
                'letra_compras' => CondicionivaLetraComprasSupport::letraComprasPorNombre(
                    (string) ($fila->nombre ?? ''),
                    (string) ($fila->letra ?? '')
                ),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('condicioniva', 'letra_compras')) {
            return;
        }

        Schema::table('condicioniva', function (Blueprint $table) {
            $table->dropColumn('letra_compras');
        });
    }
};
