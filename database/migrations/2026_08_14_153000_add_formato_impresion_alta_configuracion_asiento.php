<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracion_asiento_contable')) {
            return;
        }

        if (! Schema::hasColumn('configuracion_asiento_contable', 'formato_impresion_alta')) {
            Schema::table('configuracion_asiento_contable', function (Blueprint $table) {
                $table->string('formato_impresion_alta', 10)
                    ->default('excel')
                    ->after('mail_texto_aprobacion')
                    ->comment('pdf | excel | ninguno — salida automática al dar de alta un asiento');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuracion_asiento_contable')) {
            return;
        }

        if (Schema::hasColumn('configuracion_asiento_contable', 'formato_impresion_alta')) {
            Schema::table('configuracion_asiento_contable', function (Blueprint $table) {
                $table->dropColumn('formato_impresion_alta');
            });
        }
    }
};
