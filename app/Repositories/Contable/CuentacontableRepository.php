<?php

namespace App\Repositories\Contable;

use App\Models\Caja\Conceptogasto;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Empresa;
use App\Repositories\Contable\Cuentacontable_CentrocostoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use App\Support\Configuracion\AnitaSyncIndexSupport;
use Auth;
use Exception;

class CuentacontableRepository implements CuentacontableRepositoryInterface
{
    protected $model;
    protected $tableAnita = ['ctamae', 'ctaconc', 'ccosvalid'];
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'ctam_cuenta';

    private $centrocostoRepository;
    private $cuentacontable_centrocostoRepository;
    private $conceptogastoRepository;
    private $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cuentacontable $cuentacontable,
                                EmpresaRepositoryInterface $empresarepository,
                                ConceptogastoRepositoryInterface $conceptogastorepository,
                                CentrocostoRepositoryInterface $centrocostorepository,
                                Cuentacontable_CentrocostoRepositoryInterface $cuentacontable_centrocostorepository)
    {
        $this->model = $cuentacontable;
        $this->empresaRepository = $empresarepository;
        $this->conceptogastoRepository = $conceptogastorepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->cuentacontable_centrocostoRepository = $cuentacontable_centrocostorepository;
    }

    public function all()
    {
        $hay_cuentacontable = Cuentacontable::first();

        if (! $hay_cuentacontable && AnitaSyncIndexSupport::autoImportHabilitado()) {
            self::sincronizarConAnita();
        }

        $empresa_query = $this->empresaRepository->allFiltrado();

        if (count($empresa_query) > 1)
            $cuentacontable = $this->model->with('empresas')->whereIn('empresa_id', $empresa_query->pluck('id')->toArray())->with('rubrocontables')->orderBy('nombre','ASC')->get();
        else
            $cuentacontable = $this->model->with('empresas')->with('rubrocontables')->orderBy('nombre','ASC')->get();

        return $cuentacontable;
    }

    public function allPrimeraEmpresa()
    {
        $empresa_query = $this->empresaRepository->allFiltrado();

        $empresa_id = $empresa_query[0]->id;

        return $this->model->with('empresas')->where('empresa_id', $empresa_id)->with('rubrocontables')->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $cuentacontable = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);

        return($cuentacontable);
    }

    public function update(array $data, $id)
    {
        $cuentacontable = $this->model->findOrFail($id)
            ->update($data);

        // Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $cuentacontable;
    }

    public function delete($id)
    {
    	$cuentacontable = $this->model->find($id);
		//
		// Elimina anita
		self::eliminarAnita($cuentacontable->codigo);

        $cuentacontable = $this->model->destroy($id);

		return $cuentacontable;
    }

    public function find($id)
    {
        if (null == $cuentacontable = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cuentacontable;
    }

    public function findPorId($id)
    {
        $cuentacontable = $this->model->where('id', $id)->first();

        return $cuentacontable;
    }

    public function findPorCodigo($empresa_id, $codigo)
    {
        $cuentacontable = $this->model->where('empresa_id', $empresa_id)->where('codigo', $codigo)->first();

        return $cuentacontable;
    }

    public function findOrFail($id)
    {
        if (null == $cuentacontable = $this->model->with('cuentacontable_centrocostos')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cuentacontable;
    }

    public function sincronizarConAnita(?array $empresasCodigo = null): array
    {
        ini_set('max_execution_time', '600');

        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        $apiAnita = new ApiAnita();
        $payload = [
            'acc' => 'list',
            'sistema' => 'contab',
            'campos' => 'ctam_empresa, '.$this->keyFieldAnita,
            'tabla' => $this->tableAnita[0],
            'orderBy' => 'ctam_empresa, '.$this->keyFieldAnita,
        ];
        if ($empresasCodigo !== null && $empresasCodigo !== []) {
            $lista = implode(',', array_map(
                static fn ($c) => "'".str_replace("'", '', (string) $c)."'",
                $empresasCodigo
            ));
            $payload['whereArmado'] = " WHERE ctam_empresa IN ({$lista}) ";
        }

        $dataAnita = json_decode($apiAnita->apiCall($payload));
        if (! is_array($dataAnita)) {
            $ret['errores'][] = 'Anita no devolvió un listado válido de ctamae.';

            return $ret;
        }

        $ret['en_anita'] = count($dataAnita);

        $empresaPorCodigo = Empresa::query()->pluck('id', 'codigo');
        $locales = [];
        foreach ($this->model->newQuery()->get(['empresa_id', 'codigo']) as $cta) {
            $locales[(int) $cta->empresa_id.'|'.$cta->codigo] = true;
        }

        foreach ($dataAnita as $value) {
            $empresaCodigo = (string) ($value->ctam_empresa ?? '');
            $cuentaCodigo = (string) ($value->{$this->keyFieldAnita} ?? '');
            $empresaId = $empresaPorCodigo[$empresaCodigo] ?? null;
            if (! $empresaId) {
                $ret['errores'][] = "emp {$empresaCodigo} cta {$cuentaCodigo}: empresa no existe en ERP";
                continue;
            }

            $clave = (int) $empresaId.'|'.$cuentaCodigo;
            if (isset($locales[$clave])) {
                $ret['omitidos']++;
                continue;
            }

            try {
                $this->traerRegistroDeAnita($empresaCodigo, $cuentaCodigo);
                $locales[$clave] = true;
                $ret['importados']++;
            } catch (Exception $e) {
                $ret['errores'][] = "emp {$empresaCodigo} cta {$cuentaCodigo}: ".$e->getMessage();
            }
        }

        return $ret;
    }

    /**
     * Resincroniza conceptogasto_id de cuentas existentes desde Anita ctaconc.
     * ctaco_concepto 0 → null; id de conceptogasto = código Anita.
     *
     * @param  list<string>|null  $empresasCodigo
     * @return array{en_anita:int,actualizados:int,iguales:int,sin_cuenta:int,sin_concepto:int,errores:list<string>}
     */
    public function sincronizarConceptosDesdeAnita(bool $dryRun = false, ?array $empresasCodigo = null): array
    {
        ini_set('max_execution_time', '600');

        $ret = [
            'en_anita' => 0,
            'actualizados' => 0,
            'iguales' => 0,
            'sin_cuenta' => 0,
            'sin_concepto' => 0,
            'errores' => [],
        ];

        $apiAnita = new ApiAnita();
        $payload = [
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => $this->tableAnita[1],
            'campos' => 'ctaco_empresa,ctaco_cuenta,ctaco_concepto',
        ];
        if ($empresasCodigo !== null && $empresasCodigo !== []) {
            $lista = implode(',', array_map(
                static fn ($c) => "'".str_replace("'", '', (string) $c)."'",
                $empresasCodigo
            ));
            $payload['whereArmado'] = " WHERE ctaco_empresa IN ({$lista}) ";
        }

        $dataAnita = json_decode($apiAnita->apiCall($payload));

        if (! is_array($dataAnita)) {
            $ret['errores'][] = 'Anita no devolvió un listado válido de ctaconc.';

            return $ret;
        }

        $ret['en_anita'] = count($dataAnita);

        $empresaPorCodigo = Empresa::query()->pluck('id', 'codigo');
        $conceptoIds = Conceptogasto::query()->pluck('id', 'id')->all();

        $cuentaPorEmpresaCodigo = [];
        foreach ($this->model->newQuery()->get(['id', 'empresa_id', 'codigo', 'conceptogasto_id']) as $cta) {
            $cuentaPorEmpresaCodigo[(int) $cta->empresa_id.'|'.$cta->codigo] = $cta;
        }

        foreach ($dataAnita as $row) {
            $empresaCodigo = (string) ($row->ctaco_empresa ?? '');
            $cuentaCodigo = (string) ($row->ctaco_cuenta ?? '');
            $conceptoAnita = (int) ($row->ctaco_concepto ?? 0);

            $empresaId = $empresaPorCodigo[$empresaCodigo] ?? null;
            if (! $empresaId) {
                $ret['sin_cuenta']++;
                continue;
            }

            $clave = (int) $empresaId.'|'.$cuentaCodigo;
            $cuenta = $cuentaPorEmpresaCodigo[$clave] ?? null;
            if (! $cuenta) {
                $ret['sin_cuenta']++;
                continue;
            }

            $nuevoConceptoId = null;
            if ($conceptoAnita > 0) {
                if (! isset($conceptoIds[$conceptoAnita])) {
                    $ret['sin_concepto']++;
                    $ret['errores'][] = "emp {$empresaCodigo} cta {$cuentaCodigo}: concepto {$conceptoAnita} no existe en conceptogasto";
                    continue;
                }
                $nuevoConceptoId = (int) $conceptoIds[$conceptoAnita];
            }

            $actual = $cuenta->conceptogasto_id !== null ? (int) $cuenta->conceptogasto_id : null;
            if ($actual === $nuevoConceptoId) {
                $ret['iguales']++;
                continue;
            }

            if ($dryRun) {
                $ret['actualizados']++;
                continue;
            }

            try {
                $this->model->newQuery()
                    ->whereKey($cuenta->id)
                    ->update(['conceptogasto_id' => $nuevoConceptoId]);
                $cuenta->conceptogasto_id = $nuevoConceptoId;
                $ret['actualizados']++;
            } catch (Exception $e) {
                $ret['errores'][] = "emp {$empresaCodigo} cta {$cuentaCodigo}: ".$e->getMessage();
            }
        }

        return $ret;
    }

    public function traerRegistroDeAnita($empresa, $key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
            'sistema' => 'contab',
            'campos' => '
                ctam_empresa,
				ctam_cuenta,
				ctam_tipo,
				ctam_desc,
				ctam_nivel,
				ctam_salto_pag,
				ctam_ajustable,
				ctam_ley_debe1,
				ctam_ley_debe2,
				ctam_ley_haber1,
				ctam_ley_haber2,
				ctam_rubro,
				ctam_fl_ccosto,
				ctam_cuenta_alfa,
				ctam_aju_mon_ext,
				ctam_cta_dif_cbio
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' AND ctam_empresa = '".$empresa."'" 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $usuario_id = Auth::id() ?? 1;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            $ctamEmpresa = $data->ctam_empresa;
            $ctamCuenta = $data->ctam_cuenta;

			switch($data->ctam_tipo)
			{
			case '0':
				$tipocuenta = '1';
				break;
			case '1':
			case '3':
				$tipocuenta = '2';
				break;
			default:
				$tipocuenta = '3';
		  	}

            // Lee el concepto de gasto        
            $apiAnitaConc = new ApiAnita();
            $dataConc = array( 
                'acc' => 'list', 'tabla' => $this->tableAnita[1], 
                'sistema' => 'contab',
                'campos' => '
                    ctaco_empresa,
                    ctaco_cuenta,
                    ctaco_concepto
                ' , 
                'whereArmado' => " WHERE ctaco_cuenta = '".$key."' AND ctaco_empresa = '".$empresa."' " 
            );
            $dataAnitaConc = json_decode($apiAnita->apiCall($dataConc));

            $conceptogasto_id = null;
            if (count($dataAnitaConc) > 0)
            {
                $dataConc = $dataAnitaConc[0];

                // Busca concepto por codigo
                try {
                    $conceptoAnita = (int) ($dataConc->ctaco_concepto ?? 0);
                    if ($conceptoAnita > 0) {
                        $conceptogasto = $this->conceptogastoRepository->findPorId($conceptoAnita);
                        if ($conceptogasto) {
                            $conceptogasto_id = $conceptogasto->id;
                        }
                    }
                } catch (Exception $e) {
                    $conceptogasto_id = null;
                }
            }

            $empresa_id = null;
            $empresaModel = $this->empresaRepository->findPorCodigo($ctamEmpresa);

            if ($empresaModel)
                $empresa_id = $empresaModel->id;

            try {
                $cuentacontable = $this->model->create([
                    "empresa_id" => $empresa_id,
                    "rubrocontable_id" => $data->ctam_rubro,
                    "nivel" => $data->ctam_nivel,
                    "nombre" => $data->ctam_desc,
                    "codigo" => $data->ctam_cuenta,
                    "tipocuenta" => $tipocuenta,
                    "monetaria" => $data->ctam_ajustable,
                    "manejaccosto" => $data->ctam_fl_ccosto,
                    "usuarioultcambio_id" => $usuario_id,
                    "ajustamonedaextranjera" => $data->ctam_aju_mon_ext,
                    "conceptogasto_id" => $conceptogasto_id,
                    "cuentacontable_difcambio_id" => $data->ctam_cta_dif_cbio
                ]);
            } catch (Exception $e) {
                throw $e;
            }
			$dataCcos = array( 
				'acc' => 'list', 'tabla' => $this->tableAnita[2], 
				'sistema' => 'contab',
				'campos' => '
					ccosv_empresa,
					ccosv_cuenta,
                    ccosv_ccosto
				',
				'whereArmado' => " WHERE ccosv_empresa = '".$ctamEmpresa.
                                "' and ccosv_cuenta = '".$ctamCuenta."' "
			);
			$dataAnitaCcos = json_decode($apiAnita->apiCall($dataCcos));

			foreach ((array) $dataAnitaCcos as $cuentacontable_centrocosto)
			{
				// Busca centro de costo
                try {
                    $centrocosto = $this->centrocostoRepository->findPorCodigo($cuentacontable_centrocosto->ccosv_ccosto);
                    if ($centrocosto)
                    {
                        $centrocosto_id = $centrocosto->id;
                    
                        $arr_cuentacontable_centrocosto = [
                            "cuentacontable_id" => $cuentacontable->id,
                            "centrocosto_id" => $centrocosto_id
                        ];
                        $this->cuentacontable_centrocostoRepository->createUnRegistro($arr_cuentacontable_centrocosto);
                    }
                } catch (Exception $e) {

                }
			}
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		Self::cambia_para_grabar($request, $codigo, $tipocuenta, $ajustable, $manejaccosto, $cuenta,
                            $cuentacontable_difcambio);

        $data = array( 'tabla' => $this->tableAnita[0], 
                        'sistema' => 'contab',
						'acc' => 'insert',
            			'campos' => ' ctam_empresa, ctam_cuenta, ctam_tipo, ctam_desc, ctam_nivel, 
                                        ctam_salto_pag, ctam_ajustable, ctam_ley_debe1, ctam_ley_debe2, 
                                        ctam_ley_haber1, ctam_ley_haber2, ctam_rubro, ctam_fl_ccosto, 
                                        ctam_cuenta_alfa, ctam_aju_mon_ext, ctam_cta_dif_cbio',
            			'valores' => 
                                " '".$request['empresa_id']."', 
                                '".$codigo."', 
                                '".$tipocuenta."', 
                                '".$request['nombre']."', 
                                '".$request['nivel']."', 
                                '".'N'.", 
                                ".$ajustable."', 
                                '".' '."', 
                                '".' '."', 
                                '".' '."', 
                                '".' '."', 
                                '".$request['rubrocontable_id']."', 
                                '".$manejaccosto."', 
                                '".$cuenta."', 
                                '".$request['ajustamonedaextranjera'].", 
                                '".$cuentacontable_difcambio."' "
        );
        $apiAnita->apiCallEscritura($data);

        // Lee el concepto de gasto        
        $apiAnitaConc = new ApiAnita();
        $data = array( 
            'acc' => 'insert', 
            'tabla' => 'ctaconc', 
            'sistema' => 'contab',
            'campos' => '
                ctaco_empresa,
                ctaco_cuenta,
                ctaco_concepto
            ' , 
            'valores' => 
                " '".$request['empresa_id']."', 
                '".$codigo."', 
                '".$request['conceptogasto_id']."' ",
            'whereArmado' => " WHERE ctaco_cuenta = '".$codigo.
                            "' AND ctaco_empresa = '".$request['empresa_id']."' " 
        );
        $dataAnitaConc = json_decode($apiAnitaConc->apiCall($data));

        // Graba centros de costo
		Self::grabaCentrocosto($codigo, $request);
	}

	public function actualizarAnita($request, $codigo) {
        $apiAnita = new ApiAnita();

        Self::cambia_para_grabar($request, $codigo, $tipocuenta, $ajustable, $manejaccosto, $cuenta,
                            $cuentacontable_difcambio);

        $data = array( 'acc' => 'update', 
                        'sistema' => 'contab',
						'tabla' => $this->tableAnita[0], 
            			'valores' => "
                            ctam_empresa = '".$request['empresa_id']."', 
                            ctam_cuenta = '".$codigo."', 
                            ctam_tipo = '".$tipocuenta."', 
                            ctam_desc = '".$request['nombre']."', 
                            ctam_nivel = '".$request['nivel']."', 
                            ctam_ajustable = '".$ajustable."', 
                            ctam_rubro ='".$request['rubrocontable_id']."', 
                            ctam_fl_ccosto = '".$manejaccosto."', 
                            ctam_cuenta_alfa = '".$cuenta."',
                            ctam_aju_mon_ext = '".$request['ajustamonedaextranjera']."',
                            ctam_cta_dif_cbio = '".$cuentacontable_difcambio."'",
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$codigo.
                                            "' AND ctam_empresa='".$request['empresa_id']."' ");
        $apiAnita->apiCallEscritura($data);

        $apiAnitaConc = new ApiAnita();

		$data = array( 'acc' => 'update', 
                        'sistema' => 'contab',
						'tabla' => $this->tableAnita[1], 
            			'valores' => "
                            ctaco_empresa = '".$request['empresa_id']."', 
                            ctaco_cuenta = '".$codigo."', 
                            ctaco_concepto = '".$request['conceptogasto_id']."' ",
						'whereArmado' => " WHERE ctaco_cuenta = '".$codigo.
                                            "' AND ctaco_empresa='".$request['empresa_id']."' ");
        $apiAnitaConc->apiCallEscritura($data);

        // Borra centros de costo
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[2], 
				'sistema' => 'contab',
				'whereArmado' => " WHERE ccosv_empresa = '".$request['empresa_id'].
                                "' AND ccosv_cuenta = '".$codigo."' ");
        $apiAnita->apiCallEscritura($data);

		// Graba centros de costo
		Self::grabaCentrocosto($codigo, $request);
	}

	private function grabaCentrocosto($codigo, $request)
	{
		// Graba exclusiones
		if (isset($request['centrocosto_ids']))
		{
			$apiAnita = new ApiAnita();

			$centrocosto_ids = $request['centrocosto_ids'];

			if ($centrocosto_ids[0] != null)
				$qCentrocosto = count($centrocosto_ids);
			else
				$qCentrocosto = 0;

			for ($i_ccosto=0; $i_ccosto < $qCentrocosto; $i_ccosto++) 
			{
				$data = array( 'tabla' => $this->tableAnita[2], 
                        'acc' => 'insert',
						'sistema' => 'contab',
							'campos' => '
							ccosv_empresa,
							ccosv_cuenta,
                            ccosv_ccosto
							',
						'valores' => " 
                                '".$request['empresa_id']."',
                                '".$codigo."',
								'".$centrocosto_ids[$i_ccosto]."' " 
						);
				$apiAnita->apiCallEscritura($data);
			}
		}
	}

	public function eliminarAnita($empresa, $id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0],
                    'sistema' => 'contab',
					'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id.
                    "' AND ctam_empresa = '".$empresa."'" );
        $apiAnita->apiCallEscritura($data);

        $apiAnitaConc = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1],
                    'sistema' => 'contab',
					'whereArmado' => " WHERE ctaco_cuenta = '".$id.
                    "' AND ctaco_empresa = '".$empresa."'");
        $apiAnitaConc->apiCallEscritura($data);

        $apiAnitaConc = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[2],
                    'sistema' => 'contab',
					'whereArmado' => " WHERE ccosv_cuenta = '".$id.
                    "' AND ccosv_empresa = '".$empresa."'");
        $apiAnitaConc->apiCallEscritura($data);
	}

	public function cambia_para_grabar($request, &$codigo, &$tipocuenta, &$ajustable, 
                                        &$manejaccosto, &$cuenta, &$cuentacontable_difcambio)
	{
		switch($request['tipocuenta'])
		{
		case '1':
			$tipocuenta = '0';
			break;
		case '2':
			$tipocuenta = '1';
			break;
		default:
			$tipocuenta = '2';
		}

		$ajustable = $request['monetaria'];
        $manejaccosto = $request['manejaccosto'];

		// Convierte a formato cuenta de anita
		sprintf($codigo, "%09ld", $request['codigo']);
		$cuenta = substr($codigo,0,6).'-'.substr($codigo,-3);

        // Busca cuenta contable de diferencia de cambio
        $cuentacontable_difcambio = '0';
        if ($request['cuentacontable_difcambio_id'])
        {
            $cuentacontable = Self::find($request['cuentacontable_difcambio_id']);
            if ($cuentacontable)
                $cuentacontable_difcambio = $cuentacontable->codigo;
            else
                $cuentacontable_difcambio = '0';
        }
	}
}
