<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $listaprecioId = (int) config('precio.listaprecio_default_id', 2);
        if ($listaprecioId <= 0) {
            $listaprecioId = (int) (DB::table('listaprecio')->where('nombre', 'like', 'lista 1')->value('id') ?? 2);
        }

        if (! Schema::hasColumn('maquinavending', 'listaprecio_id')) {
            Schema::table('maquinavending', function (Blueprint $table) use ($listaprecioId) {
                $table->unsignedBigInteger('listaprecio_id')->default($listaprecioId)->after('deposito_id');
            });
        }

        DB::table('maquinavending')->update(['listaprecio_id' => $listaprecioId]);

        $fkExists = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'maquinavending'
             AND CONSTRAINT_NAME = 'fk_maquinavending_listaprecio' AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        ))->isNotEmpty();

        if (! $fkExists) {
            Schema::table('maquinavending', function (Blueprint $table) {
                $table->foreign('listaprecio_id', 'fk_maquinavending_listaprecio')
                    ->references('id')->on('listaprecio')
                    ->onDelete('restrict')->onUpdate('restrict');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('maquinavending', 'listaprecio_id')) {
            Schema::table('maquinavending', function (Blueprint $table) {
                $table->dropForeign('fk_maquinavending_listaprecio');
                $table->dropColumn('listaprecio_id');
            });
        }
    }
};
