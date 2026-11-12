<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: jurisdicciones CM05 del proveedor (inscripción por provincia).
 * Alimenta la matriz de retención IIBB: Convenio/Local con RN → 1 %.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('proveedor_cm05')) {
            return;
        }

        Schema::create('proveedor_cm05', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_proveedor_cm05_proveedor')
                ->references('id')->on('proveedor')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('provincia_id');
            $table->foreign('provincia_id', 'fk_proveedor_cm05_provincia')
                ->references('id')->on('provincia')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->date('fechavigencia')->nullable();
            $table->timestamps();
            $table->unique(['proveedor_id', 'provincia_id'], 'uk_proveedor_cm05_prov');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('proveedor_cm05');
    }
};
