<?php

namespace App\Repositories\Caja;

use App\ApiAnita;
use App\Models\Caja\ConceptoPerdida;
use App\Models\Caja\ImputacionPerdida;
use App\Models\Caja\PerdidaPersonal;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\PerdidaPersonalAnitaNumeracionSupport;
use App\Support\Caja\PerdidaPersonalListadoFiltros;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PerdidaPersonalRepository implements PerdidaPersonalRepositoryInterface
{
    protected string $keyFieldAnita = 'perdm_nro';

    /** @var list<string> */
    private const CAMPOS_LISTA_ANITA = [
        'perdm_nro',
        'perdm_fecha',
        'perdm_ccosto',
        'perdm_cod_imput',
        'perdm_emp_sueldos',
        'perdm_legajo',
        'perdm_emp_superv',
        'perdm_legajo_sup',
        'perdm_turno',
        'perdm_fecha_ing',
        'perdm_hora_ing',
        'perdm_usuario',
        'perdm_estado',
        'perdm_empresa',
        'perdm_fecha_alfa',
        'perdm_concepto',
        'perdm_maquina',
        'perdm_importe',
        'perdm_leyenda', // último: descripción (regla bridge)
    ];

    public function __construct(
        private readonly PerdidaPersonal $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, PerdidaPersonal>
     */
    public function leePerdidaPersonal($filtros, bool $paginar = false)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => PerdidaPersonalListadoFiltros::MODO_TODOS,
                'campo' => 'numero',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => $this->empresaRepository->traeEmpresasAsignadas(),
            ];
        } elseif (! is_array($filtros)) {
            $filtros = PerdidaPersonalListadoFiltros::filtrosVacios();
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('perdida_personal.*')
            ->with([
                'empresa',
                'centrocosto',
                'imputacionPerdida',
                'conceptoPerdida',
                'empleado',
                'supervisor',
                'usuario',
            ]);

        PerdidaPersonalListadoFiltros::aplicarJoinsListado($query, $filtros);
        PerdidaPersonalListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (PerdidaPersonalListadoFiltros::tieneCriteriosAplicados($filtros)) {
            PerdidaPersonalListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('perdida_personal.fecha')
            ->orderByDesc('perdida_personal.numero');

        return $paginar
            ? $query->paginate(10)->appends(PerdidaPersonalListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function create(array $data)
    {
        $payload = $this->normalizarDatos($data, true);

        DB::beginTransaction();
        try {
            $registro = $this->model->create($payload);
            $this->guardarAnita($registro->fresh([
                'empresa',
                'centrocosto',
                'imputacionPerdida',
                'conceptoPerdida',
                'empleado',
                'supervisor',
                'usuario',
            ]));

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return $registro->fresh([
            'empresa',
            'centrocosto',
            'imputacionPerdida',
            'conceptoPerdida',
            'empleado',
            'supervisor',
            'usuario',
        ]);
    }

    public function update(array $data, $id)
    {
        $payload = $this->normalizarDatos($data, false);

        DB::beginTransaction();
        try {
            $registro = $this->model->findOrFail($id);
            $numeroAnita = (int) $registro->numero;
            unset($payload['numero'], $payload['fecha_ingreso'], $payload['hora_ingreso'], $payload['usuario_id'], $payload['estado']);
            $registro->update($payload);
            $fresh = $registro->fresh([
                'empresa',
                'centrocosto',
                'imputacionPerdida',
                'conceptoPerdida',
                'empleado',
                'supervisor',
                'usuario',
            ]);
            $this->actualizarAnita($fresh, $numeroAnita);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return $registro->fresh([
            'empresa',
            'centrocosto',
            'imputacionPerdida',
            'conceptoPerdida',
            'empleado',
            'supervisor',
            'usuario',
        ]);
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try {
            $registro = $this->model->findOrFail($id);
            $numero = (int) $registro->numero;
            $this->eliminarAnita($numero);
            $resultado = $this->model->destroy($id);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return $resultado;
    }

    public function find($id)
    {
        return $this->model->with([
            'empresa',
            'centrocosto',
            'imputacionPerdida',
            'conceptoPerdida',
            'empleado',
            'supervisor',
            'usuario',
        ])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with([
            'empresa',
            'centrocosto',
            'imputacionPerdida',
            'conceptoPerdida',
            'empleado',
            'supervisor',
            'usuario',
        ])->findOrFail($id);
    }

    public function sincronizarConAnita(?int $numero = null, bool $actualizarExistentes = true): array
    {
        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'campos' => implode(', ', self::CAMPOS_LISTA_ANITA),
        ];

        if ($numero !== null && $numero > 0) {
            $data['whereArmado'] = " WHERE {$this->keyFieldAnita} = '".(int) $numero."' ";
        }

        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita)) {
            $ret['errores'][] = 'Anita no devolvió datos válidos para perdmae.';

            return $ret;
        }

        $ret['en_anita'] = count($dataAnita);
        $localesPorNumero = $this->model->newQuery()
            ->get(['id', 'numero'])
            ->keyBy(fn ($r) => (int) $r->numero);

        foreach ($dataAnita as $row) {
            $numeroLocal = (int) ($row->{$this->keyFieldAnita} ?? 0);
            if ($numeroLocal <= 0) {
                continue;
            }

            $existe = $localesPorNumero->has($numeroLocal);

            if ($existe && ! $actualizarExistentes) {
                $ret['omitidos']++;

                continue;
            }

            try {
                $payload = $this->convierteDatosDeAnita($row);
                if ($payload === null) {
                    $ret['errores'][] = "Pérdida Anita {$numeroLocal}: datos incompletos o FK no resuelta.";

                    continue;
                }

                DB::beginTransaction();
                try {
                    if ($existe) {
                        /** @var PerdidaPersonal $registro */
                        $registro = $this->model->newQuery()->where('numero', $numeroLocal)->firstOrFail();
                        $registro->update($payload);
                        $ret['actualizados']++;
                    } else {
                        $this->model->create($payload);
                        $ret['importados']++;
                        $localesPorNumero->put($numeroLocal, (object) ['id' => 0, 'numero' => $numeroLocal]);
                    }
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();

                    throw $e;
                }
            } catch (\Throwable $e) {
                $ret['errores'][] = "Pérdida Anita {$numeroLocal}: ".$e->getMessage();
                Log::warning('PerdidaPersonal: sync fila omitida', [
                    'numero' => $numeroLocal,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $ret;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarDatos(array $data, bool $esAlta): array
    {
        $numero = (int) ($data['numero'] ?? 0);
        if ($esAlta) {
            if ($numero <= 0) {
                $numero = PerdidaPersonalAnitaNumeracionSupport::reservarSiguiente();
            } else {
                // Número manual: alinear numabm si el valor supera el último reservado.
                try {
                    $ultimo = PerdidaPersonalAnitaNumeracionSupport::leerUltimoNumeroNumabm();
                    if ($numero > $ultimo) {
                        PerdidaPersonalAnitaNumeracionSupport::actualizarNumeradorNumabm($numero);
                    }
                } catch (\Throwable $e) {
                    Log::warning('PerdidaPersonal: no se pudo alinear numabm con número manual', [
                        'numero' => $numero,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $payload = [
            'numero' => $numero,
            'fecha' => $data['fecha'] ?? null,
            'empresa_id' => (int) ($data['empresa_id'] ?? 0),
            'centrocosto_id' => (int) ($data['centrocosto_id'] ?? 0) ?: null,
            'imputacion_perdida_id' => (int) ($data['imputacion_perdida_id'] ?? 0),
            'concepto_perdida_id' => (int) ($data['concepto_perdida_id'] ?? 0),
            'empleado_sueldos_id' => (int) ($data['empleado_sueldos_id'] ?? 0),
            'supervisor_empleado_sueldos_id' => (int) ($data['supervisor_empleado_sueldos_id'] ?? 0),
            'turno' => substr(trim((string) ($data['turno'] ?? PerdidaPersonal::TURNO_MANIANA)), 0, 1),
            'leyenda' => ($leyenda = trim((string) ($data['leyenda'] ?? ''))) !== ''
                ? mb_substr($leyenda, 0, 80)
                : null,
            'maquina' => ($maq = trim((string) ($data['maquina'] ?? ''))) !== ''
                ? mb_substr($maq, 0, 10)
                : null,
            'importe' => round((float) ($data['importe'] ?? 0), 2),
        ];

        if ($esAlta) {
            $ahora = Carbon::now();
            $payload['fecha_ingreso'] = $ahora->toDateString();
            $payload['hora_ingreso'] = $ahora->format('H:i:s');
            $payload['usuario_id'] = Auth::id();
            $payload['estado'] = PerdidaPersonal::ESTADO_PENDIENTE;
        }

        return $payload;
    }

    private function guardarAnita(PerdidaPersonal $registro): bool
    {
        $vals = $this->convierteDatosParaAnita($registro);
        $apiAnita = new ApiAnita();
        $payload = [
            'tabla' => $this->tablaAnita(),
            'acc' => 'insert',
            'sistema' => $this->sistemaAnita(),
            'campos' => implode(', ', self::CAMPOS_LISTA_ANITA),
            'valores' => implode(', ', [
                "'".(int) $vals['perdm_nro']."'",
                "'".(int) $vals['perdm_fecha']."'",
                "'".$this->escaparAnita((string) $vals['perdm_ccosto'])."'",
                "'".(int) $vals['perdm_cod_imput']."'",
                "'".(int) $vals['perdm_emp_sueldos']."'",
                "'".(int) $vals['perdm_legajo']."'",
                "'".(int) $vals['perdm_emp_superv']."'",
                "'".(int) $vals['perdm_legajo_sup']."'",
                "'".$this->escaparAnita((string) $vals['perdm_turno'])."'",
                "'".(int) $vals['perdm_fecha_ing']."'",
                "'".$this->escaparAnita((string) $vals['perdm_hora_ing'])."'",
                "'".(int) $vals['perdm_usuario']."'",
                "'".$this->escaparAnita((string) $vals['perdm_estado'])."'",
                "'".(int) $vals['perdm_empresa']."'",
                "'".$this->escaparAnita((string) $vals['perdm_fecha_alfa'])."'",
                "'".(int) $vals['perdm_concepto']."'",
                "'".$this->escaparAnita((string) $vals['perdm_maquina'])."'",
                "'".number_format((float) $vals['perdm_importe'], 2, '.', '')."'",
                "'".$this->escaparAnita((string) $vals['perdm_leyenda'])."'",
            ]),
        ];

        return (bool) $apiAnita->apiCallEscritura($payload);
    }

    private function actualizarAnita(PerdidaPersonal $registro, int $numeroAnita): bool
    {
        $vals = $this->convierteDatosParaAnita($registro);
        $apiAnita = new ApiAnita();
        $payload = [
            'acc' => 'update',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'valores' => implode(', ', [
                "perdm_fecha = '".(int) $vals['perdm_fecha']."'",
                "perdm_ccosto = '".$this->escaparAnita((string) $vals['perdm_ccosto'])."'",
                "perdm_cod_imput = '".(int) $vals['perdm_cod_imput']."'",
                "perdm_emp_sueldos = '".(int) $vals['perdm_emp_sueldos']."'",
                "perdm_legajo = '".(int) $vals['perdm_legajo']."'",
                "perdm_emp_superv = '".(int) $vals['perdm_emp_superv']."'",
                "perdm_legajo_sup = '".(int) $vals['perdm_legajo_sup']."'",
                "perdm_turno = '".$this->escaparAnita((string) $vals['perdm_turno'])."'",
                "perdm_fecha_ing = '".(int) $vals['perdm_fecha_ing']."'",
                "perdm_hora_ing = '".$this->escaparAnita((string) $vals['perdm_hora_ing'])."'",
                "perdm_usuario = '".(int) $vals['perdm_usuario']."'",
                "perdm_estado = '".$this->escaparAnita((string) $vals['perdm_estado'])."'",
                "perdm_empresa = '".(int) $vals['perdm_empresa']."'",
                "perdm_fecha_alfa = '".$this->escaparAnita((string) $vals['perdm_fecha_alfa'])."'",
                "perdm_concepto = '".(int) $vals['perdm_concepto']."'",
                "perdm_maquina = '".$this->escaparAnita((string) $vals['perdm_maquina'])."'",
                "perdm_importe = '".number_format((float) $vals['perdm_importe'], 2, '.', '')."'",
                "perdm_leyenda = '".$this->escaparAnita((string) $vals['perdm_leyenda'])."'",
            ]),
            'whereArmado' => " WHERE {$this->keyFieldAnita} = '".(int) $numeroAnita."' ",
        ];

        return (bool) $apiAnita->apiCallEscritura($payload);
    }

    private function eliminarAnita(int $numero): bool
    {
        $apiAnita = new ApiAnita();
        $payload = [
            'acc' => 'delete',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'whereArmado' => " WHERE {$this->keyFieldAnita} = '".(int) $numero."' ",
        ];

        return (bool) $apiAnita->apiCallEscritura($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function convierteDatosParaAnita(PerdidaPersonal $registro): array
    {
        $fecha = $registro->fecha instanceof Carbon
            ? $registro->fecha
            : Carbon::parse((string) $registro->fecha);

        $fechaIng = $registro->fecha_ingreso
            ? ($registro->fecha_ingreso instanceof Carbon
                ? $registro->fecha_ingreso
                : Carbon::parse((string) $registro->fecha_ingreso))
            : Carbon::now();

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $registro->empresa_id);

        $empleado = $registro->empleado ?? Empleado_Sueldos::query()->find($registro->empleado_sueldos_id);
        $supervisor = $registro->supervisor ?? Empleado_Sueldos::query()->find($registro->supervisor_empleado_sueldos_id);
        $centrocosto = $registro->centrocosto ?? Centrocosto::query()->find($registro->centrocosto_id);
        $concepto = $registro->conceptoPerdida ?? ConceptoPerdida::query()->find($registro->concepto_perdida_id);
        $imputacion = $registro->imputacionPerdida ?? ImputacionPerdida::query()->find($registro->imputacion_perdida_id);

        $empSueldosAnita = $empleado
            ? SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $empleado->empresa_id)
            : $empresaAnita;
        $empSupervAnita = $supervisor
            ? SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $supervisor->empresa_id)
            : $empresaAnita;

        return [
            'perdm_nro' => (int) $registro->numero,
            'perdm_fecha' => (int) $fecha->format('Ymd'),
            'perdm_ccosto' => (string) ($centrocosto->codigo ?? ''),
            'perdm_cod_imput' => (int) ($imputacion->codigo ?? 0),
            'perdm_emp_sueldos' => (int) $empSueldosAnita,
            'perdm_legajo' => (int) ($empleado->legajo ?? 0),
            'perdm_emp_superv' => (int) $empSupervAnita,
            'perdm_legajo_sup' => (int) ($supervisor->legajo ?? 0),
            'perdm_turno' => substr((string) ($registro->turno ?? '1'), 0, 1),
            'perdm_fecha_ing' => (int) $fechaIng->format('Ymd'),
            'perdm_hora_ing' => mb_substr((string) ($registro->hora_ingreso ?? Carbon::now()->format('H:i:s')), 0, 8),
            'perdm_usuario' => (int) ($registro->usuario_id ?? 0),
            'perdm_estado' => substr((string) ($registro->estado ?? PerdidaPersonal::ESTADO_PENDIENTE), 0, 1),
            'perdm_empresa' => (int) $empresaAnita,
            'perdm_fecha_alfa' => $fecha->format('d/m/y'),
            'perdm_concepto' => (int) ($concepto->codigo ?? 0),
            'perdm_maquina' => mb_substr(trim((string) ($registro->maquina ?? '')), 0, 10),
            'perdm_importe' => (float) $registro->importe,
            'perdm_leyenda' => mb_substr(trim((string) ($registro->leyenda ?? '')), 0, 80),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function convierteDatosDeAnita(object $row): ?array
    {
        $numero = (int) ($row->perdm_nro ?? 0);
        if ($numero <= 0) {
            return null;
        }

        $empresaId = $this->resolverEmpresaIdDesdeAnita((int) ($row->perdm_empresa ?? 0));
        if ($empresaId === null) {
            throw new \RuntimeException('empresa Anita '.((int) ($row->perdm_empresa ?? 0)).' no encontrada en ERP');
        }

        $centrocostoId = $this->resolverCentrocostoId(trim((string) ($row->perdm_ccosto ?? '')));
        if ($centrocostoId === null && trim((string) ($row->perdm_ccosto ?? '')) !== '') {
            throw new \RuntimeException('centro de costo Anita "'.trim((string) $row->perdm_ccosto).'" no encontrado');
        }

        $imputacionId = $this->resolverImputacionId((int) ($row->perdm_cod_imput ?? 0));
        if ($imputacionId === null) {
            throw new \RuntimeException('imputación Anita '.((int) ($row->perdm_cod_imput ?? 0)).' no encontrada');
        }

        $conceptoId = $this->resolverConceptoId((int) ($row->perdm_concepto ?? 0));
        if ($conceptoId === null) {
            throw new \RuntimeException('concepto Anita '.((int) ($row->perdm_concepto ?? 0)).' no encontrado');
        }

        $empEmpresaAnita = (int) ($row->perdm_emp_sueldos ?? 0);
        $empEmpresaId = $this->resolverEmpresaIdDesdeAnita($empEmpresaAnita) ?? $empresaId;
        $empleadoId = $this->resolverEmpleadoId($empEmpresaId, (int) ($row->perdm_legajo ?? 0));
        if ($empleadoId === null) {
            throw new \RuntimeException(
                'empleado empresa Anita '.$empEmpresaAnita.' legajo '.((int) ($row->perdm_legajo ?? 0)).' no encontrado'
            );
        }

        $supEmpresaAnita = (int) ($row->perdm_emp_superv ?? 0);
        $supEmpresaId = $this->resolverEmpresaIdDesdeAnita($supEmpresaAnita) ?? $empresaId;
        $supervisorId = $this->resolverEmpleadoId($supEmpresaId, (int) ($row->perdm_legajo_sup ?? 0));
        if ($supervisorId === null) {
            throw new \RuntimeException(
                'supervisor empresa Anita '.$supEmpresaAnita.' legajo '.((int) ($row->perdm_legajo_sup ?? 0)).' no encontrado'
            );
        }

        $fecha = $this->parseFechaAnitaYmd((int) ($row->perdm_fecha ?? 0));
        if ($fecha === null) {
            throw new \RuntimeException('fecha Anita inválida ('.$row->perdm_fecha.')');
        }

        $fechaIng = $this->parseFechaAnitaYmd((int) ($row->perdm_fecha_ing ?? 0));

        return [
            'numero' => $numero,
            'fecha' => $fecha,
            'empresa_id' => $empresaId,
            'centrocosto_id' => $centrocostoId,
            'imputacion_perdida_id' => $imputacionId,
            'concepto_perdida_id' => $conceptoId,
            'empleado_sueldos_id' => $empleadoId,
            'supervisor_empleado_sueldos_id' => $supervisorId,
            'turno' => substr(trim((string) ($row->perdm_turno ?? '1')), 0, 1) ?: '1',
            'fecha_ingreso' => $fechaIng,
            'hora_ingreso' => mb_substr(trim((string) ($row->perdm_hora_ing ?? '')), 0, 8) ?: null,
            'usuario_id' => $this->resolverUsuarioId((int) ($row->perdm_usuario ?? 0)),
            'estado' => substr(trim((string) ($row->perdm_estado ?? PerdidaPersonal::ESTADO_PENDIENTE)), 0, 1) ?: PerdidaPersonal::ESTADO_PENDIENTE,
            'leyenda' => ($l = trim((string) ($row->perdm_leyenda ?? ''))) !== '' ? mb_substr($l, 0, 80) : null,
            'maquina' => ($m = trim((string) ($row->perdm_maquina ?? ''))) !== '' ? mb_substr($m, 0, 10) : null,
            'importe' => round((float) ($row->perdm_importe ?? 0), 2),
        ];
    }

    private function resolverEmpresaIdDesdeAnita(int $codigoAnita): ?int
    {
        if ($codigoAnita <= 0) {
            return null;
        }

        $id = Empresa::query()->where('codigo', (string) $codigoAnita)->value('id');
        if ($id) {
            return (int) $id;
        }

        // Fallback: si el código ERP coincide con el id
        $porId = Empresa::query()->find($codigoAnita);

        return $porId !== null ? (int) $porId->id : null;
    }

    private function resolverCentrocostoId(string $codigo): ?int
    {
        if ($codigo === '') {
            return null;
        }

        $id = Centrocosto::query()->where('codigo', $codigo)->value('id');

        return $id ? (int) $id : null;
    }

    private function resolverImputacionId(int $codigo): ?int
    {
        if ($codigo <= 0) {
            return null;
        }

        $id = ImputacionPerdida::query()->where('codigo', $codigo)->value('id');

        return $id ? (int) $id : null;
    }

    private function resolverConceptoId(int $codigo): ?int
    {
        if ($codigo <= 0) {
            return null;
        }

        $id = ConceptoPerdida::query()->where('codigo', $codigo)->value('id');

        return $id ? (int) $id : null;
    }

    private function resolverEmpleadoId(int $empresaId, int $legajo): ?int
    {
        if ($empresaId <= 0 || $legajo <= 0) {
            return null;
        }

        $id = Empleado_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->where('legajo', $legajo)
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Usuario Anita (perdm_usuario) es entero. El modelo Usuario no tiene campo codigo;
     * se intenta match por id; si no existe → null.
     */
    private function resolverUsuarioId(int $codigoAnita): ?int
    {
        if ($codigoAnita <= 0) {
            return null;
        }

        $usuario = Usuario::query()->find($codigoAnita);

        return $usuario !== null ? (int) $usuario->id : null;
    }

    private function parseFechaAnitaYmd(int $ymd): ?string
    {
        if ($ymd < 19000101 || $ymd > 29991231) {
            return null;
        }

        $s = (string) $ymd;
        try {
            return Carbon::createFromFormat('Ymd', $s)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function escaparAnita(string $valor): string
    {
        return str_replace("'", "''", trim($valor));
    }

    private function sistemaAnita(): string
    {
        return (string) config('perdida_personal_anita.sistema', 'caja');
    }

    private function tablaAnita(): string
    {
        return (string) config('perdida_personal_anita.tabla', 'perdmae');
    }
}
