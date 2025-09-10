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
            array('id' => '3076', 'nombre' => 'Ingresa cliente uif', 'slug' => 'crea-cliente-uif', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '3077', 'nombre' => 'Lista cliente uif', 'slug' => 'lista-cliente-uif', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '3078', 'nombre' => 'Edita cliente uif', 'slug' => 'edita-cliente-uif', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '3079', 'nombre' => 'Actualiza cliente uif', 'slug' => 'actualiza-cliente-uif', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '3080', 'nombre' => 'Borra cliente uif', 'slug' => 'borra-cliente-uif', 'created_at' => $now, 'updated_at' => $now),

        ];
        DB::table('permiso')->insert($permiso);
    }
}
