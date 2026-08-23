<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobante_impresion_programa', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            $table->boolean('permite_disparo_al_grabar')->default(false);
            $table->timestamps();
        });

        Schema::create('comprobante_impresion_formulario', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('programa_id');
            $table->unsignedInteger('orden')->default(10);
            $table->string('formulario', 20);
            $table->timestamps();
            $table->unique(['programa_id', 'formulario'], 'uk_compimp_form_prog_form');
            $table->foreign('programa_id', 'fk_compimp_form_prog')
                ->references('id')->on('comprobante_impresion_programa')->onDelete('cascade');
        });

        Schema::create('comprobante_impresion_copia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('formulario_id');
            $table->unsignedInteger('orden')->default(10);
            $table->string('codigo', 20);
            $table->string('leyenda', 60);
            $table->string('destinatario', 80)->nullable();
            $table->unsignedBigInteger('salida_id')->nullable();
            $table->boolean('incluir_en_pdf_sesion')->default(true);
            $table->timestamps();
            $table->foreign('formulario_id', 'fk_compimp_copia_form')
                ->references('id')->on('comprobante_impresion_formulario')->onDelete('cascade');
            $table->foreign('salida_id', 'fk_compimp_copia_salida')
                ->references('id')->on('salida')->onDelete('restrict');
        });

        Schema::create('comprobante_impresion_regla', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('programa_id');
            $table->string('clave', 30);
            $table->unsignedBigInteger('valor_id')->nullable();
            $table->integer('prioridad')->default(0);
            $table->timestamps();
            $table->index(['clave', 'valor_id'], 'ix_compimp_regla_clave_valor');
            $table->foreign('programa_id', 'fk_compimp_regla_prog')
                ->references('id')->on('comprobante_impresion_programa')->onDelete('cascade');
        });

        Schema::create('comprobante_impresion_log', function (Blueprint $table) {
            $table->id();
            $table->string('documento_tipo', 20);
            $table->unsignedBigInteger('documento_id');
            $table->string('formulario', 20);
            $table->string('copia_codigo', 20);
            $table->string('copia_leyenda', 60);
            $table->unsignedBigInteger('salida_id')->nullable();
            $table->string('destino_path', 500)->nullable();
            $table->string('estado', 20);
            $table->text('mensaje')->nullable();
            $table->unsignedInteger('intentos')->default(0);
            $table->string('medio', 20)->default('IMPRESORA');
            $table->string('modo', 20)->default('OPERATIVO');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
            $table->index(['estado', 'medio'], 'ix_compimp_log_estado_medio');
            $table->index(['documento_tipo', 'documento_id'], 'ix_compimp_log_doc');
            $table->foreign('salida_id', 'fk_compimp_log_salida')
                ->references('id')->on('salida')->onDelete('set null');
            $table->foreign('usuario_id', 'fk_compimp_log_usuario')
                ->references('id')->on('usuario')->onDelete('set null');
        });

        Schema::table('seteosalida', function (Blueprint $table) {
            $table->boolean('disparar_al_grabar')->default(false)->after('programa');
        });
    }

    public function down(): void
    {
        Schema::table('seteosalida', function (Blueprint $table) {
            $table->dropColumn('disparar_al_grabar');
        });
        Schema::dropIfExists('comprobante_impresion_log');
        Schema::dropIfExists('comprobante_impresion_regla');
        Schema::dropIfExists('comprobante_impresion_copia');
        Schema::dropIfExists('comprobante_impresion_formulario');
        Schema::dropIfExists('comprobante_impresion_programa');
    }
};
