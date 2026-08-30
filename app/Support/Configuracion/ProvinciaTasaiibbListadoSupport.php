<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use App\Models\Configuracion\Empresa_Jurisdiccion_Iibb;
use App\Models\Configuracion\Provincia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Listado de control: provincias con alícuotas y mínimos IIBB cargados.
 */
final class ProvinciaTasaiibbListadoSupport
{
    public static function titulo(): string
    {
        return 'Tasas y mínimos IIBB por provincia';
    }

    public static function subtitulo(): string
    {
        return 'Solo jurisdicciones con alícuotas cargadas. Tasas y mínimos son patrimonio de cada fisco.';
    }

    /**
     * Una fila por provincia × condición IIBB.
     *
     * @return Collection<int, object>
     */
    public static function filas(): Collection
    {
        $nombreEmpresa = (string) config('app.empresa');

        $provincias = Provincia::query()
            ->with([
                'paises:id,nombre',
                'provincia_tasaiibbs.condicioniibbs:id,nombre,formacalculo',
                'provincia_cuentacontableiibbs.empresas:id,nombre',
                'provincia_cuentacontableiibbs.cuentacontables:id,codigo,nombre',
            ])
            ->whereHas('provincia_tasaiibbs')
            ->orderBy('jurisdiccion')
            ->orderBy('nombre')
            ->get();

        $agentesPorProvincia = self::agentesPorProvincia($provincias->pluck('id')->all());

        $filas = collect();
        foreach ($provincias as $provincia) {
            $agentes = $agentesPorProvincia[(int) $provincia->id] ?? ['percepcion' => '', 'retencion' => ''];
            $cuentas = self::textoCuentas($provincia);
            $tasas = $provincia->provincia_tasaiibbs
                ->sortBy(static fn ($tasa) => (string) ($tasa->condicioniibbs->nombre ?? ''))
                ->values();

            foreach ($tasas as $tasa) {
                $filas->push((object) [
                    'nombreempresa' => $nombreEmpresa,
                    'provincia_id' => (int) $provincia->id,
                    'jurisdiccion' => (int) $provincia->jurisdiccion,
                    'provincia' => (string) $provincia->nombre,
                    'abreviatura' => (string) $provincia->abreviatura,
                    'condicion' => (string) ($tasa->condicioniibbs->nombre ?? ''),
                    'tasa' => (float) $tasa->tasa,
                    'minimoneto' => (float) $tasa->minimoneto,
                    'minimopercepcion' => (float) $tasa->minimopercepcion,
                    'minimocoeficientecm05' => (float) ($provincia->minimocoeficientecm05 ?? 0),
                    'empresas_percepcion' => $agentes['percepcion'],
                    'empresas_retencion' => $agentes['retencion'],
                    'cuentas' => $cuentas,
                ]);
            }
        }

        return $filas;
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return array{provincias: int, alicuotas: int}
     */
    public static function resumen(Collection $filas): array
    {
        return [
            'provincias' => $filas->pluck('provincia_id')->unique()->count(),
            'alicuotas' => $filas->count(),
        ];
    }

    /**
     * @param  list<int>  $provinciaIds
     * @return array<int, array{percepcion: string, retencion: string}>
     */
    private static function agentesPorProvincia(array $provinciaIds): array
    {
        if ($provinciaIds === [] || ! Schema::hasTable('empresa_jurisdiccion_iibb')) {
            return [];
        }

        $out = [];
        $filas = Empresa_Jurisdiccion_Iibb::query()
            ->with('empresa:id,nombre')
            ->whereIn('provincia_id', $provinciaIds)
            ->orderBy('empresa_id')
            ->get();

        foreach ($filas as $fila) {
            $provinciaId = (int) $fila->provincia_id;
            if (! isset($out[$provinciaId])) {
                $out[$provinciaId] = ['percepcion' => [], 'retencion' => []];
            }
            $nombre = (string) ($fila->empresa->nombre ?? '');
            if ($nombre === '') {
                continue;
            }
            if ($fila->es_agente_percepcion) {
                $out[$provinciaId]['percepcion'][] = $nombre;
            }
            if ($fila->es_agente_retencion) {
                $out[$provinciaId]['retencion'][] = $nombre;
            }
        }

        foreach ($out as $provinciaId => $listas) {
            $out[$provinciaId] = [
                'percepcion' => implode(', ', array_values(array_unique($listas['percepcion']))),
                'retencion' => implode(', ', array_values(array_unique($listas['retencion']))),
            ];
        }

        return $out;
    }

    private static function textoCuentas(Provincia $provincia): string
    {
        $partes = [];
        foreach ($provincia->provincia_cuentacontableiibbs as $cuenta) {
            $empresa = (string) ($cuenta->empresas->nombre ?? '');
            $codigo = (string) ($cuenta->cuentacontables->codigo ?? '');
            $nombre = (string) ($cuenta->cuentacontables->nombre ?? '');
            $linea = trim($empresa.' '.$codigo.($nombre !== '' ? '-'.$nombre : ''));
            if ($linea !== '') {
                $partes[] = $linea;
            }
        }

        return implode('; ', $partes);
    }
}
