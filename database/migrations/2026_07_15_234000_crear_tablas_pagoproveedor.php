<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pago a proveedores (OP): cabecera + detalle + retenciones (SICORE) + vínculos CC/cheque/asiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagoproveedor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_pagoprov_empresa')
                ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('tipotransaccion_caja_id')->nullable();
            $table->foreign('tipotransaccion_caja_id', 'fk_pagoprov_tipocaja')
                ->references('id')->on('tipotransaccion_caja')->onDelete('restrict')->onUpdate('restrict');
            /** Tipo Anita: OPP / OPA / OPV (default OPP). */
            $table->string('tipocomprobante', 8)->default('OPP');
            $table->string('letra', 1)->default('A');
            $table->unsignedInteger('sucursal')->default(1);
            $table->string('numerotransaccion', 32);
            $table->date('fecha');
            $table->unsignedBigInteger('caja_id')->nullable();
            $table->foreign('caja_id', 'fk_pagoprov_caja')
                ->references('id')->on('caja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_pagoprov_proveedor')
                ->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->string('detalle', 255)->nullable();
            $table->string('estado', 30)->default('PRE CARGA');
            $table->decimal('monto', 22, 4)->default(0);
            $table->decimal('cotizacion', 22, 8)->default(1);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_pagoprov_moneda')
                ->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            /**
             * factura = usa cotización de cada comprobante aplicado;
             * dia = cotización manual del pago → puede generar diferencia de cambio en ARS.
             */
            $table->string('modo_cotizacion', 20)->default('factura');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_pagoprov_usuario')
                ->references('id')->on('usuario')->onDelete('set null')->onUpdate('cascade');
            $table->unsignedBigInteger('asiento_id')->nullable();
            $table->unsignedBigInteger('caja_movimiento_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['empresa_id', 'tipocomprobante', 'letra', 'sucursal', 'numerotransaccion'],
                'pagoprov_empresa_tipo_nro_unique'
            );
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('pagoproveedor_estado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pagoproveedor_id');
            $table->foreign('pagoproveedor_id', 'fk_pagoprov_est_pago')
                ->references('id')->on('pagoproveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->dateTime('fecha');
            $table->string('estado', 30);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_pagoprov_est_usuario')
                ->references('id')->on('usuario')->onDelete('set null')->onUpdate('cascade');
            $table->string('observacion', 500)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('pagoproveedor_archivo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pagoproveedor_id');
            $table->foreign('pagoproveedor_id', 'fk_pagoprov_arch_pago')
                ->references('id')->on('pagoproveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombrearchivo', 255);
            $table->softDeletes();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('pagoproveedor_comprobante', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pagoproveedor_id');
            $table->foreign('pagoproveedor_id', 'fk_pagoprov_comp_pago')
                ->references('id')->on('pagoproveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('proveedor_cuentacorriente_id');
            $table->foreign('proveedor_cuentacorriente_id', 'fk_pagoprov_comp_cc')
                ->references('id')->on('proveedor_cuentacorriente')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('montoaplicado', 22, 4);
            $table->decimal('cotizacion', 22, 8)->default(1);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_pagoprov_comp_moneda')
                ->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            /** Cotización usada al aplicar (factura o del día). */
            $table->decimal('cotizacion_aplicada', 22, 8)->nullable();
            /** Diferencia de cambio en ARS (solo modo dia + ME). */
            $table->decimal('diferencia_cambio', 22, 4)->default(0);
            /** Fila CC del ND/NC de diferencia de cambio (ya aplicada). */
            $table->unsignedBigInteger('proveedor_cuentacorriente_dc_id')->nullable();
            $table->foreign('proveedor_cuentacorriente_dc_id', 'fk_pagoprov_comp_cc_dc')
                ->references('id')->on('proveedor_cuentacorriente')->onDelete('set null')->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        /**
         * Retenciones practicadas en el pago — fuente SICORE ERP (además de retmov Anita).
         * tiporetencion: G ganancias | I iva | S suss | B iibb
         */
        Schema::create('pagoproveedor_retencion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pagoproveedor_id');
            $table->foreign('pagoproveedor_id', 'fk_pagoprov_ret_pago')
                ->references('id')->on('pagoproveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->string('tiporetencion', 1);
            $table->unsignedBigInteger('retencionganancia_id')->nullable();
            $table->foreign('retencionganancia_id', 'fk_pagoprov_ret_gan')
                ->references('id')->on('retencionganancia')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('retencioniva_id')->nullable();
            $table->foreign('retencioniva_id', 'fk_pagoprov_ret_iva')
                ->references('id')->on('retencioniva')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('retencionsuss_id')->nullable();
            $table->foreign('retencionsuss_id', 'fk_pagoprov_ret_suss')
                ->references('id')->on('retencionsuss')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_pagoprov_ret_prov')
                ->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigo_regimen', 20)->nullable();
            $table->string('codigo_retencion', 20)->nullable();
            $table->decimal('base_calculo', 22, 4)->default(0);
            $table->decimal('alicuota', 12, 4)->default(0);
            $table->decimal('importe', 22, 4)->default(0);
            $table->string('nro_certificado', 40)->nullable();
            $table->unsignedBigInteger('moneda_id')->nullable();
            $table->foreign('moneda_id', 'fk_pagoprov_ret_moneda')
                ->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('cotizacion', 22, 8)->default(1);
            /** Snapshot legible del motor (base, MNSR, escala, padrón, etc.). */
            $table->json('detalle_calculo')->nullable();
            $table->string('motivo', 80)->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tiporetencion', 'codigo_regimen'], 'pagoprov_ret_tipo_regimen_idx');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::table('cheque', function (Blueprint $table) {
            if (! Schema::hasColumn('cheque', 'pagoproveedor_id')) {
                $table->unsignedBigInteger('pagoproveedor_id')->nullable()->after('cobranza_id');
                $table->foreign('pagoproveedor_id', 'fk_cheque_pagoproveedor')
                    ->references('id')->on('pagoproveedor')->onDelete('cascade')->onUpdate('cascade');
            }
        });

        Schema::table('proveedor_cuentacorriente', function (Blueprint $table) {
            if (! Schema::hasColumn('proveedor_cuentacorriente', 'pagoproveedor_id')) {
                $table->unsignedBigInteger('pagoproveedor_id')->nullable()->after('comprobante_proveedor_cuota_id');
                $table->foreign('pagoproveedor_id', 'fk_provcc_pagoproveedor')
                    ->references('id')->on('pagoproveedor')->onDelete('set null')->onUpdate('cascade');
            }
        });

        Schema::table('proveedor_cuentacorriente_aplicacion', function (Blueprint $table) {
            if (! Schema::hasColumn('proveedor_cuentacorriente_aplicacion', 'pagoproveedor_id')) {
                $table->unsignedBigInteger('pagoproveedor_id')->nullable()->after('empresa_id');
                $table->foreign('pagoproveedor_id', 'fk_provccapl_pagoproveedor')
                    ->references('id')->on('pagoproveedor')->onDelete('set null')->onUpdate('cascade');
            }
        });

        if (Schema::hasTable('asiento') && ! Schema::hasColumn('asiento', 'pagoproveedor_id')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->unsignedBigInteger('pagoproveedor_id')->nullable();
                $table->foreign('pagoproveedor_id', 'fk_asiento_pagoproveedor')
                    ->references('id')->on('pagoproveedor')->onDelete('set null')->onUpdate('cascade');
            });
        }

        if (Schema::hasTable('caja_movimiento') && ! Schema::hasColumn('caja_movimiento', 'pagoproveedor_id')) {
            Schema::table('caja_movimiento', function (Blueprint $table) {
                $table->unsignedBigInteger('pagoproveedor_id')->nullable();
                $table->foreign('pagoproveedor_id', 'fk_cajamov_pagoproveedor')
                    ->references('id')->on('pagoproveedor')->onDelete('set null')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('caja_movimiento') && Schema::hasColumn('caja_movimiento', 'pagoproveedor_id')) {
            Schema::table('caja_movimiento', function (Blueprint $table) {
                $table->dropForeign('fk_cajamov_pagoproveedor');
                $table->dropColumn('pagoproveedor_id');
            });
        }
        if (Schema::hasTable('asiento') && Schema::hasColumn('asiento', 'pagoproveedor_id')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->dropForeign('fk_asiento_pagoproveedor');
                $table->dropColumn('pagoproveedor_id');
            });
        }
        if (Schema::hasColumn('proveedor_cuentacorriente_aplicacion', 'pagoproveedor_id')) {
            Schema::table('proveedor_cuentacorriente_aplicacion', function (Blueprint $table) {
                $table->dropForeign('fk_provccapl_pagoproveedor');
                $table->dropColumn('pagoproveedor_id');
            });
        }
        if (Schema::hasColumn('proveedor_cuentacorriente', 'pagoproveedor_id')) {
            Schema::table('proveedor_cuentacorriente', function (Blueprint $table) {
                $table->dropForeign('fk_provcc_pagoproveedor');
                $table->dropColumn('pagoproveedor_id');
            });
        }
        if (Schema::hasColumn('cheque', 'pagoproveedor_id')) {
            Schema::table('cheque', function (Blueprint $table) {
                $table->dropForeign('fk_cheque_pagoproveedor');
                $table->dropColumn('pagoproveedor_id');
            });
        }

        Schema::dropIfExists('pagoproveedor_retencion');
        Schema::dropIfExists('pagoproveedor_comprobante');
        Schema::dropIfExists('pagoproveedor_archivo');
        Schema::dropIfExists('pagoproveedor_estado');
        Schema::dropIfExists('pagoproveedor');
    }
};
