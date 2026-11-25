<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot del nro. de cliente/medidor: el maestro proveedor_servicio
 * borra y recrea filas al guardar, así que proveedor_servicio_id puede
 * quedar null (nullOnDelete). El texto conserva la asignación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('precarga_comprobante_proveedor')
            && ! Schema::hasColumn('precarga_comprobante_proveedor', 'servicio_cliente')
        ) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->string('servicio_cliente', 255)->nullable()->after('proveedor_servicio_id');
                $table->index('servicio_cliente', 'idx_precarga_cp_servicio_cliente');
            });
        }

        if (Schema::hasTable('comprobante_proveedor')
            && ! Schema::hasColumn('comprobante_proveedor', 'servicio_cliente')
        ) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->string('servicio_cliente', 255)->nullable()->after('proveedor_servicio_id');
                $table->index('servicio_cliente', 'idx_comprobante_servicio_cliente');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('comprobante_proveedor')
            && Schema::hasColumn('comprobante_proveedor', 'servicio_cliente')
        ) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->dropIndex('idx_comprobante_servicio_cliente');
                $table->dropColumn('servicio_cliente');
            });
        }

        if (Schema::hasTable('precarga_comprobante_proveedor')
            && Schema::hasColumn('precarga_comprobante_proveedor', 'servicio_cliente')
        ) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->dropIndex('idx_precarga_cp_servicio_cliente');
                $table->dropColumn('servicio_cliente');
            });
        }
    }
};
