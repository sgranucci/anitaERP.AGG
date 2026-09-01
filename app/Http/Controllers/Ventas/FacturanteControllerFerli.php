<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Ventas\FacturacionService;
use App\Services\Ventas\FacturanteService;
use Carbon\Carbon;

class FacturanteControllerFerli extends Controller
{
    private $facturacionService;
    private $facturanteService;

    public function __construct(FacturacionService $facturacionservice,
                                FacturanteService $facturanteservice)
    {
        $this->facturacionService = $facturacionservice;
        $this->facturanteService = $facturanteservice;
    }

    public function crearImportacion()
    {
        return view('ventas.facturante_ferli.crear');
    }

    public function listarComprobanteFull(Request $request)
    {
        $parameters = $request->all();

        $rules = [
            'desdefecha' => 'required',
            'hastafecha' => 'required',
        ];
        $messages = [
            'desdefecha.required' => 'Fecha desde es requerida.',
            'hastafecha.required' => 'Fecha hasta es requerida.',
        ];

        $medioPago_enum = [
            '1' => 'Mercado pago',
            '2' => 'Tienda nube',
            '3' => 'Go',
            '4' => 'Transferencia',
            '5' => 'Nube BOA',
            '6' => 'No transfiere',
        ];

        $desdefecha = $parameters['desdefecha'];
        $hastafecha = $parameters['hastafecha'];

        $validator = \Validator::make([
            'desdefecha' => $desdefecha,
            'hastafecha' => $hastafecha,
        ], $rules, $messages);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json($errors->all());
        }

        $retorno = $this->facturanteService->listadoComprobanteFull($parameters);

        if ($retorno instanceof \Exception) {
            return redirect()->route('crear_importacion_facturas_tiendanube')
                ->with('mensaje', 'Error al leer Facturante: '.$retorno->getMessage());
        }

        if ($retorno === null) {
            return redirect()->route('crear_importacion_facturas_tiendanube')
                ->with('mensaje', 'No hay comprobantes en Facturante para el periodo seleccionado.');
        }

        if (is_array($retorno)) {
            $datas = $retorno;
        } else {
            $datas = collect([$retorno]);
        }

        $totalFacturante = 0;
        $yaImportados = 0;
        $arraySalida = [];
        for ($i = 0; $i < count($datas); $i++) {
            if (!isset($datas[$i]->Prefijo)) {
                continue;
            }

            $totalFacturante++;

            if ($datas[$i]->Prefijo == 21 || $datas[$i]->Prefijo == 27) {
                $datas[$i]->mediopago = '1';
            } elseif ($datas[$i]->Prefijo == 23) {
                $datas[$i]->mediopago = '2';
            } elseif ($datas[$i]->Prefijo == 26) {
                $datas[$i]->mediopago = '5';
            } else {
                $datas[$i]->mediopago = '6';
            }

            $letra = substr($datas[$i]->TipoComprobante, -1);
            switch ($datas[$i]->TipoComprobante) {
                case 'FA':
                case 'FB':
                case 'FC':
                    $tipoComprobante = 'FAC';
                    break;
                case 'NCA':
                case 'NCB':
                case 'NCC':
                    $tipoComprobante = 'NCD';
                    break;
                case 'NDA':
                case 'NDB':
                case 'NDC':
                    $tipoComprobante = 'NDB';
                    break;
                default:
                    $tipoComprobante = substr($datas[$i]->TipoComprobante, 0, 3);
            }
            $venta = $this->facturanteService->leeComprobante(
                $tipoComprobante,
                $letra,
                $datas[$i]->Prefijo,
                $datas[$i]->Numero
            );
            if (isset($venta[0]->ven_nro) && $venta[0]->ven_nro == $datas[$i]->Numero) {
                $yaImportados++;
                continue;
            }

            $arraySalida[] = $datas[$i];
        }

        $datas = $arraySalida;
        $pendientes = count($datas);

        if ($pendientes > 0 && isset($datas[0]->TipoComprobante)) {
            return view('ventas.facturante_ferli.index', compact('datas', 'desdefecha', 'hastafecha', 'medioPago_enum'));
        }

        if ($totalFacturante == 0) {
            return redirect()->route('crear_importacion_facturas_tiendanube')
                ->with('mensaje', 'No hay comprobantes en Facturante para el periodo seleccionado.');
        }

        return redirect()->route('crear_importacion_facturas_tiendanube')
            ->with('mensaje', 'Los '.$yaImportados.' comprobante(s) del periodo ya estan importados en administracion.'
                .' Use Verificar importacion del periodo para revisar admin y stock Lugano.');
    }

    public function generarFacturasTiendaNube(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '2400');

        $datos = json_decode($request->datos, true) ?? [];

        if (count($datos) === 0) {
            return response()->json([
                'error' => 'No se recibieron facturas para importar.',
            ], 422);
        }

        $qFacturas = count($datos);
        $resumen = [
            'grabadas' => 0,
            'omitidas' => [],
            'conflictos' => [],
        ];

        for ($ii = 0; $ii < $qFacturas; $ii++) {
            if ($datos[$ii]['mediopago'] != '6' &&
                $datos[$ii]['numero'] >= 1 && $datos[$ii]['numero'] < 99999999) {
                $ret = $this->facturanteService->generaFactura(
                    $datos[$ii]['tipocomprobante'],
                    $datos[$ii]['prefijo'],
                    $datos[$ii]['numero'],
                    $datos[$ii]['condicionventa'],
                    $datos[$ii]['fechahora'],
                    $datos[$ii]['total'],
                    $datos[$ii]['totalneto'],
                    $datos[$ii]['iva1'],
                    $datos[$ii]['iva2'],
                    $datos[$ii]['subtotalnoalcanzado'],
                    $datos[$ii]['subtotalexcento'],
                    $datos[$ii]['totalpercepcioniibb'],
                    $datos[$ii]['item'],
                    $datos[$ii]['cae'],
                    $datos[$ii]['fechavencimientocae'],
                    $datos[$ii]['cliente'],
                    $datos[$ii]['mediopago']
                );

                if (!is_array($ret) || ($ret['error'] ?? '') != 'Success') {
                    return response()->json($ret ?? ['error' => 'Error al generar factura']);
                }

                $estado = $ret['estado'] ?? 'grabada';
                if ($estado === 'omitida') {
                    $resumen['omitidas'][] = $ret['comprobante'];
                    continue;
                }
                if ($estado === 'conflicto') {
                    $resumen['conflictos'][] = $ret['mensaje'];
                    continue;
                }

                $resumen['grabadas']++;

                $signo = 1;
                if (substr($datos[$ii]['tipocomprobante'], 0, 2) == 'NC') {
                    $signo = -1;
                }
                $total = $datos[$ii]['total'] * $signo;
                $this->facturanteService->generaPre($total, $datos[$ii]['mediopago']);
            }
        }

        $this->facturanteService->grabaPre(Carbon::now());

        $respuesta = [
            'error' => '',
            'mensaje' => $this->facturanteService->armaMensajeResumenFacturacion($resumen),
            'resumen' => $resumen,
        ];

        if ($request->filled('desdefecha') && $request->filled('hastafecha')) {
            $verificacion = $this->facturanteService->verificarPeriodoFacturante(
                $request->desdefecha,
                $request->hastafecha
            );
            if (!isset($verificacion['error'])) {
                $respuesta['verificacion'] = $verificacion['resumen']['mensaje'];
                $respuesta['todo_ok'] = $verificacion['resumen']['todo_ok'];
            }
        }

        return response()->json($respuesta);
    }

    public function recuperarStockLocal(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '2400');

        $rules = [
            'desdefecha' => 'required',
            'hastafecha' => 'required',
        ];
        $messages = [
            'desdefecha.required' => 'Fecha desde es requerida.',
            'hastafecha.required' => 'Fecha hasta es requerida.',
        ];

        $validator = \Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return redirect()->route('crear_importacion_facturas_tiendanube')
                ->withErrors($validator);
        }

        $dryRun = $request->has('dry_run');
        $resultado = $this->facturanteService->recuperarStockLocal(
            $request->desdefecha,
            $request->hastafecha,
            $dryRun
        );

        if (isset($resultado['error'])) {
            return redirect()->route('crear_importacion_facturas_tiendanube')
                ->with('mensaje', $resultado['error']);
        }

        $mensaje = $resultado['mensaje'];
        if (count($resultado['errores']) > 0) {
            $mensaje .= '. Errores: '.implode('; ', $resultado['errores']);
        }

        return redirect()->route('crear_importacion_facturas_tiendanube')
            ->with('mensaje', $mensaje);
    }

    public function verificarImportacionFacturante(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '2400');

        $rules = [
            'desdefecha' => 'required',
            'hastafecha' => 'required',
        ];
        $messages = [
            'desdefecha.required' => 'Fecha desde es requerida.',
            'hastafecha.required' => 'Fecha hasta es requerida.',
        ];

        $validator = \Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return redirect()->route('crear_importacion_facturas_tiendanube')
                ->withErrors($validator);
        }

        $medioPago_enum = [
            '1' => 'Mercado pago',
            '2' => 'Tienda nube',
            '3' => 'Go',
            '4' => 'Transferencia',
            '5' => 'Nube BOA',
            '6' => 'No transfiere',
        ];

        $resultado = $this->facturanteService->verificarPeriodoFacturante(
            $request->desdefecha,
            $request->hastafecha
        );

        if (isset($resultado['error'])) {
            return redirect()->route('crear_importacion_facturas_tiendanube')
                ->with('mensaje', $resultado['error']);
        }

        return view('ventas.facturante_ferli.verificar', [
            'desdefecha' => $resultado['desdefecha'],
            'hastafecha' => $resultado['hastafecha'],
            'resumen' => $resultado['resumen'],
            'detalle' => $resultado['detalle'],
            'medioPago_enum' => $medioPago_enum,
        ]);
    }
}
