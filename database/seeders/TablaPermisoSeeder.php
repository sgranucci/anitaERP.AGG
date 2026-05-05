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
            array('id' => '1040', 'nombre' => 'Listar saldo de cuentas interbanking', 'slug' => 'listar-saldo-cuenta-interbanking', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now),
            array('id' => '1041', 'nombre' => 'Ver movimientos de cuenta interbanking', 'slug' => 'ver-movimientos-cuenta-interbanking', 'menu_id' => 225, 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('permiso')->insert($permiso);
    }
}
