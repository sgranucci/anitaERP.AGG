<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor', 'impuesto_interno')) {
                $table->decimal('impuesto_interno', 15, 2)->nullable()->after('observacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor', 'impuesto_interno')) {
                $table->dropColumn('impuesto_interno');
            }
        });
    }
};
