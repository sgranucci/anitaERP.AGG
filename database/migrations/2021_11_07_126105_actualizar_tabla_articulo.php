<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ActualizarTablaArticulo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('articulo', function (Blueprint $table) {
            $table->string('detalle',255)->after('descripcion');
			$table->unsignedBigInteger('empresa_id')->nullable()->after('detalle');
            $table->foreign('empresa_id', 'fk_articulo_empresa')->references('id')->on('empresa')->onDelete('cascade')->onUpdate('cascade');
            $table->decimal('unidadesxenvase',20,6)->nullable()->after('empresa_id');
            $table->char('skualternativo', 20)->nullable()->after('unidadesxenvase');
			$table->unsignedBigInteger('categoria_id')->nullable()->after('skualternativo');
            $table->foreign('categoria_id', 'fk_articulo_categoria')->references('id')->on('categoria')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('subcategoria_id')->nullable()->after('categoria_id');
            $table->foreign('subcategoria_id', 'fk_articulo_subcategoria')->references('id')->on('subcategoria')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('linea_id')->nullable()->after('subcategoria_id');
            $table->foreign('linea_id', 'fk_articulo_linea')->references('id')->on('linea')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('mventa_id')->nullable()->after('linea_id');
            $table->foreign('mventa_id', 'fk_articulo_mventa')->references('id')->on('mventa')->onDelete('cascade')->onUpdate('cascade');
			$table->decimal('peso',20,6)->nullable()->after('mventa_id');
            $table->char('nofactura', 1)->nullable()->after('peso');
			$table->unsignedBigInteger('impuesto_id')->nullable()->after('nofactura');
            $table->foreign('impuesto_id', 'fk_articulo_impuesto')->references('id')->on('impuesto')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('formula')->nullable()->after('impuesto_id');
            $table->char('nomenclador', 10)->nullable()->after('formula');
            $table->char('foto', 100)->nullable()->after('nomenclador');
			$table->unsignedBigInteger('unidadmedida_id')->nullable()->after('foto');
            $table->foreign('unidadmedida_id', 'fk_articulo_unidadmedida')->references('id')->on('unidadmedida')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('unidadmedidaalternativa_id')->nullable()->after('unidadmedida_id');
            $table->foreign('unidadmedidaalternativa_id', 'fk_articulo_unidadmedidaalternativa_unidadmedida')->references('id')->on('unidadmedida')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('cuentacontableventa_id')->nullable()->after('unidadmedidaalternativa_id');
            $table->foreign('cuentacontableventa_id', 'fk_articulo_cuentacontableventa_cuentacontable')->references('id')->on('cuentacontable')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('cuentacontablecompra_id')->nullable()->after('cuentacontableventa_id');
            $table->foreign('cuentacontablecompra_id', 'fk_articulo_cuentacontablecompra_cuentacontable')->references('id')->on('cuentacontable')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('cuentacontableimpinterno_id')->nullable()->after('cuentacontablecompra_id');
            $table->foreign('cuentacontableimpinterno_id', 'fk_articulo_cuentacontableimpinterno_cuentacontable')->references('id')->on('cuentacontable')->onDelete('set null')->onUpdate('set null');
			$table->decimal('ppp',20,6)->nullable()->after('cuentacontableimpinterno_id');
			$table->unsignedBigInteger('usoarticulo_id')->nullable()->after('ppp');
            $table->foreign('usoarticulo_id', 'fk_articulo_usoarticulo')->references('id')->on('usoarticulo')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('material_id')->nullable()->after('usoarticulo_id');
            $table->foreign('material_id', 'fk_articulo_material')->references('id')->on('material')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('tipocorte_id')->nullable()->after('material_id');
            $table->foreign('tipocorte_id', 'fk_articulo_tipocorte')->references('id')->on('tipocorte')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('puntera_id')->nullable()->after('tipocorte_id');
            $table->foreign('puntera_id', 'fk_articulo_puntera')->references('id')->on('puntera')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('contrafuerte_id')->nullable()->after('puntera_id');
            $table->foreign('contrafuerte_id', 'fk_articulo_contrafuerte')->references('id')->on('contrafuerte')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('tipocorteforro_id')->nullable()->after('contrafuerte_id');
            $table->foreign('tipocorteforro_id', 'fk_articulo_tipocorteforro_tipocorte')->references('id')->on('tipocorte')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('forro_id')->nullable()->after('tipocorteforro_id');
            $table->foreign('forro_id', 'fk_articulo_forro')->references('id')->on('forro')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('compfondo_id')->nullable()->after('forro_id');
            $table->foreign('compfondo_id', 'fk_articulo_compfondo')->references('id')->on('compfondo')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('fondo_id')->nullable()->after('compfondo_id');
            $table->foreign('fondo_id', 'fk_articulo_fondo')->references('id')->on('fondo')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('horma_id')->nullable()->after('fondo_id');
            $table->foreign('horma_id', 'fk_articulo_horma')->references('id')->on('horma')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('serigrafia_id')->nullable()->after('horma_id');
            $table->foreign('serigrafia_id', 'fk_articulo_serigrafia')->references('id')->on('serigrafia')->onDelete('set null')->onUpdate('set null');
            $table->char('claveorden', 13)->nullable()->after('serigrafia_id');
			$table->unsignedBigInteger('usuario_id')->nullable()->after('claveorden');
            $table->foreign('usuario_id', 'fk_articulo_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
            $table->date('fechaultimacompra')->nullable()->after('usuario_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('articulo');
    }
}
