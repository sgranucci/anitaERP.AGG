<?php

namespace App\Repositories\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Camion;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CamionRepository implements CamionRepositoryInterface
{
    protected $model;

    protected $tableAnita = 'camion';

    protected $keyField = 'codigo';

    protected $keyFieldAnita = 'cam_camion';

    public function __construct(Camion $camion)
    {
        $this->model = $camion;
    }

    public function all()
    {
        $hay = Camion::first();
        if (! $hay) {
            self::sincronizarConAnita();
        }

        return $this->model->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->orderBy('dominio')->get();
    }

    public function create(array $data)
    {
        $codigo = '';
        self::ultimoCodigo($codigo);
        $data['codigo'] = (string) $codigo;
        $data = $this->normalizarPayload($data);

        $camion = $this->model->create($data);
        self::guardarAnita($data);

        return $camion;
    }

    public function update(array $data, $id)
    {
        $camion = $this->model->findOrFail($id);
        $data['codigo'] = $camion->codigo;
        $data = $this->normalizarPayload($data);

        $camion->update($data);
        self::actualizarAnita($data, $data['codigo']);

        return $camion;
    }

    public function delete($id)
    {
        $camion = Camion::find($id);
        if (! $camion) {
            return false;
        }

        self::eliminarAnita($camion->codigo);
        $this->model->destroy($id);

        return true;
    }

    public function find($id)
    {
        if (null == $camion = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $camion;
    }

    public function findOrFail($id)
    {
        if (null == $camion = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $camion;
    }

    public function findPorCodigo($codigo)
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }

        $camion = $this->model->newQuery()->where('codigo', $codigo)->first();
        if ($camion) {
            return $camion;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            return $this->model->newQuery()->where('codigo', $alt)->first();
        }

        return null;
    }

    public function sincronizarConAnita()
    {
        $this->resincronizarDesdeAnita(false, false);
    }

    /**
     * Importa faltantes y actualiza existentes desde Anita (tabla camion: 7 campos de camion.sql).
     *
     * @return array{
     *     en_anita: int,
     *     iguales: int,
     *     crear: list<array<string, mixed>>,
     *     actualizar: list<array{codigo: string, id: int, diffs: array<string, array{erp: mixed, anita: mixed}>}>,
     *     solo_erp: list<array{id: int, codigo: string, dominio: string}>,
     *     importados: int,
     *     actualizados: int,
     *     errores: list<string>
     * }
     */
    public function resincronizarDesdeAnita(bool $dryRun = true, bool $actualizarExistentes = true): array
    {
        ini_set('max_execution_time', '300');

        $ret = [
            'en_anita' => 0,
            'iguales' => 0,
            'crear' => [],
            'actualizar' => [],
            'solo_erp' => [],
            'importados' => 0,
            'actualizados' => 0,
            'errores' => [],
        ];

        $filasAnita = $this->listarDesdeAnita();
        $ret['en_anita'] = count($filasAnita);

        $erp = Camion::query()->get()->keyBy(fn (Camion $c) => $this->normalizarCodigo((string) $c->codigo));
        $codigosAnita = [];

        foreach ($filasAnita as $row) {
            $payload = $this->payloadDesdeFilaAnita($row);
            if ($payload === null) {
                continue;
            }
            $codigo = $payload['codigo'];
            $codigosAnita[$codigo] = true;

            /** @var Camion|null $local */
            $local = $erp->get($codigo);
            if (! $local) {
                $ret['crear'][] = $payload;
                if (! $dryRun) {
                    try {
                        $this->model->create($payload);
                        $ret['importados']++;
                    } catch (\Throwable $e) {
                        $ret['errores'][] = "Alta codigo {$codigo}: ".$e->getMessage();
                    }
                }

                continue;
            }

            $diffs = $this->diferenciasContraLocal($local, $payload);
            if ($diffs === []) {
                $ret['iguales']++;

                continue;
            }

            $ret['actualizar'][] = [
                'codigo' => $codigo,
                'id' => (int) $local->id,
                'diffs' => $diffs,
            ];
            if (! $dryRun && $actualizarExistentes) {
                try {
                    $local->update($payload);
                    $ret['actualizados']++;
                } catch (\Throwable $e) {
                    $ret['errores'][] = "Update codigo {$codigo}: ".$e->getMessage();
                }
            }
        }

        foreach ($erp as $codigo => $local) {
            if (! isset($codigosAnita[$codigo])) {
                $ret['solo_erp'][] = [
                    'id' => (int) $local->id,
                    'codigo' => (string) $codigo,
                    'dominio' => (string) ($local->dominio ?? ''),
                ];
            }
        }

        return $ret;
    }

    public function traerRegistroDeAnita($key)
    {
        $apiAnita = new ApiAnita();
        $keySql = str_replace("'", "''", (string) $key);
        $data = [
            'acc' => 'list',
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'campos' => $this->camposAnita(),
            'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$keySql."' ",
        ];
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));
        if ($parsed['error_lectura'] !== null || $parsed['filas'] === []) {
            return;
        }

        $payload = $this->payloadDesdeFilaAnita($parsed['filas'][0]);
        if ($payload === null) {
            return;
        }

        $local = $this->findPorCodigo($payload['codigo']);
        if ($local) {
            $local->update($payload);

            return;
        }

        $this->model->create($payload);
    }

    /**
     * @return list<object>
     */
    private function listarDesdeAnita(): array
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'campos' => $this->camposAnita(),
        ];
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));
        if ($parsed['error_lectura'] !== null) {
            return [];
        }

        return $parsed['filas'];
    }

    private function camposAnita(): string
    {
        return 'cam_camion,cam_dominio,cam_habilitacion,cam_tipo,cam_dom_acoplado,cam_cuit_chofer,cam_cant_precinto';
    }

    /**
     * @return array{codigo: string, dominio: string, habilitacion: string, tipo: string, dominio_acoplado: string, cuit_chofer: string, cantidad_precinto: int}|null
     */
    private function payloadDesdeFilaAnita(object $row): ?array
    {
        $codigo = $this->normalizarCodigo((string) ($row->cam_camion ?? ''));
        if ($codigo === '') {
            return null;
        }

        return [
            'codigo' => $codigo,
            'dominio' => trim((string) ($row->cam_dominio ?? '')),
            'habilitacion' => trim((string) ($row->cam_habilitacion ?? '')),
            'tipo' => trim((string) ($row->cam_tipo ?? '')),
            'dominio_acoplado' => trim((string) ($row->cam_dom_acoplado ?? '')),
            'cuit_chofer' => trim((string) ($row->cam_cuit_chofer ?? '')),
            'cantidad_precinto' => (int) ($row->cam_cant_precinto ?? 0),
        ];
    }

    /**
     * @param  array{codigo: string, dominio: string, habilitacion: string, tipo: string, dominio_acoplado: string, cuit_chofer: string, cantidad_precinto: int}  $payload
     * @return array<string, array{erp: mixed, anita: mixed}>
     */
    private function diferenciasContraLocal(Camion $local, array $payload): array
    {
        $diffs = [];
        foreach (['dominio', 'habilitacion', 'tipo', 'dominio_acoplado', 'cuit_chofer', 'cantidad_precinto'] as $campo) {
            $erp = $campo === 'cantidad_precinto'
                ? (int) $local->{$campo}
                : trim((string) ($local->{$campo} ?? ''));
            if ($erp !== $payload[$campo]) {
                $diffs[$campo] = ['erp' => $erp, 'anita' => $payload[$campo]];
            }
        }

        return $diffs;
    }

    private function normalizarCodigo(string $codigo): string
    {
        $codigo = ltrim(trim($codigo), '0');

        return $codigo === '' ? '0' : $codigo;
    }

    public function guardarAnita($request)
    {
        $apiAnita = new ApiAnita();
        $data = [
            'tabla' => $this->tableAnita,
            'sistema' => 'ventas',
            'acc' => 'insert',
            'campos' => '
                cam_camion,
                cam_dominio,
                cam_habilitacion,
                cam_tipo,
                cam_dom_acoplado,
                cam_cuit_chofer,
                cam_cant_precinto
            ',
            'valores' => "
                '".(int) $request['codigo']."',
                '".$this->esc($request['dominio'] ?? '')."',
                '".$this->esc($request['habilitacion'] ?? '')."',
                '".$this->esc($request['tipo'] ?? '')."',
                '".$this->esc($request['dominio_acoplado'] ?? '')."',
                '".$this->esc($request['cuit_chofer'] ?? '')."',
                '".(int) ($request['cantidad_precinto'] ?? 0)."'
            ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function actualizarAnita($request, $id)
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'update',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'valores' => "
                cam_camion = '".(int) $request['codigo']."',
                cam_dominio = '".$this->esc($request['dominio'] ?? '')."',
                cam_habilitacion = '".$this->esc($request['habilitacion'] ?? '')."',
                cam_tipo = '".$this->esc($request['tipo'] ?? '')."',
                cam_dom_acoplado = '".$this->esc($request['dominio_acoplado'] ?? '')."',
                cam_cuit_chofer = '".$this->esc($request['cuit_chofer'] ?? '')."',
                cam_cant_precinto = '".(int) ($request['cantidad_precinto'] ?? 0)."'
            ",
            'whereArmado' => " WHERE cam_camion = '".(int) $id."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function eliminarAnita($id)
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'delete',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'whereArmado' => " WHERE cam_camion = '".(int) $id."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function consultaCamion(string $consulta): string
    {
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        $consulta = strtoupper(trim($consulta));
        $query = $this->model->newQuery()->select(
            'id',
            'codigo',
            'dominio',
            'habilitacion',
            'tipo',
            'cantidad_precinto'
        );
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('id', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('codigo', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('dominio', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('habilitacion', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('tipo', 'LIKE', '%'.$consulta.'%');
            });
        }

        $data = $query->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->limit(200)->get();
        $puedeAbrirAbm = can('editar-camion', false) || can('listar-camion', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="7">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="dominio">'.e($row->dominio).'</td>';
                $output['data'] .= '<td class="habilitacion">'.e($row->habilitacion).'</td>';
                $output['data'] .= '<td class="tipo">'.e($row->tipo).'</td>';
                $output['data'] .= '<td class="cantidad_precinto text-right">'.e((int) $row->cantidad_precinto).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultacamion">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_camion', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td></tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    private function ultimoCodigo(&$codigo): void
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'campos' => " max(cam_camion) as $this->keyFieldAnita ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $maxAnita = 0;
        if (is_array($dataAnita) && count($dataAnita) > 0) {
            $maxAnita = (int) ltrim((string) ($dataAnita[0]->{$this->keyFieldAnita} ?? '0'), '0');
        }

        $maxLocal = (int) Camion::query()->max(DB::raw(SqlDialectSupport::castEntero('codigo')));
        $codigo = (string) (max($maxAnita, $maxLocal) + 1);
    }

    /** @param array<string, mixed> $data */
    private function normalizarPayload(array $data): array
    {
        $data['dominio'] = strtoupper(trim((string) ($data['dominio'] ?? '')));
        $data['habilitacion'] = trim((string) ($data['habilitacion'] ?? ''));
        $data['tipo'] = strtoupper(trim((string) ($data['tipo'] ?? '')));
        $data['dominio_acoplado'] = strtoupper(trim((string) ($data['dominio_acoplado'] ?? '')));
        $data['cuit_chofer'] = trim((string) ($data['cuit_chofer'] ?? ''));
        $data['cantidad_precinto'] = (int) ($data['cantidad_precinto'] ?? 0);

        return $data;
    }

    private function esc(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
