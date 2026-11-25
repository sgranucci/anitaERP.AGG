<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asigna un medidor/cliente de proveedor_servicio a la precarga y al comprobante
 * (p. ej. factura Edesur → nro. de cliente).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('precarga_comprobante_proveedor')
            && ! Schema::hasColumn('precarga_comprobante_proveedor', 'proveedor_servicio_id')
        ) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->unsignedBigInteger('proveedor_servicio_id')->nullable()->after('proveedor_id');
                $table->foreign('proveedor_servicio_id', 'fk_precarga_cp_proveedor_servicio')
                    ->references('id')
                    ->on('proveedor_servicio')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
                $table->index('proveedor_servicio_id', 'idx_precarga_cp_proveedor_servicio');
            });
        }

        if (Schema::hasTable('comprobante_proveedor')
            && ! Schema::hasColumn('comprobante_proveedor', 'proveedor_servicio_id')
        ) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->unsignedBigInteger('proveedor_servicio_id')->nullable()->after('proveedor_id');
                $table->foreign('proveedor_servicio_id', 'fk_comprobante_proveedor_servicio')
                    ->references('id')
                    ->on('proveedor_servicio')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
                $table->index('proveedor_servicio_id', 'idx_comprobante_proveedor_servicio');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('comprobante_proveedor')
            && Schema::hasColumn('comprobante_proveedor', 'proveedor_servicio_id')
        ) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->dropForeign('fk_comprobante_proveedor_servicio');
                $table->dropIndex('idx_comprobante_proveedor_servicio');
                $table->dropColumn('proveedor_servicio_id');
            });
        }

        if (Schema::hasTable('precarga_comprobante_proveedor')
            && Schema::hasColumn('precarga_comprobante_proveedor', 'proveedor_servicio_id')
        ) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->dropForeign('fk_precarga_cp_proveedor_servicio');
                $table->dropIndex('idx_precarga_cp_proveedor_servicio');
                $table->dropColumn('proveedor_servicio_id');
            });
        }
    }
};
