<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asiento', function (Blueprint $table) {
            if (! Schema::hasColumn('asiento', 'anita_origen')) {
                $table->string('anita_origen', 16)->nullable()->after('observacion');
            }
            if (! Schema::hasColumn('asiento', 'anita_sistema')) {
                $table->string('anita_sistema', 5)->nullable()->after('anita_origen');
            }
            if (! Schema::hasColumn('asiento', 'anita_tipo')) {
                $table->string('anita_tipo', 10)->nullable()->after('anita_sistema');
            }
            if (! Schema::hasColumn('asiento', 'anita_letra')) {
                $table->string('anita_letra', 3)->nullable()->after('anita_tipo');
            }
            if (! Schema::hasColumn('asiento', 'anita_sucursal')) {
                $table->unsignedInteger('anita_sucursal')->nullable()->after('anita_letra');
            }
            if (! Schema::hasColumn('asiento', 'anita_nro')) {
                $table->unsignedInteger('anita_nro')->nullable()->after('anita_sucursal');
            }
            if (! Schema::hasColumn('asiento', 'anita_emisor')) {
                $table->string('anita_emisor', 60)->nullable()->after('anita_nro');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asiento', function (Blueprint $table) {
            foreach ([
                'anita_emisor',
                'anita_nro',
                'anita_sucursal',
                'anita_letra',
                'anita_tipo',
                'anita_sistema',
                'anita_origen',
            ] as $columna) {
                if (Schema::hasColumn('asiento', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
