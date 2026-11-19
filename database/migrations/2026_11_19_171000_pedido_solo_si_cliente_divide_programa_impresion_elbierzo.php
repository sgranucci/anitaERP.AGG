<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasTable('comprobante_impresion_programa')) {
            return;
        }
        if (! Schema::hasColumn('comprobante_impresion_programa', 'pedido_solo_si_cliente_divide')) {
            Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
                $table->boolean('pedido_solo_si_cliente_divide')->default(true);
            });
        }

        // Programas ya cargados: el tilde queda en true (pedido solo si el cliente divide).
        DB::table('comprobante_impresion_programa')->update([
            'pedido_solo_si_cliente_divide' => true,
        ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }
        if (! Schema::hasTable('comprobante_impresion_programa')) {
            return;
        }
        if (! Schema::hasColumn('comprobante_impresion_programa', 'pedido_solo_si_cliente_divide')) {
            return;
        }

        Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
            $table->dropColumn('pedido_solo_si_cliente_divide');
        });
    }
};
