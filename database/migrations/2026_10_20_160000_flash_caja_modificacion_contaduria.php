<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flash_caja_modificacion')) {
            Schema::create('flash_caja_modificacion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flash_caja_id');
                $table->string('estado', 20);
                $table->text('motivo');
                $table->unsignedBigInteger('solicitante_usuario_id');
                $table->dateTime('solicitada_en');
                $table->unsignedBigInteger('habilitada_usuario_id')->nullable();
                $table->dateTime('habilitada_en')->nullable();
                $table->dateTime('usada_en')->nullable();
                $table->timestamps();

                $table->index(['flash_caja_id', 'estado']);
                $table->foreign('flash_caja_id', 'fk_flash_mod_flash')
                    ->references('id')->on('flash_caja')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('flash_caja_modificacion_destinatario')) {
            Schema::create('flash_caja_modificacion_destinatario', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('usuario_id')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('usuario')) {
            return;
        }

        $ahora = now();
        foreach (['favila', 'eguevara'] as $login) {
            $usuarioId = (int) (DB::table('usuario')
                ->where('usuario', $login)
                ->where('suspendido', false)
                ->value('id') ?? 0);
            if ($usuarioId <= 0) {
                continue;
            }
            $existe = DB::table('flash_caja_modificacion_destinatario')
                ->where('usuario_id', $usuarioId)
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('flash_caja_modificacion_destinatario')->insert([
                'usuario_id' => $usuarioId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_caja_modificacion');
        Schema::dropIfExists('flash_caja_modificacion_destinatario');
    }
};
