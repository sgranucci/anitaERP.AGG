<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('precio') || Schema::hasColumn('precio', 'combinacion_id')) {
            return;
        }

        Schema::table('precio', function (Blueprint $table) {
            $table->unsignedBigInteger('combinacion_id')->nullable()->after('articulo_id');
            $table->foreign('combinacion_id', 'fk_precio_combinacion')
                ->references('id')->on('combinacion')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('precio') || ! Schema::hasColumn('precio', 'combinacion_id')) {
            return;
        }

        Schema::table('precio', function (Blueprint $table) {
            $table->dropForeign('fk_precio_combinacion');
            $table->dropColumn('combinacion_id');
        });
    }
};
