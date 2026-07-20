<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convierte el sexo de la dotación a texto ('M'/'F') como en el resto de anitaERP.
 * Datos previos venían como '1'/'2' (mapeo Anita del sync inicial).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('prenda_agrupamiento_sueldos')->where('sexo', '1')->update(['sexo' => 'M']);
        DB::table('prenda_agrupamiento_sueldos')->where('sexo', '2')->update(['sexo' => 'F']);
    }

    public function down(): void
    {
        DB::table('prenda_agrupamiento_sueldos')->where('sexo', 'M')->update(['sexo' => '1']);
        DB::table('prenda_agrupamiento_sueldos')->where('sexo', 'F')->update(['sexo' => '2']);
    }
};
