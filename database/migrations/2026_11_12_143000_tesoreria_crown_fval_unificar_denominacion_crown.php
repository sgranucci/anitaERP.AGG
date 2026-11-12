<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: una sola ficha de valor (FVAL) por denominación.
 * Une series duplicadas (ex FCOL / no negociable) a la serie canónica
 * y remapea líneas hijas antes de borrar el duplicado.
 */
return new class extends Migration
{
    /** Serie que se conserva por denominación. */
    private const SERIE_CANONICA = [
        1000000.0 => 'NEG',
        500000.0 => 'ROJ',
        250000.0 => 'VER',
        100000.0 => 'BLC',
        50000.0 => 'ARB',
        20000.0 => 'AVE',
        10000.0 => 'RAV',
        5000.0 => 'FG',
        1000.0 => 'FN',
        500.0 => 'FL',
        100.0 => 'FRN',
        25.0 => 'FAV',
        10.0 => 'FCG',
        5.0 => 'FBM',
    ];

    /** @var list<array{tabla: string, parent: string, cantidad: ?string}> */
    private const LINEAS = [
        ['tabla' => 'tesoreria_crown_banca_linea', 'parent' => 'banca_id', 'cantidad' => 'cantidad'],
        ['tabla' => 'tesoreria_crown_mesa_linea', 'parent' => 'mesa_id', 'cantidad' => 'cantidad'],
        ['tabla' => 'tesoreria_crown_comprobante_linea', 'parent' => 'comprobante_id', 'cantidad' => 'cantidad'],
        ['tabla' => 'tesoreria_crown_boveda_linea', 'parent' => 'boveda_id', 'cantidad' => 'cantidad'],
        ['tabla' => 'tesoreria_crown_slot_linea', 'parent' => 'slot_id', 'cantidad' => 'cantidad'],
        ['tabla' => 'tesoreria_crown_boveda_arqueo_linea', 'parent' => 'arqueo_id', 'cantidad' => 'cantidad'],
        ['tabla' => 'tesoreria_crown_boveda_stock_dia_linea', 'parent' => 'stock_dia_id', 'cantidad' => 'cantidad'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_valor')
            || ! Schema::hasTable('tesoreria_crown_tipo_valor')) {
            return;
        }

        $fvalIds = DB::table('tesoreria_crown_tipo_valor')
            ->where('codigo', 'FVAL')
            ->pluck('id', 'sala_id');

        foreach ($fvalIds as $salaId => $tipoId) {
            $this->unificarSala((int) $salaId, (int) $tipoId);
        }
    }

    public function down(): void
    {
        // Irreversible: las series duplicadas se borran a propósito.
    }

    private function unificarSala(int $salaId, int $tipoId): void
    {
        $valores = DB::table('tesoreria_crown_valor')
            ->where('sala_id', $salaId)
            ->where('tipo_valor_id', $tipoId)
            ->get(['id', 'serie', 'denominacion']);

        $porDenom = [];
        foreach ($valores as $valor) {
            $clave = $this->claveDenominacion((float) $valor->denominacion);
            $porDenom[$clave][] = $valor;
        }

        foreach ($porDenom as $clave => $lista) {
            if (count($lista) < 2) {
                continue;
            }

            $canonSerie = self::SERIE_CANONICA[(float) $clave] ?? null;
            $destino = null;
            foreach ($lista as $valor) {
                if ($canonSerie !== null && strtoupper(trim((string) $valor->serie)) === $canonSerie) {
                    $destino = $valor;
                    break;
                }
            }
            if ($destino === null) {
                $destino = $lista[0];
            }

            foreach ($lista as $valor) {
                if ((int) $valor->id === (int) $destino->id) {
                    continue;
                }
                $this->remaparYBorrar((int) $valor->id, (int) $destino->id);
            }
        }
    }

    private function remaparYBorrar(int $desdeId, int $haciaId): void
    {
        $ahora = now();
        foreach (self::LINEAS as $def) {
            if (! Schema::hasTable($def['tabla'])) {
                continue;
            }
            $this->remaparLineas(
                $def['tabla'],
                $def['parent'],
                $def['cantidad'],
                $desdeId,
                $haciaId,
                $ahora
            );
        }

        DB::table('tesoreria_crown_valor')->where('id', $desdeId)->delete();
    }

    private function remaparLineas(
        string $tabla,
        string $parent,
        ?string $cantidad,
        int $desdeId,
        int $haciaId,
        $ahora
    ): void {
        $filas = DB::table($tabla)->where('valor_id', $desdeId)->get();
        foreach ($filas as $fila) {
            $existente = DB::table($tabla)
                ->where($parent, $fila->{$parent})
                ->where('valor_id', $haciaId)
                ->first();

            if ($existente) {
                if ($cantidad !== null && Schema::hasColumn($tabla, $cantidad)) {
                    DB::table($tabla)->where('id', $existente->id)->update([
                        $cantidad => (float) ($existente->{$cantidad} ?? 0) + (float) ($fila->{$cantidad} ?? 0),
                        'updated_at' => $ahora,
                    ]);
                }
                DB::table($tabla)->where('id', $fila->id)->delete();

                continue;
            }

            DB::table($tabla)->where('id', $fila->id)->update([
                'valor_id' => $haciaId,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function claveDenominacion(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }
};
