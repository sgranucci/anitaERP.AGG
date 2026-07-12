<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->dropForeign('fk_config_terminal_vianda_listaprecio');
        });

        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->renameColumn('listaprecio_id', 'listaprecio_venta_id');
        });

        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->foreign('listaprecio_venta_id', 'fk_config_terminal_vianda_listaprecio_venta')
                ->references('id')->on('listaprecio')
                ->onDelete('restrict')->onUpdate('restrict');
        });

        Schema::table('vianda_consumo', function (Blueprint $table) {
            $table->decimal('total_venta', 15, 4)->default(0)->after('total_costo');
        });

        DB::table('vianda_consumo')->update([
            'total_venta' => DB::raw('total_costo'),
            'total_costo' => 0,
        ]);

        Schema::table('vianda_consumo_linea', function (Blueprint $table) {
            $table->decimal('precio_costo_unitario', 15, 4)->default(0)->after('cantidad');
            $table->renameColumn('precio_unitario', 'precio_venta_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('vianda_consumo_linea', function (Blueprint $table) {
            $table->renameColumn('precio_venta_unitario', 'precio_unitario');
            $table->dropColumn('precio_costo_unitario');
        });

        DB::table('vianda_consumo')->update([
            'total_costo' => DB::raw('CASE WHEN total_costo > 0 THEN total_costo ELSE total_venta END'),
        ]);

        Schema::table('vianda_consumo', function (Blueprint $table) {
            $table->dropColumn('total_venta');
        });

        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->dropForeign('fk_config_terminal_vianda_listaprecio_venta');
        });

        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->renameColumn('listaprecio_venta_id', 'listaprecio_id');
        });

        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->foreign('listaprecio_id', 'fk_config_terminal_vianda_listaprecio')
                ->references('id')->on('listaprecio')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }
};
