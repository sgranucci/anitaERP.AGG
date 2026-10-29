<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El listado busca la OT por número. Sin índice, codigo obliga a recorrer la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('ordentrabajo', 'idx_ordentrabajo_codigo')) {
            return;
        }

        Schema::table('ordentrabajo', function (Blueprint $table) {
            $table->index('codigo', 'idx_ordentrabajo_codigo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('ordentrabajo', 'idx_ordentrabajo_codigo')) {
            return;
        }

        Schema::table('ordentrabajo', function (Blueprint $table) {
            $table->dropIndex('idx_ordentrabajo_codigo');
        });
    }
};
