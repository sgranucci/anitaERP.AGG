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
            ['id' => '2031', 'nombre' => 'Ingresar sector legajo compra', 'slug' => 'crear-sector-legajocompra', 'menu_id' => 228, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2032', 'nombre' => 'Listar sector legajo compra', 'slug' => 'listar-sector-legajocompra', 'menu_id' => 228, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2033', 'nombre' => 'Editar sector legajo compra', 'slug' => 'editar-sector-legajocompra', 'menu_id' => 228, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2034', 'nombre' => 'Actualizar sector legajo compra', 'slug' => 'actualizar-sector-legajocompra', 'menu_id' => 228, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '2035', 'nombre' => 'Borrar sector legajo compra', 'slug' => 'borrar-sector-legajocompra', 'menu_id' => 228, 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('permiso')->insert($permiso);
    }
}
