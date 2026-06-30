<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOMBRE = 'Baja de impuestos';

    public function up(): void
    {
        $existe = DB::table('tiposuspensioncliente')
            ->where('nombre', self::NOMBRE)
            ->exists();

        if ($existe) {
            return;
        }

        DB::table('tiposuspensioncliente')->insert([
            'nombre' => self::NOMBRE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('tiposuspensioncliente')
            ->where('nombre', self::NOMBRE)
            ->delete();
    }
};
