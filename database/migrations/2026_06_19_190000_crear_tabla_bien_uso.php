<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bien_uso', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('codigo_inventario')->nullable()->unique('uq_bien_uso_codigo_inventario');
            $table->string('hostname', 255);
            $table->string('ip', 45)->nullable();
            $table->string('modelo', 255)->nullable();
            $table->string('numero_serie', 100)->nullable();
            $table->char('estado', 1)->default('A')->comment('A=activo, I=inactivo');
            $table->char('centro_costo', 1)->comment('S=sistemas, M=maquinas');
            $table->char('tipo_bien', 1)->comment('I=instalaciones, M=maquinas, P=pcs');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('hostname');
            $table->index('estado');
            $table->index('centro_costo');
            $table->index('tipo_bien');
        });

        $now = now();
        $filas = [
            [1078, 'SALA05-KPC', '192.168.20.131', 'Gigabyte', null],
            [1019, 'SALAVIP-RPC', '192.168.40.206', 'ECS', null],
            [722, 'EFLORES-NTBK', '10.20.28.42', 'Latitude 3520', '1CJ1JL3'],
            [1264, 'EDIAZ-NTBK', '10.20.28.18', 'HP 250 G10 Notebook', '1H84280NNB'],
            [1277, 'MRODRIGUEZ-NTBK', '10.20.30.214', 'HP 250 G10 Notebook', '1H85020G09'],
            [1025, 'VFERNANDEZ-NTBK', '10.20.28.206', 'HP 250 G10 Notebook', '1H85020G0B'],
            [1256, 'MMICALETTI-NTBK', '10.20.28.44', 'HP 250 G10 Notebook', '1H85020G0X'],
            [1006, 'OFALQUI-NTBK', '10.20.30.215', 'HP 250 G10 Notebook', '1H85020G12'],
            [949, 'GMAGLIOLO-NTBK', '10.20.29.92', 'HP 250 G10 Notebook', '1H85020G1J'],
            [1096, 'CCARRIZO-NTBK', '10.20.28.121', 'HP 250 G10 Notebook', '1H85020G1R'],
            [1169, 'NTPEREZ-NTBK', '10.20.28.201', 'HP 250 G10 Notebook', '1H85020G1X'],
        ];

        foreach ($filas as [$codigo, $hostname, $ip, $modelo, $serie]) {
            DB::table('bien_uso')->insert([
                'codigo_inventario' => $codigo,
                'hostname' => $hostname,
                'ip' => $ip,
                'modelo' => $modelo,
                'numero_serie' => $serie,
                'estado' => 'A',
                'centro_costo' => 'S',
                'tipo_bien' => 'P',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bien_uso');
    }
};
