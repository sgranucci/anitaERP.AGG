<?php

namespace App\Repositories\Compras;

use App\ApiAnita;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Concepto_Ivacompra_Condicioniva;
use App\Models\Compras\Concepto_Ivacompra_Empresa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\ImpuestoRepositoryInterface;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Support\Compras\ConceptoIvacompraListadoFiltros;
use Auth;
use DB;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class Concepto_IvacompraRepository implements Concepto_IvacompraRepositoryInterface
{
    protected $model;

    protected $model_condicioniva;

    private $columna_ivacompraRepository;

    private $provinciaRepository;

    private $impuestoRepository;

    private $cuentacontableRepository;

    private $empresaRepository;

    protected $tableAnita = 'conccomp';

    protected $keyField = 'codigo';

    protected $keyFieldAnita = 'concc_concepto';

    private const RELACIONES_FORM = [
        'concepto_ivacompra_condicionivas',
        'columna_ivacompras',
        'empresas',
        'cuentacontablesdebe',
        'cuentacontableshaber',
        'provincias',
        'impuestos',
        'concepto_ivacompra_empresas.empresa',
        'concepto_ivacompra_empresas.cuentacontabledebe',
        'concepto_ivacompra_empresas.cuentacontablehaber',
    ];

    public function __construct(
        Concepto_Ivacompra $concepto_ivacompra,
        Concepto_Ivacompra_Condicioniva $concepto_ivacompra_condicioniva,
        Columna_IvacompraRepositoryInterface $columna_ivacomprarepository,
        ProvinciaRepositoryInterface $provinciarepository,
        ImpuestoRepositoryInterface $impuestorepository,
        CuentacontableRepositoryInterface $cuentacontablerepository,
        EmpresaRepositoryInterface $empresarepository,
    ) {
        $this->model = $concepto_ivacompra;
        $this->model_condicioniva = $concepto_ivacompra_condicioniva;
        $this->columna_ivacompraRepository = $columna_ivacomprarepository;
        $this->provinciaRepository = $provinciarepository;
        $this->impuestoRepository = $impuestorepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->empresaRepository = $empresarepository;
    }

    public function all()
    {
        $hay_concepto_ivacompra = Concepto_Ivacompra::first();

        if (! $hay_concepto_ivacompra) {
            $this->sincronizarConAnita();
        }

        return $this->model->with(self::RELACIONES_FORM)
            ->orderBy('nombre', 'ASC')
            ->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, Concepto_Ivacompra>
     */
    public function leeConceptoIvacompra($filtros, bool $paginar = false)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ConceptoIvacompraListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => $this->empresaRepository->traeEmpresasAsignadas(),
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ConceptoIvacompraListadoFiltros::filtrosVacios();
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('concepto_ivacompra.*')
            ->with([
                'columna_ivacompras',
                'provincias',
                'impuestos',
                'concepto_ivacompra_empresas.empresa',
                'concepto_ivacompra_empresas.cuentacontabledebe',
                'concepto_ivacompra_empresas.cuentacontablehaber',
                'cuentacontablesdebe',
                'cuentacontableshaber',
            ]);

        ConceptoIvacompraListadoFiltros::aplicarJoinsListado($query, $filtros);
        ConceptoIvacompraListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (ConceptoIvacompraListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ConceptoIvacompraListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('concepto_ivacompra.nombre');

        return $paginar
            ? $query->paginate(10)->appends(ConceptoIvacompraListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function create(array $data)
    {
        [$cabecera, $lineas, $condicionivaIds] = $this->separarCabeceraLineasYCondiciones($data);

        DB::beginTransaction();
        try {
            $concepto = $this->model->create($cabecera);
            $this->sincronizarLineasEmpresa((int) $concepto->id, $lineas);
            $this->sincronizarCondicionesIva((int) $concepto->id, $condicionivaIds);
            $this->sincronizarCabeceraLegacyDesdeLineas((int) $concepto->id, $lineas);

            $payloadAnita = $this->payloadAnitaDesdeRegistro(
                $concepto->fresh(self::RELACIONES_FORM),
                $condicionivaIds
            );
            $this->guardarAnita($payloadAnita);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return $concepto->fresh(self::RELACIONES_FORM);
    }

    public function update(array $data, $id)
    {
        [$cabecera, $lineas, $condicionivaIds] = $this->separarCabeceraLineasYCondiciones($data);

        DB::beginTransaction();
        try {
            $concepto = $this->model->findOrFail($id);
            $concepto->update($cabecera);
            $this->sincronizarLineasEmpresa((int) $id, $lineas);
            $this->sincronizarCondicionesIva((int) $id, $condicionivaIds);
            $this->sincronizarCabeceraLegacyDesdeLineas((int) $id, $lineas);

            $fresh = $concepto->fresh(self::RELACIONES_FORM);
            $payloadAnita = $this->payloadAnitaDesdeRegistro($fresh, $condicionivaIds);
            $this->actualizarAnita($payloadAnita, (string) ($payloadAnita['codigo'] ?? $fresh->codigo));

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return $fresh;
    }

    public function delete($id)
    {
        $concepto_ivacompra = Concepto_Ivacompra::find($id);
        if ($concepto_ivacompra === null) {
            return false;
        }

        $this->eliminarAnita($concepto_ivacompra->codigo);

        Concepto_Ivacompra_Empresa::query()->where('concepto_ivacompra_id', $id)->delete();
        $this->model_condicioniva->newQuery()->where('concepto_ivacompra_id', $id)->delete();

        return (bool) $this->model->destroy($id);
    }

    public function find($id)
    {
        $concepto_ivacompra = $this->model->with(self::RELACIONES_FORM)->find($id);
        if ($concepto_ivacompra === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $concepto_ivacompra;
    }

    public function findOrFail($id)
    {
        $concepto_ivacompra = $this->model->with(self::RELACIONES_FORM)->findOrFail($id);
        if ($concepto_ivacompra === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $concepto_ivacompra;
    }

    public function findPorCodigo($codigo)
    {
        return $this->model->with(self::RELACIONES_FORM)->where('codigo', $codigo)->first();
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                        'sistema' => 'compras',
						'campos' => "
                        			concc_concepto as codigo,
    		                        concc_concepto",
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Concepto_Ivacompra::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
            if (!in_array(ltrim($value->{$this->keyField}, '0'), $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'compras',
            'campos' => '
                concc_concepto,
                concc_desc,
                concc_formula,
                concc_columna_sub,
                concc_contenido,
                concc_cta_debe,
                concc_cta_haber,
                concc_ctapte_debe,
                concc_ctapte_haber,
                concc_tipo_conc,
                concc_alicuota_iva,
                concc_retiene_ibr
            ',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

        	$datamov = array( 
            	'acc' => 'list', 
				'sistema' => 'compras',
				'tabla' => 'concciva', 
            	'campos' => '
                	conci_concepto,
					conci_cond_iva
            	' , 
            	'whereArmado' => " WHERE conci_concepto = '".$key."' " 
        	);
        	$dataAnitamov = json_decode($apiAnita->apiCall($datamov));

            // Busca columna de subdiario
            $columna_ivacompra = $this->columna_ivacompraRepository->findPorNumeroColumna($data->concc_columna_sub);
            $columna_ivacompra_id = null;
            if ($columna_ivacompra)
                $columna_ivacompra_id = $columna_ivacompra->id;

            $retieneGanancia = 'N';
            switch($data->concc_contenido)
            {
            case 'C':
                $retieneGanancia = 'S';
                break;
            case 'D':
            case 'I':
            case 'O':
            case 'E':
                $retieneGanancia = 'N';
                break;
            }

            // Si es ingresos brutos busca la jurisdiccion
            $provincia_id = null;
            if ($data->concc_tipo_conc == 'B' || $data->concc_tipo_conc == 'S' || $data->concc_tipo_conc == 'A')
            {
                $provincia = $this->provinciaRepository->findPorJurisdiccion($data->concc_alicuota_iva);
                if ($provincia)
                    $provincia_id = $provincia->id;
            }

            // Si es alicuota busca id de impuesto
            $impuesto_id = null;
            if ($data->concc_tipo_conc == 'G' || $data->concc_tipo_conc == 'P' || $data->concc_tipo_conc == 'I')
            {
                $impuesto = $this->impuestoRepository->findPorValor($data->concc_alicuota_iva);
                if ($impuesto)
                    $impuesto_id = $impuesto->id;
            }

            // Lee cuenta contable al debe
            $cuenta = $this->cuentacontableRepository->findPorCodigo(1, $data->concc_cta_debe);
			if ($cuenta)
				$cuentacontabledebe_id = $cuenta->id;
			else
				$cuentacontabledebe_id = NULL;

            // Lee cuenta contable al haber
            $cuenta = $this->cuentacontableRepository->findPorCodigo(1, $data->concc_cta_haber);
            if ($cuenta)
                $cuentacontablehaber_id = $cuenta->id;
            else
                $cuentacontablehaber_id = NULL;
            
			$arr_campos = [
                'nombre' => $data->concc_desc, 
                'codigo' => $data->concc_concepto, 
                'formula' => $data->concc_formula, 
                'columna_ivacompra_id' => $columna_ivacompra_id, 
                'empresa_id' => null, 
                'cuentacontabledebe_id' => $cuentacontabledebe_id, 
                'cuentacontablehaber_id' => $cuentacontablehaber_id, 
                'tipoconcepto' => $data->concc_tipo_conc, 
                'retieneganancia' => $retieneGanancia, 
                'retieneIIBB' => $data->concc_retiene_ibr, 
                'provincia_id' => $provincia_id, 
                'impuesto_id' => $impuesto_id
            ];
        	$concepto_ivacompra = $this->model->create($arr_campos);

            if ($concepto_ivacompra)
			{
                $empresaIdLinea = 0;
                if ($cuentacontabledebe_id) {
                    $cta = $this->cuentacontableRepository->findPorId($cuentacontabledebe_id);
                    $empresaIdLinea = (int) ($cta->empresa_id ?? 0);
                }
                if ($empresaIdLinea <= 0 && $cuentacontablehaber_id) {
                    $cta = $this->cuentacontableRepository->findPorId($cuentacontablehaber_id);
                    $empresaIdLinea = (int) ($cta->empresa_id ?? 0);
                }
                if ($empresaIdLinea <= 0) {
                    $empresaIdLinea = 1;
                }
                if ($cuentacontabledebe_id || $cuentacontablehaber_id) {
                    Concepto_Ivacompra_Empresa::query()->create([
                        'concepto_ivacompra_id' => $concepto_ivacompra->id,
                        'empresa_id' => $empresaIdLinea,
                        'cuentacontabledebe_id' => $cuentacontabledebe_id,
                        'cuentacontablehaber_id' => $cuentacontablehaber_id,
                    ]);
                }

				for ($i = 0; $i < count($dataAnitamov); $i++)
				{
        			$this->model_condicioniva->create([
            											'concepto_ivacompra_id' => $concepto_ivacompra->id,
            											'condicioniva_id' => $dataAnitamov[$i]->conci_cond_iva
														]);
				}
			}
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $this->armaVariablesParaGrabar($request, $columnaSubdiario, $contenido, $cuentaDebe,
                                            $cuentaHaber, $alicuotaIva);

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'sistema' => 'compras',
            'campos' => ' 
                concc_concepto,
                concc_desc,
                concc_formula,
                concc_columna_sub,
                concc_contenido,
                concc_cta_debe,
                concc_cta_haber,
                concc_ctapte_debe,
                concc_ctapte_haber,
                concc_tipo_conc,
                concc_alicuota_iva,
                concc_retiene_ibr
				',
            'valores' => " 
				'".$request['codigo']."', 
                '".$request['nombre']."', 
                '".$request['formula']."', 
                '".$columnaSubdiario."', 
                '".$contenido."', 
                '".$cuentaDebe."', 
                '".$cuentaHaber."', 
                '".'0'."', 
                '".'0'."', 
                '".$request['tipoconcepto']."',
                '".$alicuotaIva."',
                '".$request['retieneIIBB']."' "
        );
        $apiAnita->apiCallEscritura($data);

        if (isset($request['condicioniva_ids']))
        {
            $condicioniva_ids = $request['condicioniva_ids'];

            for ($i_rango=0; $i_rango < count($condicioniva_ids); $i_rango++) 
            {
                if ($condicioniva_ids[$i_rango] > 0)
                {
                    $apiAnita = new ApiAnita();

                    $data = array( 'tabla' => 'concciva', 
                        'acc' => 'insert',
                        'sistema' => 'compras',
                        'campos' => '
                                conci_concepto,
                                conci_cond_iva
                                ',
                        'valores' => " 
                                '".$request['codigo']."', 
                                '".$condicioniva_ids[$i_rango]."' "
                        );
                        
                    $apiAnita->apiCallEscritura($data);
                }
            }
        }
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $this->armaVariablesParaGrabar($request, $columnaSubdiario, $contenido, $cuentaDebe,
                                            $cuentaHaber, $alicuotaIva);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'valores' => " 
                concc_concepto 	        = '".$request['codigo']."',
                concc_desc              = '".$request['nombre']."',
                concc_formula           = '".$request['formula']."',
                concc_columna_sub       = '".$columnaSubdiario."',
                concc_contenido         = '".$contenido."',
                concc_cta_debe          = '".$cuentaDebe."',
                concc_cta_haber         = '".$cuentaHaber."',
                concc_tipo_conc         = '".$request['tipoconcepto']."',
                concc_alicuota_iva      = '".$alicuotaIva."',
                concc_retiene_ibr       = '".$request['retieneIIBB']." ' ",
				'whereArmado' => " WHERE concc_concepto = '".$id."' " );
        $apiAnita->apiCallEscritura($data);

        // Elimina los movimientos
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'delete', 
            'tabla' => 'concciva',
            'sistema' => 'compras',
            'whereArmado' => " WHERE conci_concepto = '".$id."' " );
        $apiAnita->apiCallEscritura($data);

        if (isset($request['condicioniva_ids']))
        {
            $condicioniva_ids = $request['condicioniva_ids'];

            // Graba los movimientos
            for ($i_rango=0; $i_rango < count($condicioniva_ids); $i_rango++) 
            {
                if ($condicioniva_ids[$i_rango] > 0)
                {
                    $apiAnita = new ApiAnita();

                    $data = array( 'tabla' => 'concciva', 
                        'acc' => 'insert',
                        'sistema' => 'compras',
                        'campos' => '
                                conci_concepto,
                                conci_cond_iva
                                ',
                        'valores' => " 
                                '".$id."', 
                                '".$condicioniva_ids[$i_rango]."' "
                        );

                    $apiAnita->apiCallEscritura($data);
                }
            }
        }
	}

    private function armaVariablesParaGrabar($request, &$columnaSubdiario, &$contenido, &$cuentaDebe,
                                            &$cuentaHaber, &$alicuotaIva)
    {
        $columna_ivacompra = $this->columna_ivacompraRepository->find($request['columna_ivacompra_id']);
        $columnaSubdiario = 1;
        if ($columna_ivacompra)
            $columnaSubdiario = $columna_ivacompra->numerocolumna;

        $contenido = 'C';
        switch($request['tipoconcepto'])
        {
        case 'N':
        case 'G':
        case 'E':
            if ($request['retieneganancia'] == 'S')
                $contenido = 'C';
            else    
                $contenido = 'D';
            break;
        case 'I':
            $contenido = 'I';
            break;
        case 'P':
        case 'B':
        case 'M':
        case 'T':
        case 'S':
        case 'A':
            $contenido = 'O';
            break;
        }

        // Lee cuenta contable
        $cuentaDebeId = (int) ($request['cuentacontabledebe_id'] ?? 0);
        $cuenta = $cuentaDebeId > 0 ? $this->cuentacontableRepository->findPorId($cuentaDebeId) : null;
        if ($cuenta)
            $cuentaDebe = $cuenta->codigo;
        else
            $cuentaDebe = 0;

        // Lee cuenta contable
        $cuentaHaberId = (int) ($request['cuentacontablehaber_id'] ?? 0);
        $cuenta = $cuentaHaberId > 0 ? $this->cuentacontableRepository->findPorId($cuentaHaberId) : null;
        if ($cuenta)
            $cuentaHaber = $cuenta->codigo;
        else
            $cuentaHaber = 0;

        $alicuotaIva = null;
        if ($request['tipoconcepto'] == 'B' || $request['tipoconcepto'] == 'S' || $request['tipoconcepto'] == 'A')            
        {
            $provincia = ! empty($request['provincia_id'])
                ? $this->provinciaRepository->findPorId($request['provincia_id'])
                : null;
            if ($provincia)
                $alicuotaIva = $provincia->jurisdiccion;
        }
        else
        {
            $impuesto = ! empty($request['impuesto_id'])
                ? $this->impuestoRepository->find($request['impuesto_id'])
                : null;
            $alicuotaIva = 0;
            if ($impuesto)
                $alicuotaIva = $impuesto->valor;
        }
    }

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'whereArmado' => " WHERE concc_concepto = '".$id."' " );
        $apiAnita->apiCallEscritura($data);

        // Elimina los movimientos
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'delete', 
            'tabla' => 'concciva',
            'sistema' => 'compras',
            'whereArmado' => " WHERE conci_concepto = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: list<int>}
     */
    private function separarCabeceraLineasYCondiciones(array $data): array
    {
        $cabecera = [
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'nombre_ia' => trim((string) ($data['nombre_ia'] ?? '')),
            'codigo' => trim((string) ($data['codigo'] ?? '')),
            'formula' => ($data['formula'] ?? null) !== null && $data['formula'] !== ''
                ? (string) $data['formula']
                : null,
            'columna_ivacompra_id' => ($data['columna_ivacompra_id'] ?? null) ?: null,
            'tipoconcepto' => (string) ($data['tipoconcepto'] ?? ''),
            'retieneganancia' => (string) ($data['retieneganancia'] ?? 'N'),
            'retieneIIBB' => (string) ($data['retieneIIBB'] ?? 'N'),
            'provincia_id' => ($data['provincia_id'] ?? null) ?: null,
            'impuesto_id' => ($data['impuesto_id'] ?? null) ?: null,
            'empresa_id' => null,
        ];

        $lineas = $this->normalizarLineasEmpresa($data);
        $condicionivaIds = [];
        foreach (array_values((array) ($data['condicioniva_ids'] ?? [])) as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                $condicionivaIds[] = $cid;
            }
        }

        return [$cabecera, $lineas, $condicionivaIds];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function normalizarLineasEmpresa(array $data): array
    {
        $empresaIds = array_values((array) ($data['empresa_ids'] ?? []));
        $debeIds = array_values((array) ($data['cuentacontabledebe_ids'] ?? []));
        $haberIds = array_values((array) ($data['cuentacontablehaber_ids'] ?? []));

        $lineas = [];
        $vistos = [];
        $n = max(count($empresaIds), count($debeIds), count($haberIds));

        for ($i = 0; $i < $n; $i++) {
            $empresaId = (int) ($empresaIds[$i] ?? 0);
            $debeId = (int) ($debeIds[$i] ?? 0);
            $haberId = (int) ($haberIds[$i] ?? 0);
            if ($empresaId <= 0) {
                continue;
            }
            if ($debeId <= 0 && $haberId <= 0) {
                continue;
            }
            if (isset($vistos[$empresaId])) {
                throw new InvalidArgumentException('Hay empresas duplicadas en la grilla de cuentas.');
            }
            $vistos[$empresaId] = true;

            $lineas[] = [
                'empresa_id' => $empresaId,
                'cuentacontabledebe_id' => $debeId > 0 ? $debeId : null,
                'cuentacontablehaber_id' => $haberId > 0 ? $haberId : null,
            ];
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function sincronizarLineasEmpresa(int $conceptoId, array $lineas): void
    {
        Concepto_Ivacompra_Empresa::query()->where('concepto_ivacompra_id', $conceptoId)->delete();

        foreach ($lineas as $linea) {
            Concepto_Ivacompra_Empresa::query()->create([
                'concepto_ivacompra_id' => $conceptoId,
                'empresa_id' => (int) $linea['empresa_id'],
                'cuentacontabledebe_id' => $linea['cuentacontabledebe_id'] ?? null,
                'cuentacontablehaber_id' => $linea['cuentacontablehaber_id'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<int>  $condicionivaIds
     */
    private function sincronizarCondicionesIva(int $conceptoId, array $condicionivaIds): void
    {
        $this->model_condicioniva->newQuery()->where('concepto_ivacompra_id', $conceptoId)->delete();
        foreach ($condicionivaIds as $condicionivaId) {
            $this->model_condicioniva->create([
                'concepto_ivacompra_id' => $conceptoId,
                'condicioniva_id' => $condicionivaId,
            ]);
        }
    }

    /**
     * Mantiene columnas legacy de cabecera (Anita / consumidores viejos) con la 1ª línea.
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    private function sincronizarCabeceraLegacyDesdeLineas(int $conceptoId, array $lineas): void
    {
        $primera = collect($lineas)->sortBy('empresa_id')->first();
        $this->model->newQuery()->whereKey($conceptoId)->update([
            'empresa_id' => $primera['empresa_id'] ?? null,
            'cuentacontabledebe_id' => $primera['cuentacontabledebe_id'] ?? null,
            'cuentacontablehaber_id' => $primera['cuentacontablehaber_id'] ?? null,
        ]);
    }

    /**
     * Anita solo admite un set de cuentas: se toma la primera línea (empresa menor).
     *
     * @param  list<int>  $condicionivaIds
     * @return array<string, mixed>
     */
    private function payloadAnitaDesdeRegistro(Concepto_Ivacompra $registro, array $condicionivaIds): array
    {
        $linea = $registro->concepto_ivacompra_empresas->sortBy('empresa_id')->first();

        return [
            'nombre' => (string) $registro->nombre,
            'codigo' => (string) $registro->codigo,
            'formula' => (string) ($registro->formula ?? ''),
            'columna_ivacompra_id' => $registro->columna_ivacompra_id,
            'tipoconcepto' => (string) $registro->tipoconcepto,
            'retieneganancia' => (string) $registro->retieneganancia,
            'retieneIIBB' => (string) $registro->retieneIIBB,
            'provincia_id' => $registro->provincia_id,
            'impuesto_id' => $registro->impuesto_id,
            'cuentacontabledebe_id' => (int) ($linea->cuentacontabledebe_id ?? $registro->cuentacontabledebe_id ?? 0),
            'cuentacontablehaber_id' => (int) ($linea->cuentacontablehaber_id ?? $registro->cuentacontablehaber_id ?? 0),
            'condicioniva_ids' => $condicionivaIds,
        ];
    }
}
