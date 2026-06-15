<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobante_proveedor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_comprobante_proveedor_empresa')
                ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_comprobante_proveedor_proveedor')
                ->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('tipotransaccion_compra_id');
            $table->foreign('tipotransaccion_compra_id', 'fk_comprobante_proveedor_tipotransaccion_compra')
                ->references('id')->on('tipotransaccion_compra')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('ordencompra_id')->nullable();
            $table->foreign('ordencompra_id', 'fk_comprobante_proveedor_ordencompra')
                ->references('id')->on('ordencompra')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('ordencompra_comprobante_id')->nullable();
            $table->foreign('ordencompra_comprobante_id', 'fk_comprobante_proveedor_ordencompra_comprobante')
                ->references('id')->on('ordencompra_comprobante')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('precarga_comprobante_proveedor_id')->nullable();
            $table->foreign('precarga_comprobante_proveedor_id', 'fk_comprobante_proveedor_precarga')
                ->references('id')->on('precarga_comprobante_proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('condicionpago_id')->nullable();
            $table->foreign('condicionpago_id', 'fk_comprobante_proveedor_condicionpago')
                ->references('id')->on('condicionpago')->onDelete('restrict')->onUpdate('restrict');
            $table->char('letra', 1);
            $table->integer('sucursal');
            $table->integer('numerocomprobante');
            $table->date('fechacomprobante');
            $table->date('fechaiva');
            $table->date('fechavencimiento')->nullable();
            $table->datetime('fecharecepcion')->nullable();
            $table->decimal('subtotal', 22, 4)->default(0);
            $table->decimal('total', 22, 4)->default(0);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_comprobante_proveedor_moneda')
                ->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('cotizacion', 22, 8)->default(1);
            $table->string('numerocae', 50)->nullable();
            $table->date('fechavencimientocae')->nullable();
            $table->boolean('es_fce')->default(false);
            $table->string('leyenda', 255)->nullable();
            $table->string('modo_carga', 30);
            $table->string('estado', 50);
            $table->unsignedBigInteger('asiento_id')->nullable();
            $table->boolean('pararevisar')->default(false);
            // FK asiento_id se agrega tras crear la columna en asiento (evita ciclo al migrar).
            $table->unsignedBigInteger('anita_nro_interno')->nullable();
            $table->string('anita_sync_estado', 30)->nullable();
            $table->text('anita_sync_error')->nullable();
            $table->timestamp('anita_sync_at')->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_comprobante_proveedor_usuario')
                ->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->timestamps();
            $table->softDeletes();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(
                ['empresa_id', 'proveedor_id', 'tipotransaccion_compra_id', 'letra', 'sucursal', 'numerocomprobante'],
                'uq_comprobante_proveedor_identificacion'
            );
        });

        Schema::create('comprobante_proveedor_concepto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_proveedor_id');
            $table->foreign('comprobante_proveedor_id', 'fk_cp_concepto_comprobante')
                ->references('id')->on('comprobante_proveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('concepto_ivacompra_id');
            $table->foreign('concepto_ivacompra_id', 'fk_cp_concepto_ivacompra')
                ->references('id')->on('concepto_ivacompra')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedSmallInteger('orden')->default(1);
            $table->decimal('monto', 22, 4);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('comprobante_proveedor_recepcion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_proveedor_id');
            $table->foreign('comprobante_proveedor_id', 'fk_cp_recepcion_comprobante')
                ->references('id')->on('comprobante_proveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('recepcion_proveedor_id');
            $table->foreign('recepcion_proveedor_id', 'fk_cp_recepcion_recepcion')
                ->references('id')->on('recepcion_proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();

            $table->unique(
                ['comprobante_proveedor_id', 'recepcion_proveedor_id'],
                'uq_cp_recepcion'
            );
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('comprobante_proveedor_cuota', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_proveedor_id');
            $table->foreign('comprobante_proveedor_id', 'fk_cp_cuota_comprobante')
                ->references('id')->on('comprobante_proveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedSmallInteger('numero_cuota');
            $table->date('fechavencimiento');
            $table->decimal('monto', 22, 4);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_cp_cuota_moneda')
                ->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('cotizacion', 22, 8)->nullable();
            $table->unsignedBigInteger('formapago_id');
            $table->foreign('formapago_id', 'fk_cp_cuota_formapago')
                ->references('id')->on('formapago')->onDelete('restrict')->onUpdate('restrict');
            $table->text('detalle')->nullable();
            $table->unsignedBigInteger('ordencompra_comprobante_cuota_id')->nullable();
            $table->foreign('ordencompra_comprobante_cuota_id', 'fk_cp_cuota_oc_cuota')
                ->references('id')->on('ordencompra_comprobante_cuota')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('total_pagado', 22, 4)->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('comprobante_proveedor_estado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_proveedor_id');
            $table->foreign('comprobante_proveedor_id', 'fk_cp_estado_comprobante')
                ->references('id')->on('comprobante_proveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->date('fecha');
            $table->string('estado', 50);
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_cp_estado_usuario')
                ->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('comprobante_proveedor_archivo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_proveedor_id');
            $table->foreign('comprobante_proveedor_id', 'fk_cp_archivo_comprobante')
                ->references('id')->on('comprobante_proveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->string('tipo', 30)->default('ADJUNTO');
            $table->string('nombrearchivo', 255);
            $table->boolean('origen_externo')->default(false);
            $table->string('ruta_externa', 512)->nullable();
            $table->unsignedBigInteger('precarga_comprobante_proveedor_id')->nullable();
            $table->foreign('precarga_comprobante_proveedor_id', 'fk_cp_archivo_precarga')
                ->references('id')->on('precarga_comprobante_proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::table('asiento', function (Blueprint $table) {
            if (! Schema::hasColumn('asiento', 'comprobante_proveedor_id')) {
                $table->unsignedBigInteger('comprobante_proveedor_id')->nullable()->after('recepcionproveedor_id');
                $table->foreign('comprobante_proveedor_id', 'fk_asiento_comprobante_proveedor')
                    ->references('id')->on('comprobante_proveedor')->onDelete('cascade')->onUpdate('cascade');
            }
        });

        Schema::table('comprobante_proveedor', function (Blueprint $table) {
            if (! $this->foreignKeyExists('comprobante_proveedor', 'fk_comprobante_proveedor_asiento')) {
                $table->foreign('asiento_id', 'fk_comprobante_proveedor_asiento')
                    ->references('id')->on('asiento')->onDelete('restrict')->onUpdate('restrict');
            }
        });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $row = $connection->selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $name, 'FOREIGN KEY']
        );

        return $row !== null;
    }

    public function down(): void
    {
        if ($this->foreignKeyExists('comprobante_proveedor', 'fk_comprobante_proveedor_asiento')) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->dropForeign('fk_comprobante_proveedor_asiento');
            });
        }

        Schema::table('asiento', function (Blueprint $table) {
            if (Schema::hasColumn('asiento', 'comprobante_proveedor_id')) {
                $table->dropForeign('fk_asiento_comprobante_proveedor');
                $table->dropColumn('comprobante_proveedor_id');
            }
        });

        Schema::dropIfExists('comprobante_proveedor_archivo');
        Schema::dropIfExists('comprobante_proveedor_estado');
        Schema::dropIfExists('comprobante_proveedor_cuota');
        Schema::dropIfExists('comprobante_proveedor_recepcion');
        Schema::dropIfExists('comprobante_proveedor_concepto');
        Schema::dropIfExists('comprobante_proveedor');
    }
};
