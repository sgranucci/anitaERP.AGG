<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencias de medio CHT del proveedor: cheque físico y/o e-cheq.
 * Default true en ambos para no cambiar el comportamiento de programas ya armados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('proveedor', 'acepta_cheque_fisico')) {
                $table->boolean('acepta_cheque_fisico')->default(true)->after('condicionpago_id');
            }
            if (! Schema::hasColumn('proveedor', 'acepta_echeq')) {
                $table->boolean('acepta_echeq')->default(true)->after('acepta_cheque_fisico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('proveedor', 'acepta_echeq')) {
                $table->dropColumn('acepta_echeq');
            }
            if (Schema::hasColumn('proveedor', 'acepta_cheque_fisico')) {
                $table->dropColumn('acepta_cheque_fisico');
            }
        });
    }
};
