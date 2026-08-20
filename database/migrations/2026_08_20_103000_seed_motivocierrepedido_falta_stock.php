<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NOMBRE = 'Falta Stock';

    public function up(): void
    {
        if (! Schema::hasTable('motivocierrepedido')) {
            return;
        }

        $existe = DB::table('motivocierrepedido')
            ->where('nombre', self::NOMBRE)
            ->exists();

        if ($existe) {
            return;
        }

        $now = now();
        DB::table('motivocierrepedido')->insert([
            'nombre' => self::NOMBRE,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('motivocierrepedido')) {
            return;
        }

        $motivoId = (int) (DB::table('motivocierrepedido')
            ->where('nombre', self::NOMBRE)
            ->value('id') ?? 0);

        if ($motivoId <= 0) {
            return;
        }

        if (Schema::hasTable('pedido_articulo_estado')
            && DB::table('pedido_articulo_estado')->where('motivocierrepedido_id', $motivoId)->exists()) {
            return;
        }

        DB::table('motivocierrepedido')->where('id', $motivoId)->delete();
    }
};
