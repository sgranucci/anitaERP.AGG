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
            array('id' => '1006', 'nombre' => 'Editar articulos', 'slug' => 'editar-articulos', 'menu_id' => 10,'created_at' => $now, 'updated_at' => $now),
            array('id' => '1007', 'nombre' => 'Actualizar articulos', 'slug' => 'actualizar-articulos', 'menu_id' => 10,'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
