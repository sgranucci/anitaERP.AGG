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
            array('id' => '1015', 'nombre' => 'Ingresar encuestas', 'slug' => 'crear-encuesta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1016', 'nombre' => 'Listar encuestas', 'slug' => 'listar-encuesta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1017', 'nombre' => 'Editar encuestas', 'slug' => 'editar-encuesta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1018', 'nombre' => 'Actualizar encuestas', 'slug' => 'actualizar-encuesta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1019', 'nombre' => 'Borrar encuestas', 'slug' => 'borrar-encuesta', 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
