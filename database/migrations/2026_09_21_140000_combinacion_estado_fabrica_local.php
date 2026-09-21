<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estados operativos por canal en combinacion (fábrica / local).
 * Valores A/I alineados a combinacion.estado y Anita comb_estado.
 * Sin SoftDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('combinacion')) {
            return;
        }

        Schema::table('combinacion', function (Blueprint $table) {
            if (! Schema::hasColumn('combinacion', 'estado_fabrica')) {
                $table->string('estado_fabrica', 1)->nullable()->after('estado');
            }
            if (! Schema::hasColumn('combinacion', 'estado_local')) {
                $table->string('estado_local', 1)->nullable()->after('estado_fabrica');
            }
        });

        $expr = "CASE WHEN UPPER(COALESCE(estado, '')) = 'I' THEN 'I' ELSE 'A' END";

        DB::table('combinacion')
            ->where(function ($q) {
                $q->whereNull('estado_fabrica')->orWhere('estado_fabrica', '');
            })
            ->update(['estado_fabrica' => DB::raw($expr)]);

        DB::table('combinacion')
            ->where(function ($q) {
                $q->whereNull('estado_local')->orWhere('estado_local', '');
            })
            ->update(['estado_local' => DB::raw($expr)]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('combinacion')) {
            return;
        }

        Schema::table('combinacion', function (Blueprint $table) {
            if (Schema::hasColumn('combinacion', 'estado_local')) {
                $table->dropColumn('estado_local');
            }
            if (Schema::hasColumn('combinacion', 'estado_fabrica')) {
                $table->dropColumn('estado_fabrica');
            }
        });
    }
};
