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
            array('id' => '1001', 'nombre' => 'Ingresar feriados', 'slug' => 'crear-feriado', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1002', 'nombre' => 'Listar feriados', 'slug' => 'listar-feriado', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1003', 'nombre' => 'Editar feriados', 'slug' => 'editar-feriado', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1004', 'nombre' => 'Actualizar feriados', 'slug' => 'actualizar-feriado', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1005', 'nombre' => 'Borrar feriados', 'slug' => 'borrar-feriado', 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
