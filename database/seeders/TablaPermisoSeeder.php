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
            ['id' => '1050', 'nombre' => 'Listar listas de precio de proveedores', 'slug' => 'listar-listaprecio-proveedor', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1051', 'nombre' => 'Ingresar listas de precio de proveedores', 'slug' => 'crear-listaprecio-proveedor', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1052', 'nombre' => 'Editar listas de precio de proveedores', 'slug' => 'editar-listaprecio-proveedor', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1053', 'nombre' => 'Actualizar listas de precio de proveedores', 'slug' => 'actualizar-listaprecio-proveedor', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1054', 'nombre' => 'Borrar listas de precio de proveedores', 'slug' => 'borrar-listaprecio-proveedor', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('permiso')->insert($permiso);
    }
}
