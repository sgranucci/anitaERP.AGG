<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas de eliminación IC para consolidación de reportes definibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_contable_eli_regla', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_id')->nullable()->index()
                ->comment('null = regla global aplicable a todos');
            $table->string('nombre', 80);
            $table->unsignedBigInteger('codigo_desde');
            $table->unsignedBigInteger('codigo_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('reporte_contable_id', 'rd_eli_rep_fk')
                ->references('id')->on('reporte_contable')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_contable_eli_regla');
    }
};
