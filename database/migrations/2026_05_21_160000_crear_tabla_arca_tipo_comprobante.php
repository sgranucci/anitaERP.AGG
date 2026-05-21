<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('arca_tipo_comprobante')) {
            return;
        }

        Schema::create('arca_tipo_comprobante', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->string('webservice', 20);
            $table->unsignedSmallInteger('codigo_numerico');
            $table->string('codigo_afip', 10);
            $table->string('descripcion', 255);
            $table->dateTime('sincronizado_at');
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_arca_tipo_comprobante_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->unique(
                ['empresa_id', 'webservice', 'codigo_afip'],
                'uq_arca_tipo_comprobante_empresa_ws_codigo'
            );
            $table->index(['empresa_id', 'webservice'], 'idx_arca_tipo_comprobante_empresa_ws');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arca_tipo_comprobante');
    }
};
