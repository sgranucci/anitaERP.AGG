<?php

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TablaTalleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now()->toDateTimeString();
        $rols = [
            array('id' => '1', 'nombre' => '16', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '2', 'nombre' => '17', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '3', 'nombre' => '18', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '4', 'nombre' => '19', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '5', 'nombre' => '20', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '6', 'nombre' => '21', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '7', 'nombre' => '22', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '8', 'nombre' => '23', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '9', 'nombre' => '24', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '10', 'nombre' => '25', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '11', 'nombre' => '26', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '12', 'nombre' => '27', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '13', 'nombre' => '28', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '14', 'nombre' => '29', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '15', 'nombre' => '30', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '16', 'nombre' => '31', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '17', 'nombre' => '32', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '18', 'nombre' => '33', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '19', 'nombre' => '34', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '20', 'nombre' => '35', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '21', 'nombre' => '36', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '22', 'nombre' => '37', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '23', 'nombre' => '38', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '24', 'nombre' => '39', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '25', 'nombre' => '40', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '26', 'nombre' => '41', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '27', 'nombre' => '42', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '28', 'nombre' => '43', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '29', 'nombre' => '44', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '30', 'nombre' => '45', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '31', 'nombre' => '46', 'created_at' => $now, 'updated_at' => $now),
            array('id' => '32', 'nombre' => '47', 'created_at' => $now, 'updated_at' => $now),
        ];
        DB::table('talle')->insert($rols);
    }
}
