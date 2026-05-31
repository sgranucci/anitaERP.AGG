<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uif_nombre_sexo_aprendizaje', function (Blueprint $table) {
            $table->string('token', 64)->primary();
            $table->unsignedInteger('cnt_masculino')->default(0);
            $table->unsignedInteger('cnt_femenino')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uif_nombre_sexo_aprendizaje');
    }
};
