<?php

namespace App\Repositories\Caja;

use App\ApiAnita;
use App\Models\Caja\AperturaGasto;
use App\Models\Caja\AperturaGastoEmpresa;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\AperturaGastoListadoFiltros;
use DB;
use Exception;
use InvalidArgumentException;

class AperturaGastoRepository implements AperturaGastoRepositoryInterface
{
    protected string $keyFieldAnita = 'apg_concepto';

    public function __construct(
        private readonly AperturaGasto $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, AperturaGasto>
     */
    public function leeAperturaGasto($filtros, bool $paginar = false)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => AperturaGastoListadoFiltros::MODO_TODOS,
                'campo' => 'codigo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => $this->empresaRepository->traeEmpresasAsignadas(),
            ];
        } elseif (! is_array($filtros)) {
            $filtros = AperturaGastoListadoFiltros::filtrosVacios();
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('apertura_gasto.*')
            ->with([
                'empresas.empresa',
                'empresas.cuentacontable',
                'empresas.cuentacontableContrapartida',
                'empresas.centrocosto',
            ]);

        AperturaGastoListadoFiltros::aplicarJoinsListado($query, $filtros);
        AperturaGastoListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (AperturaGastoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            AperturaGastoListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('apertura_gasto.codigo');

        return $paginar
            ? $query->paginate(10)->appends(AperturaGastoListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function create(array $data)
    {
        [$cabecera, $lineas] = $this->separarCabeceraYLineas($data);

        DB::beginTransaction();
        try {
            $registro = $this->model->create($cabecera);
            $this->sincronizarLineasEmpresa((int) $registro->id, $lineas);
            $this->guardarAnita($this->payloadAnitaDesdeRegistro($registro->fresh(['empresas'])));

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return $registro->fresh(['empresas.empresa', 'empresas.cuentacontable', 'empresas.cuentacontableContrapartida', 'empresas.centrocosto']);
    }

    public function update(array $data, $id)
    {
        [$cabecera, $lineas] = $this->separarCabeceraYLineas($data);

        DB::beginTransaction();
        try {
            $registro = $this->model->findOrFail($id);
            $codigoAnita = (int) $registro->codigo;
            $registro->update($cabecera);
            $this->sincronizarLineasEmpresa((int) $registro->id, $lineas);
            $fresh = $registro->fresh(['empresas']);
            $payload = $this->payloadAnitaDesdeRegistro($fresh);
            $payload['codigo'] = $codigoAnita;
            $this->actualizarAnita($payload, $codigoAnita);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return $registro->fresh(['empresas.empresa', 'empresas.cuentacontable', 'empresas.cuentacontableContrapartida', 'empresas.centrocosto']);
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try {
            $registro = $this->model->findOrFail($id);
            $codigo = (int) $registro->codigo;
            $this->eliminarAnita($codigo);
            AperturaGastoEmpresa::query()->where('apertura_gasto_id', $id)->delete();
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
            'empresas.empresa',
            'empresas.cuentacontable',
            'empresas.cuentacontableContrapartida',
            'empresas.centrocosto',
        ])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with([
            'empresas.empresa',
            'empresas.cuentacontable',
            'empresas.cuentacontableContrapartida',
            'empresas.centrocosto',
        ])->findOrFail($id);
    }

    public function sincronizarConAnita(?int $codigo = null): array
    {
        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'campos' => implode(', ', [
                'apg_concepto',
                'apg_desc',
                'apg_cta_contable',
                'apg_cta_contrap',
                'apg_ccosto',
            ]),
        ];

        if ($codigo !== null && $codigo > 0) {
            $data['whereArmado'] = " WHERE {$this->keyFieldAnita} = '".(int) $codigo."' ";
        }

        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita)) {
            $ret['errores'][] = 'Anita no devolvió datos válidos para apgasto.';

            return $ret;
        }

        $ret['en_anita'] = count($dataAnita);
        $codigosLocales = $this->model->newQuery()->pluck('codigo')->map(fn ($c) => (int) $c)->all();

        foreach ($dataAnita as $row) {
            $codigoLocal = (int) ($row->{$this->keyFieldAnita} ?? 0);
            if ($codigoLocal <= 0) {
                continue;
            }

            if (in_array($codigoLocal, $codigosLocales, true)) {
                $ret['omitidos']++;

                continue;
            }

            try {
                $estado = $this->traerRegistroDeAnita($codigoLocal);
                if ($estado === 'importado') {
                    $ret['importados']++;
                    $codigosLocales[] = $codigoLocal;
                } else {
                    $ret['errores'][] = "Concepto Anita {$codigoLocal}: {$estado}.";
                }
            } catch (\Throwable $e) {
                $ret['errores'][] = "Concepto Anita {$codigoLocal}: ".$e->getMessage();
            }
        }

        return $ret;
    }

    public function traerRegistroDeAnita(int $codigo): string
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'campos' => implode(', ', [
                'apg_concepto',
                'apg_desc',
                'apg_cta_contable',
                'apg_cta_contrap',
                'apg_ccosto',
            ]),
            'whereArmado' => " WHERE {$this->keyFieldAnita} = '".(int) $codigo."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return 'no_encontrado';
        }

        $payload = $this->convierteDatosDeAnita($dataAnita[0]);
        if ($payload === null) {
            return 'sin_empresa';
        }

        $lineas = $payload['lineas'];
        unset($payload['lineas']);

        DB::beginTransaction();
        try {
            $registro = $this->model->create($payload);
            $this->sincronizarLineasEmpresa((int) $registro->id, $lineas);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return 'importado';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function separarCabeceraYLineas(array $data): array
    {
        $cabecera = [
            'codigo' => (int) ($data['codigo'] ?? 0),
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'estado' => (string) ($data['estado'] ?? AperturaGasto::ESTADO_ACTIVO),
        ];

        $lineas = $this->normalizarLineasEmpresa($data);

        if ($lineas === []) {
            throw new InvalidArgumentException('Debe cargar al menos una cuenta por empresa.');
        }

        return [$cabecera, $lineas];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function normalizarLineasEmpresa(array $data): array
    {
        $empresaIds = array_values((array) ($data['empresa_ids'] ?? []));
        $cuentaIds = array_values((array) ($data['cuentacontable_ids'] ?? []));
        $contrapIds = array_values((array) ($data['cuentacontable_contrapartida_ids'] ?? []));
        $ccIds = array_values((array) ($data['centrocosto_ids'] ?? []));

        $lineas = [];
        $vistos = [];
        $n = max(count($empresaIds), count($cuentaIds));

        for ($i = 0; $i < $n; $i++) {
            $empresaId = (int) ($empresaIds[$i] ?? 0);
            $cuentaId = (int) ($cuentaIds[$i] ?? 0);
            if ($empresaId <= 0 || $cuentaId <= 0) {
                continue;
            }

            if (isset($vistos[$empresaId])) {
                throw new InvalidArgumentException('Hay empresas duplicadas en la grilla de cuentas.');
            }
            $vistos[$empresaId] = true;

            $contrap = (int) ($contrapIds[$i] ?? 0);
            $cc = (int) ($ccIds[$i] ?? 0);

            $lineas[] = [
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'cuentacontable_contrapartida_id' => $contrap > 0 ? $contrap : null,
                'centrocosto_id' => $cc > 0 ? $cc : null,
            ];
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function sincronizarLineasEmpresa(int $aperturaGastoId, array $lineas): void
    {
        AperturaGastoEmpresa::query()->where('apertura_gasto_id', $aperturaGastoId)->delete();

        foreach ($lineas as $linea) {
            AperturaGastoEmpresa::query()->create([
                'apertura_gasto_id' => $aperturaGastoId,
                'empresa_id' => (int) $linea['empresa_id'],
                'cuentacontable_id' => (int) $linea['cuentacontable_id'],
                'cuentacontable_contrapartida_id' => $linea['cuentacontable_contrapartida_id'] ?? null,
                'centrocosto_id' => $linea['centrocosto_id'] ?? null,
            ]);
        }
    }

    /**
     * Anita solo admite un set de cuentas: se toma la primera línea (empresa menor).
     *
     * @return array<string, mixed>
     */
    private function payloadAnitaDesdeRegistro(AperturaGasto $registro): array
    {
        $linea = $registro->empresas->sortBy('empresa_id')->first();

        return [
            'codigo' => (int) $registro->codigo,
            'nombre' => (string) $registro->nombre,
            'cuentacontable_id' => (int) ($linea->cuentacontable_id ?? 0),
            'cuentacontable_contrapartida_id' => $linea->cuentacontable_contrapartida_id ?? null,
            'centrocosto_id' => $linea->centrocosto_id ?? null,
        ];
    }

    private function guardarAnita(array $data): bool
    {
        $apiAnita = new ApiAnita();
        $this->convierteDatosParaAnita($data, $ctaContable, $ctaContrap, $ccosto);

        $payload = [
            'tabla' => $this->tablaAnita(),
            'acc' => 'insert',
            'sistema' => $this->sistemaAnita(),
            'campos' => '
                apg_concepto,
                apg_desc,
                apg_cta_contable,
                apg_cta_contrap,
                apg_ccosto
            ',
            'valores' => "
                '".(int) $data['codigo']."',
                '".$this->escaparAnita($data['nombre'])."',
                '".$ctaContable."',
                '".$ctaContrap."',
                '".$ccosto."'
            ",
        ];

        return (bool) $apiAnita->apiCallEscritura($payload);
    }

    private function actualizarAnita(array $data, int $codigoAnita): bool
    {
        $apiAnita = new ApiAnita();
        $this->convierteDatosParaAnita($data, $ctaContable, $ctaContrap, $ccosto);

        $payload = [
            'acc' => 'update',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'valores' => "
                apg_desc = '".$this->escaparAnita($data['nombre'])."',
                apg_cta_contable = '".$ctaContable."',
                apg_cta_contrap = '".$ctaContrap."',
                apg_ccosto = '".$ccosto."'
            ",
            'whereArmado' => " WHERE {$this->keyFieldAnita} = '".(int) $codigoAnita."' ",
        ];

        return (bool) $apiAnita->apiCallEscritura($payload);
    }

    private function eliminarAnita(int $codigo): bool
    {
        $apiAnita = new ApiAnita();
        $payload = [
            'acc' => 'delete',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'whereArmado' => " WHERE {$this->keyFieldAnita} = '".(int) $codigo."' ",
        ];

        return (bool) $apiAnita->apiCallEscritura($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function convierteDatosDeAnita(object $row): ?array
    {
        $codigoCta = (int) preg_replace('/\D+/', '', (string) ($row->apg_cta_contable ?? '0'));
        $codigoContrap = (int) preg_replace('/\D+/', '', (string) ($row->apg_cta_contrap ?? '0'));
        $codigoCc = (int) preg_replace('/\D+/', '', (string) ($row->apg_ccosto ?? '0'));

        $cuenta = $this->resolverCuentacontablePorCodigoAnita($codigoCta, null);
        if ($cuenta === null) {
            return null;
        }

        $empresaId = (int) $cuenta->empresa_id;
        if ($empresaId <= 0) {
            return null;
        }

        $contrapartidaId = null;
        if ($codigoContrap > 0) {
            $contrap = $this->resolverCuentacontablePorCodigoAnita($codigoContrap, $empresaId);
            $contrapartidaId = $contrap?->id;
        }

        $centrocostoId = null;
        if ($codigoCc > 0) {
            $centrocostoId = $this->resolverCentrocostoIdPorCodigoAnita($codigoCc);
        }

        return [
            'codigo' => (int) ($row->apg_concepto ?? 0),
            'nombre' => trim((string) ($row->apg_desc ?? '')),
            'estado' => AperturaGasto::ESTADO_ACTIVO,
            'lineas' => [[
                'empresa_id' => $empresaId,
                'cuentacontable_id' => (int) $cuenta->id,
                'cuentacontable_contrapartida_id' => $contrapartidaId,
                'centrocosto_id' => $centrocostoId,
            ]],
        ];
    }

    private function convierteDatosParaAnita(array $data, &$ctaContable, &$ctaContrap, &$ccosto): void
    {
        $ctaContable = (string) $this->codigoCuentacontableParaAnita((int) ($data['cuentacontable_id'] ?? 0));
        $ctaContrap = (string) $this->codigoCuentacontableParaAnita((int) ($data['cuentacontable_contrapartida_id'] ?? 0));
        $ccosto = (string) $this->codigoCentrocostoParaAnita((int) ($data['centrocosto_id'] ?? 0));
    }

    private function resolverCuentacontablePorCodigoAnita(int $codigoAnita, ?int $empresaId): ?Cuentacontable
    {
        if ($codigoAnita <= 0) {
            return null;
        }

        $query = Cuentacontable::query()->select('id', 'codigo', 'empresa_id');

        $query->where(function ($q) use ($codigoAnita) {
            $q->where('codigo', (string) $codigoAnita)
                ->orWhere('codigo', $codigoAnita);
        });

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->orderBy('id')->first();
    }

    private function resolverCentrocostoIdPorCodigoAnita(int $codigoAnita): ?int
    {
        if ($codigoAnita <= 0) {
            return null;
        }

        $centrocosto = Centrocosto::query()
            ->where(function ($q) use ($codigoAnita) {
                $q->where('codigo', (string) $codigoAnita)
                    ->orWhere('codigo', $codigoAnita);
            })
            ->orderBy('id')
            ->first();

        return $centrocosto ? (int) $centrocosto->id : null;
    }

    private function codigoCuentacontableParaAnita(int $cuentacontableId): int
    {
        if ($cuentacontableId <= 0) {
            return 0;
        }

        $cuenta = Cuentacontable::query()->select('codigo')->find($cuentacontableId);
        if ($cuenta === null) {
            return 0;
        }

        return (int) preg_replace('/\D+/', '', (string) $cuenta->codigo);
    }

    private function codigoCentrocostoParaAnita(int $centrocostoId): int
    {
        if ($centrocostoId <= 0) {
            return 0;
        }

        $centrocosto = Centrocosto::query()->select('codigo')->find($centrocostoId);
        if ($centrocosto === null) {
            return 0;
        }

        return (int) preg_replace('/\D+/', '', (string) $centrocosto->codigo);
    }

    private function escaparAnita(string $valor): string
    {
        return str_replace("'", "''", trim($valor));
    }

    private function sistemaAnita(): string
    {
        return (string) config('apertura_gasto_anita.sistema', 'caja');
    }

    private function tablaAnita(): string
    {
        return (string) config('apertura_gasto_anita.tabla', 'apgasto');
    }
}
