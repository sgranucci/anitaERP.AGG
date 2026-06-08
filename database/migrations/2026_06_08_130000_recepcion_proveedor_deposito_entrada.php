<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor', 'deposito_id')) {
                $table->unsignedBigInteger('deposito_id')->nullable()->after('proveedor_id');
                $table->foreign('deposito_id', 'fk_recepcion_proveedor_depmae')
                    ->references('id')->on('depmae')->onDelete('restrict')->onUpdate('restrict');
            }
        });

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'precio_stock')) {
                $table->decimal('precio_stock', 18, 6)->nullable()->after('precio_ordencompra');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'precio_stock')) {
                $table->dropColumn('precio_stock');
            }
        });

        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor', 'deposito_id')) {
                $table->dropForeign('fk_recepcion_proveedor_depmae');
                $table->dropColumn('deposito_id');
            }
        });
    }
};
