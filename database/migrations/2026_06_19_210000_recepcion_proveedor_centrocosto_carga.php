<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor', 'centrocosto_id')) {
                $table->unsignedBigInteger('centrocosto_id')->nullable()->after('creousuario_id');
                $table->foreign('centrocosto_id', 'fk_recepcion_proveedor_centrocosto_carga')
                    ->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
            }
        });

        DB::statement('
            UPDATE recepcion_proveedor rp
            INNER JOIN usuario u ON u.id = rp.creousuario_id
            SET rp.centrocosto_id = u.centrocosto_id
            WHERE rp.centrocosto_id IS NULL AND u.centrocosto_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor', 'centrocosto_id')) {
                $table->dropForeign('fk_recepcion_proveedor_centrocosto_carga');
                $table->dropColumn('centrocosto_id');
            }
        });
    }
};
