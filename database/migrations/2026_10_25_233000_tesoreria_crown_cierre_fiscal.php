<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierres de ventas (ventas.cierrefiscal, p-cierrefact) tomados por una rendición.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (Schema::hasTable('tesoreria_crown_cierre_fiscal')) {
            return;
        }
        Schema::create('tesoreria_crown_cierre_fiscal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('tesoreria_crown_comprobante');
            $table->unsignedInteger('sucursal');
            $table->date('fecha_jor');
            $table->char('hora', 8);
            $table->unsignedInteger('nro_x')->default(0);
            $table->unsignedInteger('nro_z')->default(0);
            $table->char('tipo', 1);
            $table->string('nombre_sucursal', 80)->nullable();
            $table->decimal('tot_venta', 14, 2)->default(0);
            $table->decimal('tot_cred', 14, 2)->default(0);
            $table->decimal('tot_iva', 14, 2)->default(0);
            $table->decimal('tot_iva_cred', 14, 2)->default(0);
            $table->unsignedBigInteger('ult_fact_b')->default(0);
            $table->timestamps();
            $table->unique(
                ['sucursal', 'fecha_jor', 'hora', 'nro_x', 'nro_z'],
                'tc_cierre_fiscal_origen_uq'
            );
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        Schema::dropIfExists('tesoreria_crown_cierre_fiscal');
    }
};
