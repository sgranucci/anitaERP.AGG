<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificado SENASA Surmar + remito cárnico AFIP + aperturas de etiqueta.
 * Solo EL BIERZO. En AGG no crea schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        if (! Schema::hasTable('certificado_senasa_surmar')) {
            Schema::create('certificado_senasa_surmar', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedInteger('numero');
                $table->char('serie', 1)->default('A');
                $table->date('fecha');
                $table->string('estado', 20)->default('BORRADOR'); // BORRADOR|CONFIRMADO|ANULADO

                $table->unsignedBigInteger('camion_id')->nullable();
                $table->unsignedBigInteger('transporte_id')->nullable();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->string('precinto', 15)->nullable();
                $table->string('origen', 40)->nullable();
                $table->string('procedencia', 40)->nullable();
                $table->unsignedTinyInteger('opcion')->default(1);
                $table->unsignedInteger('cantidad_bulto')->default(0);
                $table->unsignedInteger('cantidad_caja')->default(0);
                $table->unsignedInteger('cantidad_precinto')->default(0);
                $table->string('ptr', 15)->nullable();
                $table->string('certif_sanitario', 15)->nullable();
                $table->string('establecimiento_nro', 15)->nullable();
                $table->unsignedInteger('nro_cert_interno')->nullable();
                $table->unsignedInteger('nro_cert_patagonico')->nullable();
                $table->unsignedInteger('establecimiento_destino')->nullable();
                $table->decimal('temperatura', 8, 1)->nullable();
                $table->boolean('abre_por_localidad')->default(false);
                $table->boolean('genera_web')->default(true);
                $table->boolean('genera_remito')->default(true);
                $table->string('xml_path', 255)->nullable();
                $table->text('observacion')->nullable();

                // Remito AFIP (wsremcarne)
                $table->unsignedInteger('punto_emision')->nullable();
                $table->unsignedBigInteger('id_req')->nullable();
                $table->string('cod_remito', 30)->nullable();
                $table->string('cod_autorizacion', 40)->nullable();
                $table->string('estado_afip', 20)->nullable();
                $table->string('resultado_afip', 10)->nullable();
                $table->date('fecha_emision_afip')->nullable();
                $table->date('fecha_vto_afip')->nullable();
                $table->string('qr_path', 255)->nullable();
                $table->string('tipo_movimiento', 5)->nullable();
                $table->unsignedTinyInteger('categoria_emisor')->nullable();
                $table->string('tipo_receptor', 5)->nullable();
                $table->unsignedTinyInteger('categoria_receptor')->nullable();
                $table->string('cuit_titular', 14)->nullable();
                $table->string('cuit_receptor', 14)->nullable();
                $table->string('cuit_depositario', 14)->nullable();
                $table->string('cuit_transportista', 14)->nullable();
                $table->string('cuit_conductor', 14)->nullable();
                $table->string('dominio_vehiculo', 15)->nullable();
                $table->string('dominio_acoplado', 15)->nullable();
                $table->unsignedInteger('cod_dom_origen')->nullable();
                $table->unsignedInteger('cod_dom_destino')->nullable();
                $table->decimal('distancia_km', 10, 2)->nullable();
                $table->text('mensaje_afip')->nullable();

                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'numero', 'serie'], 'css_emp_num_ser_uq');
                $table->index(['empresa_id', 'estado', 'fecha'], 'css_emp_est_fec_idx');
                $table->index(['cod_remito'], 'css_cod_remito_idx');

                $table->foreign('empresa_id')->references('id')->on('empresa')->restrictOnDelete();
                $table->foreign('camion_id')->references('id')->on('camion')->nullOnDelete();
                $table->foreign('transporte_id')->references('id')->on('transporte')->nullOnDelete();
                $table->foreign('cliente_id')->references('id')->on('cliente')->nullOnDelete();
                $table->foreign('usuario_id')->references('id')->on('usuario')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('certificado_senasa_surmar_articulo')) {
            Schema::create('certificado_senasa_surmar_articulo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('certificado_senasa_surmar_id');
                $table->unsignedInteger('linea');
                $table->unsignedBigInteger('articulo_id')->nullable();
                $table->string('sku', 20)->nullable();
                $table->decimal('kilos', 14, 3)->default(0);
                $table->decimal('cajas', 14, 3)->default(0);
                $table->decimal('piezas', 14, 3)->default(0);
                $table->unsignedInteger('tropa')->nullable();
                $table->unsignedInteger('grupocarne')->nullable();
                $table->unsignedInteger('tipocarne')->nullable();
                $table->string('cert_tercero', 20)->nullable();
                $table->unsignedInteger('partida')->nullable();
                $table->string('hora_piqueo', 5)->nullable();
                $table->timestamps();

                $table->foreign('certificado_senasa_surmar_id', 'fk_cssart_css')
                    ->references('id')->on('certificado_senasa_surmar')
                    ->cascadeOnDelete();
                $table->foreign('articulo_id')->references('id')->on('articulo')->nullOnDelete();
                $table->index(['articulo_id'], 'cssart_articulo_idx');
            });
        }

        if (! Schema::hasTable('certificado_senasa_surmar_cliente')) {
            Schema::create('certificado_senasa_surmar_cliente', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('certificado_senasa_surmar_id');
                $table->unsignedInteger('linea');
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->string('codigo_cliente', 20)->nullable();
                $table->timestamps();

                $table->foreign('certificado_senasa_surmar_id', 'fk_csscli_css')
                    ->references('id')->on('certificado_senasa_surmar')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('certificado_senasa_surmar_destino')) {
            Schema::create('certificado_senasa_surmar_destino', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('certificado_senasa_surmar_id');
                $table->unsignedInteger('linea');
                $table->unsignedBigInteger('zonavta_id')->nullable();
                $table->unsignedInteger('codigo_destino')->nullable();
                $table->string('localidad', 40)->nullable();
                $table->string('provincia', 40)->nullable();
                $table->boolean('patagonico')->default(false);
                $table->timestamps();

                $table->foreign('certificado_senasa_surmar_id', 'fk_cssdest_css')
                    ->references('id')->on('certificado_senasa_surmar')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('certificado_senasa_surmar_etiqueta')) {
            Schema::create('certificado_senasa_surmar_etiqueta', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('certificado_senasa_surmar_id');
                $table->unsignedBigInteger('certificado_senasa_surmar_articulo_id');
                $table->unsignedBigInteger('etiqueta_id');
                $table->unsignedBigInteger('articulo_id')->nullable();
                $table->decimal('cant_pieza', 18, 4)->default(0);
                $table->decimal('peso_bruto', 18, 4)->default(0);
                $table->decimal('peso_neto', 18, 4)->default(0);
                $table->string('lote_proveedor', 30)->nullable();
                $table->string('hora_piqueo', 5)->nullable();
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresa')->restrictOnDelete();
                $table->foreign('certificado_senasa_surmar_id', 'fk_cssetiq_css')
                    ->references('id')->on('certificado_senasa_surmar')
                    ->cascadeOnDelete();
                $table->foreign('certificado_senasa_surmar_articulo_id', 'fk_cssetiq_art')
                    ->references('id')->on('certificado_senasa_surmar_articulo')
                    ->cascadeOnDelete();
                $table->foreign('etiqueta_id')->references('id')->on('stock_etiqueta')->restrictOnDelete();
                $table->foreign('articulo_id')->references('id')->on('articulo')->nullOnDelete();

                $table->unique(
                    ['certificado_senasa_surmar_articulo_id', 'etiqueta_id'],
                    'cssetiq_linea_etiq_uq'
                );
                $table->index(['etiqueta_id'], 'cssetiq_etiqueta_idx');
            });
        }
    }

    public function down(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        Schema::dropIfExists('certificado_senasa_surmar_etiqueta');
        Schema::dropIfExists('certificado_senasa_surmar_destino');
        Schema::dropIfExists('certificado_senasa_surmar_cliente');
        Schema::dropIfExists('certificado_senasa_surmar_articulo');
        Schema::dropIfExists('certificado_senasa_surmar');
    }

    private function esEntornoSurmar(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }
};
