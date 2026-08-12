<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado publicado inmutable: congela los números tal como se presentaron, con
 * hash y filtros, para reimprimir idéntico aunque después cambie la definición o
 * se reprocesen asientos. El hash sirve además para avisar impacto: si la corrida
 * de hoy no coincide con lo publicado, el informe presentado ya no se reproduce.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reporte_contable_publicacion')) {
            return;
        }

        Schema::create('reporte_contable_publicacion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_id');
            $table->string('nombre', 160);
            $table->string('hash', 64);
            $table->json('filtros');
            $table->longText('resultado');
            $table->string('periodo_texto', 120)->nullable();
            $table->date('fecha_desde')->nullable();
            $table->date('fecha_hasta')->nullable();
            $table->unsignedInteger('filas')->default(0);
            $table->unsignedInteger('definicion_version')->default(0);
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->index(['reporte_contable_id', 'created_at'], 'rd_pub_rep_fecha_idx');
            $table->index('hash', 'rd_pub_hash_idx');
            $table->foreign('reporte_contable_id', 'rd_pub_rep_fk')
                ->references('id')->on('reporte_contable')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_contable_publicacion');
    }
};
