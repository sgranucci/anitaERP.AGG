<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracion_difcambio_factura_destinatario')) {
            Schema::create('configuracion_difcambio_factura_destinatario', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('usuario_id')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('usuario')) {
            return;
        }

        $ahora = now();
        foreach (['eguevara', 'rfogliatti', 'egalarza'] as $login) {
            $usuarioId = (int) (DB::table('usuario')
                ->where('usuario', $login)
                ->where('suspendido', false)
                ->value('id') ?? 0);
            if ($usuarioId <= 0) {
                continue;
            }
            $existe = DB::table('configuracion_difcambio_factura_destinatario')
                ->where('usuario_id', $usuarioId)
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('configuracion_difcambio_factura_destinatario')->insert([
                'usuario_id' => $usuarioId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_difcambio_factura_destinatario');
    }
};
