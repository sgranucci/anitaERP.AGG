<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (! Schema::hasColumn('cheque', 'fecha_acreditacion')) {
                $table->date('fecha_acreditacion')->nullable()->after('nro_boleta_deposito');
            }
            if (! Schema::hasColumn('cheque', 'nro_caucion')) {
                $table->string('nro_caucion', 20)->nullable()->after('fecha_acreditacion');
            }
            if (! Schema::hasColumn('cheque', 'fecha_caucion')) {
                $table->date('fecha_caucion')->nullable()->after('nro_caucion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            foreach (['fecha_caucion', 'nro_caucion', 'fecha_acreditacion'] as $col) {
                if (Schema::hasColumn('cheque', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
