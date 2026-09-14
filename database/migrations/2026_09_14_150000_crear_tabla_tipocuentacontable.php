<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de tipos de cuenta contable (códigos 1/2/3 de Anita) con tilde imputable.
 * Los filtros de consulta usan este flag, no un código fijo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tipocuentacontable')) {
            Schema::create('tipocuentacontable', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 10)->unique();
                $table->string('nombre', 80);
                $table->boolean('imputable')->default(false);
                $table->unsignedTinyInteger('orden')->default(0);
                $table->timestamps();
            });
        }

        $imputables = $this->codigosImputablesPorUso();

        $filas = [
            ['codigo' => '1', 'nombre' => 'Imputable', 'orden' => 1],
            ['codigo' => '2', 'nombre' => 'Título', 'orden' => 2],
            ['codigo' => '3', 'nombre' => 'Totalizadora', 'orden' => 3],
        ];

        foreach ($filas as $fila) {
            $existente = DB::table('tipocuentacontable')->where('codigo', $fila['codigo'])->first();
            $payload = [
                'nombre' => $fila['nombre'],
                'imputable' => in_array($fila['codigo'], $imputables, true),
                'orden' => $fila['orden'],
                'updated_at' => now(),
            ];
            if ($existente) {
                DB::table('tipocuentacontable')->where('id', $existente->id)->update($payload);
            } else {
                DB::table('tipocuentacontable')->insert(array_merge($payload, [
                    'codigo' => $fila['codigo'],
                    'created_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipocuentacontable');
    }

    /**
     * Marca imputable el tipo más usado en asientos (Ferli: 2; AGG típico: 1).
     * Sin movimientos, queda el código 1 (convención histórica del ERP).
     *
     * @return list<string>
     */
    private function codigosImputablesPorUso(): array
    {
        if (! Schema::hasTable('asiento_movimiento') || ! Schema::hasTable('cuentacontable')) {
            return ['1'];
        }

        $porTipo = DB::table('asiento_movimiento as am')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->select('cc.tipocuenta', DB::raw('COUNT(*) as lineas'))
            ->groupBy('cc.tipocuenta')
            ->pluck('lineas', 'cc.tipocuenta');

        if ($porTipo->isEmpty()) {
            return ['1'];
        }

        $ganador = (string) $porTipo->sortDesc()->keys()->first();
        if ($ganador === '' || $ganador === '3') {
            return ['1'];
        }

        return [$ganador];
    }
};
