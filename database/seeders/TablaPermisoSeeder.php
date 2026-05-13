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
            ['id' => '2111', 'nombre' => 'Listar ordenes de compra', 'slug' => 'listar-ordencompra', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2112', 'nombre' => 'Ingresar ordenes de compra', 'slug' => 'crear-ordencompra', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2113', 'nombre' => 'Editar ordenes de compra', 'slug' => 'editar-ordencompra', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2114', 'nombre' => 'Actualizar ordenes de compra', 'slug' => 'actualizar-ordencompra', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2115', 'nombre' => 'Borrar ordenes de compra', 'slug' => 'borrar-ordencompra', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('permiso')->insert($permiso);
    }
}
