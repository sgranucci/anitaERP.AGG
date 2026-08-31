<?php

namespace App\Repositories\Uif;

use App\ApiAnita;
use App\Models\Uif\Cliente_Premio_Uif;
use App\Models\Uif\Cliente_Uif;
use App\Repositories\Configuracion\TipodocumentoRepositoryInterface;
use App\Services\Uif\ClientePremioUifFotoTesoreria;
use App\Services\Uif\ClienteUifFotoDocumento;
use App\Services\Uif\JuegoUifDesdeAnitaResolver;
use App\Support\Uif\ClienteUifArchivoStorage;
use App\Support\Uif\ClienteUifListadoFiltros;
use App\Support\Uif\ClienteUifOrigenPcSupport;
use Auth;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class Cliente_UifRepository implements Cliente_UifRepositoryInterface
{
    protected $model;

    protected $cliente_premio_uifRepository;

    protected $cliente_archivo_uifRepository;

    protected $cliente_premio_archivo_uifRepository;

    protected $tipodocumentoRepository;

    protected $localidad_uifRepository;

    protected $pais_uifRepository;

    protected $provincia_uifRepository;

    protected $actividad_uifRepository;

    protected $tableAnita = 'clientes_uif';

    protected $keyField = 'id';

    protected $keyFieldAnita = 'inroclienteid';

    /**
     * PostRepository constructor.
     *
     * @param  Post  $post
     */
    public function __construct(Cliente_Uif $cliente_uif,
        Cliente_Premio_UifRepositoryInterface $cliente_premio_uifrepository,
        Cliente_Archivo_UifRepositoryInterface $cliente_archivo_uifrepository,
        Cliente_Premio_Archivo_UifRepositoryInterface $cliente_premio_archivo_uifrepository,
        TipodocumentoRepositoryInterface $tipodocumentorepository,
        Localidad_UifRepositoryInterface $localidad_uifrepository,
        Pais_UifRepositoryInterface $pais_uifrepository,
        Provincia_UifRepositoryInterface $provincia_uifrepository,
        Actividad_UifRepositoryInterface $actividad_uifrepository,
    ) {
        $this->model = $cliente_uif;
        $this->cliente_premio_uifRepository = $cliente_premio_uifrepository;
        $this->cliente_archivo_uifRepository = $cliente_archivo_uifrepository;
        $this->cliente_premio_archivo_uifRepository = $cliente_premio_archivo_uifrepository;
        $this->tipodocumentoRepository = $tipodocumentorepository;
        $this->localidad_uifRepository = $localidad_uifrepository;
        $this->pais_uifRepository = $pais_uifrepository;
        $this->provincia_uifRepository = $provincia_uifrepository;
        $this->actividad_uifRepository = $actividad_uifrepository;
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros  Criterios del listado o texto legacy (modo todos).
     */
    public function leeCliente_Uif($filtros, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ClienteUifListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'con_premios' => ClienteUifListadoFiltros::esBusquedaSoloConPremios($texto),
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = [
                'modo' => ClienteUifListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => '',
                'valor_hasta' => '',
                'con_premios' => false,
                'busqueda' => '',
            ];
        }

        $cliente_uifs = $this->model->select('cliente_uif.id as id',
            'cliente_uif.anita_origen as anita_origen',
            'cliente_uif.estado as estado',
            'cliente_uif.nombre as nombre',
            'tipodocumento.abreviatura as abreviaturatipodocumento',
            'cliente_uif.numerodocumento as numerodocumento',
            'cliente_uif.domicilio as domicilio',
            'localidad_uif.nombre as nombrelocalidad',
            'provincia_uif.nombre as nombreprovincia',
            'pais_uif.nombre as nombrepais',
            'cliente_uif.telefono as telefono',
            'cliente_uif.email as email')
            ->selectRaw('(SELECT COUNT(*) FROM cliente_premio_uif cp WHERE cp.cliente_uif_id = cliente_uif.id) as premios_count')
            ->selectRaw('(SELECT cp.fechaentrega FROM cliente_premio_uif cp WHERE cp.cliente_uif_id = cliente_uif.id ORDER BY cp.fechaentrega DESC, cp.id DESC LIMIT 1) as ultimo_premio_fecha')
            ->selectRaw('(SELECT cp.monto FROM cliente_premio_uif cp WHERE cp.cliente_uif_id = cliente_uif.id ORDER BY cp.fechaentrega DESC, cp.id DESC LIMIT 1) as ultimo_premio_monto')
            ->selectRaw('(SELECT j.nombre FROM cliente_premio_uif cp INNER JOIN juego_uif j ON j.id = cp.juego_uif_id WHERE cp.cliente_uif_id = cliente_uif.id ORDER BY cp.fechaentrega DESC, cp.id DESC LIMIT 1) as ultimo_premio_juego')
            ->join('tipodocumento', 'tipodocumento.id', '=', 'cliente_uif.tipodocumento_id')
            ->join('localidad_uif', 'localidad_uif.id', '=', 'cliente_uif.localidad_uif_id')
            ->join('provincia_uif', 'provincia_uif.id', '=', 'cliente_uif.provincia_uif_id')
            ->join('pais_uif', 'pais_uif.id', '=', 'cliente_uif.pais_uif_id')
            ->whereNull('cliente_uif.deleted_at')
            ->orderBy('cliente_uif.id', 'DESC');

        // Siempre aplicar: incluye default de origen PC aunque no haya texto de búsqueda.
        ClienteUifListadoFiltros::aplicar($cliente_uifs, $filtros);

        if (isset($flPaginando)) {
            if ($flPaginando) {
                $cliente_uifs = $cliente_uifs->paginate(10);
            } else {
                $cliente_uifs = $cliente_uifs->get();
            }
        } else {
            $cliente_uifs = $cliente_uifs->get();
        }

        return $cliente_uifs;
    }

    public function hayRegistrosClienteUifLocales(): bool
    {
        return $this->model->newQuery()->exists();
    }

    public function sincronizarArchivosAnitaSiCorresponde(Cliente_Uif $cliente): void
    {
        $clienteId = (int) ($cliente->id ?? 0);
        $inro = (int) ($cliente->inroclienteid ?? 0);
        if ($clienteId <= 0 || $inro <= 0) {
            return;
        }

        $origen = ClienteUifOrigenPcSupport::origenDeCliente($cliente) ?? 'biyemas';

        try {
            ClienteUifArchivoStorage::withOrigen($origen, function () use ($clienteId, $inro, $cliente) {
                $this->cliente_archivo_uifRepository->traerArchivosDeAnita($clienteId, $inro);
                $this->relinkFotodocumentoDesdeDisco($cliente);
            });
        } catch (Throwable $e) {
            // No bloquear la edición si el montaje Anita no responde; el banner sigue indicando faltantes.
            report($e);
        }
    }

    /**
     * Si fotodocumento está vacío o el archivo no está en disco, busca el DNI
     * en /scan (incl. Kandiko/rebisco) y lo referencia en BD.
     * Si lo asociado es una DDJJ/NOSIS o un retrato de tesorería copiado, lo descarta.
     */
    private function relinkFotodocumentoDesdeDisco(Cliente_Uif $cliente_uif): void
    {
        $cid = (int) $cliente_uif->id;
        $nroDocumento = trim((string) ($cliente_uif->numerodocumento ?? ''));
        $inro = (int) ($cliente_uif->inroclienteid ?? 0);
        $inroclienteidParaGuardar = $inro > 0 ? $inro : null;
        $storedBasename = trim((string) ($cliente_uif->fotodocumento ?? ''));
        $resolvedPath = $storedBasename !== ''
            ? ClienteUifFotoDocumento::absolutePathForBasename($storedBasename)
            : null;
        $descartoCopiaNoDni = false;
        if ($resolvedPath !== null && is_file($resolvedPath)) {
            if (ClienteUifFotoDocumento::descartarCopiaCanonicoSiNoEsDni($cid, $resolvedPath, $nroDocumento)) {
                $descartoCopiaNoDni = true;
                $resolvedPath = null;
            } else {
                ClienteUifFotoDocumento::promoverADniMountCanonico($resolvedPath, $nroDocumento);

                return;
            }
        }
        $path = ClienteUifFotoDocumento::findFirstMatchingPath($nroDocumento, $inroclienteidParaGuardar);
        if ($path !== null && is_file($path)
            && ClienteUifFotoDocumento::descartarCopiaCanonicoSiNoEsDni($cid, $path, $nroDocumento)) {
            $descartoCopiaNoDni = true;
            $path = null;
        }
        $fotoBasename = null;
        if ($path !== null && is_file($path)) {
            $promoted = ClienteUifFotoDocumento::promoverADniMountCanonico($path, $nroDocumento);
            $fotoBasename = basename(($promoted !== null && is_file($promoted)) ? $promoted : $path);
        }
        if ($fotoBasename === null && $cid > 0 && $nroDocumento !== '') {
            $fotoBasename = ClienteUifFotoDocumento::copyFirstClienteAdjuntoImageToFotodocumento($cid, $nroDocumento);
        }
        if ($fotoBasename !== null && $fotoBasename !== $storedBasename) {
            $cliente_uif->update(['fotodocumento' => $fotoBasename]);
            $cliente_uif->fotodocumento = $fotoBasename;
        } elseif ($descartoCopiaNoDni && $fotoBasename === null && $storedBasename !== '') {
            $cliente_uif->update(['fotodocumento' => null]);
            $cliente_uif->fotodocumento = null;
        }
    }

    public function create(array $data)
    {
        $data['usuario_id'] = Auth::user()->id;

        $cliente_uif = $this->model->create($data);

        return $cliente_uif;
    }

    public function update(array $data, $id)
    {
        $data['usuario_id'] = Auth::user()->id;

        $cliente_uif = $this->model->findOrFail($id)->update($data);

        return $cliente_uif;
    }

    public function delete($id)
    {
        $cliente_uif = $this->model->findOrFail($id);

        if ($cliente_uif) {
            $cliente_uif = $this->model->destroy($id);
        }

        return $cliente_uif;
    }

    public function find($id)
    {
        if (null == $cliente_uif = $this->model->with('cliente_archivos_uif')
            ->with('cliente_premios_uif')
            ->with('cliente_riesgos_uif')
            ->with('provincia_nacimientos')
            ->with('localidad_nacimientos')
            ->with('localidades_uif')
            ->with('provincias_uif')
            ->with('peps_uif')
            ->with('sos_uif')
            ->with('actividades_uif')
            ->with('estadociviles_uif')
            ->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $cliente_uif;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_uif = $this->model->with('cliente_archivos_uif')
            ->with('cliente_premios_uif')
            ->with('cliente_riesgos_uif')
            ->with('provincia_nacimientos')
            ->with('localidad_nacimientos')
            ->with('localidades_uif')
            ->with('provincias_uif')
            ->with('peps_uif')
            ->with('sos_uif')
            ->with('actividades_uif')
            ->with('estadociviles_uif')
            ->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $cliente_uif;
    }

    /**
     * @param  int|null  $limite  Máximo de clientes a procesar; null o 0 = sin límite (sincronización total).
     */
    public function sincronizarConAnita(?int $limite = 200): void
    {
        $apiAnita = new ApiAnita;
        $data = ['acc' => 'list',
            'sistema' => 'base_admin',
            'campos' => $this->keyFieldAnita,
            'orderBy' => $this->keyFieldAnita,
            'tabla' => $this->tableAnita];

        $dataAnita = json_decode($apiAnita->apiCall($data));
        if (! is_array($dataAnita)) {
            return;
        }

        $sinLimite = $limite === null || $limite <= 0;
        $off = 0;
        foreach ($dataAnita as $value) {
            $off++;

            if (! $sinLimite && $off > $limite) {
                break;
            }

            $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
        }
    }

    /**
     * Localiza un cliente ya importado dentro del mismo origen Anita (biyemas|kandiko|rebisco).
     */
    private function buscarClienteUifParaUpsertDesdeAnita(
        $inroclienteid,
        int $tipodocumento_id,
        string $numerodocumento,
        string $anitaOrigen = 'biyemas'
    ): ?Cliente_Uif {
        $anitaOrigen = strtolower(trim($anitaOrigen)) ?: 'biyemas';
        $anitaId = filter_var($inroclienteid, FILTER_VALIDATE_INT);
        if ($anitaId !== false && $anitaId > 0) {
            $porAnita = $this->model->newQuery()
                ->where('inroclienteid', $anitaId)
                ->where(function ($q) use ($anitaOrigen) {
                    $q->where('anita_origen', $anitaOrigen);
                    if ($anitaOrigen === 'biyemas') {
                        $q->orWhereNull('anita_origen');
                    }
                })
                ->first();
            if ($porAnita !== null) {
                return $porAnita;
            }
        }

        return $this->model->newQuery()
            ->where('tipodocumento_id', $tipodocumento_id)
            ->where('numerodocumento', $numerodocumento)
            ->where(function ($q) use ($anitaOrigen) {
                $q->where('anita_origen', $anitaOrigen);
                if ($anitaOrigen === 'biyemas') {
                    $q->orWhereNull('anita_origen');
                }
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * Importa/actualiza un cliente UIF desde Anita.
     *
     * @param  object|null  $clientePreload  fila ya leída (bulk cache); si null, consulta bridge
     * @param  list<object>|null  $premiosPreload  premios ya leídos; si null, consulta bridge; [] = sin premios
     * @param  array{anita_origen?:string,sala_id?:int,servidor?:string}  $opciones
     */
    public function traerRegistroDeAnita($key, $clientePreload = null, $premiosPreload = null, array $opciones = [])
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $anitaOrigen = strtolower(trim((string) ($opciones['anita_origen'] ?? 'biyemas'))) ?: 'biyemas';
        $salaId = (int) ($opciones['sala_id'] ?? 1);
        if ($salaId <= 0) {
            $salaId = 1;
        }
        $servidorOpt = isset($opciones['servidor']) ? trim((string) $opciones['servidor']) : '';

        if ($clientePreload !== null) {
            $data = $clientePreload;
        } else {
            $apiAnita = new ApiAnita;
            $payloadCliente = [
                'acc' => 'list', 'tabla' => $this->tableAnita.', outer profesion',
                'sistema' => 'base_admin',
                'campos' => '
			    inroclienteid,
				ctipodocumento,
				inrodocumento,
				ccuit,
				cnombre,
				ifechanac,
				ilocalidadnac,
				ipaisnac,
				csexo,
				cestadocivil,
				cdomicilio,
				cpiso,
				cdepto,
				clocalidad,
				ccodigopostal,
				ctelefono,
				cemail,
				iprovincia,
				ipais,
				iprofesion,
                profesion.nuevocodigo as codigoprofesion,
				fpremio,
				cmoneda,
				cdescpremio,
				ifechaentrega,
				cobservfisicas,
				ifechaalta,
				choraalta,
				iusuarioalta,
				cestado,
				ifechabaja,
				iusuariobaja,
				ifechaultmodif,
				choraultmodif,
				iusuarioultmodif,
				ilocalidad,
				ipep,
				iparaiso,
				iexterior,
				ifechafirmapep,
				ifeconfirmapep,
				ifeinformepep,
				ifeinformenosis,
				ifevtodni,
				cso,
				cactividadso,
				ccumplenormativaso,
				criesgo,
				inivelsocecon,
				cdecljur,
				ifevtoactividad
            ',
                'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$key."' AND clientes_uif.iprofesion=profesion.iprofesionid",
            ];
            if ($servidorOpt !== '') {
                $payloadCliente['servidor'] = $servidorOpt;
            }
            $dataAnita = json_decode($apiAnita->apiCall($payloadCliente));
            if (! is_array($dataAnita) || count($dataAnita) === 0) {
                return;
            }
            $data = $dataAnita[0];
        }

        if ($data) {

            // Busca tipo de documento
            $tipodocumento = $this->tipodocumentoRepository->findPorAbreviatura($data->ctipodocumento);

            $tipodocumento_id = 1;
            if ($tipodocumento) {
                $tipodocumento_id = $tipodocumento->id;
            }

            // Lee la localidad de nacimiento (y la provincia, que Anita no manda aparte)
            $localidadNacimiento_id = null;
            $provinciaNacimiento_id = null;
            try {
                $localidad = $this->localidad_uifRepository->findPorCodigo($data->ilocalidadnac);

                if ($localidad) {
                    $localidadNacimiento_id = $localidad->id;
                    $provinciaNacimiento_id = $localidad->provincia_uif_id ? (int) $localidad->provincia_uif_id : null;
                }
            } catch (Exception $e) {
                $localidadNacimiento_id = null;
                $provinciaNacimiento_id = null;
            }

            // Lee pais de nacimiento
            try {
                $pais = $this->pais_uifRepository->findPorCodigo($data->ipaisnac);

                $paisNacimiento_id = null;
                if ($pais) {
                    $paisNacimiento_id = $pais->id;
                }
            } catch (Exception $e) {
                $paisNacimiento_id = null;
            }

            // Lee la localidad
            try {
                $localidad = $this->localidad_uifRepository->findPorCodigo($data->ilocalidad);

                $localidad_id = null;
                if ($localidad) {
                    $localidad_id = $localidad->id;
                }
            } catch (Exception $e) {
                $localidad_id = 337;
            }

            // Lee la provincia
            try {
                $provincia = $this->provincia_uifRepository->findPorCodigo($data->iprovincia);

                $provincia_id = null;
                if ($provincia) {
                    $provincia_id = $provincia->id;
                }
            } catch (Exception $e) {
                $provincia_id = 26;
            }
            // Lee pais
            try {
                $pais = $this->pais_uifRepository->findPorCodigo($data->ipais);

                $pais_id = null;
                if ($pais) {
                    $pais_id = $pais->id;
                }
            } catch (Exception $e) {
                $pais_id = 257;
            }

            // Lee actividad
            try {
                $actividad = $this->actividad_uifRepository->find($data->codigoprofesion);

                $actividad_id = null;
                if ($actividad) {
                    $actividad_id = $actividad->id;
                }
            } catch (Exception $e) {
                $actividad_id = 1;
            }

            $estadoCivil_id = 1;
            switch ($data->cestadocivil) {
                case 'S':
                    $estadoCivil_id = 1;
                    break;
                case 'C':
                    $estadoCivil_id = 2;
                    break;
                case 'D':
                    $estadoCivil_id = 3;
                    break;
                case 'V':
                    $estadoCivil_id = 4;
                    break;
                case 'E':
                    $estadoCivil_id = 5;
                    break;
            }

            switch ($data->ipep) {
                case 1:
                    $pep_id = 2;
                    break;
                default:
                    $pep_id = 1;
                    break;
            }

            switch ($data->iparaiso) {
                case 1:
                    $resideParaisoFiscal = 'S';
                    break;
                default:
                    $resideParaisoFiscal = 'N';
                    break;
            }

            switch ($data->iexterior) {
                case 1:
                    $resideExterior = 'S';
                    break;
                default:
                    $resideExterior = 'N';
                    break;
            }

            switch ($data->cdecljur) {
                case '1':
                    $firmoDeclaracionJurada = 'S';
                    break;
                default:
                    $firmoDeclaracionJurada = 'N';
            }

            // Lee SO
            switch ($data->cso) {
                case '1':
                    $so_id = 2;
                    break;
                default:
                    $so_id = 1;
                    break;
            }

            // Cumple normativa de sujeto obligado
            switch ($data->ccumplenormativaso) {
                case '1':
                    $cumpleNormativaSo = 'S';
                    break;
                default:
                    $cumpleNormativaSo = 'N';
                    break;
            }

            $riesgoPep = 'BAJO';
            switch ($data->criesgo) {
                case 'B':
                    $riesgoPep = 'BAJO';
                    break;
                case 'M':
                    $riesgoPep = 'MEDIO';
                    break;
                case 'A':
                    $riesgoPep = 'ALTO';
                    break;
            }

            // Lee nivel socioeconomico
            $nivelsocioeconomico_id = 8;

            switch ($data->inivelsocecon) {
                case 1:
                    $nivelsocioeconomico_id = 1;
                    break;
                case 2:
                    $nivelsocioeconomico_id = 2;
                    break;
                case 3:
                    $nivelsocioeconomico_id = 3;
                    break;
                case 4:
                    $nivelsocioeconomico_id = 4;
                    break;
                case 5:
                    $nivelsocioeconomico_id = 6;
                    break;
                case 6:
                    $nivelsocioeconomico_id = 7;
                    break;
                case 7:
                    $nivelsocioeconomico_id = 5;
                    break;
            }

            $sexo = '';
            switch ($data->csexo) {
                case 'M':
                    $sexo = 'MASCULINO';
                    break;
                case 'F':
                    $sexo = 'FEMENINO';
                    break;
            }

            $inroclienteid = $data->inroclienteid;

            $nroDocumento = trim((string) $data->inrodocumento);

            $inroclienteidValidado = filter_var($inroclienteid, FILTER_VALIDATE_INT);
            $inroclienteidParaGuardar = ($inroclienteidValidado !== false && $inroclienteidValidado > 0)
                ? $inroclienteidValidado
                : null;

            $payload = [
                'inroclienteid' => $inroclienteidParaGuardar,
                'anita_origen' => $anitaOrigen,
                'nombre' => $data->cnombre,
                'tipodocumento_id' => $tipodocumento_id,
                'numerodocumento' => $nroDocumento,
                'cuit' => $data->ccuit,
                'fechanacimiento' => $data->ifechanac,
                'localidadnacimiento_id' => $localidadNacimiento_id,
                'provincianacimiento_id' => $provinciaNacimiento_id,
                'paisnacimiento_id' => $paisNacimiento_id,
                'sexo' => $sexo,
                'estadocivil_uif_id' => $estadoCivil_id,
                'domicilio' => $data->cdomicilio,
                'piso' => $data->cpiso,
                'departamento' => $data->cdepto,
                'localidad_uif_id' => $localidad_id,
                'codigopostal' => $data->ccodigopostal,
                'provincia_uif_id' => $provincia_id,
                'pais_uif_id' => $pais_id,
                'telefono' => $data->ctelefono,
                'email' => $data->cemail,
                'actividad_uif_id' => $actividad_id,
                'estado' => $data->cestado,
                'pep_uif_id' => $pep_id,
                'resideparaisofiscal' => $resideParaisoFiscal,
                'resideexterior' => $resideExterior,
                'fechafirmapep' => $data->ifechafirmapep,
                'fechaconfirmapep' => $data->ifeconfirmapep,
                'fechainformepep' => $data->ifeinformepep,
                'fechainformenosis' => $data->ifeinformenosis,
                'fechavencimientodni' => $data->ifevtodni,
                'fechavencimientoactividad' => $data->ifevtoactividad,
                'firmodeclaracionjurada' => $firmoDeclaracionJurada,
                'so_uif_id' => $so_id,
                'cumplenormativaso' => $cumpleNormativaSo,
                'riesgopep' => $riesgoPep,
                'nivelsocioeconomico_uif_id' => $nivelsocioeconomico_id,
                'usuario_id' => Auth::user()->id,
            ];

            $existente = $this->buscarClienteUifParaUpsertDesdeAnita(
                $inroclienteid,
                $tipodocumento_id,
                $nroDocumento,
                $anitaOrigen
            );

            if ($existente !== null) {
                $existente->update($payload);
                $cliente_uif = $existente->fresh();
            } else {
                $cliente_uif = $this->model->create($payload);
            }

            // Lee los premios (bridge o preload bulk)
            if ($premiosPreload !== null) {
                $dataAnita = $premiosPreload;
            } else {
                $apiAnita = new ApiAnita;
                $payloadPremios = [
                    'acc' => 'list', 'tabla' => 'premios_uif',
                    'sistema' => 'base_admin',
                    'campos' => '
					inropremioid,
					inroclienteid,
					ctipodocumento,
					inrodocumento,
					ifechaentrega,
					fpremio,
					cmoneda,
					cdescpremio,
					ifechaalta,
					choraalta,
					iusuarioalta,
					ifechaultmodif,
					choraultmodif,
					iusuarioultmodif,
					isupervisoralta ,
					choraentrega,
					cnroticket,
					cposicion,
					ifechatito, 
					ctipomov,
					cmediopago,
					crecibo_pago,
					cextfoto
				',
                    'whereArmado' => " WHERE inroclienteid = '".$inroclienteid."' ",
                ];
                if ($servidorOpt !== '') {
                    $payloadPremios['servidor'] = $servidorOpt;
                }
                $dataAnita = json_decode($apiAnita->apiCall($payloadPremios));
            }

            if (is_array($dataAnita) && count($dataAnita) > 0) {
                foreach ($dataAnita as $premioAnita) {
                    $anitaPremioId = (int) ($premioAnita->inropremioid ?? 0);
                    $posicionAnita = isset($premioAnita->cposicion) ? (string) $premioAnita->cposicion : null;

                    // Override RULETA por bien_uso solo en altas nuevas (no re-sync de existentes).
                    $esAltaNueva = true;
                    if ($anitaPremioId > 0) {
                        $existQ = Cliente_Premio_Uif::query()
                            ->where('cliente_uif_id', $cliente_uif->id)
                            ->where('anita_inropremioid', $anitaPremioId);
                        if ((int) $salaId > 0) {
                            $existQ->where('sala_id', $salaId);
                        }
                        $esAltaNueva = ! $existQ->exists();
                    }

                    $juego_id = JuegoUifDesdeAnitaResolver::resolveJuegoUifId(
                        isset($premioAnita->cdescpremio) ? (string) $premioAnita->cdescpremio : null,
                        $posicionAnita,
                        (int) $salaId,
                        $esAltaNueva,
                    );

                    $fechaEntrega = $this->fechaSqlDesdeAnitaYyyymmdd($premioAnita->ifechaentrega ?? 0);
                    $horaEntrega = $this->horaSqlDesdeAnita(isset($premioAnita->choraentrega) ? (string) $premioAnita->choraentrega : null);
                    if (! validarFormatoHora($horaEntrega)) {
                        $horaEntrega = '01:00:00';
                    }

                    $premioPayload = [
                        'anita_inropremioid' => $anitaPremioId,
                        'cliente_uif_id' => $cliente_uif->id,
                        'sala_id' => $salaId,
                        'juego_uif_id' => $juego_id,
                        'fechaentrega' => $fechaEntrega.' '.$horaEntrega,
                        'detalle' => $premioAnita->cdescpremio,
                        'monto' => $premioAnita->fpremio,
                        'moneda_id' => 1,
                        'posicion' => $posicionAnita,
                        'numerotito' => $premioAnita->cnroticket,
                        'fechatito' => $this->fechaSqlOpcionalDesdeAnitaYyyymmdd($premioAnita->ifechatito ?? null),
                        'piderecibopago' => $premioAnita->crecibo_pago,
                        'creousuario_id' => Auth::user()->id,
                    ];
                    // Re-sync: no pisar juego_uif_id (histórico / reclasificación manual).
                    if (! $esAltaNueva) {
                        unset($premioPayload['juego_uif_id']);
                    }

                    $premioLocal = $this->cliente_premio_uifRepository->createUnique($premioPayload);

                    $this->cliente_premio_archivo_uifRepository->traerArchivosDeAnita(
                        $premioLocal->id,
                        $inroclienteid,
                        $premioAnita->inropremioid
                    );

                    $fotoPremio = ClientePremioUifFotoTesoreria::importToPublicStorage(
                        (int) $premioAnita->inropremioid,
                        isset($premioAnita->cextfoto) ? (string) $premioAnita->cextfoto : null
                    );
                    if ($fotoPremio !== null) {
                        $anterior = $premioLocal->foto ?? '';
                        if ($anterior !== '' && $anterior !== $fotoPremio) {
                            ClientePremioUifFotoTesoreria::deletePublicFotoIfUnused($anterior);
                        }
                        $premioLocal->update(['foto' => $fotoPremio]);
                    }
                }
            }

            // Lee los archivos del cliente desde anita y los copia localmente.
            // Mismo criterio de manejo que proveedores: la logica de archivos vive
            // en Cliente_Archivo_UifRepository. La diferencia con proveedores es
            // que el filesystem anita esta montado en /usr2/www/htdocs/uif/archivos,
            // por eso el repo hace cp directo en lugar de scp.
            $this->cliente_archivo_uifRepository->traerArchivosDeAnita(
                $cliente_uif->id,
                $inroclienteid
            );

            // Foto DNI: referenciar archivo en montaje / legacy; reparar basename si en BD no coincide con disco.
            $this->relinkFotodocumentoDesdeDisco($cliente_uif);
            $cliente_uif = $cliente_uif->fresh() ?? $cliente_uif;
        }
    }

    /**
     * Anita usa fecha como entero YYYYMMDD (ifechaentrega).
     */
    private function fechaSqlDesdeAnitaYyyymmdd($valor, string $fallback = '2001-01-01'): string
    {
        $n = (int) $valor;
        if ($n < 20000000) {
            return $fallback;
        }
        $s = str_pad((string) $n, 8, '0', STR_PAD_LEFT);
        if (strlen($s) !== 8 || ! ctype_digit($s)) {
            return $fallback;
        }

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }

    /**
     * Anita: ifechatito como YYYYMMDD o null.
     */
    private function fechaSqlOpcionalDesdeAnitaYyyymmdd($valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $n = (int) $valor;
        if ($n < 18500101 || $n > 29991231) {
            return null;
        }
        $s = str_pad((string) $n, 8, '0', STR_PAD_LEFT);
        if (strlen($s) !== 8 || ! ctype_digit($s)) {
            return null;
        }

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }

    /**
     * Normaliza choraentrega a H:i:s (evita "01:00:00:00" si Anita ya manda segundos).
     */
    private function horaSqlDesdeAnita(?string $chora): string
    {
        $t = trim((string) ($chora ?? ''));
        if ($t === '') {
            return '01:00:00';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
            $h = min(23, max(0, (int) $m[1]));
            $i = min(59, max(0, (int) $m[2]));

            return sprintf('%02d:%02d:00', $h, $i);
        }
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $t, $m)) {
            $h = min(23, max(0, (int) $m[1]));
            $i = min(59, max(0, (int) $m[2]));
            $s = min(59, max(0, (int) $m[3]));

            return sprintf('%02d:%02d:%02d', $h, $i, $s);
        }

        return '01:00:00';
    }

    private function convierteProfesion($profesion)
    {
        switch ($profesion) {
            case 176:
                $profesion_id = 11;
                break;
            case 11:
                $profesion_id = 11;
                break;
            case 157:
                $profesion_id = 3;
                break;
            case 73:
                $profesion_id = 25;
                break;
            case 174:
                $profesion_id = 25;
                break;
            case 108:
                $profesion_id = 33;
                break;
            case 61:
                $profesion_id = 1;
                break;
            case 103:
                $profesion_id = 2;
                break;
            case 2:
                $profesion_id = 35;
                break;
            case 199:
                $profesion_id = 35;
                break;
            case 161:
                $profesion_id = 22;
                break;
            case 3:
                $profesion_id = 30;
                break;
            case 154:
                $profesion_id = 30;
                break;
            case 165:
                $profesion_id = 34;
                break;
            case 166:
                $profesion_id = 32;
                break;
            case 70:
                $profesion_id = 11;
                break;
            case 207:
                $profesion_id = 25;
                break;
            case 56:
                $profesion_id = 11;
                break;
            case 210:
                $profesion_id = 22;
                break;
            case 71:
                $profesion_id = 25;
                break;
            case 121:
                $profesion_id = 36;
                break;
            case 220:
                $profesion_id = 27;
                break;
            case 91:
                $profesion_id = 12;
                break;
            case 115:
                $profesion_id = 22;
                break;
            case 195:
                $profesion_id = 28;
                break;
            case 123:
                $profesion_id = 38;
                break;
            case 150:
                $profesion_id = 11;
                break;
            case 216:
                $profesion_id = 11;
                break;
            case 81:
                $profesion_id = 38;
                break;
            case 181:
                $profesion_id = 1;
                break;
            case 36:
                $profesion_id = 1;
                break;
            case 69:
                $profesion_id = 28;
                break;
            case 118:
                $profesion_id = 27;
                break;
            case 192:
                $profesion_id = 26;
                break;
            case 74:
                $profesion_id = 25;
                break;
            case 25:
                $profesion_id = 7;
                break;
            case 58:
                $profesion_id = 22;
                break;
            case 62:
                $profesion_id = 22;
                break;
            case 202:
                $profesion_id = 39;
                break;
            case 229:
                $profesion_id = 22;
                break;
            case 63:
                $profesion_id = 28;
                break;
            case 26:
                $profesion_id = 26;
                break;
            case 55:
                $profesion_id = 24;
                break;
            case 37:
                $profesion_id = 7;
                break;
            case 1:
                $profesion_id = 15;
                break;
            case 19:
                $profesion_id = 3;
                break;
            case 23:
                $profesion_id = 11;
                break;
            case 68:
                $profesion_id = 11;
                break;
            case 76:
                $profesion_id = 27;
                break;
            case 22:
                $profesion_id = 37;
                break;
            case 186:
                $profesion_id = 11;
                break;
            case 111:
                $profesion_id = 27;
                break;
            case 173:
                $profesion_id = 13;
                break;
            case 96:
                $profesion_id = 13;
                break;
            case 140:
                $profesion_id = 14;
                break;
            case 131:
                $profesion_id = 11;
                break;
            case 134:
                $profesion_id = 3;
                break;
            case 183:
                $profesion_id = 25;
                break;
            case 29:
                $profesion_id = 37;
                break;
            case 189:
                $profesion_id = 41;
                break;
            case 163:
                $profesion_id = 11;
                break;
            case 177:
                $profesion_id = 11;
                break;
            case 179:
                $profesion_id = 11;
                break;
            case 178:
                $profesion_id = 11;
                break;
            case 193:
                $profesion_id = 27;
                break;
            case 171:
                $profesion_id = 16;
                break;
            case 66:
                $profesion_id = 24;
                break;
            case 133:
                $profesion_id = 11;
                break;
            case 214:
                $profesion_id = 11;
                break;
            case 142:
                $profesion_id = 22;
                break;
            case 190:
                $profesion_id = 42;
                break;
            case 53:
                $profesion_id = 2;
                break;
            case 90:
                $profesion_id = 42;
                break;
            case 226:
                $profesion_id = 1;
                break;
            case 167:
                $profesion_id = 1;
                break;
            case 48:
                $profesion_id = 2;
                break;
            case 4:
                $profesion_id = 1;
                break;
            case 109:
                $profesion_id = 43;
                break;
            case 107:
                $profesion_id = 31;
                break;
            case 34:
                $profesion_id = 44;
                break;
            case 33:
                $profesion_id = 10;
                break;
            case 162:
                $profesion_id = 19;
                break;
            case 225:
                $profesion_id = 1;
                break;
            case 182:
                $profesion_id = 1;
                break;
            case 15:
                $profesion_id = 24;
                break;
            case 59:
                $profesion_id = 12;
                break;
            case 201:
                $profesion_id = 25;
                break;
            case 137:
                $profesion_id = 27;
                break;
            case 135:
                $profesion_id = 27;
                break;
            case 106:
                $profesion_id = 17;
                break;
            case 116:
                $profesion_id = 24;
                break;
            case 151:
                $profesion_id = 11;
                break;
            case 105:
                $profesion_id = 27;
                break;
            case 89:
                $profesion_id = 37;
                break;
            case 80:
                $profesion_id = 22;
                break;
            case 155:
                $profesion_id = 28;
                break;
            case 102:
                $profesion_id = 38;
                break;
            case 60:
                $profesion_id = 45;
                break;
            case 114:
                $profesion_id = 24;
                break;
            case 149:
                $profesion_id = 38;
                break;
            case 198:
                $profesion_id = 1;
                break;
            case 124:
                $profesion_id = 38;
                break;
            case 153:
                $profesion_id = 29;
                break;
            case 184:
                $profesion_id = 25;
                break;
            case 221:
                $profesion_id = 22;
                break;
            case 158:
                $profesion_id = 19;
                break;
            case 127:
                $profesion_id = 11;
                break;
            case 30:
                $profesion_id = 4;
                break;
            case 156:
                $profesion_id = 24;
                break;
            case 120:
                $profesion_id = 3;
                break;
            case 13:
                $profesion_id = 21;
                break;
            case 230:
                $profesion_id = 21;
                break;
            case 141:
                $profesion_id = 24;
                break;
            case 99:
                $profesion_id = 7;
                break;
            case 228:
                $profesion_id = 22;
                break;
            case 10:
                $profesion_id = 11;
                break;
            case 92:
                $profesion_id = 11;
                break;
            case 122:
                $profesion_id = 36;
                break;
            case 21:
                $profesion_id = 11;
                break;
            case 75:
                $profesion_id = 11;
                break;
            case 8:
                $profesion_id = 40;
                break;
            case 206:
                $profesion_id = 27;
                break;
            case 129:
                $profesion_id = 27;
                break;
            case 39:
                $profesion_id = 46;
                break;
            case 139:
                $profesion_id = 4;
                break;
            case 126:
                $profesion_id = 27;
                break;
            case 227:
                $profesion_id = 24;
                break;
            case 18:
                $profesion_id = 22;
                break;
            case 20:
                $profesion_id = 24;
                break;
            case 170:
                $profesion_id = 24;
                break;
            case 132:
                $profesion_id = 19;
                break;
            case 130:
                $profesion_id = 27;
                break;
            case 24:
                $profesion_id = 33;
                break;
            case 83:
                $profesion_id = 34;
                break;
            case 110:
                $profesion_id = 47;
                break;
            case 203:
                $profesion_id = 28;
                break;
            case 212:
                $profesion_id = 27;
                break;
            case 204:
                $profesion_id = 25;
                break;
            case 197:
                $profesion_id = 24;
                break;
            case 233:
                $profesion_id = 13;
                break;
            case 5:
                $profesion_id = 13;
                break;
            case 6:
                $profesion_id = 22;
                break;
            case 12:
                $profesion_id = 24;
                break;
            case 169:
                $profesion_id = 24;
                break;
            case 145:
                $profesion_id = 22;
                break;
            case 200:
                $profesion_id = 24;
                break;
            case 7:
                $profesion_id = 13;
                break;
            case 231:
                $profesion_id = 1;
                break;
            case 232:
                $profesion_id = 1;
                break;
            case 215:
                $profesion_id = 11;
                break;
            case 97:
                $profesion_id = 22;
                break;
            case 100:
                $profesion_id = 27;
                break;
            case 82:
                $profesion_id = 33;
                break;
            case 211:
                $profesion_id = 38;
                break;
            case 52:
                $profesion_id = 48;
                break;
            case 219:
                $profesion_id = 27;
                break;
            case 16:
                $profesion_id = 20;
                break;
            case 32:
                $profesion_id = 38;
                break;
            case 85:
                $profesion_id = 22;
                break;
            case 218:
                $profesion_id = 13;
                break;
            case 196:
                $profesion_id = 22;
                break;
            case 148:
                $profesion_id = 22;
                break;
            case 125:
                $profesion_id = 27;
                break;
            case 17:
                $profesion_id = 38;
                break;
            case 77:
                $profesion_id = 37;
                break;
            case 9:
                $profesion_id = 11;
                break;
            case 138:
                $profesion_id = 16;
                break;
            case 146:
                $profesion_id = 27;
                break;
            case 93:
                $profesion_id = 24;
                break;
            case 64:
                $profesion_id = 24;
                break;
            case 65:
                $profesion_id = 24;
                break;
            case 208:
                $profesion_id = 11;
                break;
            case 217:
                $profesion_id = 11;
                break;
            case 117:
                $profesion_id = 24;
                break;
            case 72:
                $profesion_id = 1;
                break;
            case 185:
                $profesion_id = 1;
                break;
            case 180:
                $profesion_id = 1;
                break;
            case 88:
                $profesion_id = 11;
                break;
            case 222:
                $profesion_id = 27;
                break;
            case 119:
                $profesion_id = 1;
                break;
            case 104:
                $profesion_id = 38;
                break;
            case 57:
                $profesion_id = 5;
                break;
            case 147:
                $profesion_id = 22;
                break;
            case 187:
                $profesion_id = 38;
                break;
            case 38:
                $profesion_id = 28;
                break;
            case 209:
                $profesion_id = 22;
                break;
            case 143:
                $profesion_id = 22;
                break;
            case 86:
                $profesion_id = 22;
                break;
            case 224:
                $profesion_id = 24;
                break;
            case 223:
                $profesion_id = 24;
                break;
            case 213:
                $profesion_id = 11;
                break;
            case 136:
                $profesion_id = 1;
                break;
            case 172:
                $profesion_id = 22;
                break;
            case 188:
                $profesion_id = 27;
                break;
            case 101:
                $profesion_id = 49;
                break;
            case 27:
                $profesion_id = 50;
                break;
            case 128:
                $profesion_id = 27;
                break;
        }

        return $profesion_id;
    }

    /**
     * HTML de filas para el modal de consulta de clientes UIF.
     *
     * @param  string|null  $consulta
     * @param  string|null  $anitaOrigen  Filtrar por origen (biyemas|kandiko|rebisco)
     */
    public function consultaCliente_UifHtml($consulta = null, $anitaOrigen = null): string
    {
        $consulta = trim((string) ($consulta ?? ''));
        $anitaOrigen = strtolower(trim((string) ($anitaOrigen ?? '')));

        $query = $this->model->newQuery()
            ->with(['tipodocumentos:id,nombre'])
            ->withCount(['cliente_premios_uif', 'cliente_archivos_uif'])
            ->orderByDesc('id')
            ->limit(80);

        if ($anitaOrigen !== '' && array_key_exists($anitaOrigen, config('uif.anita_origenes', []))) {
            $query->where('anita_origen', $anitaOrigen);
        } else {
            $permitidos = ClienteUifOrigenPcSupport::contexto()['origenes_permitidos'] ?? [];
            if (is_array($permitidos) && $permitidos !== []) {
                $query->whereIn('anita_origen', $permitidos);
            }
        }

        if ($consulta !== '') {
            $like = '%'.$consulta.'%';
            $query->where(function ($q) use ($consulta, $like) {
                if (ctype_digit($consulta)) {
                    $q->orWhere('id', (int) $consulta)
                        ->orWhere('numerodocumento', 'like', $like)
                        ->orWhere('inroclienteid', (int) $consulta);
                } else {
                    $q->orWhere('numerodocumento', 'like', $like)
                        ->orWhere('nombre', 'like', $like)
                        ->orWhere('cuit', 'like', $like);
                }
            });
        }

        $rows = $query->get();
        $puedeAbrirAbm = function_exists('can') && (
            can('editar-cliente-uif', false) || can('listar-cliente-uif', false)
        );

        $html = '';
        if ($rows->isEmpty()) {
            return '<tr><td colspan="7" class="text-center text-muted">Sin resultados</td></tr>';
        }

        foreach ($rows as $row) {
            $origenLabel = ClienteUifOrigenPcSupport::labelOrigen((string) ($row->anita_origen ?? ''));
            $html .= '<tr>';
            $html .= '<td class="id">'.(int) $row->id.'</td>';
            $html .= '<td class="origen">'.e($origenLabel).'</td>';
            $html .= '<td class="nombre">'.e((string) $row->nombre).'</td>';
            $html .= '<td class="tipodoc">'.e((string) ($row->tipodocumentos->nombre ?? '')).'</td>';
            $html .= '<td class="numerodocumento">'.e((string) $row->numerodocumento).'</td>';
            $html .= '<td class="premios">'.(int) ($row->cliente_premios_uif_count ?? 0).'</td>';
            $html .= '<td class="text-nowrap">';
            $html .= '<a class="btn btn-warning btn-sm eligeconsultacliente_uif" href="javascript:void(0)">Elegir</a>';
            if ($puedeAbrirAbm) {
                $url = route('edita_cliente_uif', [
                    'id' => (int) $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        return $html;
    }

    /**
     * Resumen JSON para resolver cliente por ID (blur/Enter del campo consulta).
     *
     * @return array<string, mixed>|null
     */
    public function findResumenParaConsulta(int $id): ?array
    {
        $cliente = $this->model->newQuery()
            ->with(['tipodocumentos:id,nombre'])
            ->withCount(['cliente_premios_uif', 'cliente_archivos_uif', 'cliente_riesgos_uif'])
            ->with(['cliente_premios_uif' => fn ($q) => $q
                ->with('juegos_uif:id,nombre')
                ->orderByDesc('fechaentrega')
                ->orderByDesc('id')
                ->limit(1)])
            ->find($id);

        if ($cliente === null) {
            return null;
        }

        $ultimo = $cliente->cliente_premios_uif->first();
        $fechaUltimo = $ultimo && $ultimo->fechaentrega
            ? $ultimo->fechaentrega->format('d/m/Y H:i')
            : null;

        return [
            'id' => (int) $cliente->id,
            'nombre' => (string) ($cliente->nombre ?? ''),
            'numerodocumento' => (string) ($cliente->numerodocumento ?? ''),
            'tipodocumento' => (string) ($cliente->tipodocumentos->nombre ?? 'DNI'),
            'anita_origen' => (string) ($cliente->anita_origen ?? ''),
            'origen_label' => ClienteUifOrigenPcSupport::labelOrigen((string) ($cliente->anita_origen ?? '')),
            'domicilio' => (string) ($cliente->domicilio ?? ''),
            'telefono' => (string) ($cliente->telefono ?? ''),
            'email' => (string) ($cliente->email ?? ''),
            'estado' => (string) ($cliente->estado ?? ''),
            'inroclienteid' => $cliente->inroclienteid !== null ? (int) $cliente->inroclienteid : null,
            'premios_count' => (int) ($cliente->cliente_premios_uif_count ?? 0),
            'archivos_count' => (int) ($cliente->cliente_archivos_uif_count ?? 0),
            'riesgos_count' => (int) ($cliente->cliente_riesgos_uif_count ?? 0),
            'ultimo_premio' => $ultimo ? [
                'fechaentrega' => $fechaUltimo,
                'monto' => (float) ($ultimo->monto ?? 0),
                'monto_fmt' => number_format((float) ($ultimo->monto ?? 0), 2, ',', '.'),
                'juego' => (string) (optional($ultimo->juegos_uif)->nombre ?? ''),
            ] : null,
        ];
    }
}
