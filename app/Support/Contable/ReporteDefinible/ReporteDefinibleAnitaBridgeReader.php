<?php

namespace App\Support\Contable\ReporteDefinible;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Lectura Informix de definiciones de informes (infomae/infomov/infocta/infoccos).
 * Solo para importación; el runtime diario no usa Anita.
 */
class ReporteDefinibleAnitaBridgeReader
{
    public function __construct(private readonly ApiAnita $apiAnita)
    {
    }

    /**
     * @return array{
     *   cabeceras: list<object>,
     *   rubros: list<object>,
     *   cuentas: list<object>,
     *   ccostos: list<object>,
     *   errores: list<string>
     * }
     */
    public function cargarTodo(?int $informeDesde = null, ?int $informeHasta = null): array
    {
        $errores = [];
        $whereInf = '';
        if ($informeDesde !== null && $informeHasta !== null) {
            $whereInf = sprintf(
                ' WHERE infm_informe BETWEEN %d AND %d',
                $informeDesde,
                $informeHasta
            );
        } elseif ($informeDesde !== null) {
            $whereInf = ' WHERE infm_informe = '.(int) $informeDesde;
        }

        $cabeceras = $this->listar(
            'infomae',
            'infm_informe,infm_desc,infm_titulo1,infm_titulo2',
            $whereInf,
            'infm_informe',
            $errores,
            'infomae'
        );

        $whereMov = $whereInf !== ''
            ? str_replace('infm_informe', 'infv_informe', $whereInf)
            : '';
        $rubros = $this->listar(
            'infomov',
            'infv_informe,infv_rubro,infv_desc,infv_nivel',
            $whereMov,
            'infv_informe,infv_rubro',
            $errores,
            'infomov'
        );

        $whereCta = $whereInf !== ''
            ? str_replace('infm_informe', 'infc_informe', $whereInf)
            : '';
        $cuentas = $this->listar(
            'infocta',
            'infc_empresa,infc_informe,infc_rubro,infc_cuenta,infc_carga_ccosto,infc_real_presup,infc_sucursal',
            $whereCta,
            'infc_informe,infc_rubro,infc_cuenta',
            $errores,
            'infocta'
        );

        $whereCc = $whereInf !== ''
            ? str_replace('infm_informe', 'infcc_informe', $whereInf)
            : '';
        $ccostos = $this->listar(
            'infoccos',
            'infcc_empresa,infcc_informe,infcc_rubro,infcc_cuenta,infcc_d_ccosto,infcc_h_ccosto,infcc_real_presup',
            $whereCc,
            'infcc_informe,infcc_rubro,infcc_cuenta,infcc_d_ccosto',
            $errores,
            'infoccos'
        );

        return [
            'cabeceras' => $cabeceras,
            'rubros' => $rubros,
            'cuentas' => $cuentas,
            'ccostos' => $ccostos,
            'errores' => $errores,
        ];
    }

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    private function listar(
        string $tabla,
        string $campos,
        string $whereArmado,
        string $orderBy,
        array &$errores,
        string $etiqueta
    ): array {
        try {
            $raw = $this->apiAnita->apiCall([
                'acc' => 'list',
                'sistema' => 'contab',
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $whereArmado,
                'orderBy' => $orderBy,
            ]);
            $msg = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
            if ($msg !== null) {
                $errores[] = "Anita {$etiqueta}: {$msg}";

                return [];
            }

            return ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : null);
        } catch (\Throwable $e) {
            Log::warning('ReporteDefinibleAnitaBridgeReader', [
                'tabla' => $tabla,
                'error' => $e->getMessage(),
            ]);
            $errores[] = "Anita {$etiqueta}: ".$e->getMessage();

            return [];
        }
    }
}
