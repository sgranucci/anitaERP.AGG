<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (! Schema::hasColumn('cheque', 'para_dep')) {
                $table->string('para_dep', 1)->nullable()->after('caracter');
            }
            if (! Schema::hasColumn('cheque', 'negociable')) {
                $table->string('negociable', 1)->nullable()->after('para_dep');
            }
            if (! Schema::hasColumn('cheque', 'nro_echeq')) {
                $table->string('nro_echeq', 50)->nullable()->after('numerocheque');
            }
            if (! Schema::hasColumn('cheque', 'fecha_entrega')) {
                $table->date('fecha_entrega')->nullable()->after('fechapago');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            foreach (['fecha_entrega', 'nro_echeq', 'negociable', 'para_dep'] as $col) {
                if (Schema::hasColumn('cheque', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
