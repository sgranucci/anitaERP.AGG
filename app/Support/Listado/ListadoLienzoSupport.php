<?php

declare(strict_types=1);

namespace App\Support\Listado;

/**
 * Hasta tres gráficos de la misma grilla, con el mismo filtro.
 * El primero sigue en la clave grafico para las vistas ya guardadas.
 */
final class ListadoLienzoSupport
{
    public const MAX_GRAFICOS = 3;

    /**
     * @param  array<string, array{column?: string, label?: string}>  $campos
     * @return list<array{tipo: string, dimension: string, medida: string}>
     */
    public static function normalizar(mixed $lista, mixed $unico, bool $listaExplicita, array $campos): array
    {
        $out = [];
        if ($listaExplicita && is_array($lista)) {
            foreach ($lista as $item) {
                if (count($out) >= self::MAX_GRAFICOS) {
                    break;
                }
                $grafico = ListadoVisualSupport::normalizarGrafico($item, $campos);
                $grafico['medida'] = ListadoMedidaSupport::normalizarClave((string) ($grafico['medida'] ?? 'conteo'), $campos);
                if ($grafico['tipo'] !== '') {
                    $out[] = $grafico;
                }
            }

            return $out;
        }
        $uno = ListadoVisualSupport::normalizarGrafico($unico, $campos);
        $uno['medida'] = ListadoMedidaSupport::normalizarClave((string) ($uno['medida'] ?? 'conteo'), $campos);

        return $uno['tipo'] === '' ? [] : [$uno];
    }

    /**
     * @param  list<array{tipo: string, dimension: string, medida: string}>  $graficos
     * @return array{tipo: string, dimension: string, medida: string}
     */
    public static function primero(array $graficos): array
    {
        return $graficos[0] ?? ListadoVisualSupport::graficoVacio();
    }
}
