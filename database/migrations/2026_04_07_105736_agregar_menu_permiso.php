<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('permiso', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_id')->after('slug')->nullable();
            $table->foreign('menu_id', 'fk_permiso_menu')->references('id')->on('menu')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permiso', function (Blueprint $table) {
            $table->dropForeign('fk_permiso_menu');
            $table->dropColumn('menu_id');
        });
    }
};
