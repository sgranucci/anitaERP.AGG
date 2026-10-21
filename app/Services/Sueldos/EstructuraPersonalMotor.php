<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\EstructuraPersonalCubo;
use App\Support\Sueldos\EstructuraPersonalPresentacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Headcount al corte: una lectura de empleados alimenta el tablero y el Excel.
 *
 * Vigente = ingresó en o antes del corte y no egresó en o antes del corte.
 * Las altas provisorias no entran. La dotación histórica sale de las fechas,
 * no del estado de hoy.
 *
 * Una persona (documento) cuenta una sola vez. Si tiene varios legajos activos,
 * se queda el alta más antigua y, si empatan, la empresa de menor id.
 */
class EstructuraPersonalMotor
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function reporte(string $corteYmd, int $empresaId = 0): array
    {
        $corte = CarbonImmutable::parse($corteYmd)->startOfDay();
        $inicioMes = $corte->startOfMonth()->subDay();
        $inicioAnio = $corte->startOfYear()->subDay();
        $desdeAnio = $corte->startOfYear();

        $query = Empleado_Sueldos::query()
            ->select([
                'id', 'legajo', 'documento', 'nombre', 'empresa_id', 'categoria_id', 'centrocosto_id',
                'sindicato_id', 'motivoegreso_id', 'lugartrabajo_id', 'fecha_ingreso',
                'fecha_egreso', 'fecha_nacimiento', 'sexo', 'email', 'estado', 'confidencial',
            ])
            ->with([
                'empresa:id,nombre',
                'categoria:id,descripcion',
                'centrocosto:id,nombre',
                'sindicato:id,descripcion',
                'motivoegreso:id,descripcion',
                'lugartrabajo:id,nombre',
            ])
            ->where('estado', '!=', EmpleadoEstados::PROVISORIO)
            ->where(function ($q) use ($corte, $desdeAnio) {
                $q->where(function ($activo) use ($corte) {
                    $activo->where(function ($ingreso) use ($corte) {
                        $ingreso->whereNull('fecha_ingreso')
                            ->orWhere('fecha_ingreso', '<=', $corte->toDateString());
                    })->where(function ($egreso) use ($corte) {
                        $egreso->whereNull('fecha_egreso')
                            ->orWhere('fecha_egreso', '>', $corte->toDateString());
                    });
                })->orWhere(function ($baja) use ($desdeAnio, $corte) {
                    $baja->where('fecha_egreso', '>=', $desdeAnio->toDateString())
                        ->where('fecha_egreso', '<=', $corte->toDateString());
                })->orWhere(function ($alta) use ($desdeAnio, $corte) {
                    $alta->where('fecha_ingreso', '>=', $desdeAnio->toDateString())
                        ->where('fecha_ingreso', '<=', $corte->toDateString());
                });
            });

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empleado_sueldos.empresa_id');

        $grupos = [];
        foreach ($query->get() as $empleado) {
            $documento = trim((string) $empleado->documento);
            $clave = $documento !== '' ? $documento : 'id:'.$empleado->id;
            $grupos[$clave][] = $empleado;
        }

        $personas = [];
        $bajas = [];
        $altas = [];
        $inicioMesPorCc = [];
        $inicioAnioPorCc = [];
        $inicioMesTotal = 0;
        $inicioAnioTotal = 0;
        $navegacionCuentas = [];

        foreach ($grupos as $filas) {
            $alCorte = $this->elegirVigente($filas, $corte);
            $alMes = $this->elegirVigente($filas, $inicioMes);
            $alAnio = $this->elegirVigente($filas, $inicioAnio);

            if ($alCorte !== null) {
                $pieza = $this->pieza($alCorte, $corte);
                $navegacionCuentas[(int) $pieza['empresa_id']]['nombre'] = $pieza['empresa'];
                $navegacionCuentas[(int) $pieza['empresa_id']]['n'] = ($navegacionCuentas[(int) $pieza['empresa_id']]['n'] ?? 0) + 1;
                if ($empresaId === 0 || (int) $alCorte->empresa_id === $empresaId) {
                    $personas[] = $pieza;
                }
            }
            if ($alMes !== null && ($empresaId === 0 || (int) $alMes->empresa_id === $empresaId)) {
                $ccId = (int) ($alMes->centrocosto_id ?? 0);
                $inicioMesPorCc[$ccId] = ($inicioMesPorCc[$ccId] ?? 0) + 1;
                $inicioMesTotal++;
            }
            if ($alAnio !== null && ($empresaId === 0 || (int) $alAnio->empresa_id === $empresaId)) {
                $ccId = (int) ($alAnio->centrocosto_id ?? 0);
                $inicioAnioPorCc[$ccId] = ($inicioAnioPorCc[$ccId] ?? 0) + 1;
                $inicioAnioTotal++;
            }

            if ($alCorte === null) {
                $baja = $this->elegirBaja($filas, $desdeAnio, $corte);
                if ($baja !== null && ($empresaId === 0 || (int) $baja->empresa_id === $empresaId)) {
                    $egreso = $this->fecha($baja->fecha_egreso);
                    $bajas[] = array_merge($this->pieza($baja, $corte), [
                        'motivo' => trim((string) ($baja->motivoegreso->descripcion ?? '')) ?: 'Sin motivo',
                        'fecha' => $egreso->toDateString(),
                        'fecha_label' => $egreso->format('d/m/Y'),
                        'mes' => $egreso->month,
                    ]);
                }
            }

            $alta = $this->elegirAltaDelMes($filas, $corte);
            if ($alta !== null && ($empresaId === 0 || (int) $alta->empresa_id === $empresaId)) {
                $ingreso = $this->fecha($alta->fecha_ingreso);
                $altas[] = array_merge($this->pieza($alta, $corte), [
                    'fecha' => $ingreso->toDateString(),
                    'fecha_label' => $ingreso->format('d/m/Y'),
                ]);
            }
        }

        usort($personas, fn (array $a, array $b): int => strcmp($a['nombre'], $b['nombre']));
        usort($bajas, fn (array $a, array $b): int => strcmp($b['fecha'], $a['fecha']) ?: strcmp($a['nombre'], $b['nombre']));
        usort($altas, fn (array $a, array $b): int => strcmp($b['fecha'], $a['fecha']) ?: strcmp($a['nombre'], $b['nombre']));

        $reporte = EstructuraPersonalCubo::desde([
            'corte' => $corte,
            'empresa_id' => $empresaId,
            'personas' => $personas,
            'bajas' => $bajas,
            'altas' => $altas,
            'inicio_mes_por_cc' => $inicioMesPorCc,
            'inicio_anio_por_cc' => $inicioAnioPorCc,
            'inicio_mes_total' => $inicioMesTotal,
            'inicio_anio_total' => $inicioAnioTotal,
            'presupuesto' => $this->presupuesto($corte->year, $empresaId),
        ]);
        $reporte['navegacion'] = $this->navegacionDesdeCuentas($navegacionCuentas);
        $reporte['personas_unicas'] = true;

        return $reporte;
    }

    /**
     * @param  array<int, array{nombre: string, n: int}>  $cuentas
     * @return list<array{id: int, nombre: string, sigla: string, dotacion: int, pct: float}>
     */
    private function navegacionDesdeCuentas(array $cuentas): array
    {
        $total = 0;
        foreach ($cuentas as $cuenta) {
            $total += (int) $cuenta['n'];
        }
        $salida = [];
        foreach ($cuentas as $id => $cuenta) {
            $nombre = trim((string) $cuenta['nombre']);
            $dotacion = (int) $cuenta['n'];
            $salida[] = [
                'id' => (int) $id,
                'nombre' => $nombre !== '' ? $nombre : 'Sin empresa',
                'sigla' => EstructuraPersonalPresentacion::sigla($nombre),
                'dotacion' => $dotacion,
                'pct' => $total > 0 ? round($dotacion * 100 / $total, 1) : 0.0,
            ];
        }
        usort($salida, function (array $a, array $b): int {
            $orden = EstructuraPersonalPresentacion::ordenSigla($a['sigla']) <=> EstructuraPersonalPresentacion::ordenSigla($b['sigla']);

            return $orden !== 0 ? $orden : $b['dotacion'] <=> $a['dotacion'];
        });

        return $salida;
    }

    /**
     * @param  list<Empleado_Sueldos>  $filas
     */
    private function elegirVigente(array $filas, CarbonImmutable $fecha): ?Empleado_Sueldos
    {
        $vigentes = [];
        foreach ($filas as $fila) {
            if ($this->vigente($this->fecha($fila->fecha_ingreso), $this->fecha($fila->fecha_egreso), (string) $fila->estado, $fecha)) {
                $vigentes[] = $fila;
            }
        }

        return $this->preferirAltaAntigua($vigentes);
    }

    /**
     * @param  list<Empleado_Sueldos>  $filas
     */
    private function elegirBaja(array $filas, CarbonImmutable $desde, CarbonImmutable $corte): ?Empleado_Sueldos
    {
        $candidatas = [];
        foreach ($filas as $fila) {
            $egreso = $this->fecha($fila->fecha_egreso);
            if ($egreso !== null && $egreso->greaterThanOrEqualTo($desde) && $egreso->lessThanOrEqualTo($corte)) {
                $candidatas[] = $fila;
            }
        }
        usort($candidatas, function (Empleado_Sueldos $a, Empleado_Sueldos $b): int {
            return strcmp((string) $b->fecha_egreso, (string) $a->fecha_egreso);
        });

        return $candidatas[0] ?? null;
    }

    /**
     * @param  list<Empleado_Sueldos>  $filas
     */
    private function elegirAltaDelMes(array $filas, CarbonImmutable $corte): ?Empleado_Sueldos
    {
        $ordenadas = $filas;
        usort($ordenadas, fn (Empleado_Sueldos $a, Empleado_Sueldos $b): int => $this->criterioAltaAntigua($a, $b));
        $primera = $ordenadas[0] ?? null;
        $ingreso = $primera ? $this->fecha($primera->fecha_ingreso) : null;
        if ($ingreso === null || $ingreso->year !== $corte->year || $ingreso->month !== $corte->month || $ingreso->greaterThan($corte)) {
            return null;
        }

        return $primera;
    }

    /**
     * @param  list<Empleado_Sueldos>  $filas
     */
    private function preferirAltaAntigua(array $filas): ?Empleado_Sueldos
    {
        if ($filas === []) {
            return null;
        }
        usort($filas, fn (Empleado_Sueldos $a, Empleado_Sueldos $b): int => $this->criterioAltaAntigua($a, $b));

        return $filas[0];
    }

    private function criterioAltaAntigua(Empleado_Sueldos $a, Empleado_Sueldos $b): int
    {
        $ingresoA = $this->fecha($a->fecha_ingreso)?->toDateString() ?? '9999-99-99';
        $ingresoB = $this->fecha($b->fecha_ingreso)?->toDateString() ?? '9999-99-99';

        return [$ingresoA, (int) $a->empresa_id, (int) $a->id] <=> [$ingresoB, (int) $b->empresa_id, (int) $b->id];
    }

    /**
     * @return array<string, int>
     */
    private function presupuesto(int $anio, int $empresaId): array
    {
        if (! Schema::hasTable('estructura_personal_presupuesto')) {
            return [];
        }

        $query = DB::table('estructura_personal_presupuesto')->where('anio', $anio);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $mapa = [];
        foreach ($query->get(['empresa_id', 'centrocosto_id', 'cantidad']) as $fila) {
            $mapa[(int) $fila->empresa_id.'|'.(int) $fila->centrocosto_id] = (int) $fila->cantidad;
        }

        return $mapa;
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return CarbonImmutable::parse($valor)->startOfDay();
    }

    private function vigente(?CarbonImmutable $ingreso, ?CarbonImmutable $egreso, string $estado, CarbonImmutable $fecha): bool
    {
        if ($estado === EmpleadoEstados::PROVISORIO) {
            return false;
        }
        if ($estado === EmpleadoEstados::BAJA && $egreso === null) {
            return false;
        }
        if ($ingreso !== null && $ingreso->greaterThan($fecha)) {
            return false;
        }
        if ($egreso !== null && $egreso->lessThanOrEqualTo($fecha)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function pieza(Empleado_Sueldos $empleado, CarbonImmutable $corte): array
    {
        $empresa = trim((string) ($empleado->empresa->nombre ?? ''));
        $centro = trim((string) ($empleado->centrocosto->nombre ?? ''));
        if ($centro === '') {
            $centro = 'Sin centro de costo';
        }
        $direccion = EstructuraPersonalPresentacion::direccion($centro);
        $sindicato = trim((string) ($empleado->sindicato->descripcion ?? ''));
        $categoria = trim((string) ($empleado->categoria->descripcion ?? ''));
        $ingreso = $this->fecha($empleado->fecha_ingreso);
        $nacimiento = $this->fecha($empleado->fecha_nacimiento);
        $anios = null;
        if ($ingreso !== null && $ingreso->lessThanOrEqualTo($corte)) {
            $anios = round($ingreso->diffInDays($corte) / 365.25, 1);
        }
        $edad = null;
        if ($nacimiento !== null && $nacimiento->lessThanOrEqualTo($corte)) {
            $edad = (int) $nacimiento->diffInYears($corte);
        }

        return [
            'id' => (int) $empleado->id,
            'legajo' => (int) $empleado->legajo,
            'nombre' => trim((string) $empleado->nombre),
            'empresa_id' => (int) $empleado->empresa_id,
            'empresa' => $empresa !== '' ? $empresa : 'Sin empresa',
            'sigla' => EstructuraPersonalPresentacion::sigla($empresa),
            'categoria' => $categoria !== '' ? $categoria : 'Sin categoría',
            'cc_id' => (int) ($empleado->centrocosto_id ?? 0),
            'cc' => $centro,
            'direccion_id' => $direccion['id'],
            'direccion' => $direccion['nombre'],
            'badge' => $direccion['badge'],
            'sindicato' => $sindicato !== '' ? $sindicato : 'Sin dato',
            'convenio' => EstructuraPersonalPresentacion::convenio($sindicato),
            'sexo' => EstructuraPersonalPresentacion::sexo($empleado->sexo),
            'edad' => $edad,
            'anios' => $anios,
            'tramo_edad' => EstructuraPersonalPresentacion::tramoEdad($edad),
            'tramo_antiguedad' => EstructuraPersonalPresentacion::tramoAntiguedad($anios),
            'ingreso' => $ingreso?->format('d/m/Y') ?? '',
            'email' => trim((string) ($empleado->email ?? '')),
            'lugar' => trim((string) ($empleado->lugartrabajo->nombre ?? '')),
            'confidencial' => (bool) $empleado->confidencial,
        ];
    }
}
