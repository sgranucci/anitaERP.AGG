<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precio', function (Blueprint $table) {
            $table->index(
                ['articulo_id', 'listaprecio_id', 'fechavigencia', 'id'],
                'idx_precio_articulo_lista_fecha_id'
            );
        });
    }

    public function down(): void
    {
        Schema::table('precio', function (Blueprint $table) {
            $table->dropIndex('idx_precio_articulo_lista_fecha_id');
        });
    }
};
