<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Varias notas de crédito por factura de Facturación Local.
 * facturacion_local_emision.venta_nc_id sigue guardando la primera.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facturacion_local_nota_credito')) {
            Schema::create('facturacion_local_nota_credito', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('venta_factura_id');
                $table->unsignedBigInteger('venta_nc_id');
                $table->timestamps();

                $table->foreign('venta_factura_id', 'fk_flnc_factura')
                    ->references('id')->on('venta')->restrictOnDelete();
                $table->foreign('venta_nc_id', 'fk_flnc_nc')
                    ->references('id')->on('venta')->restrictOnDelete();
                $table->unique('venta_nc_id', 'uq_flnc_venta_nc');
                $table->index('venta_factura_id', 'idx_flnc_factura');
            });
        }

        if (! Schema::hasTable('facturacion_local_emision')) {
            return;
        }

        $filas = DB::table('facturacion_local_emision as e')
            ->join('venta as f', 'f.id', '=', 'e.venta_id')
            ->join('venta as n', 'n.id', '=', 'e.venta_nc_id')
            ->where('e.venta_nc_id', '>', 0)
            ->get(['e.venta_id', 'e.venta_nc_id', 'e.created_at', 'e.updated_at']);

        foreach ($filas as $fila) {
            $existe = DB::table('facturacion_local_nota_credito')
                ->where('venta_nc_id', (int) $fila->venta_nc_id)
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('facturacion_local_nota_credito')->insert([
                'venta_factura_id' => (int) $fila->venta_id,
                'venta_nc_id' => (int) $fila->venta_nc_id,
                'created_at' => $fila->created_at,
                'updated_at' => $fila->updated_at ?? $fila->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facturacion_local_nota_credito');
    }
};
