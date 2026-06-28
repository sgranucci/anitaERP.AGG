<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maquinavending_articulo', function (Blueprint $table) {
            $table->decimal('precio_lista', 15, 2)->nullable()->after('articulo_id');
        });
    }

    public function down(): void
    {
        Schema::table('maquinavending_articulo', function (Blueprint $table) {
            $table->dropColumn('precio_lista');
        });
    }
};
