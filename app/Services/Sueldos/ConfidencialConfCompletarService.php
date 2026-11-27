<?php

declare(strict_types=1);

namespace App\Services\Sueldos;

use App\ApiAnita;
use App\Repositories\Sueldos\Empleado_SueldosRepository;

/**
 * Completa en el ERP los campos vacíos de los legajos confidenciales con los datos de conf (/usr2/conf).
 * Lectura de conf y plan: sin escritura. Aplicar: solo con el hash del dry-run revisado.
 */
class ConfidencialConfCompletarService
{
    private const PATH_CONF = '/usr2/conf';

    private const CAMPOS = 'emp_empresa, emp_legajo, emp_documento, emp_domicilio, emp_entre_calles, emp_localidad, '
        .'emp_cod_postal, emp_provincia, emp_telefono, emp_nacionalidad, emp_pais_nac, emp_fec_nac, emp_afil_jubil, '
        .'emp_sexo, emp_est_civil, emp_categoria, emp_cod_agrup, emp_lugartrabajo, emp_centro_costos, emp_codigo_o_soc, '
        .'emp_gremio, emp_codigo_art, emp_motivoegr, emp_fec_egr, emp_grp1, emp_grp2, emp_grp3, emp_sueldo, '
        .'emp_jornal_dia, emp_jornal_hora, emp_men_quin, emp_antig_ant, emp_mo_dir_ind, emp_contratado, '
        .'emp_codigo_afjp, emp_situacion, emp_condicion, emp_modalidad, emp_siniestrado, emp_marca_red, '
        .'emp_tipo_empresa, emp_regimen';

    public function __construct(private Empleado_SueldosRepository $repositorio)
    {
    }

    /** @return array{updates: array<int, array<string,mixed>>, resumen: array<string,mixed>, hash: string} */
    public function planificar(): array
    {
        return $this->repositorio->planCompletarConfidencialDesdeConf($this->leerConfEmpleados());
    }

    /** @param  array<int, array<string,mixed>>  $updates */
    public function ejecutar(array $updates): int
    {
        return $this->repositorio->aplicarCompletarConfidencialDesdeConf($updates);
    }

    /** @return list<array<string,mixed>> */
    private function leerConfEmpleados(): array
    {
        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita())->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'path_sistema' => self::PATH_CONF,
            'tabla' => 'empleado',
            'campos' => self::CAMPOS,
            'whereArmado' => ' WHERE emp_empresa > 0',
            'orderBy' => 'emp_empresa, emp_legajo',
        ]));
        if (! empty($parsed['error_lectura'])) {
            throw new \RuntimeException('Lectura conf empleado: '.$parsed['error_lectura']);
        }

        return array_map(fn ($f) => (array) $f, $parsed['filas']);
    }
}
