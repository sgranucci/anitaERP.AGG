<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos que Anita no graba en transmae: los arma l-estboveda.c al leer rendmbill.
 * APER es el arqueo del día anterior (origen A) tomado como saldo inicial.
 * HAB y REN son el origen C: la habilitación sale y la rendición entra.
 * Quedan inactivos para no ofrecerlos como movimiento manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            return;
        }

        $salas = DB::table('sala')->whereRaw('UPPER(codigo) <> ?', ['AC'])->pluck('id');
        $ahora = now();
        foreach ($salas as $salaId) {
            foreach ($this->tipos() as $fila) {
                $existe = DB::table('tesoreria_crown_boveda_tipo')
                    ->where('sala_id', $salaId)
                    ->where('codigo', $fila['codigo'])
                    ->exists();
                if ($existe) {
                    continue;
                }
                DB::table('tesoreria_crown_boveda_tipo')->insert($fila + [
                    'sala_id' => $salaId,
                    'activo' => false,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            return;
        }

        DB::table('tesoreria_crown_boveda_tipo')
            ->whereIn('codigo', [100, 101, 102])
            ->delete();
    }

    /**
     * @return list<array{codigo: int, abreviatura: string, nombre: string, signo: string, operacion: string}>
     */
    private function tipos(): array
    {
        return [
            ['codigo' => 100, 'abreviatura' => 'APER', 'nombre' => 'Apertura de boveda', 'signo' => '0', 'operacion' => '0'],
            ['codigo' => 101, 'abreviatura' => 'HAB', 'nombre' => 'Habilitacion de cajero', 'signo' => '1', 'operacion' => '0'],
            ['codigo' => 102, 'abreviatura' => 'REN', 'nombre' => 'Rendicion de cajero', 'signo' => '0', 'operacion' => '0'],
        ];
    }
};
