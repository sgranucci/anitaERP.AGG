<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad del envío del comprobante de entrega a TuLegajo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrega_prenda_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('entrega_prenda_sueldos', 'tulegajo_estado')) {
                $table->string('tulegajo_estado', 20)->nullable()->after('origen_anita_id');
            }
            if (! Schema::hasColumn('entrega_prenda_sueldos', 'tulegajo_enviado_at')) {
                $table->timestamp('tulegajo_enviado_at')->nullable()->after('tulegajo_estado');
            }
            if (! Schema::hasColumn('entrega_prenda_sueldos', 'tulegajo_mensaje')) {
                $table->string('tulegajo_mensaje', 255)->nullable()->after('tulegajo_enviado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entrega_prenda_sueldos', function (Blueprint $table) {
            foreach (['tulegajo_mensaje', 'tulegajo_enviado_at', 'tulegajo_estado'] as $col) {
                if (Schema::hasColumn('entrega_prenda_sueldos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
