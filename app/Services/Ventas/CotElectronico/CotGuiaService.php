<?php

namespace App\Services\Ventas\CotElectronico;

use App\ApiAnita;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\CotGuia;
use App\Models\Ventas\CotGuiaLinea;
use App\Models\Ventas\CotRemitoEnvio;
use App\Models\Ventas\Transporte;
use App\Models\Ventas\Venta;
use App\Repositories\Ventas\CotGuiaRepository;
use App\Support\Ventas\CotImporteRemitoSupport;
use App\Support\Ventas\CuitFormatoValidacionSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CotGuiaService
{
    public function __construct(
        private CotGuiaRepository $guiaRepository,
        private CotRemitoConsultaService $consultaService,
        private CotElectronicoService $cotService,
    ) {}

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array<string, mixed>>  $lineas
     */
    public function guardar(array $cabecera, array $lineas, ?int $guiaId = null): CotGuia
    {
        return $this->guiaRepository->guardar($cabecera, $lineas, $guiaId);
    }

    public function cargar(int $id): ?CotGuia
    {
        return $this->guiaRepository->find($id);
    }

    public function cargarPorNumero(int $numero): ?CotGuia
    {
        return $this->guiaRepository->findPorNumero($numero);
    }

    /**
     * Resuelve factura por tipo/letra/sucursal/número (ERP venta o Anita comprob).
     * Acepta tipo/letra/sucursal vacíos: busca por número (y PV si viene).
     *
     * @return array{ok: bool, mensaje?: string, linea?: array<string, mixed>}
     */
    public function resolverFactura(string $tipo, string $letra, int $sucursal, int $numero): array
    {
        $tipo = strtoupper(trim($tipo));
        $letra = strtoupper(trim($letra));
        if ($numero < 1) {
            return ['ok' => false, 'mensaje' => 'Indique el número de factura.'];
        }

        $desdeErp = $this->resolverDesdeVentaErp($tipo, $letra, $sucursal, $numero);
        if ($desdeErp !== null) {
            return ['ok' => true, 'linea' => $desdeErp];
        }

        $desdeAnita = $this->resolverDesdeAnitaComprob($tipo, $letra, $sucursal, $numero);
        if ($desdeAnita !== null) {
            return ['ok' => true, 'linea' => $desdeAnita];
        }

        $etiqueta = $tipo !== '' && $letra !== ''
            ? sprintf('%s %s-%04d-%08d', $tipo, $letra, $sucursal, $numero)
            : (string) $numero;

        return [
            'ok' => false,
            'mensaje' => sprintf('Factura %s no encontrada.', $etiqueta),
        ];
    }

    /**
     * Facturas/remitos del día aún no enviados a COT (ni ya en la guía opcional).
     *
     * @return list<array<string, mixed>>
     */
    public function facturasPendientesDelDia(Carbon $fecha, ?int $transporteId = null, ?int $guiaId = null): array
    {
        $repartos = $this->repartosParaConsulta($transporteId);
        if ($repartos === []) {
            return [];
        }

        $filas = $this->consultaService->listarRemitosDelDia($fecha, $repartos);
        $clavesEnGuia = $this->clavesFacturaEnGuia($guiaId);

        $pendientes = [];
        foreach ($filas as $fila) {
            if (! empty($fila['ya_enviado'])) {
                continue;
            }
            if (empty($fila['importe_ok'])) {
                continue;
            }

            $linea = $this->mapearRemitoConsultaALineaGuia($fila);
            $claveFactura = $this->claveFactura(
                (string) $linea['tipo'],
                (string) $linea['letra'],
                (int) $linea['sucursal'],
                (int) $linea['numero'],
            );
            if (isset($clavesEnGuia[$claveFactura])) {
                continue;
            }

            // Completa bultos/pares/valor desde la factura (Anita/ERP); kilos del remito no son pares.
            $resuelto = $this->resolverFactura(
                (string) $linea['tipo'],
                (string) $linea['letra'],
                (int) $linea['sucursal'],
                (int) $linea['numero'],
            );
            if ($resuelto['ok'] ?? false) {
                $datos = $resuelto['linea'];
                $linea['bultos'] = (float) ($datos['bultos'] ?? $linea['bultos']);
                $linea['cantidad'] = (float) ($datos['cantidad'] ?? 0);
                if ((float) ($datos['valor_declarado'] ?? 0) > 0) {
                    $linea['valor_declarado'] = (float) $datos['valor_declarado'];
                }
                if (! empty($datos['etiqueta'])) {
                    $linea['etiqueta'] = (string) $datos['etiqueta'];
                }
                if (! empty($datos['venta_id'])) {
                    $linea['venta_id'] = (int) $datos['venta_id'];
                }
            }

            $pendientes[] = array_merge($linea, [
                'clave_remito' => (string) ($fila['clave'] ?? ''),
                'ya_enviado' => false,
                'factura_codigo' => (string) ($fila['factura_codigo'] ?? $linea['etiqueta']),
                'importe' => (float) ($fila['importe'] ?? $linea['valor_declarado']),
                'cliente_nombre' => (string) ($fila['cliente_nombre'] ?? $linea['cliente_nombre']),
            ]);
        }

        return $pendientes;
    }

    /**
     * @return array<string, mixed>
     */
    public function enviarAArba(CotGuia $guia): array
    {
        if (! $guia->esBorrador()) {
            return [
                'ok' => false,
                'mensaje' => 'La guía ya fue enviada o anulada.',
                'resultados' => [],
            ];
        }

        $guia->loadMissing(['lineas', 'transportes']);
        if ($guia->lineas->isEmpty()) {
            return [
                'ok' => false,
                'mensaje' => 'La guía no tiene facturas.',
                'resultados' => [],
            ];
        }

        $transporteId = (int) ($guia->transporte_id ?? 0);
        if ($transporteId < 1) {
            return [
                'ok' => false,
                'mensaje' => 'Indique el expreso / reparto de cabecera antes de enviar.',
                'resultados' => [],
            ];
        }

        $transporte = Transporte::query()->find($transporteId);
        if ($transporte === null) {
            return [
                'ok' => false,
                'mensaje' => 'Transporte de cabecera inexistente.',
                'resultados' => [],
            ];
        }

        $reparto = [
            'transporte_id' => $transporteId,
            'codigo' => (string) ($transporte->codigo ?? ''),
            'nombre' => (string) ($transporte->nombre ?? ''),
            'patente' => trim((string) ($guia->dominio ?: $transporte->patentevehiculo ?: '')),
            'cuit_chofer' => CuitFormatoValidacionSupport::formatear(
                trim((string) ($guia->cuit_chofer ?: $transporte->cuit_chofer ?: ''))
            ),
        ];

        $errorCuit = CuitFormatoValidacionSupport::primerErrorEnRepartos([$reparto]);
        if ($errorCuit !== null) {
            return [
                'ok' => false,
                'mensaje' => $errorCuit,
                'resultados' => [],
            ];
        }

        $remitos = [];
        foreach ($guia->lineas as $linea) {
            $remito = $this->lineaGuiaARemitoCot($linea, $reparto, $guia->fecha);
            if ($remito === null) {
                return [
                    'ok' => false,
                    'mensaje' => 'No se pudo armar el remito COT para '
                        .$linea->etiquetaFactura()
                        .'. Verifique destino e importe.',
                    'resultados' => [],
                ];
            }
            $remitos[] = $remito;
        }

        $claves = array_map(fn ($r) => (string) ($r['clave'] ?? ''), $remitos);
        $resultado = $this->cotService->procesarRemitosPreparados(
            Carbon::parse($guia->fecha),
            [$reparto],
            $remitos,
            $claves,
        );

        if (($resultado['ok'] ?? false) && (int) ($resultado['sesion_id'] ?? 0) > 0) {
            $this->guiaRepository->marcarEnviada($guia, (int) $resultado['sesion_id']);
        }

        return $resultado;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function repartosParaConsulta(?int $transporteId): array
    {
        $q = Transporte::query()->orderBy('codigo');
        if ($transporteId !== null && $transporteId > 0) {
            $q->whereKey($transporteId);
        }

        return $q->get()->map(fn (Transporte $t) => [
            'transporte_id' => (int) $t->id,
            'codigo' => (string) ($t->codigo ?? ''),
            'nombre' => (string) ($t->nombre ?? ''),
            'patente' => (string) ($t->patentevehiculo ?? ''),
            'cuit_chofer' => (string) ($t->cuit_chofer ?? ''),
        ])->all();
    }

    /**
     * @return array<string, true>
     */
    private function clavesFacturaEnGuia(?int $guiaId): array
    {
        if ($guiaId === null || $guiaId < 1) {
            return [];
        }

        $out = [];
        foreach (CotGuiaLinea::query()->where('cot_guia_id', $guiaId)->get() as $linea) {
            $out[$this->claveFactura($linea->tipo, $linea->letra, (int) $linea->sucursal, (int) $linea->numero)] = true;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function mapearRemitoConsultaALineaGuia(array $fila): array
    {
        $anita = is_array($fila['anita'] ?? null) ? $fila['anita'] : [];
        $tipo = strtoupper(trim((string) ($anita['tipo'] ?? $fila['tipo'] ?? 'FAC')));
        $letra = strtoupper(trim((string) ($anita['letra'] ?? $fila['letra'] ?? 'A')));
        $sucursal = (int) ($anita['sucursal_factura'] ?? $fila['sucursal'] ?? 0);
        $numero = (int) ($anita['nro_fact'] ?? $fila['numero_remito'] ?? 0);

        if ($tipo === 'REM' || $numero < 1) {
            // Ferli L8 / Bierzo: remito = nro factura cuando no hay anita factura
            $tipo = $tipo === 'REM' ? 'FAC' : $tipo;
            $numero = (int) ($fila['numero_remito'] ?? $numero);
            $sucursal = (int) ($fila['sucursal'] ?? $sucursal);
        }

        return [
            'tipo' => substr($tipo, 0, 3),
            'letra' => substr($letra, 0, 1),
            'sucursal' => $sucursal,
            'numero' => $numero,
            'cliente_codigo' => (string) ($fila['cliente_codigo'] ?? ''),
            'cliente_nombre' => (string) ($fila['cliente_nombre'] ?? ''),
            'bultos' => 0.0,
            'cantidad' => 0.0,
            'valor_declarado' => (float) ($fila['importe'] ?? 0),
            'transporte_id' => ((int) ($fila['transporte_id'] ?? 0)) ?: null,
            'transporte_codigo' => (string) ($fila['transporte_codigo'] ?? ''),
            'entrega' => '',
            'venta_id' => ((int) ($fila['venta_id'] ?? 0)) ?: null,
            'etiqueta' => sprintf('%s %s-%04d-%08d', $tipo, $letra, $sucursal, $numero),
            'numero_remito' => (int) ($fila['numero_remito'] ?? $numero),
            'clave_remito_logica' => CotRemitoEnvio::armarClaveLogica(
                (string) ($fila['tipo'] ?? 'REM'),
                (string) ($fila['letra'] ?? 'R'),
                (int) ($fila['numero_remito'] ?? $numero),
            ),
        ];
    }

    /**
     * @param  array{transporte_id:int,codigo:string,nombre:string,patente:?string,cuit_chofer:?string}  $reparto
     * @return array<string, mixed>|null
     */
    private function lineaGuiaARemitoCot(CotGuiaLinea $linea, array $reparto, $fechaGuia): ?array
    {
        $fecha = $fechaGuia instanceof Carbon
            ? $fechaGuia
            : Carbon::parse((string) $fechaGuia);

        $resuelto = $this->resolverFactura(
            (string) $linea->tipo,
            (string) $linea->letra,
            (int) $linea->sucursal,
            (int) $linea->numero,
        );
        if (! ($resuelto['ok'] ?? false)) {
            return null;
        }

        $datos = $resuelto['linea'];
        $numeroRemito = (int) ($datos['numero_remito'] ?? $linea->numero);
        if ($numeroRemito < 1) {
            $numeroRemito = (int) $linea->numero;
        }

        $importe = (float) ($linea->valor_declarado > 0
            ? $linea->valor_declarado
            : ($datos['valor_declarado'] ?? 0));
        $resImporte = CotImporteRemitoSupport::resolver(['factura_anita' => $importe]);

        $cliente = null;
        $clienteId = (int) ($datos['cliente_id'] ?? 0);
        if ($clienteId > 0) {
            $cliente = Cliente::query()
                ->with(['localidades', 'provincias', 'condicionivas', 'tipodocumentos'])
                ->find($clienteId);
        }

        $destinatario = $datos['destinatario'] ?? null;
        if (! is_array($destinatario) || $destinatario === []) {
            $destinatario = $this->consultaService->destinatarioPublicoDesdeCliente(
                $cliente,
                (string) ($linea->cliente_codigo ?? '')
            );
        }

        $fila = CotImporteRemitoSupport::aplicarAFila([
            'clave' => implode('|', ['REM', 'R', 1, $numeroRemito]),
            'origen' => 'cot_guia',
            'remito_id' => null,
            'venta_id' => ((int) ($linea->venta_id ?: ($datos['venta_id'] ?? 0))) ?: null,
            'anita' => [
                'fuente' => 'guia',
                'tipo' => $linea->tipo,
                'letra' => $linea->letra,
                'sucursal_factura' => (int) $linea->sucursal,
                'nro_fact' => (int) $linea->numero,
                'cliente_codigo' => (string) ($linea->cliente_codigo ?? ''),
            ],
            'cliente_id' => $clienteId ?: null,
            'tipo' => 'REM',
            'letra' => 'R',
            'sucursal' => 1,
            'numero_remito' => $numeroRemito,
            'fecha_remito' => $fecha->format('Y-m-d'),
            'fecha_factura' => $fecha->format('d/m/Y'),
            'desde_factura' => true,
            'factura_codigo' => $linea->etiquetaFactura(),
            'cliente_codigo' => (string) ($linea->cliente_codigo ?? $datos['cliente_codigo'] ?? ''),
            'cliente_nombre' => (string) ($linea->cliente_nombre ?? $datos['cliente_nombre'] ?? ''),
            'transporte_id' => (int) $reparto['transporte_id'],
            'transporte_codigo' => (string) $reparto['codigo'],
            'transporte_nombre' => (string) $reparto['nombre'],
            'patente' => (string) ($reparto['patente'] ?? ''),
            'cuit_chofer' => (string) ($reparto['cuit_chofer'] ?? ''),
            'kilos' => (float) ($linea->bultos > 0 ? $linea->bultos : ($datos['bultos'] ?? 0)),
            'importe' => $resImporte['importe'] ?? $importe,
            'ya_enviado' => false,
            'seleccionado' => true,
            'destinatario' => $destinatario,
            'productos' => $datos['productos'] ?? [],
        ], $resImporte['importe'] ?? $importe, $resImporte['origen'] ?? 'guia');

        return $fila;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolverDesdeVentaErp(string $tipo, string $letra, int $sucursal, int $numero): ?array
    {
        $venta = Venta::query()
            ->with([
                'clientes.localidades',
                'clientes.provincias',
                'clientes.condicionivas',
                'clientes.tipodocumentos',
                'puntoventas',
                'tipotransacciones',
                'transportes',
                'venta_impuestos',
            ])
            ->where('numerocomprobante', $numero)
            ->when($sucursal > 0, function ($q) use ($sucursal) {
                $q->whereHas('puntoventas', function ($p) use ($sucursal) {
                    $p->where('codigo', $sucursal)
                        ->orWhere('codigo', str_pad((string) $sucursal, 4, '0', STR_PAD_LEFT))
                        ->orWhere('codigo', str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT));
                });
            })
            ->when($tipo !== '', function ($q) use ($tipo) {
                $q->whereHas('tipotransacciones', function ($t) use ($tipo) {
                    $t->where('abreviatura', $tipo)
                        ->orWhere('codigo', $tipo);
                });
            }, function ($q) {
                // Sin tipo: preferir facturas (FAC) sobre otros comprobantes del mismo número.
                $q->whereHas('tipotransacciones', function ($t) {
                    $t->whereIn('abreviatura', ['FAC', 'N/C', 'N/D', 'NCC', 'NDC', 'FCE', 'NCE', 'NDE'])
                        ->orWhereIn('codigo', ['001', '002', '003', 'FAC']);
                });
            })
            ->orderByDesc('id')
            ->first();

        if ($venta === null) {
            return null;
        }

        $cliente = $venta->clientes;
        $transporte = $venta->transportes;
        $importe = (float) ($venta->total ?? 0);
        $numeroRemito = (int) ($venta->numeroremito ?: $venta->numerocomprobante);
        $pv = (int) ($venta->puntoventas->codigo ?? $sucursal);
        $abr = strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? ($tipo !== '' ? $tipo : 'FAC'))));
        if ($abr === '') {
            $abr = 'FAC';
        }

        $letraResuelta = $letra !== '' ? $letra : $this->letraDesdeAnitaODefault($abr, $pv, (int) $venta->numerocomprobante);
        $pares = $this->paresDeFactura($abr, $letraResuelta, $pv, (int) $venta->numerocomprobante, (int) $venta->id);

        return [
            'tipo' => substr($abr, 0, 3),
            'letra' => substr($letraResuelta, 0, 1),
            'sucursal' => $pv,
            'numero' => (int) $venta->numerocomprobante,
            'cliente_codigo' => (string) ($cliente->codigo ?? ''),
            'cliente_nombre' => (string) ($cliente->nombre ?? ''),
            'cliente_id' => (int) ($cliente->id ?? 0) ?: null,
            'bultos' => (float) ($venta->cantidadbulto ?? 0),
            'cantidad' => $pares,
            'valor_declarado' => $importe,
            'transporte_id' => (int) ($transporte->id ?? 0) ?: null,
            'transporte_codigo' => (string) ($transporte->codigo ?? ''),
            'entrega' => (string) ($venta->lugarentrega ?? ''),
            'venta_id' => (int) $venta->id,
            'numero_remito' => $numeroRemito,
            'etiqueta' => sprintf('%s %s-%04d-%08d', $abr, $letraResuelta, $pv, (int) $venta->numerocomprobante),
            'destinatario' => $this->consultaService->destinatarioPublicoDesdeCliente(
                $cliente,
                (string) ($cliente->codigo ?? '')
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolverDesdeAnitaComprob(string $tipo, string $letra, int $sucursal, int $numero): ?array
    {
        $api = new ApiAnita();
        $where = ' WHERE comp_nro_fact = '.(int) $numero;
        if ($tipo !== '') {
            $where .= " AND comp_tipo = '".addslashes($tipo)."'";
        }
        if ($letra !== '') {
            $where .= " AND comp_letra = '".addslashes($letra)."'";
        }
        if ($sucursal > 0) {
            $where .= ' AND comp_sucursal = '.(int) $sucursal;
        }

        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'comprob',
            'campos' => '
                comp_cliente, comp_tipo, comp_letra, comp_sucursal, comp_nro_fact,
                comp_remito, comp_fecha, comp_transporte, comp_total,
                comp_iva, comp_exento, comp_gravado, comp_pedido, comp_entrega
            ',
            'whereArmado' => $where,
        ];

        $parseado = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if ($parseado['error_lectura'] !== null || ($parseado['filas'] ?? []) === []) {
            if ($parseado['error_lectura'] !== null) {
                Log::warning('cot_guia.anita_comprob', ['mensaje' => $parseado['error_lectura']]);
            }

            return null;
        }

        $row = $parseado['filas'][0];
        $tipo = strtoupper(trim((string) ($row->comp_tipo ?? $tipo)));
        $letra = strtoupper(trim((string) ($row->comp_letra ?? ($letra !== '' ? $letra : 'A'))));
        $sucursal = (int) ($row->comp_sucursal ?? $sucursal);
        $numero = (int) ($row->comp_nro_fact ?? $numero);

        $codigoCliente = trim((string) ($row->comp_cliente ?? ''));
        $cliente = Cliente::query()
            ->with(['localidades', 'provincias', 'condicionivas', 'tipodocumentos'])
            ->where('codigo', ltrim($codigoCliente, '0'))
            ->orWhere('codigo', $codigoCliente)
            ->first();

        $codigoTransporte = (int) ($row->comp_transporte ?? 0);
        $transporte = $codigoTransporte > 0
            ? Transporte::query()->where('codigo', $codigoTransporte)->orWhere('codigo', (string) $codigoTransporte)->first()
            : null;

        $importe = (float) ($row->comp_gravado ?? 0) + (float) ($row->comp_exento ?? 0);
        if ($importe <= 0) {
            $importe = (float) ($row->comp_total ?? 0);
        }

        $numeroRemito = (int) ($row->comp_remito ?? 0);
        if ($numeroRemito < 1) {
            $numeroRemito = $numero;
        }

        return [
            'tipo' => substr($tipo !== '' ? $tipo : 'FAC', 0, 3),
            'letra' => substr($letra !== '' ? $letra : 'A', 0, 1),
            'sucursal' => $sucursal,
            'numero' => $numero,
            'cliente_codigo' => (string) ($cliente->codigo ?? ltrim($codigoCliente, '0')),
            'cliente_nombre' => (string) ($cliente->nombre ?? $codigoCliente),
            'cliente_id' => (int) ($cliente->id ?? 0) ?: null,
            'bultos' => (float) ($row->comp_pedido ?? 0),
            'cantidad' => $this->paresDeFactura($tipo, $letra, $sucursal, $numero, null),
            'valor_declarado' => $importe,
            'transporte_id' => (int) ($transporte->id ?? 0) ?: null,
            'transporte_codigo' => (string) ($transporte->codigo ?? $codigoTransporte),
            'entrega' => trim((string) ($row->comp_entrega ?? '')),
            'venta_id' => null,
            'numero_remito' => $numeroRemito,
            'etiqueta' => sprintf('%s %s-%04d-%08d', $tipo !== '' ? $tipo : 'FAC', $letra !== '' ? $letra : 'A', $sucursal, $numero),
            'destinatario' => $this->consultaService->destinatarioPublicoDesdeCliente(
                $cliente,
                $codigoCliente
            ),
        ];
    }

    /**
     * Pares: movimientos ERP (si hay) o suma Anita compaux (a-controlrem).
     */
    private function paresDeFactura(string $tipo, string $letra, int $sucursal, int $numero, ?int $ventaId): float
    {
        if ($ventaId !== null && $ventaId > 0) {
            $desdeErp = (float) Articulo_Movimiento::query()
                ->where('venta_id', $ventaId)
                ->selectRaw('COALESCE(SUM(ABS(cantidad)), 0) as total')
                ->value('total');
            if ($desdeErp > 0) {
                return round($desdeErp, 2);
            }
        }

        return $this->consultaService->paresDesdeCompa($tipo, $letra, $sucursal, $numero);
    }

    private function letraDesdeAnitaODefault(string $tipo, int $sucursal, int $numero): string
    {
        $api = new ApiAnita();
        $where = " WHERE comp_tipo = '".addslashes($tipo)."'"
            .' AND comp_sucursal = '.(int) $sucursal
            .' AND comp_nro_fact = '.(int) $numero;
        $data = [
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'comprob',
            'campos' => 'comp_letra',
            'whereArmado' => $where,
        ];
        $parseado = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        $letra = strtoupper(trim((string) ($parseado['filas'][0]->comp_letra ?? '')));

        return $letra !== '' ? substr($letra, 0, 1) : 'A';
    }

    private function claveFactura(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return implode('|', [
            strtoupper(trim($tipo)),
            strtoupper(trim($letra)),
            $sucursal,
            $numero,
        ]);
    }
}
