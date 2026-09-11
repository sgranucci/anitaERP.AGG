<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Destino de la mercadería (jurisdicción de la operación) para retención IIBB.
 * Default Buenos Aires (id 2 / jurisdicción AFIP 902).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('comprobante_proveedor', 'provincia_destino_id')) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->unsignedBigInteger('provincia_destino_id')->nullable()->after('conceptogasto_id');
                $table->foreign('provincia_destino_id', 'fk_comprobprov_provincia_destino')
                    ->references('id')->on('provincia')
                    ->onDelete('restrict')->onUpdate('cascade');
            });
        }

        if (Schema::hasTable('precarga_comprobante_proveedor')
            && ! Schema::hasColumn('precarga_comprobante_proveedor', 'provincia_destino_id')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->unsignedBigInteger('provincia_destino_id')->nullable()->after('empresa_id');
                $table->foreign('provincia_destino_id', 'fk_precargaprov_provincia_destino')
                    ->references('id')->on('provincia')
                    ->onDelete('restrict')->onUpdate('cascade');
            });
        }

        $ba = 2;
        if (Schema::hasColumn('comprobante_proveedor', 'provincia_destino_id')) {
            DB::table('comprobante_proveedor')
                ->where(function ($q) {
                    $q->whereNull('provincia_destino_id')->orWhere('provincia_destino_id', 0);
                })
                ->update(['provincia_destino_id' => $ba]);
        }
        if (Schema::hasColumn('precarga_comprobante_proveedor', 'provincia_destino_id')) {
            DB::table('precarga_comprobante_proveedor')
                ->where(function ($q) {
                    $q->whereNull('provincia_destino_id')->orWhere('provincia_destino_id', 0);
                })
                ->update(['provincia_destino_id' => $ba]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('precarga_comprobante_proveedor', 'provincia_destino_id')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->dropForeign('fk_precargaprov_provincia_destino');
                $table->dropColumn('provincia_destino_id');
            });
        }
        if (Schema::hasColumn('comprobante_proveedor', 'provincia_destino_id')) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->dropForeign('fk_comprobprov_provincia_destino');
                $table->dropColumn('provincia_destino_id');
            });
        }
    }
};
