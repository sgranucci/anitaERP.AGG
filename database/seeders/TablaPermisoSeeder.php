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
            array('id' => '1020', 'nombre' => 'Listar requisiciones', 'slug' => 'listar-requisicion', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1021', 'nombre' => 'Ingresar requisiciones', 'slug' => 'crear-requisicion', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1022', 'nombre' => 'Editar requisiciones', 'slug' => 'editar-requisicion', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1023', 'nombre' => 'Actualizar requisiciones', 'slug' => 'actualizar-requisicion', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1024', 'nombre' => 'Borrar requisiciones', 'slug' => 'borrar-requisicion', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1025', 'nombre' => 'Usuario requisiciones compras', 'slug' => 'usuario-requisicion-compras', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1026', 'nombre' => 'Usuario requisiciones resto sectores', 'slug' => 'usuario-requisicion-resto', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
