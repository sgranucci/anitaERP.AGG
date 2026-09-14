<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de picking en línea de pedido (Calzados Ferli).
 * Permite despachar/facturar stock (OT fab o lote importado) sin OT de consumo intermedia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::table('pedido_combinacion', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido_combinacion', 'picking')) {
                $table->string('picking', 1)->default('N')->after('estado');
            }
            if (! Schema::hasColumn('pedido_combinacion', 'picking_lote_codigo')) {
                $table->string('picking_lote_codigo', 40)->nullable()->after('picking');
            }
            if (! Schema::hasColumn('pedido_combinacion', 'picking_deposito_id')) {
                $table->unsignedBigInteger('picking_deposito_id')->nullable()->after('picking_lote_codigo');
            }
            if (! Schema::hasColumn('pedido_combinacion', 'picking_at')) {
                $table->timestamp('picking_at')->nullable()->after('picking_deposito_id');
            }
            if (! Schema::hasColumn('pedido_combinacion', 'picking_usuario_id')) {
                $table->unsignedBigInteger('picking_usuario_id')->nullable()->after('picking_at');
            }
            if (! Schema::hasColumn('pedido_combinacion', 'picking_facturado')) {
                $table->string('picking_facturado', 1)->default('N')->after('picking_usuario_id');
            }
            if (! Schema::hasColumn('pedido_combinacion', 'picking_venta_id')) {
                $table->unsignedBigInteger('picking_venta_id')->nullable()->after('picking_facturado');
            }
        });

        Schema::table('pedido_combinacion', function (Blueprint $table) {
            if (Schema::hasColumn('pedido_combinacion', 'picking')) {
                $table->index(['picking', 'picking_facturado'], 'pedido_combinacion_picking_idx');
            }
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::table('pedido_combinacion', function (Blueprint $table) {
            try {
                $table->dropIndex('pedido_combinacion_picking_idx');
            } catch (\Throwable $e) {
                // índice ausente
            }
        });

        Schema::table('pedido_combinacion', function (Blueprint $table) {
            foreach ([
                'picking_venta_id',
                'picking_facturado',
                'picking_usuario_id',
                'picking_at',
                'picking_deposito_id',
                'picking_lote_codigo',
                'picking',
            ] as $col) {
                if (Schema::hasColumn('pedido_combinacion', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
