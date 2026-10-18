<?php

namespace App\Support\Contable;

use App\ApiAnita;
use App\Models\Contable\AjusteInflacionIndice;
use App\Models\Seguridad\Usuario;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Crown: indmae/indmov. La serie tipo I (Ajuste) es el IPC que ya usa
 * ajuste_inflacion_indice. No pisa períodos cargados (FACPCE tiene más decimales).
 */
final class IndiceAnitaImportSupport
{
    /**
     * @return array{en_anita: int, insertados: int, ya_existian: int, omitidos: int}
     */
    public static function importarSerieAjuste(): array
    {
        $ret = ['en_anita' => 0, 'insertados' => 0, 'ya_existian' => 0, 'omitidos' => 0];
        if (! EntornoEmpresaSupport::esCrown()) {
            return $ret;
        }

        $apiAnita = new ApiAnita();
        $maestros = ApiAnita::parsearRespuestaLista($apiAnita->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'indmae',
            'campos' => 'indm_indice, indm_desc, indm_tipo_valor',
        ]));
        $codigoAjuste = null;
        foreach ($maestros['filas'] as $maestro) {
            if (strtoupper(trim((string) ($maestro->indm_tipo_valor ?? ''))) === 'I') {
                $codigoAjuste = trim((string) ($maestro->indm_indice ?? ''));
                break;
            }
        }
        if ($codigoAjuste === null || $codigoAjuste === '') {
            return $ret;
        }

        $movimientos = ApiAnita::parsearRespuestaLista($apiAnita->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'indmov',
            'campos' => 'indv_indice, indv_anio, indv_mes, indv_coeficiente',
            'whereArmado' => " WHERE indv_indice = '".$codigoAjuste."' ",
        ]));
        $ret['en_anita'] = count($movimientos['filas']);

        $usuarioId = (int) Usuario::query()->orderBy('id')->value('id');
        $existentes = AjusteInflacionIndice::query()->pluck('periodo')->map(
            fn ($periodo) => substr((string) $periodo, 0, 10)
        )->all();
        $existentes = array_fill_keys($existentes, true);

        foreach ($movimientos['filas'] as $row) {
            $anio = (int) ($row->indv_anio ?? 0);
            $mes = (int) ($row->indv_mes ?? 0);
            $valor = (float) str_replace(',', '.', (string) ($row->indv_coeficiente ?? '0'));
            if ($anio < 1990 || $anio > 2100 || $mes < 1 || $mes > 12 || $valor <= 0) {
                $ret['omitidos']++;
                continue;
            }
            $periodo = sprintf('%04d-%02d-01', $anio, $mes);
            if (isset($existentes[$periodo])) {
                $ret['ya_existian']++;
                continue;
            }
            AjusteInflacionIndice::query()->create([
                'periodo' => $periodo,
                'valor' => $valor,
                'fuente' => 'Anita indmae Ajuste',
                'provisorio' => false,
                'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            ]);
            $existentes[$periodo] = true;
            $ret['insertados']++;
        }

        return $ret;
    }
}
