<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_aviso', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id');
            $table->string('disparador', 20);
            $table->string('modo', 10);
            $table->text('texto')->nullable();
            $table->string('accion', 40)->nullable();
            $table->date('desdefecha')->nullable();
            $table->date('hastafecha')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->unsignedBigInteger('creousuario_id')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id', 'fk_cli_aviso_cliente')
                ->references('id')->on('cliente')->onDelete('cascade');
            $table->foreign('creousuario_id', 'fk_cli_aviso_usuario')
                ->references('id')->on('usuario')->onDelete('restrict');
            $table->index(['cliente_id', 'disparador', 'desdefecha'], 'ix_cli_aviso_cliente_disp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_aviso');
    }
};
