<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Lectura Informix listmae/listcol/listcon (solo importación).
 */
class ReporteSueldosDefinibleAnitaBridgeReader
{
    public function __construct(private readonly ApiAnita $apiAnita)
    {
    }

    /**
     * @return array{
     *   cabeceras: list<object>,
     *   columnas: list<object>,
     *   conceptos: list<object>,
     *   errores: list<string>
     * }
     */
    public function cargarTodo(?int $listadoDesde = null, ?int $listadoHasta = null): array
    {
        $errores = [];
        $whereMae = '';
        if ($listadoDesde !== null && $listadoHasta !== null) {
            $whereMae = sprintf(
                ' WHERE lism_listado BETWEEN %d AND %d',
                $listadoDesde,
                $listadoHasta
            );
        } elseif ($listadoDesde !== null) {
            $whereMae = ' WHERE lism_listado = '.(int) $listadoDesde;
        }

        $cabeceras = $this->listar(
            'listmae',
            'lism_listado,lism_titulo,lism_tipo_list,lism_asociado',
            $whereMae,
            'lism_listado',
            $errores,
            'listmae'
        );

        $whereCol = $whereMae !== ''
            ? str_replace('lism_listado', 'lisc_listado', $whereMae)
            : '';
        $columnas = $this->listar(
            'listcol',
            'lisc_listado,lisc_nro_columna,lisc_desc,lisc_contenido,lisc_campo_empl,lisc_largo_campo',
            $whereCol,
            'lisc_listado,lisc_nro_columna',
            $errores,
            'listcol'
        );

        $whereCon = $whereMae !== ''
            ? str_replace('lism_listado', 'liscn_listado', $whereMae)
            : '';
        $conceptos = $this->listar(
            'listcon',
            'liscn_listado,liscn_nro_columna,liscn_concepto,liscn_orden,liscn_signo',
            $whereCon,
            'liscn_listado,liscn_nro_columna,liscn_orden',
            $errores,
            'listcon'
        );

        return [
            'cabeceras' => $cabeceras,
            'columnas' => $columnas,
            'conceptos' => $conceptos,
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
                'sistema' => 'sueldos',
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $whereArmado,
                'orderBy' => $orderBy,
            ]);
            $filas = ApiAnita::decodificarListaFilas($raw);

            return is_array($filas) ? $filas : [];
        } catch (\Throwable $e) {
            Log::warning('ReporteSueldosDefinibleAnitaBridgeReader: '.$etiqueta, [
                'error' => $e->getMessage(),
            ]);
            $errores[] = 'No se pudo leer '.$etiqueta.': '.$e->getMessage();

            return [];
        }
    }
}
