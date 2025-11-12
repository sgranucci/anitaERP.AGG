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
            array('id' => '890', 'nombre' => 'Ingresa padron exclusion percepcion iva', 'slug' => 'crear-padron-exclusion-percepcion-iva', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '891', 'nombre' => 'Lista padron exclusion percepcion iva', 'slug' => 'listar-padron-exclusion-percepcion-iva', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '892', 'nombre' => 'Edita padron exclusion percepcion iva', 'slug' => 'editar-padron-exclusion-percepcion-iva', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '893', 'nombre' => 'Actualiza padron exclusion percepcion iva', 'slug' => 'actualizar-padron-exclusion-percepcion-iva', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '894', 'nombre' => 'Borra padron exclusion percepcion iva', 'slug' => 'borrar-padron-exclusion-percepcion-iva', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '895', 'nombre' => 'Importar padron exclusion percepcion iva', 'slug' => 'importar-padron-exclusion-percepcion-iva', 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
