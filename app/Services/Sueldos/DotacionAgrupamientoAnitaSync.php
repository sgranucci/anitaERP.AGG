<?php

namespace App\Services\Sueldos;

use App\ApiAnita;
use App\Models\Stock\Color;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\Prenda_Agrupamiento_Sueldos;
use App\Models\Sueldos\Prenda_Sueldos;

/**
 * Sync pull unilateral desde Anita (base sueldos) de la dotación por agrupamiento/sexo
 * (tabla `prendxagr`). Inserta faltantes; no actualiza ni borra ni escribe hacia Anita.
 */
class DotacionAgrupamientoAnitaSync
{
    protected string $tableAnita = 'prendxagr';

    /**
     * @return array{en_anita: int, importados: int, omitidos: int, sin_mapeo: int, errores: list<string>}
     */
    public function sincronizar(): array
    {
        ini_set('max_execution_time', '600');

        $resultado = ['en_anita' => 0, 'importados' => 0, 'omitidos' => 0, 'sin_mapeo' => 0, 'errores' => []];

        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnita,
            'campos' => 'prxagr_cod_agrup, prxagr_orden, prxagr_prenda, prxagr_color, prxagr_lim_anual, prxagr_sexo',
            'orderBy' => 'prxagr_cod_agrup, prxagr_sexo, prxagr_orden',
        ]));

        if (! empty($parsed['error_lectura'])) {
            $resultado['errores'][] = 'prendxagr: '.(string) $parsed['error_lectura'];

            return $resultado;
        }

        $mapaAgr = Agrupamiento_Sueldos::query()->pluck('id', 'codigo')->all();
        $mapaPrenda = Prenda_Sueldos::query()->pluck('id', 'codigo')->all();
        $mapaColor = Color::query()->whereNotNull('codigo')->pluck('id', 'codigo')->all();

        foreach ($parsed['filas'] as $row) {
            $resultado['en_anita']++;

            $agrId = (int) ($mapaAgr[(int) ($row->prxagr_cod_agrup ?? 0)] ?? 0);
            $prendaId = (int) ($mapaPrenda[(int) ($row->prxagr_prenda ?? 0)] ?? 0);
            if ($agrId === 0 || $prendaId === 0) {
                $resultado['sin_mapeo']++;
                continue;
            }

            $sexo = self::normalizarSexo($row->prxagr_sexo ?? null);
            $colorCod = (int) ($row->prxagr_color ?? 0);
            $colorId = $colorCod > 0 ? (int) ($mapaColor[$colorCod] ?? 0) : 0;
            $colorId = $colorId > 0 ? $colorId : null;

            $existe = Prenda_Agrupamiento_Sueldos::query()
                ->where('agrupamiento_id', $agrId)
                ->where('sexo', $sexo)
                ->where('prenda_id', $prendaId)
                ->when($colorId === null, fn ($q) => $q->whereNull('color_id'))
                ->when($colorId !== null, fn ($q) => $q->where('color_id', $colorId))
                ->exists();
            if ($existe) {
                $resultado['omitidos']++;
                continue;
            }

            Prenda_Agrupamiento_Sueldos::create([
                'agrupamiento_id' => $agrId,
                'sexo' => $sexo,
                'orden' => (int) ($row->prxagr_orden ?? 0),
                'prenda_id' => $prendaId,
                'color_id' => $colorId,
                'limite_anual' => (float) ($row->prxagr_lim_anual ?? 0),
            ]);
            $resultado['importados']++;
        }

        return $resultado;
    }

    public static function normalizarSexo($valor): string
    {
        $v = strtoupper(trim((string) $valor));

        return match ($v) {
            'M', '1', 'H' => 'M',
            'F', '2' => 'F',
            default => 'M',
        };
    }
}
