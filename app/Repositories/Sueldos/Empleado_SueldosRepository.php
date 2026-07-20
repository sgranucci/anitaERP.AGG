<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Empleado_Archivo_Sueldos;
use App\Models\Sueldos\Empleado_Base_Sueldos;
use App\Models\Sueldos\Empleado_Ingreso_Sueldos;
use App\Models\Sueldos\Empleado_Leyenda_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Sueldos\EmpleadoDomicilioVinculador;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\EmpleadoSueldosListadoFiltros;
use App\Support\Sueldos\VacacionFechaAnita;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Empleado_SueldosRepository implements Empleado_SueldosRepositoryInterface
{
    public function __construct(
        protected Empleado_Sueldos $model,
        protected EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function all()
    {
        $query = $this->model->newQuery()->orderBy('legajo');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empleado_sueldos.empresa_id');

        return $query->get();
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        $emp = $this->model->newQuery()
            ->with([
                'empresa', 'categoria', 'agrupamiento', 'lugartrabajo', 'centrocosto',
                'obrasocial', 'sindicato', 'vacacion', 'art', 'motivoegreso',
                'leyendas', 'ingresos.motivoegreso', 'archivos', 'paisNacimiento',
            ])
            ->findOrFail($id);

        if (! $this->empresaRepository->empresaIdPermitida((int) $emp->empresa_id)) {
            abort(403, 'No tiene acceso a la empresa del empleado.');
        }

        return $emp;
    }

    public function findOperativo(int $id)
    {
        return $this->findOrFail($id);
    }

    public function proximoLegajo(int $empresaId): int
    {
        $max = (int) $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->max('legajo');

        return $max + 1;
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function leeEmpleado($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = array_merge(EmpleadoSueldosListadoFiltros::filtrosVacios(), [
                'valor' => $texto,
                'busqueda' => $texto,
            ]);
        } elseif (! is_array($filtros)) {
            $filtros = EmpleadoSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('empleado_sueldos.*')
            ->with([
                'empresa:id,nombre',
                'categoria:id,codigo,descripcion,origen_bases',
                'centrocosto:id,codigo,nombre',
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empleado_sueldos.empresa_id');

        // Los filtros externos (estado default Activo + empresa) se aplican siempre.
        EmpleadoSueldosListadoFiltros::aplicar($query, $filtros);

        $query->orderBy('empleado_sueldos.empresa_id')
            ->orderBy('empleado_sueldos.legajo');

        $result = isset($flPaginando) && $flPaginando
            ? $query->paginate(15)
            : $query->get();

        $items = method_exists($result, 'items') ? $result->items() : $result;
        foreach ($items as $row) {
            $row->setAttribute('nombreempresa', optional($row->empresa)->nombre);
        }

        return $result;
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $empresaId = (int) ($data['empresa_id'] ?? 0);
            if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
                abort(403, 'Empresa no permitida.');
            }

            $leyendas = $this->extraerLeyendas($data);
            $archivos = $data['nombrearchivos'] ?? [];
            unset($data['nombrearchivos'], $data['nombresanteriores'], $data['leyendas'], $data['foto_archivo']);

            $data = $this->mapearDomicilioDescripcion($data);
            $data['estado'] = $data['estado'] ?? EmpleadoEstados::PROVISORIO;
            $data['usuario_alta_id'] = Auth::id();
            if (empty($data['legajo'])) {
                $data['legajo'] = $this->proximoLegajo($empresaId);
            }

            $emp = $this->model->create($this->soloFillable($data));
            $this->sincronizarLeyendas((int) $emp->id, $leyendas);

            if (! empty($data['fecha_ingreso'])) {
                Empleado_Ingreso_Sueldos::create([
                    'empleado_id' => $emp->id,
                    'fecha_ingreso' => $data['fecha_ingreso'],
                    'fecha_egreso' => null,
                    'tipo_movimiento' => 'I',
                    'usuario_id' => Auth::id(),
                ]);
            }

            $this->guardarArchivosNuevos($emp, $archivos);

            return $emp->fresh(['leyendas', 'archivos', 'ingresos']);
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $emp = $this->findOrFail($id);
            $leyendas = $this->extraerLeyendas($data);
            $archivosNuevos = $data['nombrearchivos'] ?? [];
            $conservar = $data['nombresanteriores'] ?? null;
            $fotoArchivo = $data['foto_archivo'] ?? null;
            unset($data['nombrearchivos'], $data['nombresanteriores'], $data['leyendas'], $data['foto_archivo'], $data['estado']);

            // No permitir cambiar empresa en edición
            unset($data['empresa_id']);

            $data = $this->mapearDomicilioDescripcion($data);
            $emp->update($this->soloFillable($data));
            $this->sincronizarLeyendas((int) $emp->id, $leyendas);

            if (is_array($conservar)) {
                $this->sincronizarArchivosConservados($emp, $conservar);
            }
            $this->guardarArchivosNuevos($emp, $archivosNuevos);

            if ($fotoArchivo instanceof UploadedFile) {
                $this->guardarFoto($emp, $fotoArchivo);
            }

            return $emp->fresh(['leyendas', 'archivos', 'ingresos']);
        });
    }

    public function delete($id)
    {
        $emp = $this->findOrFail($id);
        $dir = 'public/archivos/empleados/'.$emp->id;
        if (Storage::exists($dir)) {
            Storage::deleteDirectory($dir);
        }

        return (bool) $emp->delete();
    }

    /** @param  list<string>  $leyendas */
    private function sincronizarLeyendas(int $empleadoId, array $leyendas): void
    {
        Empleado_Leyenda_Sueldos::query()->where('empleado_id', $empleadoId)->delete();
        $linea = 1;
        foreach ($leyendas as $texto) {
            $texto = trim((string) $texto);
            if ($texto === '') {
                continue;
            }
            Empleado_Leyenda_Sueldos::create([
                'empleado_id' => $empleadoId,
                'linea' => $linea++,
                'leyenda' => mb_substr($texto, 0, 80),
            ]);
        }
    }

    /** @return list<string> */
    private function extraerLeyendas(array &$data): array
    {
        $raw = $data['leyendas'] ?? [];
        unset($data['leyendas']);
        if (! is_array($raw)) {
            return [];
        }

        return array_values($raw);
    }

    /**
     * Vincula los textos de provincia/localidad de empleados ya cargados con los maestros reales.
     * Idempotente: sólo toca filas con provincia_id o localidad_id en null. Bypass Eloquent/auditoría.
     *
     * @return array<string, mixed>
     */
    public function vincularDomicilios(): array
    {
        $vinc = new EmpleadoDomicilioVinculador();

        $stats = [
            'procesados' => 0,
            'provincia_vinculada' => 0,
            'provincia_corregida' => 0,
            'localidad_vinculada' => 0,
            'cp_completado' => 0,
        ];
        $sinProv = [];
        $sinLoc = [];

        $this->model->newQuery()
            ->where(function ($q) {
                $q->whereNull('provincia_id')->orWhereNull('localidad_id');
            })
            ->where(function ($q) {
                $q->where(fn ($qq) => $qq->whereNotNull('provincia')->where('provincia', '!=', ''))
                    ->orWhere(fn ($qq) => $qq->whereNotNull('localidad')->where('localidad', '!=', ''));
            })
            ->select('id', 'provincia', 'localidad', 'provincia_id', 'localidad_id', 'codigo_postal')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($vinc, &$stats, &$sinProv, &$sinLoc) {
                foreach ($rows as $e) {
                    $stats['procesados']++;
                    $update = [];
                    $provId = $e->provincia_id ? (int) $e->provincia_id : null;

                    if (! $provId && trim((string) $e->provincia) !== '') {
                        $m = $vinc->matchProvincia($e->provincia);
                        if ($m) {
                            $provId = $m;
                            $update['provincia_id'] = $m;
                            $stats['provincia_vinculada']++;
                        } else {
                            $k = mb_strtoupper(trim((string) $e->provincia));
                            $sinProv[$k] = ($sinProv[$k] ?? 0) + 1;
                        }
                    }

                    if (! $e->localidad_id && trim((string) $e->localidad) !== '') {
                        $r = $vinc->matchLocalidad($e->localidad, $provId);
                        if ($r['localidad_id']) {
                            $update['localidad_id'] = $r['localidad_id'];
                            $stats['localidad_vinculada']++;

                            if ($r['provincia_id'] && (! $provId || (! empty($r['forzar_provincia']) && (int) $r['provincia_id'] !== $provId))) {
                                $stats[$provId ? 'provincia_corregida' : 'provincia_vinculada']++;
                                $update['provincia_id'] = (int) $r['provincia_id'];
                                $provId = (int) $r['provincia_id'];
                            }

                            if (trim((string) $e->codigo_postal) === '' && $r['cp']) {
                                $update['codigo_postal'] = $r['cp'];
                                $stats['cp_completado']++;
                            }
                        } else {
                            $k = mb_strtoupper(trim((string) $e->localidad));
                            $sinLoc[$k] = ($sinLoc[$k] ?? 0) + 1;
                        }
                    }

                    if ($update !== []) {
                        DB::table('empleado_sueldos')->where('id', $e->id)->update($update);
                    }
                }
            });

        arsort($sinProv);
        arsort($sinLoc);
        $stats['sin_provincia_textos'] = count($sinProv);
        $stats['sin_localidad_textos'] = count($sinLoc);
        $stats['muestra_sin_provincia'] = array_slice($sinProv, 0, 15, true);
        $stats['muestra_sin_localidad'] = array_slice($sinLoc, 0, 15, true);

        return $stats;
    }

    private function soloFillable(array $data): array
    {
        $fillable = array_flip($this->model->getFillable());

        return array_intersect_key($data, $fillable);
    }

    /**
     * Domicilio vinculado (como proveedores): la descripción del select se guarda
     * en la columna de texto denormalizada (provincia / localidad).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapearDomicilioDescripcion(array $data): array
    {
        if (array_key_exists('desc_provincia', $data) && trim((string) $data['desc_provincia']) !== '') {
            $data['provincia'] = mb_substr(trim((string) $data['desc_provincia']), 0, 40);
        }
        if (array_key_exists('desc_localidad', $data) && trim((string) $data['desc_localidad']) !== '') {
            $data['localidad'] = mb_substr(trim((string) $data['desc_localidad']), 0, 60);
        }

        return $data;
    }

    /** @param  list<UploadedFile|mixed>  $archivos */
    private function guardarArchivosNuevos(Empleado_Sueldos $emp, array $archivos): void
    {
        foreach ($archivos as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $nombre = $file->getClientOriginalName();
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) ?: 'archivo';
            $destino = 'archivos/empleados/'.$emp->id;
            $file->storeAs('public/'.$destino, $safe);
            Empleado_Archivo_Sueldos::create([
                'empleado_id' => $emp->id,
                'nombrearchivo' => $safe,
            ]);
        }
    }

    /** @param  list<string>  $conservar */
    private function sincronizarArchivosConservados(Empleado_Sueldos $emp, array $conservar): void
    {
        $conservar = array_map('strval', $conservar);
        $actuales = Empleado_Archivo_Sueldos::query()->where('empleado_id', $emp->id)->get();
        foreach ($actuales as $arch) {
            if (! in_array($arch->nombrearchivo, $conservar, true)) {
                Storage::delete('public/archivos/empleados/'.$emp->id.'/'.$arch->nombrearchivo);
                $arch->delete();
            }
        }
    }

    private function guardarFoto(Empleado_Sueldos $emp, UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }
        $nombre = 'foto.'.$ext;
        $file->storeAs('public/archivos/empleados/'.$emp->id, $nombre);
        $emp->update(['foto' => $nombre]);
    }

    /**
     * Columnas reales de la tabla Anita sueldos.empleado (verificadas contra el bridge).
     * El orden importa: el bridge legacy mapea el CSV por posición de esta lista.
     */
    private const CAMPOS_ANITA_EMPLEADO = 'emp_empresa, emp_legajo, emp_nombre, emp_domicilio, emp_localidad, '
        .'emp_nacionalidad, emp_documento, emp_fec_nac, emp_fec_ing, emp_fec_egr, emp_categoria, emp_sueldo, '
        .'emp_codigo_o_soc, emp_gremio, emp_men_quin, emp_antig_ant, emp_sexo, emp_est_civil, emp_flag_estado, '
        .'emp_cta_bancaria, emp_centro_costos, emp_afil_jubil, emp_jornal_dia, emp_jornal_hora, emp_base4, '
        .'emp_base5, emp_base6, emp_base7, emp_base8, emp_base9, emp_base10, emp_base11, emp_cod_agrup, '
        .'emp_telefono, emp_contratado, emp_mo_dir_ind, emp_cod_postal, emp_codigo_afjp, emp_codigo_art, '
        .'emp_situacion, emp_condicion, emp_modalidad, emp_provincia, emp_siniestrado, emp_marca_red, '
        .'emp_tipo_empresa, emp_regimen, emp_lugartrabajo, emp_cbu, emp_motivoegr, emp_entre_calles, '
        .'emp_cod_banco, emp_pais_nac';

    /**
     * Llenado inicial desde Anita (sueldos.empleado + emping + empley).
     * Inserta solo los empleados faltantes por (empresa, legajo). No genera auditoría ni observers.
     *
     * @return array{en_anita:int, importados:int, ya_existia:int, sin_empresa:int, omitidos:int, historia:int, leyendas:int, bases:int, errores:list<string>}
     */
    public function sincronizarConAnita(): array
    {
        ini_set('max_execution_time', '1800');
        ini_set('memory_limit', '1024M');

        $res = [
            'en_anita' => 0, 'importados' => 0, 'ya_existia' => 0, 'sin_empresa' => 0,
            'omitidos' => 0, 'historia' => 0, 'leyendas' => 0, 'bases' => 0, 'errores' => [],
        ];

        $empresaPorCodigo = $this->mapaCodigoId('empresa');
        if ($empresaPorCodigo === []) {
            $res['errores'][] = 'No hay empresas cargadas en el ERP.';

            return $res;
        }

        $categoriaPorCodigo = $this->mapaCategoria();
        $nombrebasePorCodigo = $this->mapaCodigoId('nombrebase_sueldos');
        $maps = [
            'obrasocial_id' => $this->mapaCodigoId('obrasocial_sueldos'),
            'sindicato_id' => $this->mapaCodigoId('sindicato_sueldos'),
            'agrupamiento_id' => $this->mapaCodigoId('agrupamiento_sueldos'),
            'lugartrabajo_id' => $this->mapaCodigoId('lugartrabajo_sueldos'),
            'centrocosto_id' => $this->mapaCodigoId('centrocosto'),
            'art_id' => $this->mapaCodigoId('art_sueldos'),
            'motivoegreso_id' => $this->mapaCodigoId('motivoegreso_sueldos'),
            'pais_nacimiento_id' => $this->mapaCodigoId('pais'),
        ];

        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list', 'sistema' => 'sueldos', 'tabla' => 'empleado',
            'campos' => self::CAMPOS_ANITA_EMPLEADO, 'orderBy' => 'emp_empresa, emp_legajo',
        ]));
        if (! empty($parsed['error_lectura'])) {
            $res['errores'][] = 'Lectura empleado: '.$parsed['error_lectura'];

            return $res;
        }

        $existentes = [];
        foreach (DB::table('empleado_sueldos')->select('empresa_id', 'legajo')->get() as $e) {
            $existentes[$e->empresa_id.':'.$e->legajo] = true;
        }

        $now = now();
        $insertRows = [];
        $meta = [];   // key => ['origen'=>?, 'bases'=>[cod=>valor], 'feing'=>?]
        $seen = [];

        foreach ($parsed['filas'] as $f) {
            $res['en_anita']++;
            $codEmp = $this->normCodigo($f->emp_empresa ?? null);
            $empresaId = $codEmp !== null ? ($empresaPorCodigo[$codEmp] ?? null) : null;
            if ($empresaId === null) {
                $res['sin_empresa']++;
                continue;
            }
            $legajo = (int) ($f->emp_legajo ?? 0);
            if ($legajo <= 0) {
                $res['omitidos']++;
                continue;
            }
            $key = $empresaId.':'.$legajo;
            if (isset($existentes[$key])) {
                $res['ya_existia']++;
                continue;
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $catCod = $this->normCodigo($f->emp_categoria ?? null);
            $categoria = $catCod !== null ? ($categoriaPorCodigo[$catCod] ?? null) : null;
            $feing = VacacionFechaAnita::erpDesdeAnita($f->emp_fec_ing ?? 0);
            $feegr = VacacionFechaAnita::erpDesdeAnita($f->emp_fec_egr ?? 0);
            $estado = EmpleadoEstados::desdeFlagAnita($f->emp_flag_estado ?? null);

            $insertRows[] = [
                'empresa_id' => $empresaId,
                'legajo' => $legajo,
                'nombre' => $this->txt($f->emp_nombre ?? null, 80) ?? (string) $legajo,
                'domicilio' => $this->txt($f->emp_domicilio ?? null, 80),
                'entre_calles' => $this->txt($f->emp_entre_calles ?? null, 80),
                'localidad' => $this->txt($f->emp_localidad ?? null, 60),
                'codigo_postal' => $this->txt($f->emp_cod_postal ?? null, 12),
                'provincia' => $this->txt($f->emp_provincia ?? null, 40),
                'telefono' => $this->txt($f->emp_telefono ?? null, 40),
                'nacionalidad' => $this->txt($f->emp_nacionalidad ?? null, 40),
                'pais_nacimiento_id' => $this->fk($maps['pais_nacimiento_id'], $f->emp_pais_nac ?? null),
                'documento' => $this->txt($f->emp_documento ?? null, 30),
                'fecha_nacimiento' => VacacionFechaAnita::erpDesdeAnita($f->emp_fec_nac ?? 0),
                'cuil' => $this->txt($f->emp_afil_jubil ?? null, 15),
                'sexo' => $this->sexo($f->emp_sexo ?? null),
                'estado_civil' => $this->intNull($f->emp_est_civil ?? null),
                'estado' => $estado,
                'fecha_ingreso' => $feing,
                'fecha_egreso' => $feegr,
                'motivoegreso_id' => $this->fk($maps['motivoegreso_id'], $f->emp_motivoegr ?? null),
                'categoria_id' => $categoria['id'] ?? null,
                'agrupamiento_id' => $this->fk($maps['agrupamiento_id'], $f->emp_cod_agrup ?? null),
                'lugartrabajo_id' => $this->fk($maps['lugartrabajo_id'], $f->emp_lugartrabajo ?? null),
                'centrocosto_id' => $this->fk($maps['centrocosto_id'], $f->emp_centro_costos ?? null),
                'obrasocial_id' => $this->fk($maps['obrasocial_id'], $f->emp_codigo_o_soc ?? null),
                'sindicato_id' => $this->fk($maps['sindicato_id'], $f->emp_gremio ?? null),
                'art_id' => $this->fk($maps['art_id'], $f->emp_codigo_art ?? null),
                'sueldo_basico' => $this->num($f->emp_sueldo ?? null),
                'jornal_dia' => $this->num($f->emp_jornal_dia ?? null),
                'jornal_hora' => $this->num($f->emp_jornal_hora ?? null),
                'codigo_liquidacion' => $this->txt($f->emp_men_quin ?? null, 20),
                'antiguedad_anterior' => $this->txt($f->emp_antig_ant ?? null, 12),
                'cbu' => $this->txt($f->emp_cbu ?? null, 30),
                'cuenta_bancaria' => $this->txt($f->emp_cta_bancaria ?? null, 30),
                'banco_codigo' => $this->intNull($f->emp_cod_banco ?? null),
                'mano_obra' => $this->char1($f->emp_mo_dir_ind ?? null),
                'personal_contratado' => $this->char1($f->emp_contratado ?? null),
                'codigo_afjp' => $this->txt($f->emp_codigo_afjp ?? null, 20),
                'situacion_sijp' => $this->txt($f->emp_situacion ?? null, 4),
                'condicion_sijp' => $this->txt($f->emp_condicion ?? null, 4),
                'modalidad_sijp' => $this->txt($f->emp_modalidad ?? null, 6),
                'siniestrado_sijp' => $this->txt($f->emp_siniestrado ?? null, 4),
                'marca_reduccion_sijp' => $this->char1($f->emp_marca_red ?? null),
                'tipo_empresa_sijp' => $this->char1($f->emp_tipo_empresa ?? null),
                'regimen_sijp' => $this->char1($f->emp_regimen ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $meta[$key] = [
                'origen' => $categoria['origen'] ?? null,
                'feing' => $feing,
                'bases' => [
                    4 => $this->num($f->emp_base4 ?? null),
                    5 => $this->num($f->emp_base5 ?? null),
                    6 => $this->num($f->emp_base6 ?? null),
                    7 => $this->num($f->emp_base7 ?? null),
                    8 => $this->num($f->emp_base8 ?? null),
                    9 => $this->num($f->emp_base9 ?? null),
                    10 => $this->num($f->emp_base10 ?? null),
                    11 => $this->num($f->emp_base11 ?? null),
                ],
            ];
        }

        foreach (array_chunk($insertRows, 400) as $chunk) {
            DB::table('empleado_sueldos')->insert($chunk);
        }
        $res['importados'] = count($insertRows);

        // Mapa (empresa_id:legajo) => id de los recién importados (y también preexistentes).
        $idPorKey = [];
        foreach (DB::table('empleado_sueldos')->select('id', 'empresa_id', 'legajo')->get() as $e) {
            $idPorKey[$e->empresa_id.':'.$e->legajo] = (int) $e->id;
        }
        // codigo empresa Anita => empresa_id ERP (para emping/empley que traen el codigo).
        $empresaIdPorCodigo = $empresaPorCodigo;

        $res['bases'] = $this->importarBasesInicial($meta, $idPorKey, $nombrebasePorCodigo, $now);
        $res['historia'] = $this->importarEmpingInicial($api, $empresaIdPorCodigo, $idPorKey, $maps['motivoegreso_id'], $now, $meta);
        $res['leyendas'] = $this->importarEmpleyInicial($api, $empresaIdPorCodigo, $idPorKey, $now);

        return $res;
    }

    /**
     * @param  array<string, array{origen:?string, feing:?string, bases:array<int,?float>}>  $meta
     * @param  array<string,int>  $idPorKey
     * @param  array<string,int>  $nombrebasePorCodigo
     */
    private function importarBasesInicial(array $meta, array $idPorKey, array $nombrebasePorCodigo, $now): int
    {
        $filas = [];
        foreach ($meta as $key => $m) {
            $empleadoId = $idPorKey[$key] ?? null;
            if ($empleadoId === null) {
                continue;
            }
            // Solo empleados cuya categoría carga bases en el legajo (origen != 'T').
            if (($m['origen'] ?? null) === 'T') {
                continue;
            }
            $fecha = $m['feing'] ?? now()->toDateString();
            foreach ($m['bases'] as $cod => $valor) {
                if ($valor === null || (float) $valor == 0.0) {
                    continue;
                }
                $nbId = $nombrebasePorCodigo[(string) $cod] ?? null;
                if ($nbId === null) {
                    continue;
                }
                $filas[] = [
                    'empleado_id' => $empleadoId,
                    'nombrebase_id' => $nbId,
                    'valor' => (float) $valor,
                    'fecha_vigencia' => $fecha,
                    'valor_anterior' => null,
                    'usuario_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($filas, 500) as $chunk) {
            DB::table('empleado_base_sueldos')->insert($chunk);
        }

        return count($filas);
    }

    /**
     * @param  array<string,int>  $empresaIdPorCodigo
     * @param  array<string,int>  $idPorKey
     * @param  array<string,int>  $motivoPorCodigo
     * @param  array<string, array{origen:?string, feing:?string, bases:array<int,?float>}>  $meta
     */
    private function importarEmpingInicial(ApiAnita $api, array $empresaIdPorCodigo, array $idPorKey, array $motivoPorCodigo, $now, array $meta): int
    {
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list', 'sistema' => 'sueldos', 'tabla' => 'emping',
            'campos' => 'empi_empresa, empi_legajo, empi_fecha_ing, empi_fecha_egr, empi_motivoegr, empi_coment_baja',
            'orderBy' => 'empi_empresa, empi_legajo, empi_fecha_ing',
        ]));
        if (! empty($parsed['error_lectura'])) {
            return 0;
        }

        $filas = [];
        $vistos = [];
        $conHistoria = [];
        foreach ($parsed['filas'] as $r) {
            $codEmp = $this->normCodigo($r->empi_empresa ?? null);
            $empresaId = $codEmp !== null ? ($empresaIdPorCodigo[$codEmp] ?? null) : null;
            if ($empresaId === null) {
                continue;
            }
            $legajo = (int) ($r->empi_legajo ?? 0);
            $key = $empresaId.':'.$legajo;
            $empleadoId = $idPorKey[$key] ?? null;
            // Solo historia de empleados recién importados (evita duplicar en re-ejecución).
            if ($empleadoId === null || ! isset($meta[$key])) {
                continue;
            }
            $feing = VacacionFechaAnita::erpDesdeAnita($r->empi_fecha_ing ?? 0);
            if ($feing === null) {
                continue;
            }
            $dedupe = $empleadoId.'|'.$feing;
            if (isset($vistos[$dedupe])) {
                continue;
            }
            $vistos[$dedupe] = true;
            $conHistoria[$key] = true;
            $feegr = VacacionFechaAnita::erpDesdeAnita($r->empi_fecha_egr ?? 0);

            $filas[] = [
                'empleado_id' => $empleadoId,
                'fecha_ingreso' => $feing,
                'fecha_egreso' => $feegr,
                'motivoegreso_id' => $this->fk($motivoPorCodigo, $r->empi_motivoegr ?? null),
                'comentario_baja' => $this->comentario($r->empi_coment_baja ?? null),
                'tipo_movimiento' => $feegr !== null ? 'B' : 'I',
                'usuario_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Empleados importados sin historia en emping: al menos su ingreso inicial.
        foreach ($meta as $key => $m) {
            $empleadoId = $idPorKey[$key] ?? null;
            if ($empleadoId === null || isset($conHistoria[$key])) {
                continue;
            }
            $feing = $m['feing'] ?? null;
            if ($feing === null) {
                continue;
            }
            $filas[] = [
                'empleado_id' => $empleadoId,
                'fecha_ingreso' => $feing,
                'fecha_egreso' => null,
                'motivoegreso_id' => null,
                'comentario_baja' => null,
                'tipo_movimiento' => 'I',
                'usuario_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($filas, 500) as $chunk) {
            DB::table('empleado_ingreso_sueldos')->insert($chunk);
        }

        return count($filas);
    }

    /**
     * @param  array<string,int>  $empresaIdPorCodigo
     * @param  array<string,int>  $idPorKey
     */
    private function importarEmpleyInicial(ApiAnita $api, array $empresaIdPorCodigo, array $idPorKey, $now): int
    {
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list', 'sistema' => 'sueldos', 'tabla' => 'empley',
            'campos' => 'eml_empresa, eml_legajo, eml_linea, eml_leyenda',
            'orderBy' => 'eml_empresa, eml_legajo, eml_linea',
        ]));
        if (! empty($parsed['error_lectura'])) {
            return 0;
        }

        $porEmpleado = [];
        foreach ($parsed['filas'] as $r) {
            $codEmp = $this->normCodigo($r->eml_empresa ?? null);
            $empresaId = $codEmp !== null ? ($empresaIdPorCodigo[$codEmp] ?? null) : null;
            if ($empresaId === null) {
                continue;
            }
            $legajo = (int) ($r->eml_legajo ?? 0);
            $empleadoId = $idPorKey[$empresaId.':'.$legajo] ?? null;
            if ($empleadoId === null) {
                continue;
            }
            $texto = $this->txt($r->eml_leyenda ?? null, 80);
            if ($texto === null) {
                continue;
            }
            $porEmpleado[$empleadoId][] = $texto;
        }

        $filas = [];
        $conLeyenda = DB::table('empleado_leyenda_sueldos')
            ->whereIn('empleado_id', array_keys($porEmpleado) ?: [0])
            ->distinct()->pluck('empleado_id')->all();
        $conLeyenda = array_flip($conLeyenda);

        foreach ($porEmpleado as $empleadoId => $textos) {
            if (isset($conLeyenda[$empleadoId])) {
                continue;
            }
            $linea = 1;
            foreach ($textos as $texto) {
                $filas[] = [
                    'empleado_id' => $empleadoId,
                    'linea' => $linea++,
                    'leyenda' => $texto,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($filas, 500) as $chunk) {
            DB::table('empleado_leyenda_sueldos')->insert($chunk);
        }

        return count($filas);
    }

    /** @return array<string,int> */
    private function mapaCodigoId(string $tabla): array
    {
        $out = [];
        foreach (DB::table($tabla)->select('id', 'codigo')->get() as $r) {
            $k = $this->normCodigo($r->codigo);
            if ($k !== null && ! isset($out[$k])) {
                $out[$k] = (int) $r->id;
            }
        }

        return $out;
    }

    /** @return array<string, array{id:int, origen:?string}> */
    private function mapaCategoria(): array
    {
        $out = [];
        foreach (DB::table('categoria_sueldos')->select('id', 'codigo', 'origen_bases')->get() as $r) {
            $k = $this->normCodigo($r->codigo);
            if ($k !== null && ! isset($out[$k])) {
                $out[$k] = ['id' => (int) $r->id, 'origen' => $r->origen_bases];
            }
        }

        return $out;
    }

    private function normCodigo($v): ?string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        if (is_numeric($s)) {
            return (string) (int) $s;
        }

        return mb_strtoupper($s);
    }

    /** @param  array<string,int>  $mapa */
    private function fk(array $mapa, $codigo): ?int
    {
        $k = $this->normCodigo($codigo);

        return $k !== null ? ($mapa[$k] ?? null) : null;
    }

    private function txt($v, int $len): ?string
    {
        $s = trim((string) $v);
        if ($s === '' || $s === '0') {
            return null;
        }

        return mb_substr($s, 0, $len);
    }

    private function comentario($v): ?string
    {
        $s = trim((string) $v);
        if ($s === '' || $s === '0') {
            return null;
        }

        return mb_substr($s, 0, 80);
    }

    private function char1($v): ?string
    {
        $s = strtoupper(trim((string) $v));
        if ($s === '' || $s === '0') {
            return null;
        }

        return mb_substr($s, 0, 1);
    }

    private function sexo($v): ?string
    {
        $s = trim((string) $v);

        return in_array($s, ['1', '2'], true) ? $s : null;
    }

    private function intNull($v): ?int
    {
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    private function num($v): ?float
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }

        return (float) $v;
    }
}
