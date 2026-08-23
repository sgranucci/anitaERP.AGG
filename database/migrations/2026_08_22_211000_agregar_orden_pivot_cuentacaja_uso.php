<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orden de posición financiera por uso (la misma cuenta puede ir en otro lugar
 * en gastronomía y en máquinas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuentacaja_usocuentacaja')) {
            return;
        }

        if (! Schema::hasColumn('cuentacaja_usocuentacaja', 'orden')) {
            Schema::table('cuentacaja_usocuentacaja', function (Blueprint $table) {
                $table->unsignedInteger('orden')->default(0);
            });
        }

        $this->sembrarOrdenPorUso();
    }

    public function down(): void
    {
        if (Schema::hasTable('cuentacaja_usocuentacaja') && Schema::hasColumn('cuentacaja_usocuentacaja', 'orden')) {
            Schema::table('cuentacaja_usocuentacaja', function (Blueprint $table) {
                $table->dropColumn('orden');
            });
        }
    }

    private function sembrarOrdenPorUso(): void
    {
        $usoIds = DB::table('usocuentacaja')->pluck('id', 'nombre');
        $gastroId = (int) ($usoIds['Gastronomia'] ?? 0);
        $estacId = (int) ($usoIds['Estacionamiento'] ?? 0);
        $maqId = (int) ($usoIds['Rendición de máquinas'] ?? 0);

        $ordenGastro = [
            '113010' => 10,
            '11301012' => 20,
            '100' => 30,
            '200' => 30,
            '300' => 30,
            'CTG' => 40,
            'GMEP' => 50,
        ];
        $ordenMaquina = [
            '100' => 10,
            '200' => 10,
            '300' => 10,
            '121' => 20,
            '221' => 20,
            '321' => 20,
            '122' => 30,
            '222' => 30,
            '322' => 30,
            '11105033' => 40,
            'MMEP' => 50,
            'M0QR' => 60,
            '11301012' => 70,
            '11301011' => 80,
            '25' => 90,
            '1112' => 100,
            '2112' => 100,
            '3112' => 100,
        ];

        $this->aplicarOrdenUso($gastroId, $ordenGastro);
        $this->aplicarOrdenUso($estacId, $ordenGastro);
        $this->aplicarOrdenUso($maqId, $ordenMaquina);
    }

    /**
     * @param  array<string, int>  $porCodigo
     */
    private function aplicarOrdenUso(int $usoId, array $porCodigo): void
    {
        if ($usoId <= 0) {
            return;
        }

        foreach ($porCodigo as $codigo => $orden) {
            $cuentaIds = DB::table('cuentacaja')->where('codigo', $codigo)->pluck('id');
            foreach ($cuentaIds as $cuentaId) {
                DB::table('cuentacaja_usocuentacaja')
                    ->where('cuentacaja_id', (int) $cuentaId)
                    ->where('usocuentacaja_id', $usoId)
                    ->update(['orden' => $orden]);
            }
        }
    }
};
