<?php

declare(strict_types=1);

namespace App\Services\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Empleado_Familiar_Sueldos;
use App\Models\Sueldos\Empleado_Ingreso_Sueldos;
use App\Models\Sueldos\Empleado_Leyenda_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\VacacionFechaAnita;
use Illuminate\Support\Facades\DB;

/**
 * Migración de la nómina confidencial (base Anita /usr2/conf) a sueldos del ERP.
 *
 * planificar(): solo lectura. Arma las operaciones y un hash del plan.
 * ejecutar():   aplica el plan revisado en una transacción. Sin borrados.
 *
 * Alcance: legajos de conf (empleado) marcados confidenciales, con CBU/banco,
 * familiares (deducciones Ganancias), ingresos (emping) y leyendas (empley).
 * Fuera de alcance: acumulados (acuempl, novacum) y vacaciones (paso aparte).
 */
final class ConfidencialConfMigracionService
{
    public const PATH_CONF = '/usr2/conf';

    /** Parentesco Anita → tipo del ERP. Código 2 depende del porcentaje (HIJOS / HIJOS_50). */
    private const PARENTESCO_TIPO = [
        1 => 'CONYUGE',
        7 => 'HIJO_INCAP',
    ];

    /** Parentescos sin tipo en el ERP: no se migran (decisión del operador). */
    private const PARENTESCO_SIN_TIPO = [3 => 'Madre', 4 => 'Otro', 5 => 'Padre', 6 => 'Prenatal'];

    /**
     * @return array{ops: array<string, mixed>, resumen: array<string, mixed>, hash: string}
     */
    public function planificar(): array
    {
        $empleados = $this->leerConf('empleado',
            'emp_empresa, emp_legajo, emp_nombre, emp_documento, emp_fec_ing, emp_fec_egr, emp_flag_estado, emp_cod_banco, emp_cbu, emp_cta_bancaria',
            'emp_empresa > 0', 'emp_empresa, emp_legajo');

        $empresaIdPorCodigo = [];
        foreach (DB::table('empresa')->select('id', 'codigo')->get() as $e) {
            $empresaIdPorCodigo[(int) $e->codigo] = (int) $e->id;
        }
        $bancosErp = DB::table('banco')->pluck('codigo')->map(fn ($c) => (int) $c)->flip()->all();

        $erp = [];
        foreach (DB::table('empleado_sueldos as e')->join('empresa as m', 'm.id', '=', 'e.empresa_id')
            ->select('m.codigo as cod', 'e.id', 'e.legajo', 'e.documento', 'e.confidencial', 'e.cbu', 'e.cuenta_bancaria', 'e.banco_codigo')
            ->get() as $r) {
            $erp[(int) $r->cod.':'.(int) $r->legajo] = $r;
        }

        $conLeyenda = DB::table('empleado_leyenda_sueldos')->distinct()->pluck('empleado_id')
            ->map(fn ($v) => (int) $v)->flip()->all();
        $ingresosErp = [];
        foreach (DB::table('empleado_ingreso_sueldos')->select('empleado_id', 'fecha_ingreso')->get() as $i) {
            $ingresosErp[(int) $i->empleado_id.'|'.substr((string) $i->fecha_ingreso, 0, 10)] = true;
        }
        $familiaresErp = [];
        foreach (DB::table('empleado_familiar_sueldos')->select('empleado_id', 'tipo', 'nombre', 'documento')->get() as $f) {
            $familiaresErp[(int) $f->empleado_id.'|'.$f->tipo.'|'.trim((string) $f->nombre).'|'.trim((string) $f->documento)] = true;
        }

        $resumen = [
            'conf_legajos' => count($empleados),
            'por_empresa_conf' => [],
            'altas' => 0,
            'altas_por_empresa' => [],
            'ya_confidenciales' => 0,
            'marcar_confidencial_existentes' => 0,
            'cbu_actualizar' => 0,
            'banco_actualizar' => 0,
            'banco_codigo_sin_nombre' => [],
            'cbu_compartido' => [],
            'documento_distinto' => [],
            'familiares_por_tipo' => [],
            'familiares_sin_tipo' => [],
            'ingresos' => 0,
            'leyendas_lineas' => 0,
            'leyendas_legajos' => 0,
        ];

        $ops = ['empleados' => [], 'familiares' => [], 'ingresos' => [], 'leyendas' => [], 'ids_existentes' => []];
        $claves = [];
        $cbuVistos = [];

        // CBU asociado a DNI distintos (dos personas): no se carga en ningún legajo.
        $cbuDocs = [];
        foreach ($empleados as $f) {
            $cbuPre = $this->txt($f['emp_cbu'], 30);
            if ($cbuPre !== null) {
                $cbuDocs[$cbuPre][trim((string) $f['emp_documento'])] = true;
            }
        }
        $cbuConflicto = [];
        foreach ($cbuDocs as $cbuPre => $docs) {
            if (count($docs) > 1) {
                $cbuConflicto[$cbuPre] = true;
            }
        }

        foreach ($empleados as $f) {
            $cod = (int) $f['emp_empresa'];
            $leg = (int) $f['emp_legajo'];
            $key = $cod.':'.$leg;
            $resumen['por_empresa_conf'][$cod] = ($resumen['por_empresa_conf'][$cod] ?? 0) + 1;

            $empresaId = $empresaIdPorCodigo[$cod] ?? null;
            if ($empresaId === null) {
                continue;
            }
            $claves[$key] = true;

            $cbu = $this->txt($f['emp_cbu'], 30);
            $cta = $this->txt($f['emp_cta_bancaria'], 30);
            if ($cbu !== null && isset($cbuConflicto[$cbu])) {
                $cbu = null;
                $cta = null;
                $resumen['cbu_omitido_por_conflicto'] = ($resumen['cbu_omitido_por_conflicto'] ?? 0) + 1;
            }
            if ($cbu !== null) {
                $cbuVistos[$cbu][] = $key;
            }
            $bancoVal = (int) $f['emp_cod_banco'] > 0 ? (int) $f['emp_cod_banco'] : null;
            if ($bancoVal !== null && ! isset($bancosErp[$bancoVal])) {
                $resumen['banco_codigo_sin_nombre'][$bancoVal] = ($resumen['banco_codigo_sin_nombre'][$bancoVal] ?? 0) + 1;
            }

            $actual = $erp[$key] ?? null;
            if ($actual === null) {
                $resumen['altas']++;
                $resumen['altas_por_empresa'][$cod] = ($resumen['altas_por_empresa'][$cod] ?? 0) + 1;
                $ops['empleados'][] = [
                    'accion' => 'alta',
                    'key' => $key,
                    'datos' => [
                        'empresa_id' => $empresaId,
                        'legajo' => $leg,
                        'nombre' => $this->txt($f['emp_nombre'], 80) ?? (string) $leg,
                        'documento' => $this->txt($f['emp_documento'], 30),
                        'fecha_ingreso' => VacacionFechaAnita::erpDesdeAnita($f['emp_fec_ing']),
                        'fecha_egreso' => VacacionFechaAnita::erpDesdeAnita($f['emp_fec_egr']),
                        'estado' => EmpleadoEstados::desdeFlagAnita((string) $f['emp_flag_estado']),
                        'cbu' => $cbu,
                        'cuenta_bancaria' => $cta,
                        'banco_codigo' => $bancoVal,
                        'confidencial' => true,
                    ],
                ];

                continue;
            }

            $ops['ids_existentes'][$key] = (int) $actual->id;
            $patch = [];
            if ((bool) $actual->confidencial) {
                $resumen['ya_confidenciales']++;
            } else {
                $patch['confidencial'] = true;
                $resumen['marcar_confidencial_existentes']++;
            }
            if ($cbu !== null && trim((string) $actual->cbu) !== $cbu) {
                $patch['cbu'] = $cbu;
                if ($cta !== null) {
                    $patch['cuenta_bancaria'] = $cta;
                }
                $resumen['cbu_actualizar']++;
            }
            if ($bancoVal !== null && (int) $actual->banco_codigo !== $bancoVal) {
                $patch['banco_codigo'] = $bancoVal;
                $resumen['banco_actualizar']++;
            }
            if ($patch !== []) {
                $ops['empleados'][] = ['accion' => 'actualizar', 'key' => $key, 'empleado_id' => (int) $actual->id, 'datos' => $patch];
            }

            $docConf = trim((string) $f['emp_documento']);
            if ($docConf !== '' && trim((string) $actual->documento) !== $docConf) {
                $resumen['documento_distinto'][] = $key.' erp='.trim((string) $actual->documento).' conf='.$docConf.' (no se modifica)';
            }
        }

        foreach ($cbuVistos as $cbu => $keys) {
            if (count($keys) > 1) {
                $resumen['cbu_compartido'][] = $cbu.' => '.implode(', ', $keys);
            }
        }

        $this->planificarFamiliares($claves, $ops, $resumen, $familiaresErp);
        $this->planificarIngresos($claves, $ops, $resumen, $ingresosErp);
        $this->planificarLeyendas($claves, $ops, $resumen, $conLeyenda);

        $hash = hash('sha256', json_encode($ops, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        return ['ops' => $ops, 'resumen' => $resumen, 'hash' => $hash];
    }

    /**
     * Aplica el plan revisado. Todo en una transacción; solo altas, updates e inserts.
     *
     * @param  array<string, mixed>  $ops
     * @return array<string, int>
     */
    public function ejecutar(array $ops): array
    {
        $res = ['altas' => 0, 'actualizados' => 0, 'familiares' => 0, 'ingresos' => 0, 'leyendas' => 0];

        return DB::transaction(function () use ($ops, &$res) {
            $ids = $ops['ids_existentes'];

            foreach ($ops['empleados'] as $op) {
                if ($op['accion'] === 'alta') {
                    $m = Empleado_Sueldos::create($op['datos']);
                    $ids[$op['key']] = (int) $m->id;
                    $res['altas']++;
                } else {
                    Empleado_Sueldos::query()->whereKey($op['empleado_id'])->firstOrFail()->update($op['datos']);
                    $res['actualizados']++;
                }
            }

            foreach ($ops['familiares'] as $op) {
                Empleado_Familiar_Sueldos::create(array_merge(['empleado_id' => $ids[$op['key']]], $op['datos']));
                $res['familiares']++;
            }
            foreach ($ops['ingresos'] as $op) {
                Empleado_Ingreso_Sueldos::create(array_merge(['empleado_id' => $ids[$op['key']]], $op['datos']));
                $res['ingresos']++;
            }
            foreach ($ops['leyendas'] as $op) {
                Empleado_Leyenda_Sueldos::create(array_merge(['empleado_id' => $ids[$op['key']]], $op['datos']));
                $res['leyendas']++;
            }

            return $res;
        });
    }

    /**
     * @param  array<string, bool>  $claves
     * @param  array<string, mixed>  $ops
     * @param  array<string, mixed>  $resumen
     * @param  array<string, bool>  $familiaresErp
     */
    private function planificarFamiliares(array $claves, array &$ops, array &$resumen, array $familiaresErp): void
    {
        $filas = $this->leerConf('familiar',
            'fam_empresa, fam_legajo, fam_linea, fam_nombre, fam_cod_parentezco, fam_nro_documento, fam_porc_deduccion',
            '', 'fam_empresa, fam_legajo, fam_linea');

        $ids = $ops['ids_existentes'];
        $planeados = [];
        foreach ($filas as $f) {
            $key = (int) $f['fam_empresa'].':'.(int) $f['fam_legajo'];
            if (! isset($claves[$key])) {
                continue;
            }
            $par = (int) $f['fam_cod_parentezco'];
            $porc = (int) $f['fam_porc_deduccion'] === 50 ? 50 : 100;
            if ($par === 2) {
                $tipo = $porc === 50 ? 'HIJOS_50' : 'HIJOS';
            } else {
                $tipo = self::PARENTESCO_TIPO[$par] ?? null;
            }
            if ($tipo === null) {
                $etiqueta = self::PARENTESCO_SIN_TIPO[$par] ?? ('código '.$par);
                $resumen['familiares_sin_tipo'][$etiqueta] = ($resumen['familiares_sin_tipo'][$etiqueta] ?? 0) + 1;

                continue;
            }

            $nombre = $this->txt($f['fam_nombre'], 60);
            $doc = $this->txt($f['fam_nro_documento'], 20);
            $dedupe = $key.'|'.$tipo.'|'.$nombre.'|'.$doc;
            if (isset($planeados[$dedupe])) {
                continue;
            }
            $planeados[$dedupe] = true;
            if (isset($ids[$key]) && isset($familiaresErp[$ids[$key].'|'.$tipo.'|'.trim((string) $nombre).'|'.trim((string) $doc)])) {
                continue;
            }

            $resumen['familiares_por_tipo'][$tipo] = ($resumen['familiares_por_tipo'][$tipo] ?? 0) + 1;
            $ops['familiares'][] = [
                'key' => $key,
                'datos' => [
                    'tipo' => $tipo,
                    'nombre' => $nombre,
                    'documento' => $doc,
                    'porcentaje_deduccion' => $porc,
                    'activo' => true,
                ],
            ];
        }
    }

    /**
     * @param  array<string, bool>  $claves
     * @param  array<string, mixed>  $ops
     * @param  array<string, mixed>  $resumen
     * @param  array<string, bool>  $ingresosErp
     */
    private function planificarIngresos(array $claves, array &$ops, array &$resumen, array $ingresosErp): void
    {
        $filas = $this->leerConf('emping', 'empi_empresa, empi_legajo, empi_fecha_ing, empi_fecha_egr', '', 'empi_empresa, empi_legajo, empi_fecha_ing');
        $ids = $ops['ids_existentes'];
        $vistos = [];
        foreach ($filas as $f) {
            $key = (int) $f['empi_empresa'].':'.(int) $f['empi_legajo'];
            if (! isset($claves[$key])) {
                continue;
            }
            $fi = VacacionFechaAnita::erpDesdeAnita($f['empi_fecha_ing']);
            if ($fi === null || isset($vistos[$key.'|'.$fi])) {
                continue;
            }
            $vistos[$key.'|'.$fi] = true;
            if (isset($ids[$key]) && isset($ingresosErp[$ids[$key].'|'.$fi])) {
                continue;
            }
            $fe = VacacionFechaAnita::erpDesdeAnita($f['empi_fecha_egr']);
            $resumen['ingresos']++;
            $ops['ingresos'][] = [
                'key' => $key,
                'datos' => [
                    'fecha_ingreso' => $fi,
                    'fecha_egreso' => $fe,
                    'tipo_movimiento' => $fe !== null ? 'B' : 'I',
                ],
            ];
        }
    }

    /**
     * @param  array<string, bool>  $claves
     * @param  array<string, mixed>  $ops
     * @param  array<string, mixed>  $resumen
     * @param  array<int, bool>  $conLeyenda
     */
    private function planificarLeyendas(array $claves, array &$ops, array &$resumen, array $conLeyenda): void
    {
        $filas = $this->leerConf('empley', 'eml_empresa, eml_legajo, eml_linea, eml_leyenda', '', 'eml_empresa, eml_legajo, eml_linea');
        $ids = $ops['ids_existentes'];
        $legajosConLineas = [];
        foreach ($filas as $f) {
            $key = (int) $f['eml_empresa'].':'.(int) $f['eml_legajo'];
            if (! isset($claves[$key])) {
                continue;
            }
            $texto = $this->txt($f['eml_leyenda'], 80);
            if ($texto === null) {
                continue;
            }
            if (isset($ids[$key]) && isset($conLeyenda[$ids[$key]])) {
                continue;
            }
            $linea = (int) $f['eml_linea'];
            $resumen['leyendas_lineas']++;
            $legajosConLineas[$key] = true;
            $ops['leyendas'][] = ['key' => $key, 'datos' => ['linea' => $linea, 'leyenda' => $texto]];
        }
        $resumen['leyendas_legajos'] = count($legajosConLineas);
    }

    private function txt($v, int $len): ?string
    {
        $s = trim((string) $v);
        if ($s === '' || $s === '0') {
            return null;
        }

        return mb_substr($s, 0, $len);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leerConf(string $tabla, string $campos, string $where, string $orderBy): array
    {
        $payload = [
            'acc' => 'list',
            'sistema' => 'sueldos',
            'path_sistema' => self::PATH_CONF,
            'tabla' => $tabla,
            'campos' => $campos,
            'orderBy' => $orderBy,
        ];
        if ($where !== '') {
            $payload['whereArmado'] = ' WHERE '.$where;
        }
        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita())->apiCall($payload));
        if (! empty($parsed['error_lectura'])) {
            throw new \RuntimeException('Lectura conf '.$tabla.': '.$parsed['error_lectura']);
        }

        return array_map(fn ($f) => (array) $f, $parsed['filas']);
    }
}
