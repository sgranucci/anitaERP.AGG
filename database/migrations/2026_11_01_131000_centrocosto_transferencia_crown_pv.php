<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownImputacionSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: centro de costo por punto de venta para las transferencias.
 * Precarga desde suc_leyenda2 de Anita (como p-tr-ven / p-tr-pxsol),
 * sin pisar un centro ya cargado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('transferencia_crown_pv_centrocosto')) {
            Schema::create('transferencia_crown_pv_centrocosto', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedInteger('sucursal');
                $table->unsignedBigInteger('centrocosto_id')->nullable();
                $table->timestamps();
                $table->unique(['empresa_id', 'sucursal'], 'uk_transf_crown_pv_cc');
                $table->foreign('empresa_id', 'fk_transf_crown_pv_cc_emp')
                    ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('centrocosto_id', 'fk_transf_crown_pv_cc_cc')
                    ->references('id')->on('centrocosto')->onDelete('set null')->onUpdate('restrict');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        TransferenciaCrownImputacionSupport::sembrarCentrosDesdeAnita();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('transferencia_crown_pv_centrocosto');
    }
};
