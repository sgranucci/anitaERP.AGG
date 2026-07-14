<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificado_sanitario', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('numero')->comment('certs_certificado');
            $table->char('serie', 1)->default('A');
            $table->date('fecha');
            $table->unsignedBigInteger('camion_id')->nullable();
            $table->string('precinto', 15)->nullable();
            $table->string('origen', 15)->nullable();
            $table->unsignedTinyInteger('opcion')->default(1)->comment('1=solicitud 2=permiso 3=importado');
            $table->unsignedInteger('cantidad_bulto')->default(0);
            $table->unsignedInteger('cantidad_caja')->default(0);
            $table->unsignedInteger('cantidad_precinto')->default(0);
            $table->string('procedencia', 15)->nullable();
            $table->string('ptr', 15)->nullable();
            $table->string('certif_sanitario', 15)->nullable();
            $table->string('establecimiento_nro', 15)->nullable();
            $table->unsignedBigInteger('transporte_id')->nullable();
            $table->unsignedInteger('nro_cert_interno')->nullable();
            $table->unsignedInteger('nro_cert_patagonico')->nullable();
            $table->unsignedInteger('establecimiento_destino')->nullable();
            $table->decimal('temperatura', 8, 1)->nullable();
            $table->unsignedInteger('nro_remito')->nullable();
            $table->boolean('abre_por_localidad')->default(false);
            $table->boolean('genera_web')->default(true);
            $table->string('xml_frio', 255)->nullable();
            $table->string('xml_sin_frio', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique(['numero', 'serie']);
            $table->index(['fecha', 'numero']);
            $table->foreign('camion_id', 'fk_certsan_camion')->references('id')->on('camion')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('transporte_id', 'fk_certsan_transporte')->references('id')->on('transporte')->onDelete('restrict')->onUpdate('restrict');
        });

        Schema::create('certificado_sanitario_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('certificado_sanitario_id');
            $table->unsignedInteger('linea');
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->string('sku', 20)->nullable();
            $table->decimal('cantidad', 14, 3)->default(0);
            $table->decimal('cajas', 14, 3)->default(0);
            $table->string('cert_tercero', 20)->nullable();
            $table->unsignedInteger('partida')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('certificado_sanitario_id', 'fk_certart_certsan')
                ->references('id')->on('certificado_sanitario')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->index(['articulo_id', 'partida']);
        });

        Schema::create('certificado_sanitario_cliente', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('certificado_sanitario_id');
            $table->unsignedInteger('linea');
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->string('codigo_cliente', 20)->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('certificado_sanitario_id', 'fk_certcli_certsan')
                ->references('id')->on('certificado_sanitario')
                ->onDelete('cascade')->onUpdate('restrict');
        });

        Schema::create('certificado_sanitario_destino', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('certificado_sanitario_id');
            $table->unsignedInteger('linea');
            $table->unsignedBigInteger('zonavta_id')->nullable();
            $table->unsignedInteger('codigo_destino')->nullable();
            $table->string('localidad', 40)->nullable();
            $table->string('provincia', 40)->nullable();
            $table->boolean('patagonico')->default(false);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('certificado_sanitario_id', 'fk_certdest_certsan')
                ->references('id')->on('certificado_sanitario')
                ->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificado_sanitario_destino');
        Schema::dropIfExists('certificado_sanitario_cliente');
        Schema::dropIfExists('certificado_sanitario_articulo');
        Schema::dropIfExists('certificado_sanitario');
    }
};
