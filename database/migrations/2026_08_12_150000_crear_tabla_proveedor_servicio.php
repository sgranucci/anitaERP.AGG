<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servicios / medidores por proveedor (Anita tabla `servicios`).
 * Solo cliente (medidor) y detalle; mails Anita no se usan en ERP.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proveedor_servicio')) {
            return;
        }

        Schema::create('proveedor_servicio', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id')
                ->references('id')
                ->on('proveedor')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            // Anita serv_empresa (código empresa); null = misma lógica multiempresa del proveedor.
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->foreign('empresa_id')
                ->references('id')
                ->on('empresa')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('cliente', 255);
            $table->string('detalle', 255)->nullable();
            $table->timestamps();
            $table->unique(['proveedor_id', 'empresa_id', 'cliente'], 'proveedor_servicio_prov_emp_cliente_uq');
            $table->index(['proveedor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_servicio');
    }
};
