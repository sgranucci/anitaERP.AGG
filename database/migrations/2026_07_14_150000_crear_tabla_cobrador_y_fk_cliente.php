<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cobrador')) {
            Schema::create('cobrador', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nombre', 30);
                $table->float('comision')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->foreign('empresa_id', 'fk_cobrador_empresa')
                    ->references('id')->on('empresa')
                    ->onDelete('set null')->onUpdate('cascade');
                $table->unsignedBigInteger('legajo_id')->nullable();
                $table->string('codigo', 50);
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';

                $table->unique('codigo');
                $table->index('nombre');
            });
        }

        if (Schema::hasTable('cliente') && ! Schema::hasColumn('cliente', 'cobrador_id')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->unsignedBigInteger('cobrador_id')->nullable()->after('vendedor_id');
                $table->foreign('cobrador_id', 'fk_cliente_cobrador')
                    ->references('id')->on('cobrador')
                    ->onDelete('set null')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cliente') && Schema::hasColumn('cliente', 'cobrador_id')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->dropForeign('fk_cliente_cobrador');
                $table->dropColumn('cobrador_id');
            });
        }

        Schema::dropIfExists('cobrador');
    }
};
