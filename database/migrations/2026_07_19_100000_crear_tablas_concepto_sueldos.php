<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Conceptos de liquidacion (Anita: haberes + habformula).
 *
 * Tabla basica pero compatible hacia adelante con el motor de liquidacion:
 * guarda tipo, momento, forma de calculo y las formulas (importe / cantidad /
 * valor) que en Anita viven en hab_formula, hab_formula_cant, hab_formula_valor
 * y en las lineas de habformula. La usa tipo_ausencia_sueldos para saber con
 * que concepto se liquida cada licencia/vacacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepto_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();          // Anita hab_codigo
            $table->string('descripcion', 60);                     // Anita hab_desc

            // Clasificacion (Anita hab_tipo / hab_total)
            $table->string('tipo', 30)->default('remunerativo');
            // remunerativo | no_remunerativo | descuento | aporte | contribucion | retencion | asignacion | neto | informativo
            $table->string('suma_a', 30)->nullable();
            // acumulador/base al que impacta (simplificacion de hab_total; los acumuladores completos vienen con la liquidacion)

            // Momento de liquidacion (Anita hab_momento). La forma es siempre por formula.
            $table->string('momento', 30)->default('mensual');
            // mensual | quincena_1 | quincena_2 | vacaciones | sac | final | no_liquida | especial

            $table->decimal('factor', 18, 4)->nullable();          // Anita hab_factor

            // Formulas del parser (Anita hab_formula / hab_formula_cant / hab_formula_valor)
            $table->text('formula')->nullable();
            $table->text('formula_cantidad')->nullable();
            $table->text('formula_valor')->nullable();

            $table->boolean('va_recibo')->default(true);           // Anita hab_va_recibo
            $table->smallInteger('mes_retroactivo')->default(0);   // Anita hab_retroactivo (-12..12, -99=variable, 0=no)
            $table->text('leyenda_recibo')->nullable();            // Anita hab_leyenda1..4
            $table->string('concepto_afip', 6)->nullable();        // Anita concafip: mapeo Libro de Sueldos Digital / SICOSS (etapa LSD)
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);

            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['descripcion', 'codigo']);
            $table->index(['tipo', 'activo']);
        });

        // Lineas de formula multi-renglon (Anita habformula: habf_concepto, habf_linea, habf_formula)
        Schema::create('concepto_formula_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_id');
            $table->unsignedInteger('nro_linea');
            $table->text('formula')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['concepto_id', 'nro_linea'], 'conceptoformula_concepto_linea_uq');

            $table->foreign('concepto_id')
                ->references('id')
                ->on('concepto_sueldos')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concepto_formula_sueldos');
        Schema::dropIfExists('concepto_sueldos');
    }
};
