<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistencia del F572 Web (SiRADIG - ARCA) para el módulo Sueldos.
 *
 * No hay web service: es la lectura del/los XML que el empleador descarga desde
 * "SiRADIG Empleador" (resultadosXML.zip => 1 XML por empleado, sección A; en
 * pluriempleo además sección B). El match con el legajo es por CUIL.
 *
 * Estructura espejo del XSD presentacion.xsd / presentacion_seccion_b.xsd (v1.24).
 * Se guardan todas las presentaciones (histórico + rectificativas) y se marca la
 * vigente por (empleado/CUIL, período): la última presentación reemplaza a la
 * anterior del mismo año fiscal (criterio unánime del mercado).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cabecera de la presentación F572 (sección A o B)
        Schema::create('siradig_presentacion_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('empleado_id')->nullable(); // match por CUIL; puede no existir aún

            $table->char('seccion', 1)->default('A'); // A=agente de retención, B=otros empleadores (pluriempleo)
            $table->string('version', 10)->nullable(); // atributo version del XML (>= 1.19)
            $table->unsignedSmallInteger('periodo'); // año fiscal
            $table->unsignedSmallInteger('nro_presentacion'); // 1=original, >1=rectificativa
            $table->date('fecha_presentacion')->nullable();

            // Empleado (snapshot del XML)
            $table->string('empleado_cuit', 11);
            $table->unsignedSmallInteger('empleado_tipo_doc')->nullable(); // 80 CUIT / 86 CUIL (Tabla 2)
            $table->string('empleado_apellido', 60)->nullable();
            $table->string('empleado_nombre', 60)->nullable();

            // Domicilio del empleado (Tabla 1 provincias, códigos ARCA)
            $table->unsignedSmallInteger('dom_provincia')->nullable();
            $table->string('dom_cp', 8)->nullable();
            $table->string('dom_localidad', 60)->nullable();
            $table->string('dom_calle', 40)->nullable();
            $table->string('dom_nro', 6)->nullable();
            $table->string('dom_piso', 5)->nullable();
            $table->string('dom_dpto', 5)->nullable();

            // Agente de retención designado (sección B) / empresa agente (sección A)
            $table->string('agente_retencion_cuit', 11)->nullable();
            $table->string('agente_retencion_denominacion', 200)->nullable();
            $table->boolean('es_agente_retencion')->default(true); // true en sección A

            // Control de importación
            $table->boolean('vigente')->default(true); // presentación activa para (cuil, período)
            $table->string('archivo_nombre', 255)->nullable();
            $table->char('archivo_hash', 64)->nullable(); // sha256 del contenido para evitar duplicados
            $table->longText('xml_crudo')->nullable();

            $table->unsignedBigInteger('importado_por_id')->nullable();
            $table->timestamp('importado_at')->nullable();
            $table->timestamps();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(
                ['empresa_id', 'empleado_cuit', 'periodo', 'nro_presentacion', 'seccion'],
                'siradig_pres_uq'
            );
            $table->index(['empresa_id', 'empleado_cuit', 'periodo'], 'siradig_pres_cuil_periodo_idx');
            $table->index(['empresa_id', 'empleado_id', 'periodo'], 'siradig_pres_empleado_idx');
            $table->index(['empresa_id', 'periodo', 'vigente'], 'siradig_pres_vigente_idx');
            $table->index(['empresa_id', 'archivo_hash'], 'siradig_pres_hash_idx');

            $table->foreign('empresa_id')->references('id')->on('empresa');
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->nullOnDelete();
        });

        // Cargas de familia
        Schema::create('siradig_carga_familia_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('presentacion_id');
            $table->unsignedSmallInteger('tipo_doc')->nullable(); // Tabla 2
            $table->string('nro_doc', 11)->nullable();
            $table->string('apellido', 50)->nullable();
            $table->string('nombre', 50)->nullable();
            $table->date('fecha_nac')->nullable();
            $table->unsignedTinyInteger('mes_desde')->nullable();
            $table->unsignedTinyInteger('mes_hasta')->nullable();
            $table->unsignedSmallInteger('parentesco')->nullable(); // Tabla 3
            $table->char('vigente_proximos_periodos', 1)->nullable(); // S/N
            $table->date('fecha_limite')->nullable();
            $table->unsignedSmallInteger('porcentaje_deduccion')->nullable(); // 50 o 100
            $table->timestamps();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index('presentacion_id', 'siradig_cf_pres_idx');
            $table->foreign('presentacion_id', 'siradig_cf_pres_fk')->references('id')->on('siradig_presentacion_sueldos')->onDelete('cascade');
        });

        // Ganancias liquidadas por otros empleadores / entidades (pluriempleo)
        Schema::create('siradig_otro_empleador_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('presentacion_id');
            $table->string('cuit', 11)->nullable();
            $table->string('denominacion', 200)->nullable();
            $table->string('convenio_colectivo', 10)->nullable();
            $table->char('transporte_larga_dist', 1)->nullable(); // S/N
            $table->char('transporte_terr_larga_dist', 1)->nullable(); // S/N
            $table->timestamps();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index('presentacion_id', 'siradig_oe_pres_idx');
            $table->foreign('presentacion_id', 'siradig_oe_pres_fk')->references('id')->on('siradig_presentacion_sueldos')->onDelete('cascade');
        });

        // Ingresos y aportes por mes de cada otro empleador (IngresoAporteType)
        Schema::create('siradig_otro_empleador_mes_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('otro_empleador_id');
            $table->unsignedTinyInteger('mes'); // 1..12
            $table->char('regimen', 1)->nullable(); // G=General, C=Cedular (>= 2024)

            $table->decimal('obra_soc', 15, 2)->nullable();
            $table->decimal('seg_soc', 15, 2)->nullable();            // hasta 2024
            $table->decimal('seg_soc_anses', 15, 2)->nullable();      // >= 2025
            $table->decimal('seg_soc_cajas', 15, 2)->nullable();      // >= 2025
            $table->decimal('sind', 15, 2)->nullable();
            $table->decimal('gan_brut', 15, 2)->nullable();
            $table->decimal('ret_gan', 15, 2)->nullable();
            $table->decimal('retrib_no_hab', 15, 2)->nullable();
            $table->decimal('ajuste', 15, 2)->nullable();             // hasta 2024
            $table->decimal('ajuste_rem_gravadas', 15, 2)->nullable();          // >= 2025
            $table->decimal('ajuste_rem_exe_no_alcanzadas', 15, 2)->nullable(); // >= 2025
            $table->decimal('exe_no_alc', 15, 2)->nullable();         // eliminado en 1.22
            $table->decimal('asign_fam', 15, 2)->nullable();
            $table->decimal('int_prest_emp', 15, 2)->nullable();
            $table->decimal('remun_judiciales', 15, 2)->nullable();   // v1.23
            $table->decimal('indem_ley4003', 15, 2)->nullable();
            $table->decimal('remun_ley19640', 15, 2)->nullable();
            $table->decimal('remun_cct_petro', 15, 2)->nullable();
            $table->decimal('cursos_semin', 15, 2)->nullable();
            $table->decimal('indum_equip_emp', 15, 2)->nullable();
            $table->decimal('sac', 15, 2)->nullable();
            $table->decimal('horas_ext_gr', 15, 2)->nullable();
            $table->decimal('horas_ext_ex', 15, 2)->nullable();
            $table->decimal('mat_did', 15, 2)->nullable();
            $table->decimal('movilidad', 15, 2)->nullable();
            $table->decimal('viaticos', 15, 2)->nullable();
            $table->decimal('otros_con_an', 15, 2)->nullable();
            $table->decimal('bonos_prod', 15, 2)->nullable();
            $table->decimal('fallos_caja', 15, 2)->nullable();
            $table->decimal('con_sim_nat', 15, 2)->nullable();
            $table->decimal('remun_exenta_ley27549', 15, 2)->nullable();
            $table->decimal('suplem_partic_ley19101', 15, 2)->nullable();
            $table->decimal('teletrabajo_exento', 15, 2)->nullable();
            $table->decimal('no_ret_med_caut', 15, 2)->nullable();    // v1.24
            $table->timestamps();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['otro_empleador_id', 'mes'], 'siradig_oem_mes_idx');
            $table->foreign('otro_empleador_id', 'siradig_oem_oe_fk')->references('id')->on('siradig_otro_empleador_sueldos')->onDelete('cascade');
        });

        // Detalles de conceptos análogos de cada mes (otrosConAn/bonosProd/fallosCaja/conSimNat)
        Schema::create('siradig_otro_empleador_mes_detalle_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('otro_empleador_mes_id');
            $table->string('grupo', 20); // otrosConAn|bonosProd|fallosCaja|conSimNat
            $table->string('descripcion', 30)->nullable();
            $table->decimal('monto', 15, 2)->nullable();
            $table->timestamps();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['otro_empleador_mes_id', 'grupo'], 'siradig_oemd_idx');
            $table->foreign('otro_empleador_mes_id', 'siradig_oemd_oem_fk')->references('id')->on('siradig_otro_empleador_mes_sueldos')->onDelete('cascade');
        });

        // Conceptos: deducciones + retenciones/percepciones/pagos + ajustes (ConceptoType/AjusteType)
        Schema::create('siradig_concepto_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('presentacion_id');
            $table->char('grupo', 1); // D=deducción, R=retención/percepción/pago, J=ajuste
            $table->unsignedSmallInteger('tipo'); // código Tabla 4 (D) / Tabla 9 (R) / Tabla 6 (J)
            $table->unsignedSmallInteger('tipo_doc')->nullable();
            $table->string('nro_doc', 11)->nullable();
            $table->string('cuit', 11)->nullable(); // usado por ajustes
            $table->string('denominacion', 200)->nullable();
            $table->string('desc_basica', 300)->nullable();
            $table->string('desc_adicional', 300)->nullable();
            $table->decimal('monto_total', 15, 2)->default(0);
            $table->timestamps();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['presentacion_id', 'grupo', 'tipo'], 'siradig_con_pres_idx');
            $table->foreign('presentacion_id', 'siradig_con_pres_fk')->references('id')->on('siradig_presentacion_sueldos')->onDelete('cascade');
        });

        // Períodos de cada concepto (PeriodoType)
        Schema::create('siradig_concepto_periodo_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_id');
            $table->unsignedTinyInteger('mes_desde')->nullable();
            $table->unsignedTinyInteger('mes_hasta')->nullable();
            $table->decimal('monto_mensual', 15, 2)->nullable();
            $table->timestamps();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index('concepto_id', 'siradig_conper_idx');
            $table->foreign('concepto_id', 'siradig_conper_con_fk')->references('id')->on('siradig_concepto_sueldos')->onDelete('cascade');
        });

        // Detalles nombre/valor de cada concepto (DetalleType)
        Schema::create('siradig_concepto_detalle_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_id');
            $table->string('nombre', 60)->nullable();
            $table->string('valor', 300)->nullable();
            $table->timestamps();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index('concepto_id', 'siradig_condet_idx');
            $table->foreign('concepto_id', 'siradig_condet_con_fk')->references('id')->on('siradig_concepto_sueldos')->onDelete('cascade');
        });

        // Datos adicionales de la presentación (DatoAdicionalType, Tabla 11)
        Schema::create('siradig_dato_adicional_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('presentacion_id');
            $table->string('nombre', 60);
            $table->unsignedTinyInteger('mes_desde')->nullable();
            $table->unsignedTinyInteger('mes_hasta')->nullable();
            $table->string('valor', 300)->nullable();
            $table->timestamps();

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index('presentacion_id', 'siradig_da_pres_idx');
            $table->foreign('presentacion_id', 'siradig_da_pres_fk')->references('id')->on('siradig_presentacion_sueldos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siradig_dato_adicional_sueldos');
        Schema::dropIfExists('siradig_concepto_detalle_sueldos');
        Schema::dropIfExists('siradig_concepto_periodo_sueldos');
        Schema::dropIfExists('siradig_concepto_sueldos');
        Schema::dropIfExists('siradig_otro_empleador_mes_detalle_sueldos');
        Schema::dropIfExists('siradig_otro_empleador_mes_sueldos');
        Schema::dropIfExists('siradig_otro_empleador_sueldos');
        Schema::dropIfExists('siradig_carga_familia_sueldos');
        Schema::dropIfExists('siradig_presentacion_sueldos');
    }
};
