<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría y dedupe de la ingesta de facturas por correo
 * (compras:ingestar-facturas-mail). Una fila por adjunto PDF procesado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('precarga_comprobante_mail_mensaje')) {
            return;
        }

        Schema::create('precarga_comprobante_mail_mensaje', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 255);
            $table->string('uid', 50)->nullable();
            $table->string('carpeta', 100)->nullable();
            $table->string('remitente', 255)->nullable();
            $table->string('asunto', 500)->nullable();
            $table->dateTime('fecha_mensaje')->nullable();
            $table->string('adjunto_nombre', 255)->nullable();
            $table->string('adjunto_hash', 64);
            $table->string('numero_oc', 10)->nullable();
            $table->string('estado', 20)->default('PROCESADO'); // PROCESADO | ERROR | IGNORADO
            $table->unsignedBigInteger('precarga_id')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'adjunto_hash'], 'uk_mail_mensaje_adjunto');
            $table->index('estado', 'ix_mail_mensaje_estado');
            $table->index('fecha_mensaje', 'ix_mail_mensaje_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precarga_comprobante_mail_mensaje');
    }
};
