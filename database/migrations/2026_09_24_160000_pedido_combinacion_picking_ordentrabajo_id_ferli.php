<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bucket de stock elegido en el modal de picking (OT lote=0 vs lote importado).
 * Si picking_ordentrabajo_id > 0 → consumo con lote=0 + esa OT; si null → lote=picking_lote_codigo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::table('pedido_combinacion', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido_combinacion', 'picking_ordentrabajo_id')) {
                $table->unsignedBigInteger('picking_ordentrabajo_id')->nullable()->after('picking_deposito_id');
            }
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::table('pedido_combinacion', function (Blueprint $table) {
            if (Schema::hasColumn('pedido_combinacion', 'picking_ordentrabajo_id')) {
                $table->dropColumn('picking_ordentrabajo_id');
            }
        });
    }
};
