<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TablaPermisoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now()->toDateTimeString();
        $permiso = [
            array('id' => '970', 'nombre' => 'Ingresar partidas de gastos', 'slug' => 'crear-partidagasto', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '971', 'nombre' => 'Listar partidas de gastos', 'slug' => 'listar-partidagasto', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '972', 'nombre' => 'Editar partidas de gastos', 'slug' => 'editar-partidagasto', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '973', 'nombre' => 'Actualizar partidas de gastos', 'slug' => 'actualizar-partidagasto', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '974', 'nombre' => 'Borrar partidas de gastos', 'slug' => 'borrar-partidagasto', 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
