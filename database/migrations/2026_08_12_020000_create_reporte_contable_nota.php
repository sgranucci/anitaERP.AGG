<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reporte_contable_nota')) {
            return;
        }

        Schema::create('reporte_contable_nota', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_id');

            // Línea del informe a la que cuelga la nota. Se guarda el código además del id
            // para que la nota sobreviva si el rubro se recrea con el mismo código de línea.
            $table->unsignedBigInteger('reporte_contable_rubro_id')->nullable();
            $table->string('codigo_linea', 20)->nullable();

            $table->text('texto');

            // Vigencia por período (YYYYMM). Null = sin límite por ese lado.
            $table->unsignedInteger('periodo_desde')->nullable();
            $table->unsignedInteger('periodo_hasta')->nullable();

            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);

            // Historial: cada edición crea una fila nueva y deja la anterior inactiva.
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('nota_original_id')->nullable();

            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->index(['reporte_contable_id', 'activo'], 'rcn_reporte_activo_idx');
            $table->index(['nota_original_id', 'version'], 'rcn_original_version_idx');
            $table->index(['reporte_contable_rubro_id', 'activo'], 'rcn_rubro_activo_idx');
        });

        Schema::table('reporte_contable_nota', function (Blueprint $table) {
            if (Schema::hasTable('reporte_contable')) {
                $table->foreign('reporte_contable_id', 'rcn_reporte_fk')
                    ->references('id')->on('reporte_contable')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_contable_nota');
    }
};
