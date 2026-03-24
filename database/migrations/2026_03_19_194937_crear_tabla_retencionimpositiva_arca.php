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
        Schema::create('retencionimpositiva_arca', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_retencionimpositiva_arca_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->string('cuit', 255);
            $table->string('nombre', 255);
            $table->string('impuesto', 50);
            $table->string('descripcionimpuesto', 255);
            $table->string('regimen', 50);
            $table->string('descripcionregimen', 255);
            $table->date('fecharetencion');
            $table->string('numerocertificado', 50);
            $table->string('descripcionoperacion', 255);
            $table->decimal('montoretencion', 22, 4);
            $table->string('numerocomprobante', 50);
            $table->date('fechacomprobante');
            $table->string('descripcioncomprobante', 255);
            $table->date('fecharegistracion');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retencionimpositiva_arca');
    }
};
