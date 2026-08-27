<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CompletarProvincianacimientoClienteUif extends Migration
{
    /**
     * provincianacimiento_id se usa con provincia_uif (form y relación),
     * pero la FK original apuntaba a provincia (config). Por eso Córdoba y otras
     * no se podían grabar y el combo de nacimiento quedaba vacío al reabrir.
     */
    public function up()
    {
        Schema::table('cliente_uif', function (Blueprint $table) {
            $table->dropForeign('fk_cliente_uif_provincianacimiento');
        });

        Schema::table('cliente_uif', function (Blueprint $table) {
            $table->foreign('provincianacimiento_id', 'fk_cliente_uif_provincianacimiento')
                ->references('id')
                ->on('provincia_uif')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });

        MigrationDialectSupport::statementPorDriver(
            'UPDATE cliente_uif c
             INNER JOIN localidad_uif l ON l.id = c.localidadnacimiento_id
             SET c.provincianacimiento_id = l.provincia_uif_id
             WHERE c.provincianacimiento_id IS NULL
               AND l.provincia_uif_id IS NOT NULL',
            'UPDATE cliente_uif AS c
             SET provincianacimiento_id = l.provincia_uif_id
             FROM localidad_uif AS l
             WHERE l.id = c.localidadnacimiento_id
               AND c.provincianacimiento_id IS NULL
               AND l.provincia_uif_id IS NOT NULL'
        );
    }

    public function down()
    {
        Schema::table('cliente_uif', function (Blueprint $table) {
            $table->dropForeign('fk_cliente_uif_provincianacimiento');
        });

        Schema::table('cliente_uif', function (Blueprint $table) {
            $table->foreign('provincianacimiento_id', 'fk_cliente_uif_provincianacimiento')
                ->references('id')
                ->on('provincia')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }
}
