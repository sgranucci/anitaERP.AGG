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
            array('id' => '980', 'nombre' => 'Ingresar modelos de etiquetas', 'slug' => 'crear-modeloetiqueta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '981', 'nombre' => 'Listar modelos de etiquetas', 'slug' => 'listar-modeloetiqueta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '982', 'nombre' => 'Editar modelos de etiquetas', 'slug' => 'editar-modeloetiqueta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '983', 'nombre' => 'Actualizar modelos de etiquetas', 'slug' => 'actualizar-modeloetiqueta', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '984', 'nombre' => 'Borrar modelos de etiquetas', 'slug' => 'borrar-modeloetiqueta', 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
