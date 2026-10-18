<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía bien_uso con campos contables Anita (biemae) y crea tablas satélite
 * (bierub, biesubr, indmae, indmov, amormov, revmov, bieubic).
 * Columnas nuevas nullable al inicio para no romper bienes IT existentes.
 * Sin SoftDeletes. Sin DELETE de datos.
 *
 * Módulo ESTÁNDAR del ERP (AGG, Crown y demás): sin EntornoEmpresaSupport.
 * Menú/permisos futuros del informe tampoco llevan gate de cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bien_uso_rubro')) {
            Schema::create('bien_uso_rubro', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('codigo')->unique('uq_bien_uso_rubro_codigo');
                $table->string('nombre', 80);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bien_uso_subrubro')) {
            Schema::create('bien_uso_subrubro', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('codigo')->unique('uq_bien_uso_subrubro_codigo');
                $table->string('nombre', 80);
                $table->unsignedBigInteger('bien_uso_rubro_id');
                $table->timestamps();

                $table->foreign('bien_uso_rubro_id', 'fk_bien_uso_subrubro_rubro')
                    ->references('id')->on('bien_uso_rubro')
                    ->restrictOnDelete()->cascadeOnUpdate();
                $table->index('bien_uso_rubro_id', 'ix_bien_uso_subrubro_rubro');
            });
        }

        if (! Schema::hasTable('bien_uso_indice')) {
            Schema::create('bien_uso_indice', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('codigo')->unique('uq_bien_uso_indice_codigo');
                $table->string('nombre', 80);
                // C=coeficiente, I=índices (coef_actual/coef_periodo), D=coeficiente directo
                $table->char('tipo_valor', 1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bien_uso_indice_coeficiente')) {
            Schema::create('bien_uso_indice_coeficiente', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bien_uso_indice_id');
                $table->unsignedSmallInteger('anio');
                $table->unsignedTinyInteger('mes');
                $table->decimal('coeficiente', 18, 6);
                $table->timestamps();

                $table->unique(
                    ['bien_uso_indice_id', 'anio', 'mes'],
                    'uq_bien_uso_indice_coef_periodo'
                );
                $table->foreign('bien_uso_indice_id', 'fk_bien_uso_indice_coef_indice')
                    ->references('id')->on('bien_uso_indice')
                    ->restrictOnDelete()->cascadeOnUpdate();
            });
        }

        if (Schema::hasTable('bien_uso') && ! Schema::hasColumn('bien_uso', 'codigo_bien')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->unsignedInteger('codigo_bien')->nullable()->after('empresa_id');
                $table->string('descripcion', 80)->nullable()->after('codigo_bien');
                $table->unsignedBigInteger('bien_uso_rubro_id')->nullable()->after('descripcion');
                $table->unsignedBigInteger('bien_uso_subrubro_id')->nullable()->after('bien_uso_rubro_id');
                $table->date('fecha_alta')->nullable()->after('bien_uso_subrubro_id');
                $table->date('fecha_compra')->nullable()->after('fecha_alta');
                $table->date('fecha_baja')->nullable()->after('fecha_compra');
                $table->date('fecha_baja_reemplazo')->nullable()->after('fecha_baja');
                $table->unsignedInteger('vida_util')->nullable()->after('fecha_baja_reemplazo');
                $table->decimal('valor_origen', 18, 2)->default(0)->after('vida_util');
                $table->decimal('valor_baja', 18, 2)->default(0)->after('valor_origen');
                $table->unsignedBigInteger('cuentacontable_origen_id')->nullable()->after('valor_baja');
                $table->unsignedBigInteger('cuentacontable_amort_id')->nullable()->after('cuentacontable_origen_id');
                $table->unsignedBigInteger('cuentacontable_contrap_id')->nullable()->after('cuentacontable_amort_id');
                $table->unsignedBigInteger('moneda_id')->nullable()->after('cuentacontable_contrap_id');
                // Default mes (BIEM_AMORT_MES='2') — ver BienUsoAmortizacionUnidad
                $table->char('fl_amort_unid', 1)->default('2')->after('moneda_id');
                $table->string('marca', 20)->nullable()->after('fl_amort_unid');
                $table->unsignedBigInteger('proveedor_id')->nullable()->after('marca');
                $table->string('tipo_factura', 3)->nullable()->after('proveedor_id');
                $table->char('letra_factura', 1)->nullable()->after('tipo_factura');
                $table->unsignedInteger('sucursal_factura')->nullable()->after('letra_factura');
                $table->unsignedBigInteger('nro_factura')->nullable()->after('sucursal_factura');
                $table->decimal('monto_factura', 18, 2)->default(0)->after('nro_factura');
                $table->date('fecha_factura')->nullable()->after('monto_factura');
                $table->unsignedBigInteger('bien_reemplazo_id')->nullable()->after('fecha_factura');
            });

            Schema::table('bien_uso', function (Blueprint $table) {
                $table->unique(['empresa_id', 'codigo_bien'], 'uq_bien_uso_empresa_codigo_bien');

                $table->foreign('bien_uso_rubro_id', 'fk_bien_uso_rubro')
                    ->references('id')->on('bien_uso_rubro')
                    ->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('bien_uso_subrubro_id', 'fk_bien_uso_subrubro')
                    ->references('id')->on('bien_uso_subrubro')
                    ->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('cuentacontable_origen_id', 'fk_bien_uso_cta_origen')
                    ->references('id')->on('cuentacontable')
                    ->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('cuentacontable_amort_id', 'fk_bien_uso_cta_amort')
                    ->references('id')->on('cuentacontable')
                    ->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('cuentacontable_contrap_id', 'fk_bien_uso_cta_contrap')
                    ->references('id')->on('cuentacontable')
                    ->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('moneda_id', 'fk_bien_uso_moneda')
                    ->references('id')->on('moneda')
                    ->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('proveedor_id', 'fk_bien_uso_proveedor')
                    ->references('id')->on('proveedor')
                    ->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('bien_reemplazo_id', 'fk_bien_uso_bien_reemplazo')
                    ->references('id')->on('bien_uso')
                    ->nullOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('bien_uso_amortizacion')) {
            Schema::create('bien_uso_amortizacion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bien_uso_id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedSmallInteger('periodo_anio');
                $table->decimal('monto_ejercicio', 18, 2)->default(0);
                $table->decimal('monto_anterior', 18, 2)->default(0);
                $table->decimal('monto_acumulado', 18, 2)->default(0);
                $table->unsignedInteger('vida_util_transcurrida')->default(0);
                $table->decimal('monto_mensual', 18, 2)->default(0);
                $table->timestamps();

                $table->unique(['bien_uso_id', 'periodo_anio'], 'uq_bien_uso_amort_bien_periodo');
                $table->foreign('bien_uso_id', 'fk_bien_uso_amort_bien')
                    ->references('id')->on('bien_uso')
                    ->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('empresa_id', 'fk_bien_uso_amort_empresa')
                    ->references('id')->on('empresa')
                    ->restrictOnDelete()->cascadeOnUpdate();
                $table->index('empresa_id', 'ix_bien_uso_amort_empresa');
            });
        }

        if (! Schema::hasTable('bien_uso_revaluo')) {
            Schema::create('bien_uso_revaluo', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bien_uso_id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedSmallInteger('periodo_anio');
                $table->decimal('valor_actual', 18, 2)->default(0);
                $table->decimal('valor_residual', 18, 2)->default(0);
                $table->decimal('valor_comienzo', 18, 2)->default(0);
                $table->decimal('revaluo_acumulado', 18, 2)->default(0);
                $table->timestamps();

                $table->unique(['bien_uso_id', 'periodo_anio'], 'uq_bien_uso_revaluo_bien_periodo');
                $table->foreign('bien_uso_id', 'fk_bien_uso_revaluo_bien')
                    ->references('id')->on('bien_uso')
                    ->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('empresa_id', 'fk_bien_uso_revaluo_empresa')
                    ->references('id')->on('empresa')
                    ->restrictOnDelete()->cascadeOnUpdate();
                $table->index('empresa_id', 'ix_bien_uso_revaluo_empresa');
            });
        }

        if (! Schema::hasTable('bien_uso_ubicacion')) {
            Schema::create('bien_uso_ubicacion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bien_uso_id');
                $table->unsignedBigInteger('empresa_id');
                $table->date('fecha');
                $table->string('observacion', 80)->nullable();
                $table->timestamps();

                $table->foreign('bien_uso_id', 'fk_bien_uso_ubicacion_bien')
                    ->references('id')->on('bien_uso')
                    ->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('empresa_id', 'fk_bien_uso_ubicacion_empresa')
                    ->references('id')->on('empresa')
                    ->restrictOnDelete()->cascadeOnUpdate();
                $table->index(['bien_uso_id', 'fecha'], 'ix_bien_uso_ubicacion_bien_fecha');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bien_uso_ubicacion');
        Schema::dropIfExists('bien_uso_revaluo');
        Schema::dropIfExists('bien_uso_amortizacion');

        if (Schema::hasTable('bien_uso') && Schema::hasColumn('bien_uso', 'codigo_bien')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $fks = [
                    'fk_bien_uso_rubro',
                    'fk_bien_uso_subrubro',
                    'fk_bien_uso_cta_origen',
                    'fk_bien_uso_cta_amort',
                    'fk_bien_uso_cta_contrap',
                    'fk_bien_uso_moneda',
                    'fk_bien_uso_proveedor',
                    'fk_bien_uso_bien_reemplazo',
                ];
                foreach ($fks as $fk) {
                    try {
                        $table->dropForeign($fk);
                    } catch (\Throwable) {
                        // índice/FK puede no existir en motores distintos
                    }
                }
                try {
                    $table->dropUnique('uq_bien_uso_empresa_codigo_bien');
                } catch (\Throwable) {
                }
                $table->dropColumn([
                    'codigo_bien',
                    'descripcion',
                    'bien_uso_rubro_id',
                    'bien_uso_subrubro_id',
                    'fecha_alta',
                    'fecha_compra',
                    'fecha_baja',
                    'fecha_baja_reemplazo',
                    'vida_util',
                    'valor_origen',
                    'valor_baja',
                    'cuentacontable_origen_id',
                    'cuentacontable_amort_id',
                    'cuentacontable_contrap_id',
                    'moneda_id',
                    'fl_amort_unid',
                    'marca',
                    'proveedor_id',
                    'tipo_factura',
                    'letra_factura',
                    'sucursal_factura',
                    'nro_factura',
                    'monto_factura',
                    'fecha_factura',
                    'bien_reemplazo_id',
                ]);
            });
        }

        Schema::dropIfExists('bien_uso_indice_coeficiente');
        Schema::dropIfExists('bien_uso_indice');
        Schema::dropIfExists('bien_uso_subrubro');
        Schema::dropIfExists('bien_uso_rubro');
    }
};
