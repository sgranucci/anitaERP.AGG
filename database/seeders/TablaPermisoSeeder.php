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
            array('id' => '960', 'nombre' => 'Ingresar precarga proveedores', 'slug' => 'crear-precarga-proveedores', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '961', 'nombre' => 'Listar precarga proveedores', 'slug' => 'listar-precarga-proveedores', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '962', 'nombre' => 'Editar precarga proveedores', 'slug' => 'editar-precarga-proveedores', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '963', 'nombre' => 'Actualizar precarga proveedores', 'slug' => 'actualizar-precarga-proveedores', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '964', 'nombre' => 'Borrar precarga proveedores', 'slug' => 'borrar-precarga-proveedores', 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
