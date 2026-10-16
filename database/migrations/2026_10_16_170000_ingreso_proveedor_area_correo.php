<?php

use App\Support\Seguridad\IngresoProveedorAreaCorreoSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correos de aviso del ingreso de proveedor, por área de destino y empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ingreso_proveedor_area_correo')) {
            Schema::create('ingreso_proveedor_area_correo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('area_id');
                $table->unsignedBigInteger('empresa_id');
                $table->string('email', 180);
                $table->timestamps();

                $table->unique(['area_id', 'empresa_id', 'email'], 'uq_ingprov_area_correo');
                $table->index(['area_id', 'empresa_id'], 'idx_ingprov_area_correo_emp');

                $table->foreign('area_id', 'fk_ingprov_area_correo_area')
                    ->references('id')->on('ingreso_proveedor_area')->onDelete('cascade');
                $table->foreign('empresa_id', 'fk_ingprov_area_correo_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
            });
        }

        IngresoProveedorAreaCorreoSupport::sembrarCanonicoAgg();
    }

    public function down(): void
    {
        IngresoProveedorAreaCorreoSupport::quitarCanonicoAgg();
        Schema::dropIfExists('ingreso_proveedor_area_correo');
    }
};
