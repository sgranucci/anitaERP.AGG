<?php

namespace App\Services\Caja;

use App\Models\Caja\Cheque;
use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Impuesto;
use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Concepto_Venta;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturaMailEnvioService;
use App\Services\Ventas\FacturacionService;
use App\Support\Caja\ChequeNdConfigSupport;
use App\Support\Caja\ChequeTerceroRechazoAnitaSupport;
use App\Support\Ventas\ConceptoVentaMostradorSupport;
use Exception;
use InvalidArgumentException;

final class ChequeRechazadoNotaDebitoService
{
    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly ChequeRechazadoDeudaProveedorService $deudaProveedorService,
        private readonly FacturaMailEnvioService $facturaMailEnvioService,
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

        $cheque->loadMissing(['bancos', 'clientes.condicionivas', 'monedas', 'empresas', 'proveedores']);
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
                'rol' => 'cheque',
                'concepto_venta_id' => $conceptoId,
                'codigo' => (string) ($concepto->codigo ?? ''),
                'descripcion' => 'Cheque rechazado — '.$refCheque,
                'cantidad' => 1.,
                'precio' => round((float) $cheque->monto, 2),
                'impuesto_id' => null,
                'tratamiento_fiscal' => 'nogravado',
            ];

            $gastosId = ChequeNdConfigSupport::conceptoIdParaGastosBancarios();
            $gastos = $gastosId ? Concepto_Venta::query()->find($gastosId) : null;
            if ($gastosId && $gastos) {
                $lineas[] = [
                    'rol' => 'gasto',
                    'concepto_venta_id' => $gastosId,
                    'codigo' => (string) ($gastos->codigo ?? ''),
                    'descripcion' => 'Gastos bancarios por cheque rechazado — '.$refCheque,
                    'cantidad' => 1.,
                    'precio' => 0.,
                    'impuesto_id' => (int) ($gastos->impuesto_id ?? $impuestoId),
                    'tratamiento_fiscal' => 'gravado',
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
                'proveedor_id' => (int) ($cheque->proveedor_id ?? 0),
                'proveedor' => (string) ($cheque->proveedores->nombre ?? ''),
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
            'impuestos' => Impuesto::query()->orderBy('valor')->orderBy('id')->get(['id', 'nombre', 'valor'])
                ->map(static fn (Impuesto $imp) => [
                    'id' => (int) $imp->id,
                    'nombre' => (string) $imp->nombre,
                    'valor' => (float) $imp->valor,
                ])->values()->all(),
            'cuenta_nominal' => $this->describirCuentaNominal($cheque),
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
    public function previewNotaDebitoChequeRechazado(
        int $chequeId,
        array $lineas,
        ?string $fecha = null,
        ?string $leyendaUsuario = null,
        ?int $puntoventaId = null,
    ): array {
        $sesion = $this->prepararSesion($chequeId, $lineas, $fecha, $leyendaUsuario, $puntoventaId);
        $calculo = $this->facturacionService->calculaFacturaGeneral($sesion['payload']);
        if (isset($calculo['error'])) {
            throw new InvalidArgumentException((string) $calculo['error']);
        }

        $conceptos = $calculo['conceptostotales'] ?? [];
        $asientoCreditos = $this->facturacionService->armaContabilidad(
            $calculo['datosfactura'] ?? [],
            $conceptos,
            (int) $sesion['puntoventa']->empresa_id,
            (float) ($calculo['totalcomprobante'] ?? 0),
        );

        return [
            'totales' => $this->totalesDesdeConceptos($conceptos, (float) ($calculo['totalcomprobante'] ?? 0)),
            'asiento' => $this->asientoConContrapartida(
                $asientoCreditos,
                $sesion['cliente'],
                $sesion['tipo'],
                (int) $sesion['puntoventa']->empresa_id,
            ),
        ];
    }

    public function emitirNotaDebitoChequeRechazado(
        int $chequeId,
        array $lineas,
        ?string $fecha = null,
        ?string $leyendaUsuario = null,
        ?string $motivoRechazo = null,
        ?int $puntoventaId = null,
        bool $enviarClienteProveedor = false,
    ): array {
        $sesion = $this->prepararSesion($chequeId, $lineas, $fecha, $leyendaUsuario, $puntoventaId);
        $cheque = $sesion['cheque'];
        $fechaNd = $sesion['fecha'];
        $payload = $sesion['payload'];
        $payload['omitir_envio_mail_automatico'] = true;

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
        $importe = round(abs((float) ($ventaNd->total ?? 0)), 2);

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

        $mailCliente = null;
        $deudaProveedor = null;
        if ($enviarClienteProveedor) {
            $mailCliente = $this->facturaMailEnvioService->enviar($ventaNdId);
            if ($ventaNd instanceof Venta && (int) ($cheque->proveedor_id ?? 0) > 0) {
                $deudaProveedor = $this->deudaProveedorService->generarSiCorresponde($cheque, $ventaNd);
            }
        }

        return [
            'venta_nd_id' => $ventaNdId,
            'codigo_nd' => $codigoNd,
            'cheque_id' => (int) $cheque->id,
            'importe' => $importe,
            'anita_ok' => $anitaOk,
            'enviar_cliente_proveedor' => $enviarClienteProveedor,
            'mail_cliente' => $mailCliente,
            'deuda_proveedor' => $deudaProveedor,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{
     *   cheque: Cheque,
     *   cliente: Cliente,
     *   puntoventa: Puntoventa,
     *   tipo: Tipotransaccion,
     *   fecha: string,
     *   payload: array<string, mixed>
     * }
     */
    private function prepararSesion(
        int $chequeId,
        array $lineas,
        ?string $fecha,
        ?string $leyendaUsuario,
        ?int $puntoventaId,
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

        $cliente = $cheque->clientes;
        if (! $cliente instanceof Cliente) {
            $cliente = Cliente::query()->with('condicionivas')->find((int) $cheque->cliente_id);
        }
        if (! $cliente) {
            throw new InvalidArgumentException('El cheque no tiene cliente asociado.');
        }

        $empresaId = (int) $cheque->empresa_id;
        $letra = ChequeNdConfigSupport::letraDesdeCliente($cliente);
        $puntoventa = ChequeNdConfigSupport::puntoventaParaNotaDebito($empresaId, $puntoventaId);
        $tipoNdId = ChequeNdConfigSupport::tipotransaccionNotaDebitoId($letra);
        $tipo = Tipotransaccion::query()->find($tipoNdId);
        if (! $tipo) {
            throw new InvalidArgumentException('No se encontró el tipo de nota de débito.');
        }

        $fechaNd = $fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d');
        $lineasNormalizadas = $this->normalizarLineas($cheque, $lineas, $empresaId, $tipoNdId, $fechaNd);

        $refCheque = $this->referenciaCheque($cheque);
        $leyendaManual = trim((string) $leyendaUsuario);
        $leyendaNd = $leyendaManual !== '' ? $leyendaManual : ('ND por cheque rechazado — '.$refCheque);
        if (mb_strlen($leyendaNd) > 255) {
            $leyendaNd = mb_substr($leyendaNd, 0, 255);
        }

        return [
            'cheque' => $cheque,
            'cliente' => $cliente,
            'puntoventa' => $puntoventa,
            'tipo' => $tipo,
            'fecha' => $fechaNd,
            'payload' => [
                'tipotransaccion_id' => $tipoNdId,
                'puntoventa_id' => (int) $puntoventa->id,
                'fechafactura' => $fechaNd,
                'leyendafactura' => $leyendaNd,
                'actividad_arca_id' => ChequeNdConfigSupport::actividadArcaIdParaPuntoventa((int) $puntoventa->id),
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
                'tratamientos_fiscales' => array_column($lineasNormalizadas, 'tratamiento_fiscal'),
                'cuentacontable_ids_linea' => array_column($lineasNormalizadas, 'cuentacontable_id'),
            ],
        ];
    }

    /**
     * El nominal queda fijo, sin IVA. Los gastos son neto + la alícuota del renglón.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function normalizarLineas(Cheque $cheque, array $lineas, int $empresaId, int $tipoId, string $fecha): array
    {
        $conceptoChequeId = ChequeNdConfigSupport::conceptoIdParaChequeRechazado();
        $impuestoExentoId = (int) (Impuesto::query()->where('valor', 0)->orderBy('id')->value('id') ?? 0);
        if ($impuestoExentoId <= 0) {
            throw new InvalidArgumentException('No hay un impuesto en cero para informar el nominal del cheque como no gravado.');
        }

        $cuentaDepositoId = $this->cuentaContableDeposito($cheque);
        $nominal = round((float) $cheque->monto, 2);
        $out = [];
        $hayCheque = false;

        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $rol = (string) ($linea['rol'] ?? 'gasto');
            if ($rol === 'cheque') {
                if ($hayCheque) {
                    continue;
                }
                $hayCheque = true;
                $concepto = $this->conceptoActivo($conceptoChequeId);
                $cuenta = $cuentaDepositoId > 0
                    ? $cuentaDepositoId
                    : $this->cuentaDelConcepto($concepto, $empresaId, $tipoId, $fecha);
                $out[] = $this->filaNormalizada(
                    $concepto,
                    1.,
                    $nominal,
                    trim((string) ($linea['descripcion'] ?? '')) !== ''
                        ? (string) $linea['descripcion']
                        : 'Cheque rechazado — '.$this->referenciaCheque($cheque),
                    $impuestoExentoId,
                    'nogravado',
                    $cuenta,
                );

                continue;
            }

            $conceptoId = (int) ($linea['concepto_venta_id'] ?? 0);
            $cantidad = round((float) ($linea['cantidad'] ?? 1), 4);
            $precio = round((float) ($linea['precio'] ?? 0), 2);
            if ($conceptoId <= 0 || $cantidad <= 0. || $precio <= 0.) {
                continue;
            }
            if ($conceptoId === $conceptoChequeId) {
                throw new InvalidArgumentException('El concepto del nominal del cheque no se usa en los gastos. Elegí un concepto de gasto.');
            }

            $concepto = $this->conceptoActivo($conceptoId);
            $impuestoId = (int) ($linea['impuesto_id'] ?? $concepto->impuesto_id ?? 0);
            if ($impuestoId <= 0 || ! Impuesto::query()->whereKey($impuestoId)->exists()) {
                throw new InvalidArgumentException('Elegí la alícuota de IVA del gasto '.$concepto->codigo.'.');
            }

            $out[] = $this->filaNormalizada(
                $concepto,
                $cantidad,
                $precio,
                (string) ($linea['descripcion'] ?? ''),
                $impuestoId,
                'gravado',
                $this->cuentaDelConcepto($concepto, $empresaId, $tipoId, $fecha),
            );
        }

        if (! $hayCheque) {
            throw new InvalidArgumentException('Falta el renglón del nominal del cheque.');
        }

        return $out;
    }

    private function filaNormalizada(
        Concepto_Venta $concepto,
        float $cantidad,
        float $precio,
        string $descripcion,
        int $impuestoId,
        string $tratamiento,
        int $cuentaId,
    ): array {
        $texto = trim($descripcion);
        if ($texto === '') {
            $texto = (string) ($concepto->nombre ?? '');
        }
        if (mb_strlen($texto) > 255) {
            $texto = mb_substr($texto, 0, 255);
        }

        return [
            'concepto_venta_id' => (int) $concepto->id,
            'cantidad' => $cantidad,
            'precio' => $precio,
            'descripcion' => $texto,
            'impuesto_id' => $impuestoId,
            'incluyeimpuesto' => 'N',
            'tratamiento_fiscal' => $tratamiento,
            'cuentacontable_id' => $cuentaId,
        ];
    }

    private function conceptoActivo(int $conceptoId): Concepto_Venta
    {
        $concepto = Concepto_Venta::query()->whereKey($conceptoId)->where('activo', true)->first();
        if (! $concepto) {
            throw new InvalidArgumentException('El concepto de venta id '.$conceptoId.' no existe o no está activo.');
        }

        return $concepto;
    }

    private function cuentaDelConcepto(Concepto_Venta $concepto, int $empresaId, int $tipoId, string $fecha): int
    {
        $linea = ConceptoVentaMostradorSupport::resolverLinea((int) $concepto->id, $empresaId, $tipoId, $fecha);
        $cuentaId = (int) ($linea['cuentacontable_id'] ?? 0);
        if ($cuentaId <= 0) {
            throw new InvalidArgumentException(
                'El concepto '.$concepto->codigo.' no tiene cuenta contable para esta empresa. Cargala en el concepto de venta antes de emitir la nota de débito.'
            );
        }

        return $cuentaId;
    }

    private function cuentaContableDeposito(Cheque $cheque): int
    {
        $cajaId = (int) ($cheque->cuentacaja_deposito_id ?? 0);
        if ($cajaId <= 0) {
            return 0;
        }

        $cuentaId = (int) (Cuentacaja::query()->whereKey($cajaId)->value('cuentacontable_id') ?? 0);
        if ($cuentaId <= 0) {
            throw new InvalidArgumentException('La cuenta de caja del depósito no tiene cuenta contable. No se puede acreditar el nominal al banco.');
        }

        return $cuentaId;
    }

    /**
     * @return array{id:int, codigo:string, nombre:string, origen:string}|null
     */
    private function describirCuentaNominal(Cheque $cheque): ?array
    {
        try {
            $deposito = $this->cuentaContableDeposito($cheque);
        } catch (InvalidArgumentException $e) {
            return [
                'id' => 0,
                'codigo' => '',
                'nombre' => $e->getMessage(),
                'origen' => 'error',
            ];
        }

        if ($deposito > 0) {
            return $this->etiquetaCuenta($deposito, 'deposito');
        }

        try {
            $concepto = $this->conceptoActivo(ChequeNdConfigSupport::conceptoIdParaChequeRechazado());
            $cuentaId = $this->cuentaDelConcepto($concepto, (int) $cheque->empresa_id, 0, date('Y-m-d'));
        } catch (InvalidArgumentException $e) {
            return [
                'id' => 0,
                'codigo' => '',
                'nombre' => $e->getMessage(),
                'origen' => 'error',
            ];
        }

        return $this->etiquetaCuenta($cuentaId, 'cartera');
    }

    /**
     * @return array{id:int, codigo:string, nombre:string, origen:string}
     */
    private function etiquetaCuenta(int $cuentaId, string $origen): array
    {
        $cuenta = Cuentacontable::query()->find($cuentaId);

        return [
            'id' => $cuentaId,
            'codigo' => (string) ($cuenta->codigo ?? ''),
            'nombre' => (string) ($cuenta->nombre ?? ''),
            'origen' => $origen,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @return array{no_gravado:float, gravado:float, exento:float, iva:float, total:float}
     */
    private function totalesDesdeConceptos(array $conceptos, float $total): array
    {
        $noGravado = 0.;
        $gravado = 0.;
        $exento = 0.;
        $iva = 0.;
        foreach ($conceptos as $conc) {
            if (! is_array($conc)) {
                continue;
            }
            $nombre = (string) ($conc['concepto'] ?? '');
            $importe = round((float) ($conc['importe'] ?? 0), 2);
            if (str_starts_with($nombre, 'No Gravado')) {
                $noGravado += $importe;
            } elseif (str_starts_with($nombre, 'Gravado')) {
                $gravado += $importe;
            } elseif (str_starts_with($nombre, 'Exento')) {
                $exento += $importe;
            } elseif (str_starts_with($nombre, 'Iva ')) {
                $iva += $importe;
            }
        }

        return [
            'no_gravado' => round($noGravado, 2),
            'gravado' => round($gravado, 2),
            'exento' => round($exento, 2),
            'iva' => round($iva, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Misma convención que el asiento de la factura: signo S pone los renglones al Haber
     * y al cliente al Debe.
     *
     * @param  list<array<string, mixed>>  $creditos
     * @return list<array{codigo:string, nombre:string, debe:float, haber:float}>
     */
    private function asientoConContrapartida(array $creditos, Cliente $cliente, Tipotransaccion $tipo, int $empresaId): array
    {
        $signo = $tipo->signo == 'S' ? 1. : -1.;
        $filas = [];
        $suma = 0.;
        $ids = [];
        foreach ($creditos as $imp) {
            $monto = round(abs((float) ($imp['monto'] ?? 0)), 2);
            if ($monto < 0.009) {
                continue;
            }
            $cuentaId = (int) ($imp['cuentacontable_id'] ?? 0);
            $ids[] = $cuentaId;
            $suma += $monto;
            $filas[] = [
                'cuenta_id' => $cuentaId,
                'debe' => $signo > 0 ? 0. : $monto,
                'haber' => $signo > 0 ? $monto : 0.,
            ];
        }

        $clienteCuentaId = (int) ($cliente->cuentacontable_id ?? 0);
        if ($clienteCuentaId <= 0) {
            $clienteCuentaId = (int) (Cuentacontable::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', (string) config('cliente.DEUDORES_POR_VENTAS'))
                ->value('id') ?? 0);
        }
        if ($clienteCuentaId <= 0) {
            throw new InvalidArgumentException('El cliente no tiene cuenta de deudores para el asiento de la nota de débito.');
        }
        $ids[] = $clienteCuentaId;
        array_unshift($filas, [
            'cuenta_id' => $clienteCuentaId,
            'debe' => $signo > 0 ? round($suma, 2) : 0.,
            'haber' => $signo > 0 ? 0. : round($suma, 2),
        ]);

        $cuentas = Cuentacontable::query()->whereIn('id', array_unique($ids))->get()->keyBy('id');
        $out = [];
        foreach ($filas as $fila) {
            $cuenta = $cuentas->get($fila['cuenta_id']);
            $out[] = [
                'codigo' => (string) ($cuenta->codigo ?? ''),
                'nombre' => (string) ($cuenta->nombre ?? ''),
                'debe' => (float) $fila['debe'],
                'haber' => (float) $fila['haber'],
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
