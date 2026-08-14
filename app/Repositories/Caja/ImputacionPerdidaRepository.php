<?php

namespace App\Repositories\Caja;

use App\ApiAnita;
use App\Models\Caja\ImputacionPerdida;
use App\Models\Caja\ImputacionPerdidaEmpresa;
use App\Models\Contable\Cuentacontable;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\ImputacionPerdidaListadoFiltros;
use DB;
use Exception;
use InvalidArgumentException;

class ImputacionPerdidaRepository implements ImputacionPerdidaRepositoryInterface
{
    protected string $keyFieldAnita = 'impp_codigo';

    public function __construct(
        private readonly ImputacionPerdida $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, ImputacionPerdida>
     */
    public function leeImputacionPerdida($filtros, bool $paginar = false)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ImputacionPerdidaListadoFiltros::MODO_TODOS,
                'campo' => 'codigo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => $this->empresaRepository->traeEmpresasAsignadas(),
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ImputacionPerdidaListadoFiltros::filtrosVacios();
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('imputacion_perdida.*')
            ->with([
                'empresas.empresa',
                'empresas.cuentacontable',
            ]);

        ImputacionPerdidaListadoFiltros::aplicarJoinsListado($query, $filtros);
        ImputacionPerdidaListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (ImputacionPerdidaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ImputacionPerdidaListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('imputacion_perdida.codigo');

        return $paginar
            ? $query->paginate(10)->appends(ImputacionPerdidaListadoFiltros::paraQueryString($filtros))
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

        return $registro->fresh(['empresas.empresa', 'empresas.cuentacontable']);
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

        return $registro->fresh(['empresas.empresa', 'empresas.cuentacontable']);
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try {
            $registro = $this->model->findOrFail($id);
            $codigo = (int) $registro->codigo;
            $this->eliminarAnita($codigo);
            ImputacionPerdidaEmpresa::query()->where('imputacion_perdida_id', $id)->delete();
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
        ])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with([
            'empresas.empresa',
            'empresas.cuentacontable',
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
                'impp_codigo',
                'impp_desc',
                'impp_cta_contable',
            ]),
        ];

        if ($codigo !== null && $codigo > 0) {
            $data['whereArmado'] = " WHERE {$this->keyFieldAnita} = '".(int) $codigo."' ";
        }

        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita)) {
            $ret['errores'][] = 'Anita no devolvió datos válidos para impperd.';

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
                    $ret['errores'][] = "Imputación Anita {$codigoLocal}: {$estado}.";
                }
            } catch (\Throwable $e) {
                $ret['errores'][] = "Imputación Anita {$codigoLocal}: ".$e->getMessage();
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
                'impp_codigo',
                'impp_desc',
                'impp_cta_contable',
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

            $lineas[] = [
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
            ];
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function sincronizarLineasEmpresa(int $imputacionPerdidaId, array $lineas): void
    {
        ImputacionPerdidaEmpresa::query()->where('imputacion_perdida_id', $imputacionPerdidaId)->delete();

        foreach ($lineas as $linea) {
            ImputacionPerdidaEmpresa::query()->create([
                'imputacion_perdida_id' => $imputacionPerdidaId,
                'empresa_id' => (int) $linea['empresa_id'],
                'cuentacontable_id' => (int) $linea['cuentacontable_id'],
            ]);
        }
    }

    /**
     * Anita solo admite una cuenta: se toma la primera línea (empresa menor).
     *
     * @return array<string, mixed>
     */
    private function payloadAnitaDesdeRegistro(ImputacionPerdida $registro): array
    {
        $linea = $registro->empresas->sortBy('empresa_id')->first();

        return [
            'codigo' => (int) $registro->codigo,
            'nombre' => (string) $registro->nombre,
            'cuentacontable_id' => (int) ($linea->cuentacontable_id ?? 0),
        ];
    }

    private function guardarAnita(array $data): bool
    {
        $apiAnita = new ApiAnita();
        $ctaContable = (string) $this->codigoCuentacontableParaAnita((int) ($data['cuentacontable_id'] ?? 0));

        $payload = [
            'tabla' => $this->tablaAnita(),
            'acc' => 'insert',
            'sistema' => $this->sistemaAnita(),
            'campos' => '
                impp_codigo,
                impp_desc,
                impp_cta_contable
            ',
            'valores' => "
                '".(int) $data['codigo']."',
                '".$this->escaparAnita($data['nombre'])."',
                '".$ctaContable."'
            ",
        ];

        return (bool) $apiAnita->apiCallEscritura($payload);
    }

    private function actualizarAnita(array $data, int $codigoAnita): bool
    {
        $apiAnita = new ApiAnita();
        $ctaContable = (string) $this->codigoCuentacontableParaAnita((int) ($data['cuentacontable_id'] ?? 0));

        $payload = [
            'acc' => 'update',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'valores' => "
                impp_desc = '".$this->escaparAnita($data['nombre'])."',
                impp_cta_contable = '".$ctaContable."'
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
        $codigoCta = (int) preg_replace('/\D+/', '', (string) ($row->impp_cta_contable ?? '0'));

        $cuenta = $this->resolverCuentacontablePorCodigoAnita($codigoCta, null);
        if ($cuenta === null) {
            return null;
        }

        $empresaId = (int) $cuenta->empresa_id;
        if ($empresaId <= 0) {
            return null;
        }

        $lineas = [[
            'empresa_id' => $empresaId,
            'cuentacontable_id' => (int) $cuenta->id,
        ]];

        $codigoCuentaStr = (string) ($cuenta->codigo ?? '');
        if ($codigoCuentaStr !== '') {
            foreach ($this->empresaRepository->all() as $empresa) {
                $otraEmpresaId = (int) $empresa->id;
                if ($otraEmpresaId <= 0 || $otraEmpresaId === $empresaId) {
                    continue;
                }

                $cuentaOtra = Cuentacontable::query()
                    ->select('id', 'codigo', 'empresa_id')
                    ->where('empresa_id', $otraEmpresaId)
                    ->where(function ($q) use ($codigoCuentaStr, $codigoCta) {
                        $q->where('codigo', $codigoCuentaStr)
                            ->orWhere('codigo', $codigoCta)
                            ->orWhere('codigo', (string) $codigoCta);
                    })
                    ->orderBy('id')
                    ->first();

                if ($cuentaOtra === null) {
                    continue;
                }

                $lineas[] = [
                    'empresa_id' => $otraEmpresaId,
                    'cuentacontable_id' => (int) $cuentaOtra->id,
                ];
            }
        }

        return [
            'codigo' => (int) ($row->impp_codigo ?? 0),
            'nombre' => mb_substr(trim((string) ($row->impp_desc ?? '')), 0, 30),
            'lineas' => $lineas,
        ];
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

    private function escaparAnita(string $valor): string
    {
        return str_replace("'", "''", trim($valor));
    }

    private function sistemaAnita(): string
    {
        return (string) config('imputacion_perdida_anita.sistema', 'caja');
    }

    private function tablaAnita(): string
    {
        return (string) config('imputacion_perdida_anita.tabla', 'impperd');
    }
}
