<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Tipotransaccion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturaListadoFiltros;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use Auth;
use App\ApiAnita;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class VentaRepository implements VentaRepositoryInterface
{
    protected $model;
    protected $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Venta $venta,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->model = $venta;
        $this->empresaRepository = $empresarepository;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function leeSinPaginar($filtros)
    {
        return $this->construyeQueryListado($filtros)->get();
    }

    public function leePaginando($filtros)
    {
        return $this->construyeQueryListado($filtros)->paginate(12);
    }

    /**
     * Query base del listado de comprobantes de venta.
     *
     * Restringe siempre a las empresas asignadas al usuario (vía punto de venta)
     * y aplica los filtros inteligentes de texto, empresa y rango de fechas.
     *
     * @param  array<string, mixed>|string|null  $filtros  Array de filtros o búsqueda legacy (string).
     * @return Builder<Venta>
     */
    private function construyeQueryListado($filtros): Builder
    {
        // Compatibilidad con llamadas legacy que pasaban una cadena de búsqueda.
        if (! is_array($filtros)) {
            $legacy = FacturaListadoFiltros::filtrosVacios();
            $legacy['valor'] = trim((string) $filtros);
            $legacy['busqueda'] = $legacy['valor'];
            // Las exportaciones legacy no acotan por rango de fechas.
            $legacy['fecha_desde'] = '';
            $legacy['fecha_hasta'] = '';
            $filtros = $legacy;
        }

        $query = $this->model->newQuery()
            ->with(['puntoventas.empresas', 'clientes.condicionivas', 'tipotransacciones']);

        // Restricción por empresas asignadas al usuario (acceso a los comprobantes).
        $empresas = $this->empresaRepository->traeEmpresasAsignadas();
        if (count($empresas) >= 1) {
            $query->whereHas('puntoventas', static function ($q) use ($empresas): void {
                $q->whereIn('empresa_id', $empresas);
            });
        }

        FacturaListadoFiltros::aplicar($query, $filtros);

        return $query->orderBy('venta.id', 'desc');
    }

    public function create(array $data)
    {
        $data = $this->aplicarCodigoAfipElBierzo($data);

        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $data = $this->aplicarCodigoAfipElBierzo($data, (int) $id);

        return $this->model->findOrFail($id)->update($data);
    }

    /**
     * El Bierzo PV manual/CAEA: tipo ARCA efectivo (001+letra, 003 NC, 201 FCE…) para unique y max()+1.
     * AGG no tiene la columna ni el unique.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function aplicarCodigoAfipElBierzo(array $data, ?int $ventaId = null): array
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            unset($data['codigo_afip']);

            return $data;
        }

        if (! Schema::hasColumn('venta', 'codigo_afip')) {
            unset($data['codigo_afip']);

            return $data;
        }

        if ((int) ($data['codigo_afip'] ?? 0) > 0) {
            return $data;
        }

        $tipoId = (int) ($data['tipotransaccion_id'] ?? 0);
        $codigoVenta = (string) ($data['codigo'] ?? '');
        if ($tipoId <= 0 || $codigoVenta === '') {
            $existente = $ventaId !== null && $ventaId > 0
                ? $this->model->query()->whereKey($ventaId)->first(['tipotransaccion_id', 'codigo'])
                : null;
            $tipoId = $tipoId > 0 ? $tipoId : (int) ($existente->tipotransaccion_id ?? 0);
            $codigoVenta = $codigoVenta !== '' ? $codigoVenta : (string) ($existente->codigo ?? '');
        }

        $codigoAlmacenado = $tipoId > 0
            ? (string) (Tipotransaccion::query()->whereKey($tipoId)->value('codigo') ?? '')
            : '';
        $afip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada($codigoAlmacenado, $codigoVenta);
        $data['codigo_afip'] = $afip > 0 ? $afip : null;

        return $data;
    }

    public function delete($id)
    {
    	return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $venta = $this->model
                                ->with('venta_impuestos')
                                ->with('venta_emisiones')
                                ->with('venta_exportaciones')
                                ->with('cliente_cuentacorrientes')
                                ->with('clientes')        
                                ->with('tipotransacciones')
                                ->with('puntoventas')
                                ->with('ordenventas')
                                ->with('asientos')
                                ->with('pedidos')
                                ->with('cobranzas')
                                ->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $venta;
    }

    public function findOrFail($id)
    {
        if (null == $venta = $this->model
                                ->with('venta_impuestos')
                                ->with('venta_emisiones')
                                ->with('venta_exportaciones')
                                ->with('cliente_cuentacorrientes')
                                ->with('clientes')    
                                ->with('tipotransacciones')
                                ->with('puntoventas')
                                ->with('ordenventas')                                    
                                ->with('asientos')
                                ->with('pedidos')
                                ->with('cobranzas')
                                ->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $venta;
    }

    public function traeUltimoNumeroRemito($tipo, $letra, $sucursal)
    {
        // El Bierzo numera como a-remito/a-comprob: compemis resuelve la clave
        // y numerador contiene el ultimo numero reservado. No usar MAX(pendmae):
        // el remito puede originarse al facturar sin existir aun en pendmae.
        if (EntornoEmpresaSupport::esElBierzo()) {
            try {
                return $this->leerUltimoNumeradorCompemisEstricto(
                    (string) $tipo,
                    (string) $letra,
                    (string) (int) $sucursal,
                ) + 1;
            } catch (\Throwable $e) {
                Log::error('ventas.remito.numerador_anita_no_disponible', [
                    'tipo' => $tipo,
                    'letra' => $letra,
                    'sucursal' => $sucursal,
                    'error' => $e->getMessage(),
                ]);

                return 'error';
            }
        }

        // Lee numerador desde anita
		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'pendmae', 
            'sistema' => 'ventas',
            'campos' => '
                max(penm_nro) as ultnro
			' , 
            'whereArmado' => " WHERE penm_tipo='".$tipo."' and penm_letra='".$letra."' 
                                    and penm_sucursal='".$sucursal."' " 
        );
        $fila = ApiAnita::primeraFilaLista($apiAnita->apiCall($data));

        if ($fila !== null && isset($fila->ultnro)) {
            return (int) $fila->ultnro + 1;
        }

        return 'error';
    }

    public function numeraAnita($tipo, $letra, $sucursal, $path_sistema = null)
    {
        // Lee numerador desde anita
		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'compemis', 
            'campos' => '
                compe_numero
			' , 
            'whereArmado' => " WHERE compe_tipo='".$tipo."' and compe_letra='".$letra."' 
                                    and ".$this->sqlSucursalAnita('compe_sucursal', (string) $sucursal) 
        );
        if (isset($path_sistema))
            $data['path_sistema'] = $path_sistema;
        $rawCompe = $apiAnita->apiCallEscritura($data);
        $errCompe = ApiAnita::extraerMensajeError($rawCompe);
        if ($errCompe !== null) {
            return 'Error al leer compemis: '.$errCompe;
        }

        $filaCompe = ApiAnita::primeraFilaLista((string) $rawCompe);
        if ($filaCompe === null || ! isset($filaCompe->compe_numero)) {
            return 0;
        }

        $claveNumero = $filaCompe->compe_numero;

        $apiAnita = new ApiAnita();
        $data = array(
            'acc' => 'list',
            'tabla' => 'numerador',
            'campos' => '
                num_ult_numero
            ',
            'whereArmado' => " WHERE num_clave='".$claveNumero."' ",
        );
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }

        $rawNumerador = $apiAnita->apiCallEscritura($data);
        $errNumerador = ApiAnita::extraerMensajeError($rawNumerador);
        if ($errNumerador !== null) {
            return 'Error al leer numerador: '.$errNumerador;
        }

        $filaNumerador = ApiAnita::primeraFilaLista((string) $rawNumerador);
        if ($filaNumerador === null || ! isset($filaNumerador->num_ult_numero)) {
            return 'Error al actualizar numerador';
        }

        $numero = (int) $filaNumerador->num_ult_numero + 1;

        $apiAnita = new ApiAnita();
        $data = array(
            'acc' => 'update',
            'tabla' => 'numerador',
            'valores' => "num_ult_numero = '".$numero."' ",
            'whereArmado' => " WHERE num_clave = '".$claveNumero."' ",
        );
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }
        $numerador = $apiAnita->apiCallEscritura($data);

        if (ApiAnita::extraerMensajeError($numerador) !== null) {
            return 'Error al actualizar numerador';
        }

        return $numero;
    }

    /**
     * Último número en Anita (MAX venta + numerador compemis), sin incrementar.
     * Misma fuente que AGG usaba con Informix vivo: tipo + letra + sucursal.
     */
    public function maxNumeroComprobanteAnitaBridge(
        string $tipo,
        string $letra,
        string $sucursal,
        $path_sistema = null,
    ): int {
        $tipo = strtoupper(trim($tipo));
        $letra = strtoupper(trim($letra));
        $sucursal = trim($sucursal);
        if ($tipo === '' || $letra === '' || $sucursal === '') {
            return 0;
        }

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => 'max(ven_nro) as ultimonumero',
            'whereArmado' => " WHERE ven_tipo = '".$tipo."' AND
									ven_letra = '".$letra."' AND
									".$this->sqlSucursalAnita('ven_sucursal', $sucursal),
        ];
        if ($path_sistema !== null && $path_sistema !== '') {
            $data['path_sistema'] = $path_sistema;
        }
        $filaUltimo = ApiAnita::primeraFilaLista($apiAnita->apiCall($data));
        $maxVenta = 0;
        if ($filaUltimo !== null && isset($filaUltimo->ultimonumero)) {
            $maxVenta = (int) $filaUltimo->ultimonumero;
        }

        $maxNumerador = $this->leerUltimoNumeradorCompemis($tipo, $letra, $sucursal, $path_sistema);

        return max($maxVenta, $maxNumerador);
    }

    public function traeUltimoComprobanteVenta($tipotransaccion_id, $puntoventa_id, ?int $empresa_id = null)
    {
        $query = $this->model->select('venta.numerocomprobante')
            ->where('venta.tipotransaccion_id', $tipotransaccion_id)
            ->where('venta.puntoventa_id', $puntoventa_id);

        if ($empresa_id !== null && $empresa_id > 0) {
            $query->whereHas('puntoventas', static function ($q) use ($empresa_id): void {
                $q->where('empresa_id', $empresa_id);
            });
        }

        return $query->orderBy('venta.numerocomprobante', 'desc')->first();
    }

    /**
     * Último número reservado en compemis/numerador Anita (sin incrementar).
     */
    public function leerUltimoNumeradorCompemis(string $tipo, string $letra, string $sucursal, $path_sistema = null): int
    {
        try {
            return $this->leerUltimoNumeradorCompemisEstricto($tipo, $letra, $sucursal, $path_sistema);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function leerUltimoNumeradorCompemisEstricto(
        string $tipo,
        string $letra,
        string $sucursal,
        $path_sistema = null
    ): int {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'compemis',
            'campos' => 'compe_numero',
            'whereArmado' => " WHERE compe_tipo='".$tipo."' and compe_letra='".$letra."'
                                    and ".$this->sqlSucursalAnita('compe_sucursal', $sucursal),
        ];
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }
        $rawCompe = $apiAnita->apiCallEscritura($data);
        $errCompe = ApiAnita::extraerMensajeError($rawCompe);
        if ($errCompe !== null) {
            throw new RuntimeException('Error al leer compemis: '.$errCompe);
        }

        $filaCompe = ApiAnita::primeraFilaLista((string) $rawCompe);
        if ($filaCompe === null || ! isset($filaCompe->compe_numero)) {
            throw new RuntimeException(
                "No existe compemis para {$tipo} {$letra} sucursal {$sucursal}."
            );
        }

        $claveNumero = (int) $filaCompe->compe_numero;
        if ($claveNumero <= 0) {
            throw new RuntimeException(
                "compemis no tiene una clave de numerador valida para {$tipo} {$letra} sucursal {$sucursal}."
            );
        }

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => " WHERE num_clave='".$claveNumero."' ",
        ];
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }

        $rawNumerador = $apiAnita->apiCallEscritura($data);
        $errNumerador = ApiAnita::extraerMensajeError($rawNumerador);
        if ($errNumerador !== null) {
            throw new RuntimeException('Error al leer numerador: '.$errNumerador);
        }

        $filaNumerador = ApiAnita::primeraFilaLista((string) $rawNumerador);
        if ($filaNumerador === null || ! isset($filaNumerador->num_ult_numero)) {
            throw new RuntimeException("No existe numerador Anita con clave {$claveNumero}.");
        }

        return max(0, (int) $filaNumerador->num_ult_numero);
    }

    /**
     * Anita guarda sucursal como 15 o 00015 según la tabla. El ERP usa siempre 5 dígitos.
     */
    private function sqlSucursalAnita(string $campo, string $sucursal): string
    {
        $sucursal = trim($sucursal);
        $variantes = [$sucursal];
        if ($sucursal !== '' && ctype_digit($sucursal)) {
            $sinCeros = ltrim($sucursal, '0');
            if ($sinCeros === '') {
                $sinCeros = '0';
            }
            $variantes[] = $sinCeros;
            $variantes[] = str_pad($sinCeros, 5, '0', STR_PAD_LEFT);
        }
        $variantes = array_values(array_unique(array_filter(
            $variantes,
            static fn ($v) => $v !== ''
        )));
        if ($variantes === []) {
            return $campo." = ''";
        }
        $in = implode(', ', array_map(
            static fn (string $v): string => "'".str_replace("'", "''", $v)."'",
            $variantes
        ));

        return $campo.' IN ('.$in.')';
    }

    public function leeComprobantePorOrdenVenta($ordenventa_id)
    {
        return $this->model->select('venta.id as id', 
                                    'venta.codigo as codigo', 
                                    'venta.fecha as fecha', 
                                    'cliente_cuentacorriente.fechavencimiento as fechavencimiento',
                                    'moneda.abreviatura as moneda', 
                                    'venta.total as total')
                                ->leftjoin('cliente_cuentacorriente', 'cliente_cuentacorriente.venta_id', '=', 'venta.id')
                                ->addSelect([
                                    'aplicado' => Cliente_Cuentacorriente_Aplicacion::query()
                                        ->selectRaw('SUM(total)')
                                        ->whereColumn('cliente_cuentacorriente_id', 'cliente_cuentacorriente.id')
                                ])                                    
                                ->join('moneda', 'moneda.id', 'venta.moneda_id')
                                ->with('cliente_cuentacorrientes')
                                ->where('ordenventa_id', $ordenventa_id)
                                ->where('cliente_cuentacorriente.cobranza_id', null)
                                ->orderBy('venta.fecha')->get();
    }
}
