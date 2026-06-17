<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use Illuminate\Support\Facades\DB;

/**
 * Resuelve proveedor_id desde subd_emisor (código Anita) para links al ABM.
 */
class MayorPlanoCuentaProveedorEnricher
{
    /** @var array<string, int> */
    private array $cachePorCodigo = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas): array
    {
        $codigosEmisor = [];
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $codigo = self::normalizarCodigoEmisor((string) ($fila['emisor'] ?? ''));
            if ($codigo !== '') {
                $codigosEmisor[$codigo] = true;
            }
        }

        if ($codigosEmisor === []) {
            return $filas;
        }

        $this->precargar(array_keys($codigosEmisor));

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $codigo = self::normalizarCodigoEmisor((string) ($fila['emisor'] ?? ''));
            $filas[$idx]['proveedor_id'] = $codigo !== '' ? (int) ($this->cachePorCodigo[$codigo] ?? 0) : 0;
        }

        return $filas;
    }

    public static function normalizarCodigoEmisor(string $emisor): string
    {
        $emisor = trim($emisor);
        if ($emisor === '') {
            return '';
        }

        $codigo = ltrim($emisor, '0');

        return $codigo !== '' ? $codigo : '';
    }

    /**
     * @param  list<string>  $codigosNormalizados
     */
    private function precargar(array $codigosNormalizados): void
    {
        $faltantes = array_values(array_filter(
            $codigosNormalizados,
            fn (string $codigo) => ! isset($this->cachePorCodigo[$codigo]),
        ));

        if ($faltantes === []) {
            return;
        }

        $mapa = DB::table('proveedor')
            ->whereIn('codigo', $faltantes)
            ->pluck('id', 'codigo')
            ->all();

        foreach ($mapa as $codigo => $id) {
            $this->cachePorCodigo[(string) $codigo] = (int) $id;
        }
    }
}
