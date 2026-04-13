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
            array('id' => '2011', 'nombre' => 'Ingresar orden de produccion', 'slug' => 'crear-orden-produccion', 'menu_id' => 200, 'created_at' => $now, 'updated_at' => $now),
            array('id' => '2012', 'nombre' => 'Listar orden de produccion', 'slug' => 'listar-orden-produccion', 'menu_id' => 200, 'created_at' => $now, 'updated_at' => $now),
            array('id' => '2013', 'nombre' => 'Editar orden de produccion', 'slug' => 'editar-orden-produccion', 'menu_id' => 200, 'created_at' => $now, 'updated_at' => $now),
            array('id' => '2014', 'nombre' => 'Actualizar orden de produccion', 'slug' => 'actualizar-orden-produccion', 'menu_id' => 200, 'created_at' => $now, 'updated_at' => $now),
            array('id' => '2015', 'nombre' => 'Borrar orden de produccion', 'slug' => 'borrar-orden-produccion', 'menu_id' => 200, 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
