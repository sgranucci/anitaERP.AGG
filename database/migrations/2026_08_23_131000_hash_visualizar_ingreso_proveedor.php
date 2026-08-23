<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ingreso_proveedor')) {
            return;
        }
        if (! Schema::hasColumn('ingreso_proveedor', 'hashvisualizar')) {
            Schema::table('ingreso_proveedor', function (Blueprint $table) {
                $table->string('hashvisualizar', 64)->nullable()->after('comentario');
            });
        }
        $ids = DB::table('ingreso_proveedor')
            ->where(function ($q) {
                $q->whereNull('hashvisualizar')->orWhere('hashvisualizar', '');
            })
            ->pluck('id');
        foreach ($ids as $id) {
            DB::table('ingreso_proveedor')->where('id', $id)->update([
                'hashvisualizar' => Str::lower(Str::random(48)),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ingreso_proveedor') && Schema::hasColumn('ingreso_proveedor', 'hashvisualizar')) {
            Schema::table('ingreso_proveedor', function (Blueprint $table) {
                $table->dropColumn('hashvisualizar');
            });
        }
    }
};
