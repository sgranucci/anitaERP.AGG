<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Concepto de cash-flow del comprobante, equivalente a com_concepto de Anita.
 *
 * En Anita lo carga el operador en el alta (a-compprov.c, campo in_concepto validado contra
 * concoper) y pago.c lo arrastra a auxpag.axp_concepto al aplicar el pago. El EFE lo usa para
 * derivar el rubro cuando no encuentra cuenta de gasto, así que sin este dato el ERP degradaba
 * la información: venía mandando un 5 fijo (GASTRONOMIA) a Anita para todo comprobante.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('comprobante_proveedor', 'conceptogasto_id')) {
            return;
        }

        Schema::table('comprobante_proveedor', function (Blueprint $table) {
            $table->unsignedBigInteger('conceptogasto_id')->nullable()->after('condicionpago_id');

            $table->foreign('conceptogasto_id', 'fk_comprobprov_conceptogasto')
                ->references('id')->on('conceptogasto')
                ->onDelete('restrict')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('comprobante_proveedor', 'conceptogasto_id')) {
            return;
        }

        Schema::table('comprobante_proveedor', function (Blueprint $table) {
            $table->dropForeign('fk_comprobprov_conceptogasto');
            $table->dropColumn('conceptogasto_id');
        });
    }
};
