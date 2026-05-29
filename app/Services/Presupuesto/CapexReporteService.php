<?php

namespace App\Services\Presupuesto;

use App\ApiAnita;
use App\Models\Presupuesto\Capex;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\OrdencompraService;
use Illuminate\Support\Collection;

class CapexReporteService
{
    /** Tipos de comprobante en aplicped que no son facturas (ej. COM = comprobante interno). */
    private const TIPOS_FACTURA_EXCLUIDOS = ['COM'];

    public function __construct(
        private readonly OrdencompraService $ordencompraService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @return array{filas: list<array<string, mixed>>, total: int}
     */
    public function generar(array $filtros): array
    {
        $capexs = $this->consultarCapex($filtros);
        $filas = [];

        foreach ($capexs as $capex) {
            $filas = array_merge($filas, $this->armarFilasCapex($capex));
        }

        return [
            'filas' => $filas,
            'total' => count($filas),
        ];
    }

    /**
     * @return Collection<int, Capex>
     */
    public function consultarCapex(array $filtros): Collection
    {
        $empresasAsignadas = $this->empresaRepository->traeEmpresasAsignadas();

        $query = Capex::query()
            ->select([
                'capex.*',
                'empresa.nombre as nombreempresa',
                'presupuesto.nombre as nombrepresupuesto',
                'presupuesto.anio as aniopresupuesto',
                'centrocosto.nombre as nombrecentrocosto',
            ])
            ->join('empresa', 'empresa.id', '=', 'capex.empresa_id')
            ->join('presupuesto', 'presupuesto.id', '=', 'capex.presupuesto_id')
            ->join('centrocosto', 'centrocosto.id', '=', 'capex.centrocosto_id')
            ->whereIn('capex.empresa_id', $empresasAsignadas)
            ->with([
                'capex_partidas.capex_partida_montos',
                'capex_partidas.monedas',
            ])
            ->orderBy('capex.id');

        if (! empty($filtros['empresa_id'])) {
            $query->where('capex.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['presupuesto_id'])) {
            $query->where('capex.presupuesto_id', (int) $filtros['presupuesto_id']);
        }
        if (! empty($filtros['centrocosto_id'])) {
            $query->where('capex.centrocosto_id', (int) $filtros['centrocosto_id']);
        }
        if (! empty($filtros['capex_id'])) {
            $query->where('capex.id', (int) $filtros['capex_id']);
        }

        return $query->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function armarFilasCapex(Capex $capex): array
    {
        $base = [
            'id' => $capex->id,
            'nombreempresa' => $capex->nombreempresa ?? '',
            'empresa' => $capex->nombreempresa ?? '',
            'presupuesto' => $capex->nombrepresupuesto ?? '',
            'centrocosto' => $capex->nombrecentrocosto ?? '',
            'nombre' => $capex->nombre ?? '',
            'detalle' => $capex->detalle ?? '',
            'codigoproyecto' => $capex->codigoproyecto ?? '',
            'anio' => $this->resolverAnio($capex),
            'nro_proyecto' => $capex->codigo ?? '',
            'estado' => $capex->estado ?? '',
            'partidas' => $this->formatearPartidas($capex),
        ];

        $ordenes = $this->agruparOrdenesCompra(
            $this->ordencompraService->leeOrdenCompraPorCodigo($capex->codigo)
        );

        if ($ordenes === []) {
            return [array_merge($base, [
                'mes' => '',
                'moneda' => '',
                'importe' => '',
                'oc' => '',
                'fc' => '',
                'pago' => '',
            ])];
        }

        $filas = [];

        foreach ($ordenes as $orden) {
            $facturas = $this->leeFacturasPorOrdenCompra((int) $orden['numero']);
            $ocTexto = $this->formatearComprobante(
                $orden['tipo'],
                $orden['letra'],
                $orden['sucursal'],
                $orden['numero']
            );

            if ($facturas === []) {
                $filas[] = array_merge($base, [
                    'mes' => $orden['mes'] ?? '',
                    'moneda' => $this->nombreMoneda($orden['moneda_id'] ?? null),
                    'importe' => $this->formatearImporte($orden['total'] ?? null),
                    'oc' => $ocTexto,
                    'fc' => '',
                    'pago' => '',
                ]);

                continue;
            }

            foreach ($facturas as $factura) {
                $fcTexto = $this->formatearComprobante(
                    $factura['tipo'],
                    $factura['letra'],
                    $factura['sucursal'],
                    $factura['numero']
                );
                $pagos = $this->leePagosPorFactura(
                    $factura['proveedor'],
                    $factura['tipo'],
                    $factura['letra'],
                    $factura['sucursal'],
                    $factura['numero']
                );

                if ($pagos === []) {
                    $filas[] = array_merge($base, [
                        'mes' => $orden['mes'] ?? '',
                        'moneda' => $this->nombreMoneda($orden['moneda_id'] ?? null),
                        'importe' => $this->formatearImporte($orden['total'] ?? null),
                        'oc' => $ocTexto,
                        'fc' => $fcTexto,
                        'pago' => '',
                    ]);

                    continue;
                }

                foreach ($pagos as $pago) {
                    $filas[] = array_merge($base, [
                        'mes' => $orden['mes'] ?? '',
                        'moneda' => $this->nombreMoneda($orden['moneda_id'] ?? null),
                        'importe' => $this->formatearImporte($orden['total'] ?? null),
                        'oc' => $ocTexto,
                        'fc' => $fcTexto,
                        'pago' => $this->formatearPago($pago),
                    ]);
                }
            }
        }

        return $filas;
    }

    /**
     * @param  mixed  $raw
     * @return list<array{tipo:string, letra:string, sucursal:int, numero:int, mes:string, moneda_id:string, total:float, proveedor:string}>
     */
    private function agruparOrdenesCompra($raw): array
    {
        if (! is_array($raw) && ! ($raw instanceof \Traversable)) {
            return [];
        }

        $ordenes = [];

        foreach ($raw as $row) {
            $tipo = trim((string) ($row->movp_tipo ?? 'PEP'));
            $letra = 'X';
            $sucursal = 0;
            $numero = (int) ($row->movp_nro ?? 0);

            if ($numero <= 0) {
                continue;
            }

            $clave = $tipo.'|'.$letra.'|'.$sucursal.'|'.$numero;

            if (isset($ordenes[$clave])) {
                continue;
            }

            $ordenes[$clave] = [
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => $sucursal,
                'numero' => $numero,
                'mes' => (string) ($row->mes ?? ''),
                'moneda_id' => (string) ($row->moneda_id ?? ''),
                'total' => (float) ($row->total ?? 0),
            ];
        }

        return array_values($ordenes);
    }

    /**
     * @return list<array{proveedor:string, tipo:string, letra:string, sucursal:int, numero:int}>
     */
    public function leeFacturasPorOrdenCompra(int $numeroOc, string $tipoRef = 'PEP', string $letraRef = 'X', int $sucursalRef = 0): array
    {
        if ($numeroOc <= 0) {
            return [];
        }

        $apiAnita = new ApiAnita();
        $leeAnita = [
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'aplicped',
            'campos' => '
                aplp_proveedor,
                aplp_tipo,
                aplp_letra,
                aplp_sucursal,
                aplp_nro
            ',
            'whereArmado' => " WHERE
                aplp_ref_tipo='".$tipoRef."' and
                aplp_ref_letra='".$letraRef."' and
                aplp_ref_sucursal=".$sucursalRef." and
                aplp_ref_nro=".$numeroOc." and
                aplp_tipo<>'COM'",
        ];

        $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

        if (! is_array($dataAnita)) {
            return [];
        }

        $facturas = [];

        foreach ($dataAnita as $row) {
            $proveedor = trim((string) ($row->aplp_proveedor ?? ''));
            $tipo = trim((string) ($row->aplp_tipo ?? ''));
            $letra = trim((string) ($row->aplp_letra ?? ''));
            $sucursal = (int) ($row->aplp_sucursal ?? 0);
            $numero = (int) ($row->aplp_nro ?? 0);

            if ($proveedor === '' || $tipo === '' || $numero <= 0 || $this->esTipoFacturaExcluido($tipo)) {
                continue;
            }

            $clave = $proveedor.'|'.$tipo.'|'.$letra.'|'.$sucursal.'|'.$numero;
            $facturas[$clave] = [
                'proveedor' => $proveedor,
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => $sucursal,
                'numero' => $numero,
            ];
        }

        return array_values($facturas);
    }

    /**
     * @return list<object>
     */
    public function leePagosPorFactura(string $proveedor, string $tipo, string $letra, int $sucursal, int $numero): array
    {
        $apiAnita = new ApiAnita();
        $leeAnita = [
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'aplmovp',
            'campos' => '
                aplvp_fecha,
                aplvp_monto,
                aplvp_tipo_cob,
                aplvp_letra_cob,
                aplvp_sucursal_cob,
                aplvp_nro_cob
            ',
            'whereArmado' => " WHERE
                aplvp_proveedor='".str_pad(trim($proveedor), 6, '0', STR_PAD_LEFT)."' and
                aplvp_tipo='".trim($tipo)."' and
                aplvp_letra='".trim($letra)."' and
                aplvp_sucursal=".(int) $sucursal." and
                aplvp_nro=".(int) $numero,
        ];

        $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

        return is_array($dataAnita) ? $dataAnita : [];
    }

    private function formatearPartidas(Capex $capex): string
    {
        $lineas = [];

        foreach ($capex->capex_partidas as $partida) {
            $montoTotal = $partida->capex_partida_montos->sum('monto');
            $moneda = $partida->monedas->abreviatura ?? '';
            $lineas[] = 'Nro.'.$partida->codigo.' '.$partida->nombre.' '.$moneda.' '.number_format((float) $montoTotal, 2, '.', ',');
        }

        return implode("\n", $lineas);
    }

    private function resolverAnio(Capex $capex): string
    {
        $codigoProyecto = (string) ($capex->codigoproyecto ?? '');

        if (str_contains($codigoProyecto, '/')) {
            return trim(substr($codigoProyecto, strrpos($codigoProyecto, '/') + 1));
        }

        if (! empty($capex->aniopresupuesto)) {
            return (string) $capex->aniopresupuesto;
        }

        return '';
    }

    private function formatearComprobante(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return trim($tipo).' '.trim($letra).' '.$sucursal.'-'.$numero;
    }

    private function formatearFechaAnita($fecha): string
    {
        $fecha = preg_replace('/\D/', '', (string) $fecha);

        if (strlen($fecha) < 8) {
            return (string) $fecha;
        }

        return substr($fecha, 6, 2).'/'.substr($fecha, 4, 2).'/'.substr($fecha, 0, 4);
    }

    private function formatearPago(object $pago): string
    {
        $fecha = $this->formatearFechaAnita($pago->aplvp_fecha ?? '');
        $monto = number_format((float) ($pago->aplvp_monto ?? 0), 2, '.', ',');
        $ordenPago = $this->formatearComprobante(
            (string) ($pago->aplvp_tipo_cob ?? ''),
            (string) ($pago->aplvp_letra_cob ?? ''),
            (int) ($pago->aplvp_sucursal_cob ?? 0),
            (int) ($pago->aplvp_nro_cob ?? 0)
        );

        return $fecha.' '.$monto.' OP '.$ordenPago;
    }

    private function nombreMoneda($monedaId): string
    {
        return match ((string) $monedaId) {
            '1' => 'PESOS',
            '2' => 'DOLARES',
            '3' => 'EUROS',
            default => '',
        };
    }

    private function formatearImporte($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return number_format((float) $valor, 2, '.', ',');
    }

    private function esTipoFacturaExcluido(string $tipo): bool
    {
        return in_array(strtoupper(trim($tipo)), self::TIPOS_FACTURA_EXCLUIDOS, true);
    }
}
