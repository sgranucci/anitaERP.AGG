<?php

namespace App\Services\Caja;

use App\Models\Caja\Cheque;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Concepto_Venta;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionService;
use App\Support\Caja\ChequeNdConfigSupport;
use App\Support\Caja\ChequeTerceroRechazoAnitaSupport;
use Exception;
use InvalidArgumentException;

final class ChequeRechazadoNotaDebitoService
{
    public function __construct(
        private readonly FacturacionService $facturacionService,
    ) {}

    public function esElegible(Cheque $cheque): bool
    {
        if (! ChequeNdConfigSupport::habilitado()) {
            return false;
        }
        if ((string) ($cheque->origen ?? '') !== 'R') {
            return false;
        }
        $estado = (string) ($cheque->estado ?? ' ');
        if (in_array($estado, ['R', 'A'], true)) {
            return false;
        }
        if ((int) ($cheque->venta_nd_id ?? 0) > 0) {
            return false;
        }
        if ((int) ($cheque->cliente_id ?? 0) <= 0) {
            return false;
        }
        if ((int) ($cheque->empresa_id ?? 0) <= 0) {
            return false;
        }
        if ((float) ($cheque->monto ?? 0) <= 0.) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function datosParaModal(Cheque $cheque): array
    {
        if (! $this->esElegible($cheque)) {
            throw new InvalidArgumentException('El cheque no es elegible para rechazo con nota de débito.');
        }

        $cheque->loadMissing(['bancos', 'clientes.condicionivas', 'monedas', 'empresas']);
        $empresaId = (int) $cheque->empresa_id;
        $cliente = $cheque->clientes;
        $letra = ChequeNdConfigSupport::letraDesdeCliente($cliente);
        $pv = ChequeNdConfigSupport::puntoventaResumen($empresaId);
        $tipoNdId = ChequeNdConfigSupport::tipotransaccionNotaDebitoId($letra);
        $refCheque = $this->referenciaCheque($cheque);

        $lineas = [];
        $configError = null;
        try {
            $conceptoId = ChequeNdConfigSupport::conceptoIdParaChequeRechazado();
            $concepto = Concepto_Venta::query()->find($conceptoId);
            $impuestoId = (int) ($concepto->impuesto_id ?? ChequeNdConfigSupport::impuestoIdDefault());
            $lineas[] = [
                'concepto_venta_id' => $conceptoId,
                'codigo' => (string) ($concepto->codigo ?? ''),
                'descripcion' => 'Cheque rechazado — '.$refCheque,
                'cantidad' => 1.,
                'precio' => round((float) $cheque->monto, 2),
                'impuesto_id' => $impuestoId,
                'incluyeimpuesto' => '1',
            ];

            $gastosId = ChequeNdConfigSupport::conceptoIdParaGastosBancarios();
            $gastos = $gastosId ? Concepto_Venta::query()->find($gastosId) : null;
            if ($gastosId && $gastos) {
                $lineas[] = [
                    'concepto_venta_id' => $gastosId,
                    'codigo' => (string) ($gastos->codigo ?? ''),
                    'descripcion' => 'Gastos bancarios por cheque rechazado — '.$refCheque,
                    'cantidad' => 1.,
                    'precio' => 0.,
                    'impuesto_id' => (int) ($gastos->impuesto_id ?? $impuestoId),
                    'incluyeimpuesto' => '1',
                ];
            }
        } catch (InvalidArgumentException $e) {
            $configError = $e->getMessage();
        }

        return [
            'cheque' => [
                'id' => (int) $cheque->id,
                'numerocheque' => (string) ($cheque->numerocheque ?? ''),
                'nro_interno_anita' => $cheque->nro_interno_anita,
                'monto' => round((float) $cheque->monto, 2),
                'moneda_id' => (int) ($cheque->moneda_id ?? 1),
                'moneda' => (string) ($cheque->monedas->abreviatura ?? ''),
                'empresa_id' => $empresaId,
                'empresa' => (string) ($cheque->empresas->nombre ?? ''),
                'cliente_id' => (int) $cheque->cliente_id,
                'cliente' => (string) ($cliente->nombre ?? ''),
                'banco' => (string) ($cheque->bancos->nombre ?? ''),
                'fechapago' => (string) ($cheque->fechapago ?? ''),
                'letra' => $letra,
            ],
            'puntoventa' => $pv,
            'tipotransaccion_id' => $tipoNdId,
            'lineas' => $lineas,
            'fecha' => date('Y-m-d'),
            'leyenda_sugerida' => 'ND por cheque rechazado — '.$refCheque,
            'config_error' => $configError,
        ];
    }

    /**
     * @param  list<array{
     *   concepto_venta_id:int,
     *   cantidad?:float,
     *   precio:float,
     *   descripcion?:string,
     *   impuesto_id?:int,
     *   incluyeimpuesto?:string
     * }>  $lineas
     * @return array{
     *   venta_nd_id:int,
     *   codigo_nd:string,
     *   cheque_id:int,
     *   importe:float,
     *   anita_ok:bool
     * }
     */
    public function emitirNotaDebitoChequeRechazado(
        int $chequeId,
        array $lineas,
        ?string $fecha = null,
        ?string $leyendaUsuario = null,
        ?string $motivoRechazo = null,
    ): array {
        if (! ChequeNdConfigSupport::habilitado()) {
            throw new InvalidArgumentException('La emisión de ND por cheque rechazado está deshabilitada.');
        }

        $cheque = Cheque::query()
            ->with(['bancos', 'clientes.condicionivas', 'monedas'])
            ->find($chequeId);

        if (! $cheque) {
            throw new InvalidArgumentException('No se encontró el cheque id '.$chequeId.'.');
        }

        if (! $this->esElegible($cheque)) {
            throw new InvalidArgumentException('El cheque no es elegible para rechazo con nota de débito.');
        }

        $lineasNormalizadas = $this->normalizarLineas($lineas);
        if ($lineasNormalizadas === []) {
            throw new InvalidArgumentException('Debe indicar al menos una línea con concepto e importe mayor a cero.');
        }

        $cliente = $cheque->clientes;
        if (! $cliente instanceof Cliente) {
            $cliente = Cliente::query()->with('condicionivas')->find((int) $cheque->cliente_id);
        }
        if (! $cliente) {
            throw new InvalidArgumentException('El cheque no tiene cliente asociado.');
        }

        $letra = ChequeNdConfigSupport::letraDesdeCliente($cliente);
        $puntoventaId = ChequeNdConfigSupport::puntoventaIdParaEmpresa((int) $cheque->empresa_id);
        $tipoNdId = ChequeNdConfigSupport::tipotransaccionNotaDebitoId($letra);
        $fechaNd = $fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d');

        $refCheque = $this->referenciaCheque($cheque);
        $leyendaManual = trim((string) $leyendaUsuario);
        $leyendaNd = $leyendaManual !== '' ? $leyendaManual : ('ND por cheque rechazado — '.$refCheque);
        if (mb_strlen($leyendaNd) > 255) {
            $leyendaNd = mb_substr($leyendaNd, 0, 255);
        }

        $payload = [
            'tipotransaccion_id' => $tipoNdId,
            'puntoventa_id' => $puntoventaId,
            'fechafactura' => $fechaNd,
            'leyendafactura' => $leyendaNd,
            'actividad_arca_id' => 1,
            'cliente_id' => (int) $cheque->cliente_id,
            'moneda_id' => (int) ($cheque->moneda_id ?? 1),
            'listaprecio_id' => 1,
            'descuentolinea' => 0.,
            'descuentopie' => 0.,
            'descuentoimportepie' => 0.,
            'articulo_ids' => array_fill(0, count($lineasNormalizadas), 0),
            'concepto_venta_ids' => array_column($lineasNormalizadas, 'concepto_venta_id'),
            'concepto_venta_id' => (int) $lineasNormalizadas[0]['concepto_venta_id'],
            'cantidades' => array_column($lineasNormalizadas, 'cantidad'),
            'precios' => array_column($lineasNormalizadas, 'precio'),
            'descripcionarticulos' => array_column($lineasNormalizadas, 'descripcion'),
            'impuesto_ids' => array_column($lineasNormalizadas, 'impuesto_id'),
            'incluyeimpuestos' => array_column($lineasNormalizadas, 'incluyeimpuesto'),
        ];

        $resultado = $this->facturacionService->generaComprobanteGeneral($payload);

        if (! empty($resultado['error'])) {
            throw new Exception(is_string($resultado['error']) ? $resultado['error'] : 'Error al emitir nota de débito en ARCA.');
        }

        $ventaNdId = (int) ($resultado['venta_id'] ?? 0);
        if ($ventaNdId <= 0) {
            throw new Exception('No se pudo recuperar la nota de débito generada.');
        }

        $ventaNd = Venta::query()->find($ventaNdId);
        $codigoNd = (string) ($ventaNd->codigo ?? $resultado['factura'] ?? '');
        $importe = round(array_sum(array_map(
            static fn (array $l) => (float) $l['cantidad'] * (float) $l['precio'],
            $lineasNormalizadas
        )), 2);

        $motivo = trim((string) $motivoRechazo);
        if ($motivo === '') {
            $motivo = null;
        } elseif (mb_strlen($motivo) > 255) {
            $motivo = mb_substr($motivo, 0, 255);
        }

        $cheque->estado = 'R';
        $cheque->venta_nd_id = $ventaNdId;
        $cheque->fecha_rechazo = $fechaNd;
        $cheque->motivo_rechazo = $motivo;
        $cheque->save();

        $anitaOk = ChequeTerceroRechazoAnitaSupport::marcarRechazo($cheque, $fechaNd);

        return [
            'venta_nd_id' => $ventaNdId,
            'codigo_nd' => $codigoNd,
            'cheque_id' => (int) $cheque->id,
            'importe' => $importe,
            'anita_ok' => $anitaOk,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array{concepto_venta_id:int,cantidad:float,precio:float,descripcion:string,impuesto_id:int,incluyeimpuesto:string}>
     */
    private function normalizarLineas(array $lineas): array
    {
        $out = [];
        $impuestoDefault = ChequeNdConfigSupport::impuestoIdDefault();

        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $conceptoId = (int) ($linea['concepto_venta_id'] ?? 0);
            $cantidad = round((float) ($linea['cantidad'] ?? 1), 4);
            $precio = round((float) ($linea['precio'] ?? 0), 2);
            if ($conceptoId <= 0 || $cantidad <= 0. || $precio <= 0.) {
                continue;
            }

            $concepto = Concepto_Venta::query()->whereKey($conceptoId)->where('activo', true)->first();
            if (! $concepto) {
                throw new InvalidArgumentException('El concepto de venta id '.$conceptoId.' no existe o no está activo.');
            }

            $incluye = (string) ($linea['incluyeimpuesto'] ?? '1');
            $incluye = in_array($incluye, ['S', '1', 'Y'], true) ? '1' : 'N';
            $descripcion = trim((string) ($linea['descripcion'] ?? ''));
            if ($descripcion === '') {
                $descripcion = (string) ($concepto->nombre ?? 'Cheque rechazado');
            }
            if (mb_strlen($descripcion) > 255) {
                $descripcion = mb_substr($descripcion, 0, 255);
            }

            $out[] = [
                'concepto_venta_id' => $conceptoId,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'descripcion' => $descripcion,
                'impuesto_id' => (int) ($linea['impuesto_id'] ?? $concepto->impuesto_id ?? $impuestoDefault),
                'incluyeimpuesto' => $incluye,
            ];
        }

        return $out;
    }

    private function referenciaCheque(Cheque $cheque): string
    {
        $nro = trim((string) ($cheque->numerocheque ?? ''));
        $banco = trim((string) ($cheque->bancos->nombre ?? ''));
        $partes = [];
        if ($nro !== '') {
            $partes[] = 'N° '.$nro;
        }
        if ($banco !== '') {
            $partes[] = $banco;
        }
        if ($cheque->nro_interno_anita) {
            $partes[] = 'int. '.$cheque->nro_interno_anita;
        }

        return $partes !== [] ? implode(' / ', $partes) : 'id '.$cheque->id;
    }
}
