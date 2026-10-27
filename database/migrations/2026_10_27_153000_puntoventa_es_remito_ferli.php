<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sucursales que salieron en el remito cientos o miles de veces.
     * La 00012 quedó 13 veces: es la factura electrónica, no una sucursal de remito.
     */
    private const MINIMO_USOS_COMO_REMITO = 50;

    public function up(): void
    {
        if (! Schema::hasColumn('puntoventa', 'es_remito')) {
            Schema::table('puntoventa', function (Blueprint $table) {
                $table->boolean('es_remito')->default(false);
            });
        }

        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $conteos = DB::table('venta')
            ->whereNotNull('puntoventaremito_id')
            ->where('puntoventaremito_id', '>', 0)
            ->select('puntoventaremito_id', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('puntoventaremito_id')
            ->get();

        $ids = [];
        foreach ($conteos as $fila) {
            if ((int) $fila->cantidad >= self::MINIMO_USOS_COMO_REMITO) {
                $ids[] = (int) $fila->puntoventaremito_id;
            }
        }

        if ($ids === []) {
            return;
        }

        DB::table('puntoventa')->whereIn('id', $ids)->update(['es_remito' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('puntoventa', 'es_remito')) {
            return;
        }

        Schema::table('puntoventa', function (Blueprint $table) {
            $table->dropColumn('es_remito');
        });
    }
};
