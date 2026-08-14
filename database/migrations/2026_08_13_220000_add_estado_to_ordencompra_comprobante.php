<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordencompra_comprobante')) {
            return;
        }
        if (Schema::hasColumn('ordencompra_comprobante', 'estado')) {
            return;
        }

        Schema::table('ordencompra_comprobante', function (Blueprint $table) {
            $table->string('estado', 20)->default('PENDIENTE')->after('condicionpago_id');
            $table->index('estado', 'ordencompra_comprobante_estado_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordencompra_comprobante')) {
            return;
        }
        if (! Schema::hasColumn('ordencompra_comprobante', 'estado')) {
            return;
        }

        Schema::table('ordencompra_comprobante', function (Blueprint $table) {
            $table->dropIndex('ordencompra_comprobante_estado_idx');
            $table->dropColumn('estado');
        });
    }
};
