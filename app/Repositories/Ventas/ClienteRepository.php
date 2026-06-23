<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Impuesto;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Pais;
use App\Models\Ventas\Zonavta;
use App\Models\Ventas\Subzonavta;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Transporte;
use App\Models\Ventas\Abasto;
use App\Models\Ventas\Coeficiente;
use App\Models\Ventas\Cliente_Articulo_Suspendido;
use App\Models\Ventas\Cliente_Seguimiento;
use App\Models\Ventas\Cliente_Cm05;
use App\Models\Ventas\Distribuidor;
use App\Models\Ventas\TipoempresaCliente;
use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Mventa;
use App\Models\Configuracion\Tipodocumento;
use App\Models\Configuracion\Condicioniva;
use App\Models\Seguridad\Usuario;
use App\Repositories\Ventas\DescuentoventaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use App\Support\Ventas\ClienteListadoFiltros;
use App\Services\Ventas\ClienteAnitaSyncService;
use App\Traits\AnitaBridgeEscritura;
use Carbon\Carbon;
use Auth;

class ClienteRepository implements ClienteRepositoryInterface
{
    use AnitaBridgeEscritura;

    protected $model;
    protected $tableAnita = ['climae', 'cliley', 'clicomi', 'movscli', 'stksuspcli'];
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'clim_cliente';
	private $vendedorRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente $cliente,
								VendedorRepositoryInterface $vendedorRepository
								)
    {
        $this->model = $cliente;
		$this->vendedorRepository = $vendedorRepository;
    }

    public function create(array $data, ?bool $syncAnita = null)
    {
		if (config('app.empresa') !== 'INTERFORMING')
		{
			$codigo = '';
			self::ultimoCodigo($codigo);
			$data['codigo'] = $codigo;
		}
		$data['estado'] = '0';

		if (config('app.empresa') == 'EL BIERZO')
			$data['emitenotadecredito'] = 'NO EMITE';

		if ($data['retieneiva'] == null)
			$data['retieneiva'] = 'N';

		if ($data['condicioniibb_id'] == null)
			$data['condicioniibb_id'] = '4';

        $cliente = $this->model->create($data);

		if ($syncAnita ?? config('app.anita_sync_cliente_write')) {
			self::guardarAnita($data);
		}

		return $cliente;
    }

    public function update(array $data, $id, ?bool $syncAnita = null)
    {
        $cliente = $this->model->findOrFail($id)
            ->update($data);

		$data['tiene_cm05'] = $this->model->find($id)?->cliente_cm05s()->exists() ?? false;
		if ($syncAnita ?? config('app.anita_sync_cliente_write')) {
			self::actualizarAnita($data, $data['codigo']);
		}

		return $cliente;

        //return $this->model->where('id', $id)
         //   ->update($data);
    }

    public function updateEmiteNc($id)
    {
        $cliente = $this->model->findOrFail($id);

		if ($cliente->emitenotadecredito == 'No Emite Nota de Credito')
            $emite = ['emitenotadecredito' => 'Emite Nota de Credito'];
		else
			$emite = ['emitenotadecredito' => 'No Emite Nota de Credito'];

		$this->model->find($id)->update($emite);

		// Actualiza anita
		if (config('app.empresa') == "EL BIERZO")
			$cliente = self::actualizarEmiteNc($emite, $cliente->codigo);

		return $cliente;
    }

    public function delete($id)
    {
    	$cliente = Cliente::find($id);

		// Elimina anita
		if ($cliente)
			self::eliminarAnita($cliente->codigo);

        $cliente = $this->model->destroy($id);

		return $cliente;
    }

    public function find($id)
    {
		// Filtra vendedores
		$vendedores = $this->vendedorRepository->leeVendedoresAsociados();

        $cliente = $this->model->with("cliente_entregas.zonavtas")->with("cliente_seguimientos")
										->with("cliente_cm05s.provincias")
										->with("cliente_articulo_suspendidos")->with("cliente_archivos")
										->with("provincias")->with("localidades")->with("paises")
										->with("tipossuspensioncliente")->with('zonavtas')
										->with("abastos")->with("coeficientes")
										->with("vendedores")->with("distribuidores")->with("cuentascontables")
										->where('id', $id);

		if (count($vendedores) > 0)
			$cliente = $cliente->whereIn('vendedor_id', $vendedores);

		$cliente = $cliente->first();

		if (null == $cliente)										
            throw new ModelNotFoundException("Registro no encontrado");

        return $cliente;
    }

	public function findPorCodigo($codigo)
    {
		// Filtra vendedores
		$vendedores = $this->vendedorRepository->leeVendedoresAsociados();

		$cliente = $this->model->with("cliente_entregas.zonavtas")->with("cliente_seguimientos")
										->with("cliente_cm05s.provincias")
										->with("cliente_articulo_suspendidos")->with("cliente_archivos")
										->with("provincias")->with("localidades")->with("paises")
										->with("tipossuspensioncliente")->with('zonavtas')
										->with("abastos")->with("coeficientes")
										->with("vendedores")->with("distribuidores")->with("cuentascontables")
										->where('codigo', $codigo);

		if (count($vendedores) > 0)
			$cliente = $cliente->whereIn('vendedor_id', $vendedores);

		$cliente = $cliente->first();

		if (null == $cliente)										
            throw new ModelNotFoundException("Registro no encontrado");

        return $cliente;
    }

	public function findPorNumeroDocumento($numerodocumento)
    {
		$cliente = $this->model->with("cliente_entregas.zonavtas")->with("cliente_seguimientos")
										->with("cliente_cm05s.provincias")
										->with("cliente_articulo_suspendidos")->with("cliente_archivos")
										->with("provincias")->with("localidades")->with("paises")
										->with("tipossuspensioncliente")->with('zonavtas')
										->with("abastos")->with("coeficientes")
										->with("vendedores")->with("distribuidores")->with("cuentascontables")
										->where('numerodocumento', $numerodocumento);

		$cliente = $cliente->first();

        return $cliente;
    }

    public function findOrFail($id)
    {
        if (null == $cliente = $this->model->with("cliente_entregas.zonavtas")->with("cliente_seguimientos")
											->with("cliente_cm05s.provincias")
											->with("cliente_articulo_suspendidos")->with("cliente_archivos")
											->with("provincias")->with("localidades")->with("paises")
											->with("tipossuspensioncliente")->with('zonavtas')
											->with("abastos")->with("coeficientes")
										->with("vendedores")->with("distribuidores")->with("cuentascontables")
											->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $cliente;
    }

	public function actualizaPadronMipymePorCuit($cuit, $modo)
	{
		$this->model->whereRaw("REPLACE(REPLACE(numerodocumento, '-', ''), '.', '') = ?", [$cuit])
					->update(['modofacturacion' => $modo]);
	}

	public function actualizaPadronMipyme($modo)
	{
		$this->model->query()->update(['modofacturacion' => $modo]);
	}

	/**
	 * Código ERP unificado para comparar con Anita (solo numéricos; alfanuméricos sin cambio).
	 */
	private function normalizarCodigoCliente(?string $codigo): string
	{
		$codigo = trim((string) $codigo);
		if ($codigo === '') {
			return '';
		}
		if (ctype_digit($codigo)) {
			$sinCeros = ltrim($codigo, '0');

			return $sinCeros !== '' ? $sinCeros : '0';
		}

		return $codigo;
	}

	/**
	 * Variantes de código que pueden coexistir en BD (3016 vs 003016).
	 *
	 * @return list<string>
	 */
	private function variantesCodigoCliente(string $codigo): array
	{
		$codigo = trim($codigo);
		if ($codigo === '') {
			return [];
		}
		if (! ctype_digit($codigo)) {
			return [$codigo];
		}
		$norm = $this->normalizarCodigoCliente($codigo);

		return array_values(array_unique([$codigo, $norm, str_pad($norm, 6, '0', STR_PAD_LEFT)]));
	}

	private function queryClientePorCodigo(string $codigo)
	{
		$variantes = $this->variantesCodigoCliente($codigo);
		if ($variantes === []) {
			return $this->model->newQuery()->whereRaw('1 = 0');
		}

		return $this->model->newQuery()->whereIn('codigo', $variantes);
	}

	public function existeClientePorCodigo(string $codigo): bool
	{
		return $this->queryClientePorCodigo($codigo)->exists();
	}

    public function sincronizarConAnita()
    {
        app(ClienteAnitaSyncService::class)->sincronizarConAnita();
    }

    /**
     * Actualiza cliente.distribuidor_id en el ERP leyendo clim_distribuidor desde Anita (solo lectura bridge).
     *
     * @return array{en_anita:int, actualizados:int, omitidos:int, sin_cliente:int, errores:list<string>}
     */
    public function actualizarDistribuidorIdDesdeAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (config('app.empresa') !== 'EL BIERZO') {
            throw new \RuntimeException('La sincronización de distribuidor en clientes solo aplica a EL BIERZO.');
        }

        $ret = ['en_anita' => 0, 'actualizados' => 0, 'omitidos' => 0, 'sin_cliente' => 0, 'errores' => []];

        $api = new ApiAnita();
        $payload = [
            'acc' => 'list',
            'tabla' => $this->tableAnita[0],
            'campos' => 'clim_cliente, clim_distribuidor',
            'sistema' => 'ventas',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($payload));
        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException($parsed['error_lectura']);
        }

        foreach ($parsed['filas'] as $row) {
            $ret['en_anita']++;
            $codigo = trim((string) ($row->clim_cliente ?? ''));
            if ($codigo === '') {
                $ret['omitidos']++;

                continue;
            }

            try {
                $cliente = $this->queryClientePorCodigo($codigo)->first();
                if ($cliente === null) {
                    $ret['sin_cliente']++;

                    continue;
                }

                $distribuidorId = Distribuidor::resolverIdPorCodigoAnita($row->clim_distribuidor ?? null);
                $actual = $cliente->distribuidor_id !== null ? (int) $cliente->distribuidor_id : null;

                if ($actual === $distribuidorId) {
                    $ret['omitidos']++;

                    continue;
                }

                $cliente->distribuidor_id = $distribuidorId;
                $cliente->save();
                $ret['actualizados']++;
            } catch (\Throwable $e) {
                $ret['errores'][] = "Cliente Anita clim_cliente={$codigo}: ".$e->getMessage();
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerRegistroDeAnita($key, $fl_crea_registro = null)
    {
        $apiAnita = new ApiAnita();
        $clienteRecienCreado = false;
        $estado = 'omitido';
        $keyAnita = $this->codigoParaConsultaAnita((string) $key);
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
			'sistema' => 'ventas',
            'campos' => '
					clim_cliente,
					clim_nombre,
					clim_contacto,
					clim_direccion,
					clim_localidad,
					clim_cod_postal,
					clim_provincia,
					clim_telefono,
					clim_cuit,
					clim_cond_iva,
					clim_porc_excen,
					clim_letra,
					clim_cond_venta,
					clim_cta_contable,
					clim_credito,
					clim_dias_atraso,
					clim_zonavta,
					clim_subzona,
					clim_zonamult,
					clim_vendedor,
					clim_cobrador,
					clim_expreso,
					clim_tipo_empresa,
					clim_dir_cobranza,
					clim_hs_cobranza,
					clim_lugar_entrega,
					clim_retiene_iva,
					clim_lista_precio,
					clim_descuento,
					clim_nro_interno,
					clim_fecha_interes,
					clim_proveedor,
					clim_minimo_fact,
					clim_estado_cli,
					clim_dias_cobranza,
					clim_dias_atencion,
					clim_hs_atencion,
					clim_pais,
					clim_perc_ing_br,'.
					(config('app.empresa') == 'EL BIERZO' ? 'clim_nro_ing_bruto,' : 'clim_nro_ing_br,').
					'clim_dir_postal,
					clim_loc_postal,
					clim_cp_postal,
					clim_fantasia,
					clim_fecha_alta,
					clim_ley_liberado,
					clim_regimen,
					clim_leyenda_fact,
					clim_prov_postal,
					clim_lugar_de_pago,
					clim_excl_perc_iva,
					clim_fe_excl_piva,
					clim_dto_integrado,
					clim_fecha_boletin,
					clim_e_mail,
					clim_fax'.(config('app.empresa') == 'EL BIERZO'?
					',
					clim_abasto,
					clim_distribuidor,
					clim_coef,
					clim_logistica,
					clim_emite_cert,
					clim_emite_nc,
					clim_coef_extra,
					clim_referencia,
					clim_cod_localidad,
					clim_cod_provincia,
					clim_agrega_bonif,
					clim_e_mail2,
					clim_dfexcl_piva,
					clim_hfexcl_piva' : '').
					(config('app.empresa') == 'INTERFORMING' ?
					',
					clim_url,
					clim_hs_atencion2' : '')
			,
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$keyAnita."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return 'omitido';
        }

		$data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[1], 
			'sistema' => 'ventas',
            'campos' => '
			clil_cliente,
    		clil_leyenda
			',
            'whereArmado' => " WHERE clil_cliente = '".$keyAnita."' " 
        );
        $dataleyAnita = json_decode($apiAnita->apiCall($data));

		if (config("app.empresa") == "EL BIERZO")
		{
			$data = array( 
				'acc' => 'list', 'tabla' => $this->tableAnita[3], 
				'sistema' => 'ventas',
				'campos' => '
				movsc_cliente,
				movsc_orden,
				movsc_fecha,
				movsc_estado,
				movsc_observacion,
				movsc_fec_ult_tra,
				movsc_usuario,
				movsc_hora_ult_tra
				',
				'whereArmado' => " WHERE movsc_cliente = '".$keyAnita."' " 
			);
			$dataseguimientoAnita = json_decode($apiAnita->apiCall($data));

			$data = array( 
				'acc' => 'list', 'tabla' => $this->tableAnita[4], 
				'sistema' => 'ventas',
				'campos' => '
				stksc_cliente,
				stksc_articulo
				',
				'whereArmado' => " WHERE stksc_cliente = '".$keyAnita."' " 
			);
			$dataarticulo_suspendidoAnita = json_decode($apiAnita->apiCall($data));
		}

		$usuario_id = $this->usuarioIdParaSincronizacion();

        if (is_array($dataAnita) && count($dataAnita) > 0) {
            $data = $dataAnita[0];

			if (isset($data->clim_cod_localidad))
				$localidad = Localidad::select('id', 'nombre')->where('codigo' , '=', $data->clim_cod_localidad)->first();
			else
				$localidad = Localidad::select('id', 'nombre')->where('nombre' , '=', $data->clim_localidad)->where('codigopostal','=',$data->clim_cod_postal)->first();
			if ($localidad)
				$localidad_id = $localidad->id;
			else
				$localidad_id = NULL;

			if (isset($data->clim_cod_provincia))
				$provincia = Provincia::select('id', 'nombre')->where('codigo' , '=', $data->clim_cod_provincia)->first();
			else
				$provincia = Provincia::select('id', 'nombre')->where('nombre' , '=', $data->clim_provincia)->first();
			if ($provincia)
				$provincia_id = $provincia->id;
			else
				$provincia_id = NULL;

        	$pais = Pais::select('id', 'nombre')->where('codigo' , $data->clim_pais)->first();
			if ($pais)
				$pais_id = $pais->id;
			else
				$pais_id = 1;
	
        	$cuenta = Cuentacontable::select('id', 'codigo')->where('codigo' , $data->clim_cta_contable)->first();
			if ($cuenta)
				$cuentacontable_id = $cuenta->id;
			else
				$cuentacontable_id = NULL;
	
        	$zonavta = Zonavta::select('id')->where('codigo' , $data->clim_zonavta)->first();
			if ($zonavta)
				$zonavta_id = $zonavta->id;
			else
				$zonavta_id = NULL;
	
        	$subzonavta = Subzonavta::select('id')->where('id' , $data->clim_subzona)->first();
			if ($subzonavta)
				$subzonavta_id = $subzonavta->id;
			else
				$subzonavta_id = NULL;
	
        	$vendedor = Vendedor::select('id')->where('codigo' , $data->clim_vendedor)->first();
			if ($vendedor)
				$vendedor_id = $vendedor->id;
			else
				$vendedor_id = NULL;
	
        	$codigoCondicionVenta = trim((string) ($data->clim_cond_venta ?? ''));
        	$condicionventa = $codigoCondicionVenta !== ''
				? Condicionventa::select('id')->where('codigo', $codigoCondicionVenta)->first()
				: null;
			if (! $condicionventa && $codigoCondicionVenta !== '' && ctype_digit($codigoCondicionVenta)) {
				$condicionventa = Condicionventa::select('id')->where('id', (int) $codigoCondicionVenta)->first();
			}
			if ($condicionventa) {
				$condicionventa_id = $condicionventa->id;
			} else {
				$condicionventa_id = NULL;
			}
	
        	$listaprecio = Listaprecio::select('id')->where('codigo' , $data->clim_lista_precio)->first();
			if ($listaprecio)
				$listaprecio_id = $listaprecio->id;
			else
				$listaprecio_id = NULL;

       		$transporte_id = $this->resolverTransporteIdDesdeClimExpreso($data->clim_expreso ?? null);

			if (config('app.empresa') == 'EL BIERZO')
			{
				$abasto = Abasto::select('id')->where('codigo' , $data->clim_abasto)->first();
				if ($abasto)
					$abasto_id = $abasto->id;
				else
					$abasto_id = NULL;
					
				$coeficiente = Coeficiente::select('id')->where('codigo' , $data->clim_coef)->first();
				if ($coeficiente)
					$coeficiente_id = $coeficiente->id;
				else
					$coeficiente_id = NULL;
								
				$distribuidor_id = Distribuidor::resolverIdPorCodigoAnita($data->clim_distribuidor ?? null);
			}

			$condicioniva_id = 1;
			switch($data->clim_cond_iva)
			{
			case '0':
				$condicioniva_id = 1;
				break;
			case '3':
				$condicioniva_id = 3;
				break;
			case '4':
				if ($data->clim_letra == 'E')
					$condicioniva_id = 5;
				else
					$condicioniva_id = 2;
				break;
			case '5':
				$condicioniva_id = 4;
				break;
			case '6':
				$condicioniva_id = 6;
				break;
			case '8':
				$condicioniva_id = 7;
				break;
			}
			$condicioniibb_id = 1;
			switch($data->clim_perc_ing_br)
			{
			case '1':
				$condicioniibb_id = 3;
				break;
			case '2':
			case '4':
			case '5':
			case 'C':
			case 'A':
				$condicioniibb_id = 1;
				break;
			case '3':
			case '6':
				$condicioniibb_id = 2;
				break;
			case 'N':
			case 'E':
				$condicioniibb_id = 4;
				break;
			case '7':
				if (config('app.empresa') === 'INTERFORMING') {
					$condicioniibb_id = 1;
				}
				break;
			}

			$tipoempresa_cliente_id = null;
			$codigoTipoEmp = trim((string) ($data->clim_tipo_empresa ?? ''));
			if ($codigoTipoEmp !== '' && $codigoTipoEmp !== '0') {
				$tipoemp = TipoempresaCliente::select('id')
					->where('codigo', $codigoTipoEmp)
					->orWhere('codigo', ltrim($codigoTipoEmp, '0'))
					->first();
				if ($tipoemp) {
					$tipoempresa_cliente_id = $tipoemp->id;
				}
			}

			// Lee las leyendas
			$leyenda = "";
			foreach (is_array($dataleyAnita) ? $dataleyAnita : [] as $ley) {
				$leyenda .= $ley->clil_leyenda;
			}
			$leyenda = trim($leyenda);

			if (config('app.empresa') == 'EL BIERZO')
			{
				if ($data->clim_emite_cert == 'S')
					$emiteCertificado = "Emite Certificado";
				else	
					$emiteCertificado = "No Emite Certificado";

				if ($data->clim_emite_nc == 'S')
					$emiteNotaDeCredito = "Emite Nota de Credito";
				else	
					$emiteNotaDeCredito = "No Emite Nota de Credito";

				if ($data->clim_agrega_bonif == 'S')
					$agregaBonificacion = "Agrega Bonificacion";
				else
					$agregaBonificacion = "No Agrega Bonificacion";

				if ($data->clim_regimen == '1')
					$modoFacturacion = 'C';
				else
					$modoFacturacion = 'N';
			}
			else
				$modoFacturacion = 'N';

			if (config('app.empresa') == 'EL BIERZO')
				$email = trim(rtrim((string) ($data->clim_e_mail ?? ''), ' ').rtrim((string) ($data->clim_e_mail2 ?? ''), ' '));
			else
				$email = trim(rtrim((string) ($data->clim_e_mail ?? ''), ' '));

			if (config('app.empresa') == 'INTERFORMING')
				$horarioAtencion = trim((string) ($data->clim_hs_atencion ?? '').' '.(string) ($data->clim_hs_atencion2 ?? ''));
			else
				$horarioAtencion = trim((string) ($data->clim_hs_atencion ?? ''));

			$codigoCliente = $this->normalizarCodigoCliente($data->clim_cliente);

			if (config("app.empresa") == 'EL BIERZO')
			{
				$arr_campos = [
					"nombre" => $data->clim_nombre,
					"codigo" => $codigoCliente,
					"contacto" => $data->clim_contacto,
					"fantasia" => $data->clim_fantasia,
					"email" => $email,
					"telefono" => $data->clim_telefono.' '.$data->clim_fax,
					"urlweb" => (config('app.empresa') == 'INTERFORMING' ? $data->clim_url : ' '),
					"domicilio" => $data->clim_direccion,
					"localidad_id" => $localidad_id,
					"provincia_id" => $provincia_id,
					"pais_id" => $pais_id,
					"codigopostal" => $data->clim_cod_postal,
					"zonavta_id" => $zonavta_id,
					"subzonavta_id" => $subzonavta_id,
					"vendedor_id" => $vendedor_id,
					"transporte_id" => $transporte_id,
					"numerodocumento" => $data->clim_cuit,
					"condicioniva_id" => $condicioniva_id,
					"retieneiva" => $data->clim_retiene_iva,
					"nroiibb" => $data->clim_nro_ing_bruto,
					"condicioniibb_id" => $condicioniibb_id,
					"tipoempresa_cliente_id" => $tipoempresa_cliente_id,
					"condicionventa_id" => $condicionventa_id,
					"listaprecio_id" => $listaprecio_id,
					"descuento" => $data->clim_descuento,
					"cuentacontable_id" => $cuentacontable_id,
					"vaweb" => 'N',
					"estado" => $this->mapEstadoClienteDesdeAnita($data->clim_estado_cli ?? null),
					"leyenda" => $leyenda,
					"modofacturacion" => $modoFacturacion,
					"usuario_id" => $usuario_id,
					'abasto_id' => $abasto_id, 
					'coeficiente_id' => $coeficiente_id, 
					'porcentajelogistica' => $data->clim_logistica, 
					'emitecertificado' => $emiteCertificado, 
					'emitenotadecredito' => $emiteNotaDeCredito,
					'coeficienteextra' => $data->clim_coef_extra,
					'agregabonificacion' => $agregaBonificacion,
					'desdefecha_exclusionpercepcioniva' => $data->clim_dfexcl_piva,
					'hastafecha_exclusionpercepcioniva' => $data->clim_hfexcl_piva,
					'distribuidor_id' => $distribuidor_id,
					'descuentoventa_id' => null,
					'tipodocumento_id' => 1,
					'lugarentrega' => $data->clim_lugar_entrega,
					'horarioatencion' => $horarioAtencion
					];
			}
			else
			{
				$arr_campos = [
					"nombre" => $data->clim_nombre,
					"codigo" => $codigoCliente,
					"contacto" => $data->clim_contacto,
					"fantasia" => $data->clim_fantasia,
					"email" => $email,
					"telefono" => $data->clim_telefono.' '.$data->clim_fax,
					"urlweb" => (config('app.empresa') == 'INTERFORMING' ? $data->clim_url : ' '),
					"domicilio" => $data->clim_direccion,
					"localidad_id" => $localidad_id,
					"provincia_id" => $provincia_id,
					"pais_id" => $pais_id,
					"codigopostal" => $data->clim_cod_postal,
					"zonavta_id" => $zonavta_id,
					"subzonavta_id" => $subzonavta_id,
					"vendedor_id" => $vendedor_id,
					"transporte_id" => $transporte_id,
					"numerodocumento" => $data->clim_cuit,
					"condicioniva_id" => $condicioniva_id,
					"retieneiva" => $data->clim_retiene_iva,
					"nroiibb" => $data->clim_nro_ing_br,
					"condicioniibb_id" => $condicioniibb_id,
					"tipoempresa_cliente_id" => $tipoempresa_cliente_id,
					"condicionventa_id" => $condicionventa_id,
					"listaprecio_id" => $listaprecio_id,
					"descuento" => $data->clim_descuento,
					"cuentacontable_id" => $cuentacontable_id,
					"vaweb" => 'N',
					"estado" => $this->mapEstadoClienteDesdeAnita($data->clim_estado_cli ?? null),
					"leyenda" => $leyenda,
					"modofacturacion" => $modoFacturacion,
					"usuario_id" => $usuario_id,
					'horarioatencion' => $horarioAtencion
					];
			}

			$arr_campos = $this->normalizarCamposClienteSync($arr_campos);
			foreach (['desdefecha_exclusionpercepcioniva', 'hastafecha_exclusionpercepcioniva'] as $campoFecha) {
				if (array_key_exists($campoFecha, $arr_campos) && $this->fechaSyncCliente($arr_campos[$campoFecha]) === '') {
					$arr_campos[$campoFecha] = null;
				}
			}
		
			$clienteExistente = $this->queryClientePorCodigo((string) $data->clim_cliente)->first();
			$estado = 'omitido';

			if ($clienteExistente !== null) {
				unset($arr_campos['usuario_id']);
				if (! $this->hayCambiosCliente($clienteExistente, $arr_campos)) {
					return 'omitido';
				}
				$clienteExistente->update($arr_campos);
				$cliente = $clienteExistente->fresh();
				$estado = 'actualizado';
			} elseif ($fl_crea_registro !== false) {
            	$cliente = Cliente::create($arr_campos);
				$clienteRecienCreado = true;
				$estado = 'importado';
			} else {
				return 'omitido';
			}
        } else {
			return 'omitido';
		}

		if ($clienteRecienCreado && isset($cliente) && $cliente instanceof Cliente) {
			if (isset($dataseguimientoAnita) && is_array($dataseguimientoAnita) && count($dataseguimientoAnita) > 0) {
				foreach ($dataseguimientoAnita as $dataSeg) {
					Cliente_Seguimiento::create([
						'cliente_id' => $cliente->id,
						'fecha' => $dataSeg->movsc_fecha,
						'observacion' => $dataSeg->movsc_observacion,
						'leyenda' => '',
						'creousuario_id' => $usuario_id,
					]);
				}
			}

			if (isset($dataarticulo_suspendidoAnita) && is_array($dataarticulo_suspendidoAnita) && count($dataarticulo_suspendidoAnita) > 0) {
				foreach ($dataarticulo_suspendidoAnita as $dataArt) {
					$articulo = Articulo::select('sku', 'id')->where('sku', ltrim($dataArt->stksc_articulo, '0'))->first();

					if ($articulo) {
						Cliente_Articulo_Suspendido::create([
							'cliente_id' => $cliente->id,
							'articulo_id' => $articulo->id,
							'creousuario_id' => $usuario_id,
						]);
					}
				}
			}

			if (config('app.empresa') === 'INTERFORMING') {
				$this->importarCm05DesdeAnita($apiAnita, trim((string) $data->clim_cuit), $cliente->id);
			}
		}

		return $estado ?? 'omitido';
    }

	/**
	 * @param  array<string, mixed>  $datos
	 */
	private function hayCambiosCliente(Cliente $existente, array $datos): bool
	{
		$floats = ['descuento', 'porcentajelogistica', 'coeficienteextra'];
		$enteros = [
			'localidad_id', 'provincia_id', 'pais_id', 'zonavta_id', 'subzonavta_id',
			'vendedor_id', 'transporte_id', 'condicioniva_id', 'condicioniibb_id',
			'tipoempresa_cliente_id', 'condicionventa_id', 'listaprecio_id', 'cuentacontable_id',
			'abasto_id', 'coeficiente_id', 'distribuidor_id', 'descuentoventa_id', 'tipodocumento_id',
		];

		$fechas = ['desdefecha_exclusionpercepcioniva', 'hastafecha_exclusionpercepcioniva'];
		$flags = ['estado', 'retieneiva', 'modofacturacion', 'vaweb'];

		foreach ($datos as $campo => $nuevo) {
			$actual = $existente->{$campo};

			if (in_array($campo, $fechas, true)) {
				if ($this->fechaSyncCliente($actual) !== $this->fechaSyncCliente($nuevo)) {
					return true;
				}

				continue;
			}

			if (in_array($campo, $flags, true)) {
				if (trim((string) ($actual ?? '')) !== trim((string) ($nuevo ?? ''))) {
					return true;
				}

				continue;
			}

			if (in_array($campo, $floats, true)) {
				if (round((float) $actual, 4) !== round((float) $nuevo, 4)) {
					return true;
				}

				continue;
			}

			if (in_array($campo, $enteros, true)) {
				if ((int) ($actual ?? 0) !== (int) ($nuevo ?? 0)) {
					return true;
				}

				continue;
			}

			if ($this->textoSyncCliente($actual) !== $this->textoSyncCliente($nuevo)) {
				return true;
			}
		}

		return false;
	}

	private function fechaSyncCliente(mixed $valor): string
	{
		$texto = $this->textoSyncCliente($valor);
		if ($texto === '' || $texto === '0' || $texto === '0000-00-00' || $texto === '00000000') {
			return '';
		}

		return $texto;
	}

	private function textoSyncCliente(mixed $valor): string
	{
		return trim((string) ($valor ?? ''));
	}

	/**
	 * Código Informix para WHERE (clim_cliente suele estar con 6 dígitos: 010348).
	 */
	private function codigoParaConsultaAnita(string $codigo): string
	{
		$codigo = trim($codigo);
		if ($codigo === '') {
			return $codigo;
		}
		if (ctype_digit($codigo)) {
			$norm = ltrim($codigo, '0');

			return str_pad($norm !== '' ? $norm : '0', 6, '0', STR_PAD_LEFT);
		}

		return $codigo;
	}

	/**
	 * Anita clim_estado_cli: 0 = Activo, 1 = Suspendido (mismo enum que cliente.estado en ERP).
	 */
	private function mapEstadoClienteDesdeAnita(mixed $climEstado): string
	{
		return trim((string) ($climEstado ?? '')) === '1' ? '1' : '0';
	}

	/**
	 * @param  array<string, mixed>  $datos
	 * @return array<string, mixed>
	 */
	private function normalizarCamposClienteSync(array $datos): array
	{
		$texto = [
			'nombre', 'codigo', 'contacto', 'fantasia', 'email', 'telefono', 'urlweb', 'domicilio',
			'codigopostal', 'numerodocumento', 'nroiibb', 'leyenda',
			'emitecertificado', 'emitenotadecredito', 'agregabonificacion',
			'lugarentrega', 'horarioatencion',
			'desdefecha_exclusionpercepcioniva', 'hastafecha_exclusionpercepcioniva',
		];

		foreach ($texto as $campo) {
			if (array_key_exists($campo, $datos)) {
				if (in_array($campo, ['desdefecha_exclusionpercepcioniva', 'hastafecha_exclusionpercepcioniva'], true)) {
					$datos[$campo] = $this->fechaSyncCliente($datos[$campo]);
				} else {
					$datos[$campo] = $this->textoSyncCliente($datos[$campo]);
				}
			}
		}

		if (array_key_exists('email', $datos) && config('app.empresa') == 'EL BIERZO') {
			$datos['email'] = trim((string) $datos['email']);
		}

		if (array_key_exists('telefono', $datos)) {
			$datos['telefono'] = trim(preg_replace('/\s+/', ' ', (string) $datos['telefono']));
		}

		return $datos;
	}

	/**
	 * Escapa comillas simples para literales SQL Informix ('' dentro de '...').
	 */
	private function sqlLit($value): string
	{
		return str_replace("'", "''", (string) ($value ?? ''));
	}

	/**
	 * clim_direccion en Informix: solo letras, números y espacios (legacy Anita).
	 */
	private function domicilioParaAnita(?string $domicilio): string
	{
		return preg_replace('([^A-Za-z0-9 ])', '', (string) ($domicilio ?? ''));
	}

	/**
	 * Resuelve desc_localidad / desc_provincia desde los IDs del ERP (fuente de verdad para Anita).
	 *
	 * @param  array<string, mixed>  $request
	 * @return array<string, mixed>
	 */
	private function prepararDatosAnitaCliente(array $request, ?Cliente $cliente = null): array
	{
		$prepared = $request;

		if (! empty($prepared['localidad_id'])) {
			$nombreLocalidad = Localidad::query()
				->whereKey((int) $prepared['localidad_id'])
				->value('nombre');
			if ($nombreLocalidad !== null && trim($nombreLocalidad) !== '') {
				$prepared['desc_localidad'] = trim($nombreLocalidad);
			}
		}

		if (! empty($prepared['provincia_id'])) {
			$nombreProvincia = Provincia::query()
				->whereKey((int) $prepared['provincia_id'])
				->value('nombre');
			if ($nombreProvincia !== null && trim($nombreProvincia) !== '') {
				$prepared['desc_provincia'] = trim($nombreProvincia);
			}
		}

		if ($cliente !== null) {
			$cliente->loadMissing(['localidades', 'provincias']);
			if (empty($prepared['desc_localidad'])) {
				$prepared['desc_localidad'] = trim((string) ($cliente->localidades?->nombre ?? ''));
			}
			if (empty($prepared['desc_provincia'])) {
				$prepared['desc_provincia'] = trim((string) ($cliente->provincias?->nombre ?? ''));
			}
		}

		$prepared['desc_localidad'] = trim((string) ($prepared['desc_localidad'] ?? ''));
		$prepared['desc_provincia'] = trim((string) ($prepared['desc_provincia'] ?? ''));

		return $prepared;
	}

	/**
	 * Arma el payload HTTP (acc insert) para climae sin ejecutar el bridge.
	 */
	public function payloadInsertClimae(array $request): array
	{
		$request = $this->prepararDatosAnitaCliente($request);

		$this->setCamposAnita($request, $cuentacontable, $condicioniva, $condicioniibb, $codigotransporte,
			$codigolocalidad, $codigoprovincia, $codigopais, $codigozonavta, $codigovendedor,
			$codigolistaprecio, $codigoabasto, $codigocoeficiente, $codigodistribuidor,
			$emitecertificado, $emitenotadecredito, $agregabonificacion, $regimen, $codigotipoempresa);

		$fecha = Carbon::now()->format('Ymd');

		if (config('app.empresa') == 'EL BIERZO') {
			$desdefecha_exclusionpercepcioniva = $request['desdefecha_exclusionpercepcioniva'] ?? null;
			$dfexcl_piva = $desdefecha_exclusionpercepcioniva
				? $desdefecha_exclusionpercepcioniva->format('Ymd')
				: '00000000';
			$hastafecha_exclusionpercepcioniva = $request['hastafecha_exclusionpercepcioniva'] ?? null;
			$hfexcl_piva = $hastafecha_exclusionpercepcioniva
				? $hastafecha_exclusionpercepcioniva->format('Ymd')
				: '00000000';
		}

		$nombre = preg_replace('([^A-Za-z0-9 ])', '', $request['nombre'] ?? '');
		$contacto = preg_replace('([^A-Za-z0-9 ])', '', $request['contacto'] ?? '');
		$domicilio = $this->domicilioParaAnita($request['domicilio'] ?? '');

		$tipodocumento = ! empty($request['tipodocumento_id'])
			? Tipodocumento::find($request['tipodocumento_id'])
			: null;

		$documento = $this->sqlLit($request['numerodocumento'] ?? '');
		if ($tipodocumento && $tipodocumento->codigoexterno != '80') {
			$documento = $this->sqlLit($tipodocumento->abreviatura.' '.($request['numerodocumento'] ?? ''));
		}

		$campo_ing_bruto = (config('app.empresa') == 'AGG' || config('app.empresa') == 'INTERFORMING')
			? 'clim_nro_ing_br'
			: 'clim_nro_ing_bruto';

		$horarioAtencion = $this->sqlLit($request['horarioatencion'] ?? '');
		$urlweb = $this->sqlLit($request['urlweb'] ?? '');
		$cuentacontableSql = $this->sqlLit($cuentacontable ?? '');

		return [
			'tabla' => $this->tableAnita[0],
			'acc' => 'insert',
			'sistema' => 'ventas',
			'campos' => '
					clim_cliente, clim_nombre, clim_contacto, clim_direccion, clim_localidad, clim_cod_postal, clim_provincia, clim_telefono,
					clim_cuit, clim_cond_iva, clim_porc_excen, clim_letra, clim_cond_venta, clim_cta_contable, clim_credito, clim_dias_atraso,
					clim_zonavta, clim_subzona, clim_zonamult, clim_vendedor, clim_cobrador, clim_expreso, clim_tipo_empresa, clim_dir_cobranza,
					clim_hs_cobranza, clim_lugar_entrega, clim_retiene_iva, clim_lista_precio, clim_descuento, clim_nro_interno, clim_fecha_interes,
					clim_proveedor, clim_minimo_fact, clim_estado_cli, clim_dias_cobranza, clim_dias_atencion, clim_hs_atencion,clim_pais,
					clim_perc_ing_br, '.$campo_ing_bruto.', clim_dir_postal, clim_loc_postal, clim_cp_postal, clim_fantasia, clim_fecha_alta,
					clim_ley_liberado, clim_regimen, clim_leyenda_fact, clim_prov_postal, clim_lugar_de_pago, clim_excl_perc_iva, clim_fe_excl_piva,
					clim_dto_integrado, clim_fecha_boletin, clim_e_mail, clim_fax'.(config('app.empresa') == 'EL BIERZO' ?
					',clim_abasto, clim_distribuidor, clim_coef, clim_logistica, clim_emite_cert, clim_emite_nc, clim_coef_extra,clim_referencia,
					clim_cod_localidad, clim_cod_provincia, clim_agrega_bonif, clim_e_mail2, clim_dfexcl_piva, clim_hfexcl_piva' : '').
					(config('app.empresa') == 'INTERFORMING' ? ', clim_url, clim_hs_atencion2' : ''),
			'valores' => "
				'".str_pad($request['codigo'], 6, '0', STR_PAD_LEFT)."',
				'".$this->sqlLit($nombre)."',
				'".$this->sqlLit($contacto)."',
				'".$this->sqlLit($domicilio)."',
				'".$this->sqlLit($request['desc_localidad'] ?? '')."',
				'".$this->sqlLit($request['codigopostal'] ?? '')."',
				'".$this->sqlLit($request['desc_provincia'] ?? '')."',
				'".$this->sqlLit($request['telefono'] ?? '')."',
				'".$documento."',
				'".$condicioniva."',
				'0',
				'".$this->sqlLit($request['letra'] ?? '')."',
				'".$this->codigoCondicionVentaAnita($request['condicionventa_id'] ?? 0)."',
				'".$cuentacontableSql."',
				'0',
				'0',
				'".$codigozonavta."',
				'".(($request['subzonavta_id'] ?? 0) > 0 ? $request['subzonavta_id'] : 0)."',
				'".$codigoprovincia."',
				'".$codigovendedor."',
				'0',
				'".$codigotransporte."',
				'".$codigotipoempresa."',
				' ',
				' ',
				'".$this->sqlLit($request['lugarentrega'] ?? '')."',
				'".$this->sqlLit($request['retieneiva'] ?? 'N')."',
				'".$codigolistaprecio."',
				'".(($request['descuento'] ?? 0) > 0 ? $request['descuento'] : 0)."',
				'0',
				'0',
				' ',
				'0',
				'".$this->sqlLit($request['estado'] ?? '0')."',
				' ',
				' ',
				' ',
				'".$codigopais."',
				'".$condicioniibb."',
				'".$this->sqlLit($request['nroiibb'] ?? '')."',
				' ',
				' ',
				' ',
				'".$this->sqlLit($request['fantasia'] ?? '')."',
				'".$fecha."',
				' ',
				'".$regimen."',
				'0',
				'".$this->sqlLit($request['desc_provincia'] ?? '')."',
				' ',
				'0',
				'0',
				' ',
				'0',
				'".substr($this->sqlLit($request['email'] ?? ''), 0, 40)."',
				'FAX'".
				(config('app.empresa') == 'EL BIERZO' ? ",
				'".$codigoabasto."',
				'".$codigodistribuidor."',
				'".$codigocoeficiente."',
				'".($request['porcentajelogistica'] ?? 0)."',
				'".$emitecertificado."',
				'".$emitenotadecredito."',
				'".($request['coeficienteextra'] ?? 0)."',
				'0',
				'".$codigolocalidad."',
				'".$codigoprovincia."',
				'".$agregabonificacion."',
				'".substr($this->sqlLit($request['email'] ?? ''), 40, 40)."',
				'".$dfexcl_piva."',
				'".$hfexcl_piva."' " : '').
				(config('app.empresa') == 'INTERFORMING' ? ",
				'".$urlweb."',
				'".$horarioAtencion."' " : ''),
		];
	}

	/**
	 * SQL INSERT climae que enviaría el bridge (misma lógica que guardarAnita).
	 */
	public function previewInsertClimaeSqlPorCodigo(string $codigo): string
	{
		$codigoNorm = ltrim($codigo, '0');
		$cliente = $this->model->where('codigo', $codigoNorm)->first();
		if ($cliente === null) {
			throw new ModelNotFoundException('Cliente ERP no encontrado con código '.$codigo);
		}

		$payload = $this->payloadInsertClimae($this->datosRequestDesdeCliente($cliente));
		$apiAnita = new ApiAnita();

		return trim(preg_replace('/\s\s+/', ' ', $apiAnita->armarSql($payload)));
	}

	protected function anitaBridgeLogEvento(): string
	{
		return 'cliente.anita_bridge.fallo';
	}

	/**
	 * Arma el array de request que esperan guardarAnita/actualizarAnita a partir del modelo ERP.
	 */
	private function datosRequestDesdeCliente(Cliente $cliente): array
	{
		$cliente->loadMissing(['localidades', 'provincias', 'cliente_articulo_suspendidos', 'cliente_cm05s']);

		$condicioniva = $cliente->condicioniva_id
			? Condicioniva::find($cliente->condicioniva_id)
			: null;

		$defaults = [
			'codigo' => $cliente->codigo,
			'nombre' => $cliente->nombre ?? '',
			'contacto' => $cliente->contacto ?? '',
			'domicilio' => $cliente->domicilio ?? '',
			'desc_localidad' => $cliente->localidades->nombre ?? '',
			'desc_provincia' => $cliente->provincias->nombre ?? '',
			'codigopostal' => $cliente->codigopostal ?? '',
			'telefono' => $cliente->telefono ?? '',
			'letra' => $condicioniva?->letra ?? '',
			'numerodocumento' => $cliente->numerodocumento ?? '',
			'tipodocumento_id' => $cliente->tipodocumento_id,
			'condicionventa_id' => $cliente->condicionventa_id ?? 0,
			'subzonavta_id' => $cliente->subzonavta_id ?? 0,
			'vendedor_id' => $cliente->vendedor_id ?? 0,
			'lugarentrega' => $cliente->lugarentrega ?? '',
			'retieneiva' => $cliente->retieneiva ?? 'N',
			'descuento' => $cliente->descuento ?? 0,
			'estado' => $cliente->estado ?? '0',
			'nroiibb' => $cliente->nroiibb ?? '',
			'fantasia' => $cliente->fantasia ?? '',
			'email' => $cliente->email ?? '',
			'leyenda' => $cliente->leyenda ?? '',
			'horarioatencion' => $cliente->horarioatencion ?? '',
			'urlweb' => $cliente->urlweb ?? '',
			'condicioniva_id' => $cliente->condicioniva_id ?? 1,
			'condicioniibb_id' => $cliente->condicioniibb_id ?? 4,
			'tipoempresa_cliente_id' => $cliente->tipoempresa_cliente_id,
			'cuentacontable_id' => $cliente->cuentacontable_id,
			'transporte_id' => $cliente->transporte_id ?? 0,
			'localidad_id' => $cliente->localidad_id ?? 0,
			'provincia_id' => $cliente->provincia_id ?? 0,
			'pais_id' => $cliente->pais_id ?? 1,
			'zonavta_id' => $cliente->zonavta_id ?? 0,
			'listaprecio_id' => $cliente->listaprecio_id ?? 0,
			'porcentajelogistica' => $cliente->porcentajelogistica ?? 0,
			'coeficienteextra' => $cliente->coeficienteextra ?? 0,
			'emitecertificado' => $cliente->emitecertificado ?? 'No Emite Certificado',
			'emitenotadecredito' => $cliente->emitenotadecredito ?? 'No Emite Nota de Credito',
			'agregabonificacion' => $cliente->agregabonificacion ?? 'No Agrega Bonificacion',
			'modofacturacion' => $cliente->modofacturacion ?? 'N',
			'abasto_id' => $cliente->abasto_id ?? 0,
			'coeficiente_id' => $cliente->coeficiente_id ?? 0,
			'distribuidor_id' => $cliente->distribuidor_id ?? null,
			'desdefecha_exclusionpercepcioniva' => $cliente->desdefecha_exclusionpercepcioniva,
			'hastafecha_exclusionpercepcioniva' => $cliente->hastafecha_exclusionpercepcioniva,
			'articulo_suspendido_ids' => $cliente->cliente_articulo_suspendidos
				->pluck('articulo_id')
				->filter()
				->values()
				->all(),
			'tiene_cm05' => $cliente->cliente_cm05s->isNotEmpty(),
		];

		return array_merge($cliente->toArray(), $defaults);
	}

	/**
	 * Replica en Informix un cliente ya existente en el ERP (insert si no está en climae, update si existe).
	 */
	public function replicarClienteEnAnitaPorCodigo(string $codigo): string
	{
		$codigoNorm = ltrim($codigo, '0');
		$cliente = $this->model->where('codigo', $codigoNorm)->first();
		if ($cliente === null) {
			throw new ModelNotFoundException('Cliente ERP no encontrado con código '.$codigo);
		}

		$apiAnita = new ApiAnita();
		$consulta = [
			'acc' => 'list',
			'tabla' => $this->tableAnita[0],
			'sistema' => 'ventas',
			'campos' => $this->keyFieldAnita,
			'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".str_pad($codigoNorm, 6, '0', STR_PAD_LEFT)."' ",
		];
		$existeEnAnita = ApiAnita::decodificarListaFilas((string) $apiAnita->apiCall($consulta)) !== [];

		$datos = $this->datosRequestDesdeCliente($cliente);

		if ($existeEnAnita) {
			self::actualizarAnita($datos, $codigoNorm);
			$this->sincronizarCm05Anita($apiAnita, $datos, $codigoNorm);

			return 'actualizado';
		}

		self::guardarAnita($datos);
		$this->sincronizarCm05Anita($apiAnita, $datos, $codigoNorm);

		return 'insertado';
	}

	/**
	 * Actualiza climae en Anita después de grabar tablas asociadas (CM05, entregas, etc.).
	 */
	public function sincronizarAnitaDespuesDeGrabado(int $clienteId): void
	{
		if (! config('app.anita_sync_cliente_write', false)) {
			return;
		}

		$cliente = $this->model->findOrFail($clienteId);
		$datos = $this->datosRequestDesdeCliente($cliente);
		self::actualizarAnita($datos, $datos['codigo']);
	}

	private function guardarAnita($request) {
        $apiAnita = new ApiAnita();
		$data = $this->payloadInsertClimae($request);
        $this->apiCallAnitaEscritura($apiAnita, $data, 'climae insert');

		// Graba leyenda
		$leyenda = explode("\n", $request['leyenda']);
		$linea = 0;
		foreach ($leyenda as $ley)
		{
        	$data = array( 'tabla' => $this->tableAnita[1], 'acc' => 'insert',
							'sistema' => 'ventas',
            				'campos' => '
								clil_cliente,
								clil_linea,
								clil_leyenda
										',
            				'valores' => " 
								'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
								'".$linea++."', 
								'".preg_replace("/\r/", "", $ley)."' "
						);

        	$this->apiCallAnitaEscritura($apiAnita, $data, 'cliley insert');
		}

		// Graba articulos suspendidos
		if (isset($request['articulo_suspendido_ids']))
		{
			foreach($request['articulo_suspendido_ids'] as $articulo)
			{
				$articulo = Articulo::find($articulo);

				if ($articulo)
				{
					$data = array( 'tabla' => $this->tableAnita[4], 'acc' => 'insert',
						'sistema' => 'ventas',
						'campos' => '
							stksc_cliente,
							stksc_articulo
									',
						'valores' => " 
							'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
							'".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."' "
					);

        			$this->apiCallAnitaEscritura($apiAnita, $data, 'stksuspcli insert');
				}
			}
		}

		// Graba comisiones
		if ($request['vendedor_id'] > 0 && config('app.empresa') == 'Calzados Ferli')
		{
			$mventa = Mventa::all();
			foreach ($mventa as $marca)
			{
        		$data = array( 'tabla' => $this->tableAnita[2], 'acc' => 'insert',
							'sistema' => 'ventas',
            				'campos' => '
								clico_cliente,
								clico_marca,
								clico_vendedor
										',
            				'valores' => " 
								'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
								'".$marca->id."', 
								'".$request['vendedor_id']."' "
						);

        		$this->apiCallAnitaEscritura($apiAnita, $data, 'clicomi insert');
			}
		}

		$this->sincronizarCm05Anita($apiAnita, $request, $request['codigo']);
	}

	private function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$request = $this->prepararDatosAnitaCliente($request);
        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

		if (config("app.empresa") == "EL BIERZO")
		{
			$desdefecha_exclusionpercepcioniva = $request['desdefecha_exclusionpercepcioniva'];
			if ($desdefecha_exclusionpercepcioniva)
				$dfexcl_piva = $desdefecha_exclusionpercepcioniva->format('Ymd');
			else
				$dfexcl_piva = '00000000';

			$hastafecha_exclusionpercepcioniva = $request['hastafecha_exclusionpercepcioniva'];
			if ($hastafecha_exclusionpercepcioniva)
				$hfexcl_piva = $hastafecha_exclusionpercepcioniva->format('Ymd');
			else
				$hfexcl_piva = '00000000';
		}

		$this->setCamposAnita($request, $cuentacontable, $condicioniva, $condicioniibb, $codigotransporte,
								$codigolocalidad, $codigoprovincia, $codigopais, $codigozonavta, $codigovendedor,
								$codigolistaprecio, $codigoabasto, $codigocoeficiente, $codigodistribuidor,
								$emitecertificado, $emitenotadecredito, $agregabonificacion, $regimen, $codigotipoempresa);

		if (array_key_exists('localidad_id', $request))
			$localidad_id = $request['localidad_id'];
		else
			$localidad_id = 0;

		$nombre = preg_replace('([^A-Za-z0-9 ])', '', $request['nombre']);
		$contacto = preg_replace('([^A-Za-z0-9 ])', '', $request['contacto']);
		$domicilio = $this->domicilioParaAnita($request['domicilio'] ?? '');

		$tipodocumento = Tipodocumento::find($request['tipodocumento_id']);

		$documento = $request['numerodocumento'];
		if ($tipodocumento)
		{
			if ($tipodocumento->codigoexterno != "80")
				$documento = $tipodocumento->abreviatura.' '.$request['numerodocumento'];
		}		
		if (config('app.empresa') == 'AGG' || config('app.empresa') == 'INTERFORMING')
			$campo_ing_bruto = 'clim_nro_ing_br';
		else
			$campo_ing_bruto = 'clim_nro_ing_bruto';
		$horarioAtencion = $request['horarioatencion'];

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'ventas',
				'valores' => " 
                clim_cliente 	                = '".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."',
                clim_nombre 	                = '".$nombre."',
                clim_contacto 	                = '".$contacto."',
                clim_direccion 	                = '".$this->sqlLit($domicilio)."',
                clim_localidad 	                = '".$request['desc_localidad']."',
                clim_cod_postal 	            = '".$request['codigopostal']."',
                clim_provincia 	                = '".$request['desc_provincia']."',
                clim_telefono 	                = '".$request['telefono']."',
                clim_cuit 	                    = '".$documento."',
                clim_cond_iva 	                = '".$condicioniva."',
                clim_letra 	                    = '".$request['letra']."',
                clim_cond_venta 	            = '".$this->codigoCondicionVentaAnita($request['condicionventa_id'] ?? 0)."',
                clim_cta_contable 	            = '".$cuentacontable."',
                clim_zonavta 	                = '".$codigozonavta."',
                clim_subzona 	                = '".($request['subzonavta_id']>0?$request['subzonavta_id']:0)."',
                clim_zonamult 	                = '".$codigoprovincia."',
                clim_vendedor 	                = '".$codigovendedor."',
                clim_expreso 	                = '".$codigotransporte."',
                clim_tipo_empresa               = '".$codigotipoempresa."',
				clim_lugar_entrega              = '".$request['lugarentrega']."',
                clim_retiene_iva 	            = '".$request['retieneiva']."',
                clim_lista_precio 	            = '".$codigolistaprecio."',
                clim_descuento 	                = '".($request['descuento'] > 0 ? $request['descuento'] : 0)."',
                clim_estado_cli 	            = '".$request['estado']."',
                clim_pais 	                    = '".$codigopais."',
                clim_perc_ing_br 	            = '".$condicioniibb."',
                ".$campo_ing_bruto."            = '".$request['nroiibb']."',
                clim_fantasia 	                = '".$request['fantasia']."',
                clim_fecha_alta 	            = '".$fecha."',
                clim_e_mail 	                = '".substr($request['email'],0,40)."'".
				(config("app.empresa") == "EL BIERZO" ? ",
				clim_abasto                     = '".$codigoabasto."',
				clim_distribuidor               = '".$codigodistribuidor."',
				clim_coef                       = '".$codigocoeficiente."',
				clim_logistica                  = '".$request['porcentajelogistica']."',
				clim_emite_cert                 = '".$emitecertificado."',
				clim_emite_nc                   = '".$emitenotadecredito."',
				clim_coef_extra                 = '".$request['coeficienteextra']."',
				clim_referencia                 = '".'0'."',
				clim_cod_localidad              = '".$codigolocalidad."',
				clim_cod_provincia              = '".$codigoprovincia."',
				clim_agrega_bonif               = '".$agregabonificacion."',
				clim_e_mail2                    = '".substr($request['email'],40,40)."',
				clim_dfexcl_piva                = '".$dfexcl_piva."',
				clim_hfexcl_piva                = '".$hfexcl_piva."' " : "").
				(config('app.empresa') == "INTERFORMING" ? ",
				clim_url                        = '".$request['urlweb']."',
				clim_hs_atencion2               = '".$horarioAtencion."' " : "")
				,
				'whereArmado' => " WHERE clim_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $this->apiCallAnitaEscritura($apiAnita, $data, 'climae update');

		// Borra leyenda
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'whereArmado' => " WHERE clil_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $this->apiCallAnitaEscritura($apiAnita, $data, 'cliley delete');

		// Graba leyenda
		$leyenda = explode("\n", $request['leyenda']);
		$linea = 0;
		foreach ($leyenda as $ley)
		{
        	$data = array( 'tabla' => $this->tableAnita[1], 'acc' => 'insert',
							'sistema' => 'ventas',
            				'campos' => '
								clil_cliente,
								clil_linea,
								clil_leyenda
										',
            				'valores' => " 
								'".str_pad($id, 6, "0", STR_PAD_LEFT)."', 
								'".$linea++."', 
								'".preg_replace("/\r/", "", $ley)."' "
						);

        	$this->apiCallAnitaEscritura($apiAnita, $data, 'cliley insert');
		}

		// Borra articulos suspendidos
		if (config("app.empresa") == "EL BIERZO")
		{
			$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[4], 
					'sistema' => 'ventas',
					'whereArmado' => " WHERE stksc_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
			$this->apiCallAnitaEscritura($apiAnita, $data, 'stksuspcli delete');

			// Graba articulos suspendidos
			if (isset($request['articulo_suspendido_ids']))
			{
				foreach($request['articulo_suspendido_ids'] as $articulo)
				{
					$articulo = Articulo::find($articulo);

					if ($articulo)
					{
						$data = array( 'tabla' => $this->tableAnita[4], 'acc' => 'insert',
							'sistema' => 'ventas',
							'campos' => '
								stksc_cliente,
								stksc_articulo
										',
							'valores' => " 
								'".str_pad($id, 6, "0", STR_PAD_LEFT)."', 
								'".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."' "
						);

						$this->apiCallAnitaEscritura($apiAnita, $data, 'stksuspcli insert');
					}
				}		
			}
		}

		// Borra comisiones
		if ($request['vendedor_id'] > 0 && config('app.empresa') == 'Calzados Ferli')
		{
			$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[2], 
					'sistema' => 'ventas',
					'whereArmado' => " WHERE clico_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
			$this->apiCallAnitaEscritura($apiAnita, $data, 'clicomi delete');

			// Graba comisiones
			$mventa = Mventa::all();
			foreach ($mventa as $marca)
			{
        		$data = array( 'tabla' => $this->tableAnita[2], 'acc' => 'insert',
							'sistema' => 'ventas',
            				'campos' => '
								clico_cliente,
								clico_marca,
								clico_vendedor
										',
            				'valores' => " 
								'".str_pad($id, 6, "0", STR_PAD_LEFT)."', 
								'".$marca->id."', 
								'".$request['vendedor_id']."' "
						);

        		$this->apiCallAnitaEscritura($apiAnita, $data, 'clicomi insert');
			}
		}

		$this->sincronizarCm05Anita($apiAnita, $request, $id);
	}

	private function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'ventas',
				'whereArmado' => " WHERE clim_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $this->apiCallAnitaEscritura($apiAnita, $data, 'climae delete');

		// Borra leyenda
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'sistema' => 'ventas',
				'whereArmado' => " WHERE clil_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $this->apiCallAnitaEscritura($apiAnita, $data, 'cliley delete');

		if (config("app.empresa") == "EL BIERZO")
		{
			// Borra articulos suspendidos
			$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[4], 
					'sistema' => 'ventas',
					'whereArmado' => " WHERE stksc_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
			$this->apiCallAnitaEscritura($apiAnita, $data, 'stksuspcli delete');

			// Borra seguimiento de clientes
			$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[3], 
					'sistema' => 'ventas',
					'whereArmado' => " WHERE movsc_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
			$this->apiCallAnitaEscritura($apiAnita, $data, 'movscli delete');
		}
	}

	private function actualizarEmiteNc($emite, $id) 
	{
        $apiAnita = new ApiAnita();

		if ($emite['emitenotadecredito'] == 'Emite Nota de Credito')
			$emitenotadecredito = 'S';
		else
			$emitenotadecredito = 'N';
				
		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'ventas',
				'valores' => " 
				clim_emite_nc = '".$emitenotadecredito."' ",
				'whereArmado' => " WHERE clim_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );

		return $this->apiCallAnitaEscritura($apiAnita, $data, 'climae emite_nc');
	}

	// Devuelve ultimo codigo de clientes + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
        $apiAnita = new ApiAnita();
		if (config('app.empresa') == 'AGG')
        	$data = array( 'acc' => 'list', 
				'tabla' => $this->tableAnita[0], 
				'sistema' => 'ventas',
				'campos' => " max(clim_cliente) as $this->keyFieldAnita ",
				'whereArmado' => " WHERE clim_cliente[1,3] = 'ERP' " 
				);
		else
        	$data = array( 'acc' => 'list', 
				'tabla' => $this->tableAnita[0], 
				'sistema' => 'ventas',
				'campos' => " max(clim_cliente) as $this->keyFieldAnita "
				);		
				
        $dataAnita = json_decode($apiAnita->apiCall($data));

		if ($dataAnita[0]->{$this->keyFieldAnita} != '')
		{
			$numero = filter_var($dataAnita[0]->{$this->keyFieldAnita}, FILTER_SANITIZE_NUMBER_INT);
			$numero = $numero + 1;

			if (config('app.empresa') == 'AGG')
				$codigo = 'ERP'.str_pad($numero, 3, "0", STR_PAD_LEFT);
			else
				$codigo = str_pad($numero, 6, "0", STR_PAD_LEFT);
		}
		else
		{
			if (config('app.empresa') == 'AGG')
				$codigo = 'ERP001';
			else
				$codigo = "000001";
		}
	}

	private function setCamposAnita($request, &$cuentacontable, &$condicioniva, &$condicioniibb, &$codigotransporte,
									&$codigolocalidad, &$codigoprovincia, &$codigopais, &$codigozonavta, &$codigovendedor,
									&$codigolistaprecio, &$codigoabasto, &$codigocoeficiente, &$codigodistribuidor,
									&$emitecertificado, &$emitenotadecredito, &$agregabonificacion, &$regimen,
									&$codigotipoempresa)
	{
		$regimen = '0';
		$codigodistribuidor = 0;

       	$cuenta = Cuentacontable::select('id', 'codigo')->where('id' , $request['cuentacontable_id'])->first();
		if ($cuenta)
			$cuentacontable = $cuenta->codigo;
		else
			$cuentacontable = NULL;

		$codigotransporte = $this->codigoTransporteAnitaDesdeTransporteId($request['transporte_id'] ?? null);

		$condicioniva_id = 1;
		switch($request['condicioniva_id'])
		{
		case '1':
			$condicioniva = '0';
			break;
		case '3':
			$condicioniva = '3';
			break;
		case '2':
		case '5':
			$condicioniva = '4';
			break;
		case '4':
			$condicioniva = '5';
			break;
		}
		$condicioniibb = $this->codigoPercIngBrAnita($request);

		if (config("app.empresa") == "EL BIERZO")
		{
			if ($request['emitecertificado'] == 'Emite Certificado')
				$emitecertificado = 'S';
			else
				$emitecertificado = 'N';

			if ($request['emitenotadecredito'] == 'Emite Nota de Credito')
				$emitenotadecredito = 'S';
			else
				$emitenotadecredito = 'N';

			if ($request['agregabonificacion'] == 'Agrega Bonificacion')		
				$agregabonificacion = 'S';
			else
				$agregabonificacion = 'N';

			if ($request['modofacturacion'] == 'N')
				$regimen = '0';
			else
				$regimen = '1';
		}
		$localidad = Localidad::select('id', 'codigo')->where('id', $request['localidad_id'] ?? 0)->first();
		if ($localidad)
			$codigolocalidad = $localidad->codigo;
		else
			$codigolocalidad = 0;

		$provincia = Provincia::select('id', 'codigo')->where('id' , $request['provincia_id'])->first();
		if ($provincia)
			$codigoprovincia = $provincia->codigo;
		else
			$codigoprovincia = 0;

		$pais = Pais::select('id', 'codigo')->where('id', $request['pais_id'])->first();
		if ($pais && ($codigo = $pais->codigoAnita()) !== null) {
			$codigopais = $codigo;
		} else {
			$codigopais = 1;
		}

		$zonavta = Zonavta::select('id', 'codigo')->where('id' , $request['zonavta_id'])->first();
		if ($zonavta)
			$codigozonavta = $zonavta->codigo;
		else
			$codigozonavta = 0;

		$vendedor = Vendedor::select('id', 'codigo')->where('id' , $request['vendedor_id'])->first();
		if ($vendedor)
			$codigovendedor = $vendedor->codigo;
		else
			$codigovendedor = 0;
	
		$listaprecio = Listaprecio::select('id', 'codigo')->where('id' , $request['listaprecio_id'])->first();
		if ($listaprecio)
			$codigolistaprecio = $listaprecio->codigo;
		else
			$codigolistaprecio = 0;

		if (config("app.empresa") == "EL BIERZO")
		{
			$abasto = Abasto::select('id', 'codigo')->where('id' , $request['abasto_id'])->first();
			if ($abasto)
				$codigoabasto = $abasto->codigo;
			else
				$codigoabasto = 0;
				
			$coeficiente = Coeficiente::select('id', 'codigo')->where('id' , $request['coeficiente_id'])->first();
			if ($coeficiente)
				$codigocoeficiente = $coeficiente->codigo;
			else
				$codigocoeficiente = 0;

			$codigodistribuidor = Distribuidor::codigoAnitaDesdeId(
				isset($request['distribuidor_id']) && $request['distribuidor_id'] !== ''
					? (int) $request['distribuidor_id']
					: null
			);
		}

		$tipoemp = TipoempresaCliente::select('id', 'codigo')->where('id', $request['tipoempresa_cliente_id'] ?? 0)->first();
		if ($tipoemp) {
			$codigotipoempresa = $tipoemp->codigo;
		} else {
			$codigotipoempresa = 0;
		}
	}

	/**
	 * clim_expreso en Informix (legacy): código del transporte/reparto (tabla expreso / transporte.codigo ERP).
	 */
	private function codigoTransporteAnitaDesdeTransporteId($transporteId): string
	{
		$id = (int) ($transporteId ?? 0);
		if ($id <= 0) {
			return '0';
		}

		$codigo = Transporte::query()->whereKey($id)->value('codigo');
		$codigo = trim((string) ($codigo ?? ''));

		return $codigo !== '' ? $codigo : '0';
	}

	/**
	 * Resuelve transporte_id ERP desde clim_expreso importado de Anita.
	 */
	private function resolverTransporteIdDesdeClimExpreso($codigoAnita): ?int
	{
		$codigoAnita = trim((string) ($codigoAnita ?? ''));
		if ($codigoAnita === '' || $codigoAnita === '0') {
			return null;
		}

		$variantes = array_values(array_unique([
			$codigoAnita,
			ltrim($codigoAnita, '0'),
		]));

		$id = Transporte::query()->whereIn('codigo', $variantes)->value('id');

		return $id ? (int) $id : null;
	}

	/**
	 * Código condmae (Informix / clim_cond_venta) desde condicionventa.codigo del ERP.
	 */
	private function codigoCondicionVentaAnita($condicionventaId): string
	{
		if ((int) $condicionventaId <= 0) {
			return '0';
		}

		$condicion = Condicionventa::find($condicionventaId);
		if ($condicion) {
			$codigo = trim((string) ($condicion->codigo ?? ''));
			if ($codigo !== '') {
				return $codigo;
			}
		}

		return '0';
	}

	/**
	 * clim_perc_ing_br: en INTERFORMING con jurisdicciones CM05 graba 7 (CM05); el resto mantiene convenio/local/etc.
	 */
	private function codigoPercIngBrAnita(array $request): string
	{
		if (config('app.empresa') === 'INTERFORMING' && ! empty($request['tiene_cm05'])) {
			return '7';
		}

		$condicioniibb = '2';
		switch ($request['condicioniibb_id'] ?? null) {
			case 3:
				$condicioniibb = '1';
				break;
			case 1:
				$condicioniibb = '2';
				break;
			case 2:
				$condicioniibb = '3';
				break;
			case 4:
				$condicioniibb = 'N';
				break;
		}

		return $condicioniibb;
	}

	/**
	 * CUIT en formato Anita (clim_cuit / clii_cuit), hasta 13 caracteres.
	 */
	private function cuitAnitaDesdeRequest(array $request): string
	{
		$tipodocumento = ! empty($request['tipodocumento_id'])
			? Tipodocumento::find($request['tipodocumento_id'])
			: null;

		$documento = trim((string) ($request['numerodocumento'] ?? ''));
		if ($tipodocumento && $tipodocumento->codigoexterno != '80') {
			$documento = trim($tipodocumento->abreviatura.' '.$documento);
		}

		return substr($documento, 0, 13);
	}

	private function cuitAnitaDesdeCliente(Cliente $cliente): string
	{
		return $this->cuitAnitaDesdeRequest([
			'numerodocumento' => $cliente->numerodocumento,
			'tipodocumento_id' => $cliente->tipodocumento_id,
		]);
	}

	private function fechaAnitaInformix($fecha): string
	{
		if ($fecha === null || $fecha === '') {
			return '00000000';
		}

		return Carbon::parse($fecha)->format('Ymd');
	}

	/**
	 * ERP tipopercepcion → cliibr.clii_codigo_perc (1=percibe, 2=no percibe, 3=agente).
	 */
	private function codigoPercCliibrAnita(string $tipopercepcion): string
	{
		return match ($tipopercepcion) {
			'N' => '2',
			default => '1',
		};
	}

	/**
	 * cliibr.clii_codigo_perc → ERP tipopercepcion.
	 */
	private function tipopercepcionDesdeCliibr(string $codigoPerc, $coeficiente): string
	{
		if ($codigoPerc === '2') {
			return 'N';
		}

		if ($codigoPerc === '1' && (float) $coeficiente > 0) {
			return 'C';
		}

		return 'P';
	}

	/**
	 * Replica jurisdicciones CM05 del ERP a Informix (cliibr), solo INTERFORMING.
	 */
	private function sincronizarCm05Anita(ApiAnita $apiAnita, array $request, $codigoCliente): void
	{
		if (config('app.empresa') !== 'INTERFORMING') {
			return;
		}

		$tabla = (string) config('cliente_anita.cm05_tabla', 'cliibr');
		if ($tabla === '') {
			return;
		}

		$cliente = $this->model->where('codigo', ltrim((string) $codigoCliente, '0'))->first();
		if ($cliente === null) {
			return;
		}

		$cuit = $this->cuitAnitaDesdeCliente($cliente);
		if ($cuit === '') {
			return;
		}

		$cuitSql = $this->sqlLit($cuit);
		$cm05s = $cliente->cliente_cm05s()->with('provincias')->get();

		$data = [
			'acc' => 'delete',
			'tabla' => $tabla,
			'sistema' => 'ventas',
			'whereArmado' => " WHERE clii_cuit = '".$cuitSql."' ",
		];
		$this->apiCallAnitaEscritura($apiAnita, $data, 'cliibr delete');

		foreach ($cm05s as $cm05) {
			$zonamult = (int) ($cm05->provincias?->codigo ?? 0);
			if ($zonamult <= 0) {
				continue;
			}

			$codigoPerc = $this->codigoPercCliibrAnita((string) $cm05->tipopercepcion);
			$coeficiente = $cm05->coeficiente ?? 0;
			$certificado = in_array($cm05->certificadonoretencion, ['S', 'N'], true)
				? $cm05->certificadonoretencion
				: 'N';

			$data = [
				'tabla' => $tabla,
				'acc' => 'insert',
				'sistema' => 'ventas',
				'campos' => 'clii_cuit, clii_zonamult, clii_codigo_perc, clii_vigencia, clii_coeficiente, clii_cert_no_ret, clii_dfecha_no_ret, clii_hfecha_no_ret',
				'valores' => " '".$cuitSql."', '".$zonamult."', '".$codigoPerc."', '".$this->fechaAnitaInformix($cm05->fechavigencia)."', '".$coeficiente."', '".$certificado."', '".$this->fechaAnitaInformix($cm05->desdefechanoretencion)."', '".$this->fechaAnitaInformix($cm05->hastafechanoretencion)."' ",
			];
			$this->apiCallAnitaEscritura($apiAnita, $data, 'cliibr insert');
		}
	}

	/**
	 * Importa cliibr (Anita) → cliente_cm05 (ERP), solo INTERFORMING.
	 */
	private function importarCm05DesdeAnita(ApiAnita $apiAnita, string $cuitAnita, int $clienteId): void
	{
		if (config('app.empresa') !== 'INTERFORMING' || trim($cuitAnita) === '') {
			return;
		}

		$tabla = (string) config('cliente_anita.cm05_tabla', 'cliibr');
		$cuitSql = $this->sqlLit(trim($cuitAnita));

		$data = [
			'acc' => 'list',
			'sistema' => 'ventas',
			'tabla' => $tabla,
			'campos' => 'clii_cuit, clii_zonamult, clii_codigo_perc, clii_vigencia, clii_coeficiente, clii_cert_no_ret, clii_dfecha_no_ret, clii_hfecha_no_ret',
			'whereArmado' => " WHERE clii_cuit = '".$cuitSql."' ",
		];
		$filas = json_decode($apiAnita->apiCall($data));
		if (! is_array($filas) || count($filas) === 0) {
			return;
		}

		Cliente_Cm05::where('cliente_id', $clienteId)->delete();

		$usuarioId = $this->usuarioIdParaSincronizacion();

		foreach ($filas as $fila) {
			$provincia = Provincia::select('id')->where('codigo', $fila->clii_zonamult)->first();
			if (! $provincia) {
				continue;
			}

			$coeficiente = (float) ($fila->clii_coeficiente ?? 0);
			$codigoPerc = trim((string) ($fila->clii_codigo_perc ?? '1'));

			Cliente_Cm05::create([
				'cliente_id' => $clienteId,
				'provincia_id' => $provincia->id,
				'tipopercepcion' => $this->tipopercepcionDesdeCliibr($codigoPerc, $coeficiente),
				'coeficiente' => $coeficiente > 0 ? $coeficiente : null,
				'fechavigencia' => $this->fechaErpDesdeAnita($fila->clii_vigencia ?? null),
				'certificadonoretencion' => in_array(trim((string) ($fila->clii_cert_no_ret ?? 'N')), ['S', 'N'], true)
					? trim((string) $fila->clii_cert_no_ret)
					: 'N',
				'desdefechanoretencion' => $this->fechaErpDesdeAnita($fila->clii_dfecha_no_ret ?? null),
				'hastafechanoretencion' => $this->fechaErpDesdeAnita($fila->clii_hfecha_no_ret ?? null),
				'creousuario_id' => $usuarioId,
			]);
		}
	}

	private function fechaErpDesdeAnita($fechaAnita): ?string
	{
		$fecha = (int) $fechaAnita;
		if ($fecha <= 0) {
			return null;
		}

		return Carbon::createFromFormat('Ymd', (string) $fecha)->format('Y-m-d');
	}

	/**
	 * @param  array<string, mixed>|string|null  $filtros  Criterios del listado o texto legacy (modo todos).
	 */
	public function leeCliente($filtros, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ClienteListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ClienteListadoFiltros::filtrosVacios();
        }

        $cliente = $this->model->select('cliente.id as id',
                                        'cliente.nombre as nombre',
										'transporte.codigo as ctransporte',
										'transporte.nombre as nombretransporte',
										'cliente.numerodocumento as numerodocumento',
                                        'cliente.domicilio as domicilio',
										'cliente.codigo as codigo',
                                        'cliente.estado as estado',
                                        'localidad.nombre as nombrelocalidad',
										'provincia.nombre as nombreprovincia')
                                ->leftjoin('localidad', 'localidad.id', 'cliente.localidad_id')
								->leftjoin('provincia', 'provincia.id', 'cliente.provincia_id')
								->leftjoin('transporte', 'transporte.id', 'cliente.transporte_id');
		
		$vendedores = $this->vendedorRepository->leeVendedoresAsociados();

		if (count($vendedores) > 0)
		{
			$cliente = $cliente->whereIn('vendedor_id', $vendedores);
		}

        if (ClienteListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ClienteListadoFiltros::aplicar($cliente, $filtros);
        }

		$cliente = $cliente->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $cliente = $cliente->paginate(10);
            else
                $cliente = $cliente->get();
        }
        else
            $cliente = $cliente->get();

        return $cliente;
    }

	public function consultaCliente($consulta)
    {
		$columns = ['cliente.id', 'cliente.nombre', 'cliente.codigo', 'cliente.domicilio', 'provincia.nombre', 'localidad.nombre'];
        $columnsOut = ['id', 'nombre', 'codigo', 'domicilio', 'provincia', 'localidad'];
		$colspan = count($columnsOut) + 1;

		$consulta = is_string($consulta) ? trim($consulta) : '';
		$minLen = preg_match('/^\d+$/', $consulta) ? 1 : 2;

		if (mb_strlen($consulta) < $minLen) {
			$hint = 'Ingrese al menos '.$minLen
				.($minLen === 1 ? ' dígito' : ' caracteres')
				.' para buscar (solo clientes activos).';

			return json_encode([
				'data' => '<tr><td colspan="'.$colspan.'" class="text-muted">'.$hint.'</td></tr>',
			], JSON_UNESCAPED_UNICODE);
		}

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('cliente.id as id',
									'cliente.nombre as nombre',
                                    'cliente.codigo as codigo',
									'cliente.domicilio as domicilio',
									'provincia.nombre as provincia',
									'localidad.nombre as localidad')
							->leftjoin('provincia', 'provincia.id', '=', 'cliente.provincia_id')
							->leftjoin('localidad', 'localidad.id', '=', 'cliente.localidad_id')
							->activos()
							->whereNull('cliente.deleted_at');

		// Filtra vendedores
		$vendedores = $this->vendedorRepository->leeVendedoresAsociados();

		if (count($vendedores) > 0)
			$data = $data->where('vendedor_id', $vendedores);

		$data = $data->where(function ($query) use ($count, $consulta, $columns) {
                        			for ($i = 0; $i < $count; $i++)
                            			$query->orWhere($columns[$i], 'LIKE', '%'.$consulta.'%');
                            })
							->orderBy('cliente.nombre', 'asc')
							->limit(250)
                            ->get();

        $output = [];
		$output['data'] = '';
        $flSinDatos = true;
        $count = count($columns);
		if (count($data) > 0)
		{
			foreach ($data as $row)
			{
                $flSinDatos = false;
                $output['data'] .= '<tr>';
                for ($i = 0; $i < $count; $i++)
                    $output['data'] .= '<td class="'.$columnsOut[$i].'">' . $row->{$columnsOut[$i]} . '</td>';
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultacliente">Elegir</a></td>';
                $output['data'] .= '</tr>';
			}
		}

        if ($flSinDatos)
		{
			$output['data'] .= '<tr><td colspan="'.$colspan.'">Sin resultados</td></tr>';
		}

		return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Usuario para altas desde sync Anita (consola sin sesión o HTTP sin auth).
     */
    private function usuarioIdParaSincronizacion(): int
    {
        $id = Auth::id();
        if ($id) {
            return (int) $id;
        }

        return (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
    }

}
