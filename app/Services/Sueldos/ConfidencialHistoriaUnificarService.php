<?php

declare(strict_types=1);

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Ingreso_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Support\Sueldos\EmpleadoBajaSupport;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\VacacionFechaAnita;
use Illuminate\Support\Facades\DB;

/**
 * Unifica la historia (cabecera + empleado_ingreso_sueldos) de los legajos confidenciales
 * con la base Confidencial de Anita (/usr2/conf), que es la fuente de verdad.
 *
 * Regla: un período de conf y los períodos del ERP que se superponen con él son la misma
 * relación laboral. Se fusionan en una sola fila: ingreso = el más antiguo, egreso = el de conf.
 * Los períodos del ERP que no se superponen con ninguno de conf (historia previa de la
 * nómina común) se conservan. La cabecera toma el último período.
 *
 * planificar(): solo lectura. ejecutar(): aplica el plan revisado (hash) en una transacción.
 */
final class ConfidencialHistoriaUnificarService
{
    public function __construct(private ConfidencialConfMigracionService $conf)
    {
    }

    /**
     * @param  list<int>  $legajos  Vacío = todos.
     * @param  list<int>  $empresasCodigo  Códigos de empresa Anita (1 Biyemas, 3 Rebisco...). Vacío = todas.
     * @return array{ops: list<array<string, mixed>>, resumen: array<string, int>, hash: string}
     */
    public function planificar(array $legajos = [], array $empresasCodigo = []): array
    {
        $empleadosConf = $this->conf->leerConf('empleado',
            'emp_empresa, emp_legajo, emp_fec_ing, emp_fec_egr, emp_flag_estado',
            'emp_empresa > 0', 'emp_empresa, emp_legajo');
        $empingConf = $this->conf->leerConf('emping',
            'empi_empresa, empi_legajo, empi_fecha_ing, empi_fecha_egr',
            '', 'empi_empresa, empi_legajo, empi_fecha_ing');

        $confPeriodos = [];
        foreach ($empingConf as $f) {
            $fi = VacacionFechaAnita::erpDesdeAnita($f['empi_fecha_ing']);
            if ($fi === null) {
                continue;
            }
            $k = (int) $f['empi_empresa'].':'.(int) $f['empi_legajo'];
            $confPeriodos[$k][$fi] = VacacionFechaAnita::erpDesdeAnita($f['empi_fecha_egr']);
        }

        $erp = [];
        foreach (DB::table('empleado_sueldos as e')->join('empresa as m', 'm.id', '=', 'e.empresa_id')
            ->where('e.confidencial', true)
            ->select('m.codigo as cod', 'm.nombre as empresa', 'e.id', 'e.legajo', 'e.nombre', 'e.estado',
                'e.fecha_ingreso', 'e.fecha_egreso', 'e.motivoegreso_id', 'e.comentario_baja')
            ->get() as $r) {
            $erp[(int) $r->cod.':'.(int) $r->legajo] = $r;
        }

        $resumen = ['legajos_conf' => 0, 'sin_cambios' => 0, 'con_cambios' => 0,
            'filas_actualizar' => 0, 'filas_borrar' => 0, 'filas_crear' => 0, 'cabeceras_actualizar' => 0];
        $ops = [];

        foreach ($empleadosConf as $fc) {
            $k = (int) $fc['emp_empresa'].':'.(int) $fc['emp_legajo'];
            $r = $erp[$k] ?? null;
            if ($r === null) {
                continue;
            }
            if ($legajos !== [] && ! in_array((int) $r->legajo, $legajos, true)) {
                continue;
            }
            if ($empresasCodigo !== [] && ! in_array((int) $fc['emp_empresa'], $empresasCodigo, true)) {
                continue;
            }
            $resumen['legajos_conf']++;

            $estadoConf = trim((string) $fc['emp_flag_estado']) === 'B' ? EmpleadoEstados::BAJA : EmpleadoEstados::ACTIVO;
            $periodosConf = $confPeriodos[$k] ?? [];
            if ($periodosConf === []) {
                $fi = VacacionFechaAnita::erpDesdeAnita($fc['emp_fec_ing']);
                if ($fi !== null) {
                    $periodosConf[$fi] = VacacionFechaAnita::erpDesdeAnita($fc['emp_fec_egr']);
                }
            }
            if ($periodosConf === []) {
                continue;
            }
            ksort($periodosConf);

            $filas = DB::table('empleado_ingreso_sueldos')->where('empleado_id', $r->id)
                ->where(fn ($q) => $q->whereNull('tipo_movimiento')->orWhere('tipo_movimiento', '<>', EmpleadoBajaSupport::MOV_ANULA_PROVISORIA))
                ->orderBy('fecha_ingreso')->orderBy('id')->get()->map(fn ($h) => [
                    'id' => (int) $h->id,
                    'ing' => substr((string) $h->fecha_ingreso, 0, 10),
                    'egr' => $h->fecha_egreso ? substr((string) $h->fecha_egreso, 0, 10) : null,
                    'tipo' => (string) $h->tipo_movimiento,
                    'motivo' => $h->motivoegreso_id !== null ? (int) $h->motivoegreso_id : null,
                    'coment' => $h->comentario_baja,
                ])->all();

            $usadas = [];
            $final = [];
            $opsLegajo = ['actualizar' => [], 'borrar' => [], 'crear' => []];

            foreach ($periodosConf as $ci => $ce) {
                $grupo = [];
                foreach ($filas as $i => $fila) {
                    if (isset($usadas[$i])) {
                        continue;
                    }
                    if ($this->seSuperponen($fila['ing'], $fila['egr'], $ci, $ce)) {
                        $grupo[$i] = $fila;
                    }
                }
                foreach (array_keys($grupo) as $i) {
                    $usadas[$i] = true;
                }

                $ingreso = $ci;
                foreach ($grupo as $fila) {
                    if ($fila['ing'] < $ingreso) {
                        $ingreso = $fila['ing'];
                    }
                }
                $tipo = $ce === null
                    ? EmpleadoBajaSupport::MOV_INGRESO
                    : ($estadoConf === EmpleadoEstados::BAJA ? EmpleadoBajaSupport::MOV_BAJA_DEFINITIVA : EmpleadoBajaSupport::MOV_BAJA_PROVISORIA);

                // Motivo/comentario: solo si alguna fila del grupo ya describe ese mismo egreso.
                $motivo = null;
                $coment = null;
                if ($ce !== null) {
                    foreach ($grupo as $fila) {
                        if ($fila['egr'] === $ce && ($fila['motivo'] !== null || $fila['coment'])) {
                            $motivo = $fila['motivo'];
                            $coment = $fila['coment'];
                            break;
                        }
                    }
                }

                $nueva = ['ing' => $ingreso, 'egr' => $ce, 'tipo' => $tipo, 'motivo' => $motivo, 'coment' => $coment];

                if ($grupo === []) {
                    $opsLegajo['crear'][] = $nueva;
                    $final[] = $nueva;

                    continue;
                }

                // Se conserva la fila de ingreso más antiguo; el resto se borra.
                $objetivo = array_values($grupo)[0];
                foreach ($grupo as $fila) {
                    if ($fila['id'] !== $objetivo['id']) {
                        $opsLegajo['borrar'][] = $fila;
                    }
                }
                $cambia = $objetivo['ing'] !== $nueva['ing'] || $objetivo['egr'] !== $nueva['egr']
                    || $objetivo['tipo'] !== $nueva['tipo'] || $objetivo['motivo'] !== $nueva['motivo']
                    || ($objetivo['coment'] ?? null) !== ($nueva['coment'] ?? null);
                if ($cambia) {
                    $opsLegajo['actualizar'][] = ['id' => $objetivo['id'], 'antes' => $objetivo, 'despues' => $nueva];
                }
                $final[] = $nueva;
            }

            // Filas del ERP sin período de conf equivalente: historia previa, se conserva.
            foreach ($filas as $i => $fila) {
                if (! isset($usadas[$i])) {
                    $final[] = ['ing' => $fila['ing'], 'egr' => $fila['egr'], 'tipo' => $fila['tipo'], 'motivo' => $fila['motivo'], 'coment' => $fila['coment']];
                }
            }
            usort($final, fn ($a, $b) => strcmp($a['ing'], $b['ing']));
            $ultimo = end($final);

            $cabActual = [
                'estado' => $r->estado === EmpleadoEstados::BAJA ? EmpleadoEstados::BAJA : EmpleadoEstados::ACTIVO,
                'fecha_ingreso' => $r->fecha_ingreso ? substr((string) $r->fecha_ingreso, 0, 10) : null,
                'fecha_egreso' => $r->fecha_egreso ? substr((string) $r->fecha_egreso, 0, 10) : null,
                'motivoegreso_id' => $r->motivoegreso_id !== null ? (int) $r->motivoegreso_id : null,
                'comentario_baja' => $r->comentario_baja,
            ];
            // Motivo/comentario de la cabecera: se conservan si el egreso no cambia; si cambia, solo
            // se usan los de la fila del último período (que ya describe ese egreso). Sin egreso, nulos.
            $mantieneMotivo = $ultimo['egr'] !== null && $ultimo['egr'] === $cabActual['fecha_egreso'];
            $cab = [
                'estado' => $estadoConf,
                'fecha_ingreso' => $ultimo['ing'],
                'fecha_egreso' => $ultimo['egr'],
                'motivoegreso_id' => $mantieneMotivo ? $cabActual['motivoegreso_id'] : $ultimo['motivo'],
                'comentario_baja' => $mantieneMotivo ? $cabActual['comentario_baja'] : $ultimo['coment'],
            ];
            $cabCambia = $cab != $cabActual;

            if ($opsLegajo['actualizar'] === [] && $opsLegajo['borrar'] === [] && $opsLegajo['crear'] === [] && ! $cabCambia) {
                $resumen['sin_cambios']++;

                continue;
            }

            $resumen['con_cambios']++;
            $resumen['filas_actualizar'] += count($opsLegajo['actualizar']);
            $resumen['filas_borrar'] += count($opsLegajo['borrar']);
            $resumen['filas_crear'] += count($opsLegajo['crear']);
            $resumen['cabeceras_actualizar'] += $cabCambia ? 1 : 0;

            $ops[] = [
                'empleado_id' => (int) $r->id,
                'empresa' => $r->empresa,
                'legajo' => (int) $r->legajo,
                'nombre' => $r->nombre,
                'cabecera_antes' => $cabActual,
                'cabecera' => $cabCambia ? $cab : null,
                'actualizar' => $opsLegajo['actualizar'],
                'borrar' => array_map(fn ($b) => ['id' => $b['id'], 'ing' => $b['ing'] ?? null, 'egr' => $b['egr'] ?? null, 'coment' => $b['coment'] ?? null], $opsLegajo['borrar']),
                'crear' => $opsLegajo['crear'],
            ];
        }

        $hash = hash('sha256', json_encode($ops, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        return ['ops' => $ops, 'resumen' => $resumen, 'hash' => $hash];
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     * @return array<string, int>
     */
    public function ejecutar(array $ops): array
    {
        $res = ['cabeceras' => 0, 'actualizadas' => 0, 'borradas' => 0, 'creadas' => 0];

        return DB::transaction(function () use ($ops, &$res) {
            foreach ($ops as $op) {
                $empleadoId = (int) $op['empleado_id'];

                // Modelos (no Builder) para que dispare el evento y quede en audits cuando corresponda.
                foreach ($op['borrar'] as $b) {
                    Empleado_Ingreso_Sueldos::query()->where('empleado_id', $empleadoId)->whereKey($b['id'])->firstOrFail()->delete();
                    $res['borradas']++;
                }
                foreach ($op['actualizar'] as $u) {
                    $d = $u['despues'];
                    Empleado_Ingreso_Sueldos::query()->where('empleado_id', $empleadoId)->whereKey($u['id'])->firstOrFail()->update([
                        'fecha_ingreso' => $d['ing'],
                        'fecha_egreso' => $d['egr'],
                        'tipo_movimiento' => $d['tipo'],
                        'motivoegreso_id' => $d['motivo'],
                        'comentario_baja' => $d['coment'],
                    ]);
                    $res['actualizadas']++;
                }
                foreach ($op['crear'] as $c) {
                    Empleado_Ingreso_Sueldos::create([
                        'empleado_id' => $empleadoId,
                        'fecha_ingreso' => $c['ing'],
                        'fecha_egreso' => $c['egr'],
                        'tipo_movimiento' => $c['tipo'],
                        'motivoegreso_id' => $c['motivo'],
                        'comentario_baja' => $c['coment'],
                    ]);
                    $res['creadas']++;
                }
                if ($op['cabecera'] !== null) {
                    Empleado_Sueldos::query()->whereKey($empleadoId)->firstOrFail()->update($op['cabecera']);
                    $res['cabeceras']++;
                }
            }

            return $res;
        });
    }

    private function seSuperponen(string $ing1, ?string $egr1, string $ing2, ?string $egr2): bool
    {
        $finito1 = $egr1 ?? '9999-12-31';
        $finito2 = $egr2 ?? '9999-12-31';

        return $ing1 <= $finito2 && $ing2 <= $finito1;
    }
}
