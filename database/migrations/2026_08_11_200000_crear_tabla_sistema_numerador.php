<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numeradores de documento del ERP (reemplazo futuro de Anita ventas.numerador).
 * Semillas iniciales caja OPP/EGR/ING/TRA por empresa, alineadas a Anita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sistema_numerador', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 80);
            $table->string('nombre', 120);
            $table->unsignedBigInteger('empresa_id');
            $table->string('modulo', 40)->default('caja');
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->string('anita_sistema', 30)->nullable();
            $table->string('anita_fuente', 20)->nullable();
            $table->string('anita_clave', 40)->nullable();
            $table->boolean('activo')->default(true);
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresa')->restrictOnDelete();
            $table->unique(['codigo', 'empresa_id'], 'sistema_numerador_codigo_empresa_unique');
            $table->index(['modulo', 'empresa_id']);
        });

        $semillas = [
            // codigo, nombre, emp => [clave Anita, ultimo leído si falla bridge]
            'caja.OPP' => ['Orden de pago (OPP)', [
                1 => ['223', 124355],
                2 => ['224', 57513],
                3 => ['225', 68224],
            ]],
            'caja.EGR' => ['Egresos de caja (EGR)', [
                1 => ['361', 15129],
                2 => ['362', 10212],
                3 => ['363', 10246],
            ]],
            'caja.ING' => ['Ingresos de caja (ING)', [
                1 => ['346', 89806],
                2 => ['347', 43037],
                3 => ['348', 54380],
            ]],
            'caja.TRA' => ['Transferencias de caja (TRA)', [
                1 => ['334', 3930],
                2 => ['335', 2288],
                3 => ['336', 2920],
            ]],
        ];

        $ahora = now();
        $filas = [];
        foreach ($semillas as $codigo => [$nombre, $porEmpresa]) {
            foreach ($porEmpresa as $empresaId => [$clave, $ultimoFallback]) {
                if (! DB::table('empresa')->where('id', $empresaId)->exists()) {
                    continue;
                }
                $ultimo = $this->leerUltimoAnita((string) $clave) ?? (int) $ultimoFallback;
                $filas[] = [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'empresa_id' => $empresaId,
                    'modulo' => 'caja',
                    'ultimo_numero' => $ultimo,
                    'anita_sistema' => 'ventas',
                    'anita_fuente' => 'numerador',
                    'anita_clave' => (string) $clave,
                    'activo' => true,
                    'observacion' => 'Semilla alineada a Anita num_clave '.$clave,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        if ($filas !== []) {
            DB::table('sistema_numerador')->insert($filas);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sistema_numerador');
    }

    private function leerUltimoAnita(string $clave): ?int
    {
        try {
            if (! class_exists(\App\ApiAnita::class)) {
                return null;
            }
            $api = new \App\ApiAnita;
            $raw = $api->apiCallEscritura([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'numerador',
                'campos' => 'num_ult_numero',
                'whereArmado' => " WHERE num_clave = '".str_replace("'", "''", $clave)."'",
            ], 'seed sistema_numerador '.$clave);
            $err = \App\ApiAnita::extraerMensajeError($raw);
            if ($err !== null) {
                return null;
            }
            $fila = \App\ApiAnita::primeraFilaLista((string) $raw);
            if ($fila === null || ! isset($fila->num_ult_numero)) {
                return null;
            }

            return max(0, (int) $fila->num_ult_numero);
        } catch (\Throwable $e) {
            return null;
        }
    }
};
