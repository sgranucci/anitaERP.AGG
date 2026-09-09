<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota libre del legajo de compras (bandeja): visible y editable desde herramientas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra', 'nota_legajo')) {
                $table->text('nota_legajo')->nullable()
                    ->after('comentario')
                    ->comment('Nota interna del legajo (bandeja de compras)');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (Schema::hasColumn('ordencompra', 'nota_legajo')) {
                $table->dropColumn('nota_legajo');
            }
        });
    }
};
