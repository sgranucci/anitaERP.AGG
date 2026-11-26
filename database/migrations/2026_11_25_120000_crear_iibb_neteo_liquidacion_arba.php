<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Neteo de devoluciones de retenciones ARBA (IIBB), liquidación quincenal y
 * solicitud de pago. Tablas sin seed: la configuración de cuentas y proveedor
 * por empresa se carga en pantalla (regla cuentas-contables-no-hardcode).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iibb_neteo_devolucion')) {
            Schema::create('iibb_neteo_devolucion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('iibb_presentacion_config_id');
                $table->unsignedBigInteger('asiento_movimiento_id');
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->unsignedBigInteger('asiento_numero')->nullable();
                $table->string('periodo', 6);
                $table->unsignedTinyInteger('quincena');
                $table->date('fecha');
                $table->string('detalle', 255)->nullable();
                $table->decimal('importe', 18, 2);
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->unsignedBigInteger('cert_referencia')->nullable();
                $table->string('identificacion', 30)->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'iibb_presentacion_config_id', 'asiento_movimiento_id'], 'uq_iibb_neteo_dev_mov');
                $table->index(['empresa_id', 'iibb_presentacion_config_id', 'periodo', 'quincena'], 'ix_iibb_neteo_dev_periodo');
            });
        }

        if (! Schema::hasTable('iibb_neteo_aplicacion')) {
            Schema::create('iibb_neteo_aplicacion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('iibb_neteo_devolucion_id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('iibb_presentacion_config_id');
                $table->string('periodo', 6);
                $table->unsignedTinyInteger('quincena');
                $table->unsignedBigInteger('nro_certificado');
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->decimal('importe_aplicado', 18, 2);
                $table->timestamps();

                $table->index(['empresa_id', 'iibb_presentacion_config_id', 'periodo', 'quincena'], 'ix_iibb_neteo_apl_periodo');
                $table->index('iibb_neteo_devolucion_id', 'ix_iibb_neteo_apl_dev');
            });
        }

        if (! Schema::hasTable('iibb_liquidacion_arba')) {
            Schema::create('iibb_liquidacion_arba', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('iibb_presentacion_config_id');
                $table->string('periodo', 6);
                $table->unsignedTinyInteger('quincena');
                $table->string('nombre_txt', 120);
                $table->string('nombre_pdf', 160)->nullable();
                $table->string('ruta_pdf', 255)->nullable();
                $table->decimal('total_erp', 18, 2)->default(0);
                $table->decimal('total_neteos', 18, 2)->default(0);
                $table->decimal('total_txt', 18, 2)->default(0);
                $table->decimal('total_pendientes', 18, 2)->default(0);
                $table->decimal('total_dj', 18, 2)->default(0);
                $table->decimal('vep_esperado', 18, 2)->default(0);
                $table->boolean('conciliacion_ok')->default(false);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'periodo', 'quincena'], 'ix_iibb_liq_arba_periodo');
            });
        }

        if (! Schema::hasTable('iibb_solicitudpago_config')) {
            Schema::create('iibb_solicitudpago_config', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id')->unique('uq_iibb_sp_config_empresa');
                $table->unsignedBigInteger('proveedor_id');
                $table->unsignedBigInteger('sector_solicitudpago_id');
                $table->unsignedBigInteger('concepto_solicitudpago_id');
                $table->unsignedBigInteger('formapagosol_id');
                $table->unsignedBigInteger('moneda_id')->default(1);
                $table->unsignedBigInteger('centrocosto_id');
                $table->unsignedBigInteger('cuentacontable_debe_id');
                $table->unsignedBigInteger('cuentacontable_banco_id');
                $table->string('plantilla_detalle', 160)->default('Retenciones ARBA {1Q|2Q} {MM.AAAA} | {EMP}');
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iibb_solicitudpago_config');
        Schema::dropIfExists('iibb_liquidacion_arba');
        Schema::dropIfExists('iibb_neteo_aplicacion');
        Schema::dropIfExists('iibb_neteo_devolucion');
    }
};
