<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->dropForeign('fk_config_terminal_vianda_tipotransaccion');
            $table->dropColumn('tipotransaccion_id');
        });

        // El valor anterior (tipo de transacción de ventas) no mapea a un id de
        // tipotransaccion_stock, por eso la columna queda nullable: cada terminal debe
        // reelegir su tipo de transacción de stock de salida. La validación lo exige al guardar.
        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->unsignedBigInteger('tipotransaccion_stock_id')->nullable()->after('listaprecio_id');
            $table->foreign('tipotransaccion_stock_id', 'fk_config_terminal_vianda_tt_stock')
                ->references('id')->on('tipotransaccion_stock')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->dropForeign('fk_config_terminal_vianda_tt_stock');
            $table->dropColumn('tipotransaccion_stock_id');
        });

        Schema::table('configuracion_terminal_vianda', function (Blueprint $table) {
            $table->unsignedBigInteger('tipotransaccion_id')->nullable()->after('listaprecio_id');
            $table->foreign('tipotransaccion_id', 'fk_config_terminal_vianda_tipotransaccion')
                ->references('id')->on('tipotransaccion')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }
};
