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
            array('id' => '1010', 'nombre' => 'Ingresar tipo de servicio de proveedores', 'slug' => 'crear-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1011', 'nombre' => 'Listar tipo de servicio de proveedores', 'slug' => 'listar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1012', 'nombre' => 'Editar tipo de servicio de proveedores', 'slug' => 'editar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1013', 'nombre' => 'Actualizar tipo de servicio de proveedores', 'slug' => 'actualizar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1014', 'nombre' => 'Borrar tipo de servicio de proveedores', 'slug' => 'borrar-tipo-servicio-proveedor', 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
