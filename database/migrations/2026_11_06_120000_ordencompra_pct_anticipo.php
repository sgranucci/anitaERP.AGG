<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }
        if (Schema::hasColumn('ordencompra', 'pct_anticipo')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            $table->decimal('pct_anticipo', 5, 2)->nullable()->after('tratamiento');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }
        if (! Schema::hasColumn('ordencompra', 'pct_anticipo')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            $table->dropColumn('pct_anticipo');
        });
    }
};
