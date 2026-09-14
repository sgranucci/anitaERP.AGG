<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cimientos Facturación Local: canal, local_venta, turno, vale.
 * Sin SoftDeletes. Reutilizable por otras empresas (canal es genérico).
 */
return new class extends Migration
{
    private const USO_CUENTACAJA = 'Local';

    public function up(): void
    {
        if (! Schema::hasTable('canal')) {
            Schema::create('canal', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 30)->unique();
                $table->string('nombre', 80);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('articulo_canal')) {
            Schema::create('articulo_canal', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('articulo_id');
                $table->unsignedBigInteger('canal_id');
                $table->timestamps();

                $table->unique(['articulo_id', 'canal_id']);
                $table->foreign('articulo_id')->references('id')->on('articulo')->onDelete('cascade');
                $table->foreign('canal_id')->references('id')->on('canal')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('local_venta')) {
            Schema::create('local_venta', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 20)->unique();
                $table->string('nombre', 120);
                $table->boolean('activo')->default(true);
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('puntoventa_id');
                $table->unsignedBigInteger('deposito_id');
                $table->unsignedBigInteger('listaprecio_id')->nullable();
                $table->unsignedBigInteger('tipotransaccion_fac_id')->nullable();
                $table->unsignedBigInteger('tipotransaccion_nc_id')->nullable();
                $table->unsignedBigInteger('tipotransaccion_caja_id')->nullable();
                $table->unsignedBigInteger('tipotransaccion_caja_devolucion_id')->nullable();
                $table->unsignedBigInteger('cuentacaja_efectivo_id')->nullable();
                $table->string('anita_servidor', 40)->nullable()->default('LOCAL_IP');
                $table->string('anita_ifx_server', 40)->nullable()->default('IFX_SERVER_LOCAL');
                $table->unsignedInteger('anita_deposito')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresa')->onDelete('restrict');
                $table->foreign('puntoventa_id')->references('id')->on('puntoventa')->onDelete('restrict');
                $table->foreign('deposito_id')->references('id')->on('depmae')->onDelete('restrict');
                $table->foreign('listaprecio_id')->references('id')->on('listaprecio')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('local_venta_cuentacaja')) {
            Schema::create('local_venta_cuentacaja', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('local_venta_id');
                $table->unsignedBigInteger('cuentacaja_id');
                $table->string('medio', 40)->nullable();
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('es_default')->default(false);
                $table->timestamps();

                $table->unique(['local_venta_id', 'cuentacaja_id']);
                $table->foreign('local_venta_id')->references('id')->on('local_venta')->onDelete('cascade');
                $table->foreign('cuentacaja_id')->references('id')->on('cuentacaja')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('turno_operativo_local')) {
            Schema::create('turno_operativo_local', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('local_venta_id');
                $table->string('identificador_pc', 80)->nullable();
                $table->string('estado', 20)->default('abierto');
                $table->unsignedBigInteger('usuario_apertura_id');
                $table->timestamp('apertura_en')->nullable();
                $table->decimal('fondo_inicial', 14, 2)->default(0);
                $table->text('observacion_apertura')->nullable();
                $table->unsignedBigInteger('usuario_cierre_id')->nullable();
                $table->timestamp('cierre_en')->nullable();
                $table->decimal('sobrante_faltante', 14, 2)->nullable();
                $table->json('medios_contado_cierre_json')->nullable();
                $table->text('observacion_cierre')->nullable();
                $table->decimal('monto_facturacion_turno', 14, 2)->default(0);
                $table->timestamps();

                $table->foreign('local_venta_id')->references('id')->on('local_venta')->onDelete('restrict');
                $table->index(['local_venta_id', 'estado']);
            });
        }

        if (! Schema::hasTable('vale_cliente_local')) {
            Schema::create('vale_cliente_local', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('local_venta_id')->nullable();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->string('tipo_documento', 10)->nullable();
                $table->string('nro_documento', 20)->nullable();
                $table->string('nombre', 120)->nullable();
                $table->string('tipo', 10)->default('VAL');
                $table->decimal('importe_original', 14, 2);
                $table->decimal('saldo', 14, 2);
                $table->string('estado', 20)->default('activo');
                $table->unsignedBigInteger('venta_origen_id')->nullable();
                $table->unsignedBigInteger('venta_aplicacion_id')->nullable();
                $table->unsignedBigInteger('turno_operativo_local_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();

                $table->index(['tipo_documento', 'nro_documento']);
                $table->index(['cliente_id', 'estado']);
            });
        }

        if (! Schema::hasTable('facturacion_local_emision')) {
            Schema::create('facturacion_local_emision', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('local_venta_id');
                $table->unsignedBigInteger('turno_operativo_local_id')->nullable();
                $table->unsignedBigInteger('venta_id');
                $table->unsignedBigInteger('venta_nc_id')->nullable();
                $table->unsignedBigInteger('vale_cliente_local_id')->nullable();
                $table->boolean('es_ticket_regalo')->default(false);
                $table->json('payload_resumen_json')->nullable();
                $table->timestamps();

                $table->foreign('local_venta_id')->references('id')->on('local_venta')->onDelete('restrict');
                $table->foreign('venta_id')->references('id')->on('venta')->onDelete('restrict');
                $table->index(['local_venta_id', 'created_at']);
            });
        }

        $this->seedCanalLocal();
        $this->seedUsocuentacajaLocal();
    }

    public function down(): void
    {
        Schema::dropIfExists('facturacion_local_emision');
        Schema::dropIfExists('vale_cliente_local');
        Schema::dropIfExists('turno_operativo_local');
        Schema::dropIfExists('local_venta_cuentacaja');
        Schema::dropIfExists('local_venta');
        Schema::dropIfExists('articulo_canal');
        Schema::dropIfExists('canal');
    }

    private function seedCanalLocal(): void
    {
        if (! Schema::hasTable('canal')) {
            return;
        }
        if (DB::table('canal')->where('codigo', 'LOCAL')->exists()) {
            return;
        }
        DB::table('canal')->insert([
            'codigo' => 'LOCAL',
            'nombre' => 'Local',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedUsocuentacajaLocal(): void
    {
        if (! Schema::hasTable('usocuentacaja')) {
            return;
        }
        if (DB::table('usocuentacaja')->where('nombre', self::USO_CUENTACAJA)->exists()) {
            return;
        }
        DB::table('usocuentacaja')->insert([
            'nombre' => self::USO_CUENTACAJA,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
