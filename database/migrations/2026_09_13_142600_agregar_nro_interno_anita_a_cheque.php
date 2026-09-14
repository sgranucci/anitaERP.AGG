<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (! Schema::hasColumn('cheque', 'nro_interno_anita')) {
                $table->unsignedBigInteger('nro_interno_anita')->nullable()->after('numerocheque');
                $table->unique('nro_interno_anita', 'cheque_nro_interno_anita_uk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cheque', function (Blueprint $table) {
            if (Schema::hasColumn('cheque', 'nro_interno_anita')) {
                $table->dropUnique('cheque_nro_interno_anita_uk');
                $table->dropColumn('nro_interno_anita');
            }
        });
    }
};
