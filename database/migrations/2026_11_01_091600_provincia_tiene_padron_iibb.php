<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La provincia publica padrón IIBB (carpeta de bajada y mail de vigencia).
 * Ser agente no implica que el fisco publique padrón.
 *
 * El alta inicial marca las jurisdicciones que el ERP ya sabe importar.
 * Después manda el tilde de Configuración general.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PUBLICAN = ['901', '902', '904', '908', '914', '921', '924'];

    public function up(): void
    {
        if (! Schema::hasTable('provincia')) {
            return;
        }
        if (Schema::hasColumn('provincia', 'tiene_padron')) {
            return;
        }

        Schema::table('provincia', function (Blueprint $table) {
            $table->boolean('tiene_padron')->default(false)->after('jurisdiccion');
        });

        DB::table('provincia')
            ->whereIn('jurisdiccion', self::PUBLICAN)
            ->update(['tiene_padron' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('provincia')) {
            return;
        }
        if (! Schema::hasColumn('provincia', 'tiene_padron')) {
            return;
        }

        Schema::table('provincia', function (Blueprint $table) {
            $table->dropColumn('tiene_padron');
        });
    }
};
