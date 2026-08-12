<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unidades de medida Anita Surmar (stkumd en /usr2/surmar).
 * Distintas de unidadmedida ERP/El Bierzo (ids y códigos no coinciden).
 * Solo EL BIERZO.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        if (Schema::hasTable('unidadmedida_surmar')) {
            return;
        }

        Schema::create('unidadmedida_surmar', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary()->comment('stkum_umd Anita Surmar');
            $table->string('abreviatura', 10);
            $table->string('nombre', 60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        Schema::dropIfExists('unidadmedida_surmar');
    }

    private function esEntornoSurmar(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }
};
