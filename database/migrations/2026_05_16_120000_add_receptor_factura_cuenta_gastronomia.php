<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            $table->string('factura_receptor_nombre', 255)->nullable()->after('cliente_id');
            $table->string('factura_receptor_documento', 30)->nullable()->after('factura_receptor_nombre');
            $table->string('factura_receptor_domicilio', 255)->nullable()->after('factura_receptor_documento');
            $table->unsignedBigInteger('factura_receptor_tipodocumento_id')->nullable()->after('factura_receptor_domicilio');
            $table->foreign('factura_receptor_tipodocumento_id', 'fk_cuenta_gastro_factura_tipodoc')
                ->references('id')->on('tipodocumento')
                ->onDelete('set null')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            $table->dropForeign('fk_cuenta_gastro_factura_tipodoc');
            $table->dropColumn([
                'factura_receptor_nombre',
                'factura_receptor_documento',
                'factura_receptor_domicilio',
                'factura_receptor_tipodocumento_id',
            ]);
        });
    }
};
