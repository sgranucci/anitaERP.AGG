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
            array('id' => '896', 'nombre' => 'Ingresa padron iibb', 'slug' => 'crear-padron-iibb', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '897', 'nombre' => 'Lista padron iibb', 'slug' => 'listar-padron-iibb', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '898', 'nombre' => 'Edita padron iibb', 'slug' => 'editar-padron-iibb', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '899', 'nombre' => 'Actualiza padron iibb', 'slug' => 'actualizar-padron-iibb', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '900', 'nombre' => 'Borra padron iibb', 'slug' => 'borrar-padron-iibb', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '901', 'nombre' => 'Importar padron iibb', 'slug' => 'importar-padron-iibb', 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
