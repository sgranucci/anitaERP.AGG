<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('arca_caea_informe_observacion')) {
            return;
        }

        Schema::create('arca_caea_informe_observacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('arca_caea_id')->nullable();
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->string('fuente', 10);
            $table->unsignedInteger('pto_vta');
            $table->unsignedSmallInteger('tipo_afip');
            $table->string('tipo_anita', 8)->nullable();
            $table->char('letra', 1)->nullable();
            $table->unsignedInteger('numero');
            $table->date('fecha_comprobante')->nullable();
            $table->text('texto');
            $table->timestamp('informado_at');
            $table->timestamps();

            $table->unique(
                ['empresa_id', 'pto_vta', 'tipo_afip', 'numero'],
                'arca_caea_obs_comprobante_uk',
            );
            $table->index(['empresa_id', 'informado_at'], 'arca_caea_obs_empresa_fecha_idx');
            $table->foreign('empresa_id')->references('id')->on('empresa');
            $table->foreign('arca_caea_id')
                ->references('id')
                ->on('arca_caea')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arca_caea_informe_observacion');
    }
};
