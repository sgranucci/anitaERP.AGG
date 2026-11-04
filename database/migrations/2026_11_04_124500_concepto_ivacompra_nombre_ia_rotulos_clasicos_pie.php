<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alias de pie AFIP clásico (Cianbox / e-tiquetas): Gravado, Neto gravado,
 * I.V.A. INSCRIPTO, Operaciones exentas. Separador de lista = ", " (no partir 10,5%).
 */
return new class extends Migration
{
    public function up(): void
    {
        $porTipo = [
            'G' => ['Gravado', 'Neto gravado', 'Neto', 'Subtotal'],
            'I' => ['I.V.A. INSCRIPTO', 'I.V.A. INSC', 'IVA inscripto', 'Impuesto Valor Agregado'],
        ];

        foreach ($porTipo as $tipo => $extras) {
            $rows = DB::table('concepto_ivacompra')
                ->where('tipoconcepto', $tipo)
                ->get(['id', 'nombre_ia']);
            foreach ($rows as $row) {
                $nuevo = $this->mergeAliases((string) ($row->nombre_ia ?? ''), $extras);
                if ($nuevo === null) {
                    continue;
                }
                DB::table('concepto_ivacompra')->where('id', $row->id)->update([
                    'nombre_ia' => $nuevo,
                    'updated_at' => now(),
                ]);
            }
        }

        $exento = DB::table('concepto_ivacompra')
            ->where('codigo', '1')
            ->where('tipoconcepto', 'N')
            ->first(['id', 'nombre_ia']);
        if ($exento) {
            $nuevo = $this->mergeAliases(
                (string) ($exento->nombre_ia ?? ''),
                ['Operaciones exentas', 'Importe exento', 'No Gravado', 'Exento']
            );
            if ($nuevo !== null) {
                DB::table('concepto_ivacompra')->where('id', $exento->id)->update([
                    'nombre_ia' => $nuevo,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No revierte alias: quitaría también sinónimos cargados a mano.
    }

    /**
     * @param  list<string>  $extras
     */
    private function mergeAliases(string $actual, array $extras): ?string
    {
        $partes = [];
        $ya = [];
        foreach (preg_split('/,\s+/', $actual) ?: [] as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $k = mb_strtolower($p);
            if (isset($ya[$k])) {
                continue;
            }
            $ya[$k] = true;
            $partes[] = $p;
        }

        $agrego = false;
        foreach ($extras as $extra) {
            $k = mb_strtolower($extra);
            if (isset($ya[$k])) {
                continue;
            }
            $ya[$k] = true;
            $partes[] = $extra;
            $agrego = true;
        }

        if (! $agrego) {
            return null;
        }

        return implode(', ', $partes);
    }
};
