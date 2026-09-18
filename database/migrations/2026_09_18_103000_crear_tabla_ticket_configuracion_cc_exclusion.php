<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_configuracion_cc_exclusion')) {
            Schema::create('ticket_configuracion_cc_exclusion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('usuario_id');
                $table->timestamps();

                $table->unique('usuario_id', 'uk_ticket_cfg_cc_excl_usuario');
                $table->foreign('usuario_id', 'fk_ticket_cfg_cc_excl_usuario')
                    ->references('id')
                    ->on('usuario')
                    ->cascadeOnDelete();

                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        $usuarioId = (int) (DB::table('usuario')->where('usuario', 'mbmendez')->value('id') ?: 0);
        if ($usuarioId > 0) {
            $ya = DB::table('ticket_configuracion_cc_exclusion')
                ->where('usuario_id', $usuarioId)
                ->exists();
            if (! $ya) {
                $ahora = now();
                DB::table('ticket_configuracion_cc_exclusion')->insert([
                    'usuario_id' => $usuarioId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_configuracion_cc_exclusion');
    }
};
