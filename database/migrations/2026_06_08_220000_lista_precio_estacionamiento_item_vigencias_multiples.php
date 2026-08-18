<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexExists('lista_precio_estacionamiento_item', 'uq_lp_est_item_lista_item_fecha')) {
            return;
        }

        Schema::table('lista_precio_estacionamiento_item', function (Blueprint $table) {
            if ($this->foreignKeyExists('lista_precio_estacionamiento_item', 'fk_lp_est_item_lista')) {
                $table->dropForeign('fk_lp_est_item_lista');
            }
            if ($this->foreignKeyExists('lista_precio_estacionamiento_item', 'fk_lp_est_item_item')) {
                $table->dropForeign('fk_lp_est_item_item');
            }
        });

        if ($this->indexExists('lista_precio_estacionamiento_item', 'uq_lp_est_item_lista_item')) {
            Schema::table('lista_precio_estacionamiento_item', function (Blueprint $table) {
                $table->dropUnique('uq_lp_est_item_lista_item');
            });
        }

        Schema::table('lista_precio_estacionamiento_item', function (Blueprint $table) {
            $table->unique(
                ['lista_precio_estacionamiento_id', 'item_estacionamiento_id', 'fecha_vigencia'],
                'uq_lp_est_item_lista_item_fecha'
            );
            $table->foreign('lista_precio_estacionamiento_id', 'fk_lp_est_item_lista')
                ->references('id')->on('lista_precio_estacionamiento')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('item_estacionamiento_id', 'fk_lp_est_item_item')
                ->references('id')->on('item_estacionamiento')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('lista_precio_estacionamiento_item', function (Blueprint $table) {
            if ($this->foreignKeyExists('lista_precio_estacionamiento_item', 'fk_lp_est_item_lista')) {
                $table->dropForeign('fk_lp_est_item_lista');
            }
            if ($this->foreignKeyExists('lista_precio_estacionamiento_item', 'fk_lp_est_item_item')) {
                $table->dropForeign('fk_lp_est_item_item');
            }
            if ($this->indexExists('lista_precio_estacionamiento_item', 'uq_lp_est_item_lista_item_fecha')) {
                $table->dropUnique('uq_lp_est_item_lista_item_fecha');
            }
            $table->unique(
                ['lista_precio_estacionamiento_id', 'item_estacionamiento_id'],
                'uq_lp_est_item_lista_item'
            );
            $table->foreign('lista_precio_estacionamiento_id', 'fk_lp_est_item_lista')
                ->references('id')->on('lista_precio_estacionamiento')->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('item_estacionamiento_id', 'fk_lp_est_item_item')
                ->references('id')->on('item_estacionamiento')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return \App\Support\Database\MigrationDialectSupport::tieneForeignKey($table, $name);
    }

    private function indexExists(string $table, string $name): bool
    {
        return \App\Support\Database\MigrationDialectSupport::tieneIndice($table, $name);
    }
};
