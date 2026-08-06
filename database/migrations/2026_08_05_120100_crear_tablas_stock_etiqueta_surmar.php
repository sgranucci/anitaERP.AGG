<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etiquetas físicas Surmar (reemplazo operativo de Anita recepaper).
 * PK `id` = identificador de lectura/impresión. Referencias Anita se conservan
 * solo para importación / trazabilidad histórica.
 *
 * Solo corre en EL BIERZO. En AGG no crea tablas Surmar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        if (! Schema::hasTable('stock_etiqueta')) {
            Schema::create('stock_etiqueta', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('articulo_id');
                $table->unsignedBigInteger('deposito_id')->nullable();
                $table->unsignedBigInteger('unidadmedida_id')->nullable();

                // Estado operativo
                $table->string('estado', 20)->default('DISPONIBLE'); // DISPONIBLE|RESERVADA|CONSUMIDA|ANULADA
                $table->string('origen_tipo', 20); // COM|DES|AP|TRA|IMPORT_ANITA
                $table->unsignedBigInteger('origen_id')->nullable(); // recepcion_proveedor_id / movimientostock_id
                $table->unsignedBigInteger('origen_linea_id')->nullable(); // línea del comprobante origen
                $table->unsignedBigInteger('articulo_movimiento_id')->nullable();
                $table->unsignedBigInteger('etiqueta_origen_id')->nullable(); // si nace de transformación

                // Datos de negocio (recepaper)
                $table->string('lote_proveedor', 30)->nullable(); // certificado / lote proveedor
                $table->date('fecha_vto')->nullable();
                $table->date('fecha_emision')->nullable();
                $table->string('hora_emision', 5)->nullable();
                $table->decimal('cant_pieza', 18, 4)->default(0);
                $table->decimal('peso_bruto', 18, 4)->default(0);
                $table->decimal('peso_neto', 18, 4)->default(0);
                $table->unsignedInteger('nro_establecimiento')->nullable();
                $table->string('descripcion_snapshot', 60)->nullable();

                // Referencias Anita (solo legacy / import)
                $table->string('anita_proveedor', 6)->nullable();
                $table->string('anita_tipo', 3)->nullable();
                $table->string('anita_letra', 1)->nullable();
                $table->unsignedInteger('anita_sucursal')->nullable();
                $table->unsignedInteger('anita_nro')->nullable();
                $table->unsignedInteger('anita_orden')->nullable();
                $table->unsignedInteger('anita_nro_interno')->nullable();
                $table->unsignedInteger('anita_nro_apertura')->nullable();

                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresa')->restrictOnDelete();
                $table->foreign('articulo_id')->references('id')->on('articulo')->restrictOnDelete();
                $table->foreign('deposito_id')->references('id')->on('depmae')->nullOnDelete();
                $table->foreign('unidadmedida_id')->references('id')->on('unidadmedida')->nullOnDelete();
                $table->foreign('articulo_movimiento_id')->references('id')->on('articulo_movimiento')->nullOnDelete();
                $table->foreign('etiqueta_origen_id')->references('id')->on('stock_etiqueta')->nullOnDelete();
                $table->foreign('usuario_id')->references('id')->on('usuario')->nullOnDelete();

                $table->index(['empresa_id', 'estado', 'articulo_id'], 'stk_etiq_emp_est_art_idx');
                $table->index(['empresa_id', 'deposito_id', 'estado'], 'stk_etiq_emp_dep_est_idx');
                $table->index(['origen_tipo', 'origen_id'], 'stk_etiq_origen_idx');
                $table->index(
                    ['anita_tipo', 'anita_nro_interno', 'anita_nro_apertura'],
                    'stk_etiq_anita_nint_idx'
                );
                $table->index(['lote_proveedor', 'anita_nro_interno'], 'stk_etiq_lote_nint_idx');
            });
        }

        // Consumo de etiquetas en un movimiento de producción (Anita apcom / tapcom)
        if (! Schema::hasTable('stock_etiqueta_consumo')) {
            Schema::create('stock_etiqueta_consumo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('movimientostock_id')->nullable();
                $table->unsignedBigInteger('articulo_movimiento_id')->nullable(); // línea producida (DES/AP)
                $table->unsignedBigInteger('etiqueta_id'); // etiqueta consumida
                $table->unsignedBigInteger('articulo_id'); // artículo de la etiqueta (snapshot)
                $table->decimal('cant_pieza', 18, 4)->default(0);
                $table->decimal('peso_bruto', 18, 4)->default(0);
                $table->decimal('peso_neto', 18, 4)->default(0);
                $table->unsignedBigInteger('unidadmedida_id')->nullable();
                $table->string('lote_proveedor', 30)->nullable();
                $table->date('fecha_vto')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresa')->restrictOnDelete();
                $table->foreign('movimientostock_id')->references('id')->on('movimientostock')->nullOnDelete();
                $table->foreign('articulo_movimiento_id')->references('id')->on('articulo_movimiento')->nullOnDelete();
                $table->foreign('etiqueta_id')->references('id')->on('stock_etiqueta')->restrictOnDelete();
                $table->foreign('articulo_id')->references('id')->on('articulo')->restrictOnDelete();
                $table->foreign('unidadmedida_id')->references('id')->on('unidadmedida')->nullOnDelete();

                $table->index(['movimientostock_id', 'articulo_movimiento_id'], 'stk_etiq_cons_mov_idx');
                $table->index(['etiqueta_id'], 'stk_etiq_cons_etiq_idx');
            });
        }

        // Vínculo etiqueta ↔ movimiento (TRA / alta COM / salida DES)
        if (! Schema::hasTable('stock_etiqueta_movimiento')) {
            Schema::create('stock_etiqueta_movimiento', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('etiqueta_id');
                $table->unsignedBigInteger('articulo_movimiento_id');
                $table->string('rol', 20); // ENTRADA|SALIDA|TRANSFERENCIA
                $table->unsignedBigInteger('deposito_origen_id')->nullable();
                $table->unsignedBigInteger('deposito_destino_id')->nullable();
                $table->timestamps();

                $table->foreign('etiqueta_id')->references('id')->on('stock_etiqueta')->restrictOnDelete();
                $table->foreign('articulo_movimiento_id')->references('id')->on('articulo_movimiento')->restrictOnDelete();
                $table->foreign('deposito_origen_id')->references('id')->on('depmae')->nullOnDelete();
                $table->foreign('deposito_destino_id')->references('id')->on('depmae')->nullOnDelete();

                $table->unique(['etiqueta_id', 'articulo_movimiento_id', 'rol'], 'stk_etiq_mov_unique');
            });
        }
    }

    public function down(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        Schema::dropIfExists('stock_etiqueta_movimiento');
        Schema::dropIfExists('stock_etiqueta_consumo');
        Schema::dropIfExists('stock_etiqueta');
    }

    private function esEntornoSurmar(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }
};
