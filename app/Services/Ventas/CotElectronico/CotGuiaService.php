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
use App\Support\Ventas\CotGuiaFacturaIdentidadSupport;
use App\Support\Ventas\CotGuiaSuburbanoSupport;
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
        $guia = $this->guiaRepository->find($id);
        if ($guia !== null) {
            $this->hidratarIdentidadFacturaDesdeVenta($guia);
        }

        return $guia;
    }

    public function cargarPorNumero(int $numero): ?CotGuia
    {
        $guia = $this->guiaRepository->findPorNumero($numero);
        if ($guia !== null) {
            $this->hidratarIdentidadFacturaDesdeVenta($guia);
        }

        return $guia;
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
     * En modo guía Ferli la cabecera suele ser SUBURBANO (vehículo ARBA), mientras cada
     * remito lleva su expreso real: no filtrar pendientes por ese transporte de cabecera.
     *
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   cantidad_total_dia: int,
     *   cantidad_emitidas: int,
     *   cantidad_sin_importe: int,
     *   cantidad_en_guia: int
     * }
     */
    public function facturasPendientesDelDia(Carbon $fecha, ?int $transporteId = null, ?int $guiaId = null): array
    {
        $transporteFiltro = $this->transporteFiltroPendientes($transporteId);
        $repartos = $this->repartosParaConsulta($transporteFiltro);
        if ($repartos === []) {
            return [
                'filas' => [],
                'cantidad_total_dia' => 0,
                'cantidad_emitidas' => 0,
                'cantidad_sin_importe' => 0,
                'cantidad_en_guia' => 0,
            ];
        }

        $filas = $this->consultaService->listarRemitosDelDia($fecha, $repartos);
        $clavesEnGuia = $this->clavesFacturaEnGuia($guiaId);

        $pendientes = [];
        $emitidas = 0;
        $sinImporte = 0;
        $enGuia = 0;
        foreach ($filas as $fila) {
            if (! empty($fila['ya_enviado'])) {
                $emitidas++;

                continue;
            }
            if (empty($fila['importe_ok'])) {
                $sinImporte++;

                continue;
            }

            $linea = $this->mapearRemitoConsultaALineaGuia($fila);

            // Completa identidad + bultos/pares/valor desde la factura (Anita/ERP).
            $resuelto = $this->resolverFactura(
                (string) $linea['tipo'],
                (string) $linea['letra'],
                (int) $linea['sucursal'],
                (int) $linea['numero'],
            );
            if ($resuelto['ok'] ?? false) {
                $linea = $this->aplicarIdentidadResuelta($linea, $resuelto['linea']);
            }

            $claveFactura = $this->claveFactura(
                (string) $linea['tipo'],
                (string) $linea['letra'],
                (int) $linea['sucursal'],
                (int) $linea['numero'],
            );
            if (isset($clavesEnGuia[$claveFactura])) {
                $enGuia++;

                continue;
            }

            $pendientes[] = array_merge($linea, [
                'clave_remito' => (string) ($fila['clave'] ?? ''),
                'ya_enviado' => false,
                'factura_codigo' => (string) ($linea['etiqueta'] ?? $fila['factura_codigo'] ?? ''),
                'importe' => (float) ($linea['valor_declarado'] ?? $fila['importe'] ?? 0),
                'cliente_nombre' => (string) ($linea['cliente_nombre'] ?? $fila['cliente_nombre'] ?? ''),
            ]);
        }

        return [
            'filas' => $pendientes,
            'cantidad_total_dia' => count($filas),
            'cantidad_emitidas' => $emitidas,
            'cantidad_sin_importe' => $sinImporte,
            'cantidad_en_guia' => $enGuia,
        ];
    }

    /**
     * El expreso de cabecera suburbano no filtra remitos (van con su expreso real).
     */
    private function transporteFiltroPendientes(?int $transporteId): ?int
    {
        if ($transporteId === null || $transporteId < 1) {
            return null;
        }

        $transporte = Transporte::query()->find($transporteId);
        if ($transporte !== null && CotGuiaSuburbanoSupport::esSuburbano($transporte)) {
            return null;
        }

        return $transporteId;
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
        $this->hidratarIdentidadFacturaDesdeVenta($guia);
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
            $identidad = $this->identidadDesdeVentaId((int) ($linea->venta_id ?? 0) ?: null);
            if ($identidad !== null) {
                $out[$this->claveFactura(
                    $identidad['tipo'],
                    $identidad['letra'],
                    $identidad['sucursal'],
                    $identidad['numero']
                )] = true;
            }
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
        $sucursal = (int) ($anita['sucursal_factura'] ?? 0);
        $numero = (int) ($anita['nro_fact'] ?? 0);

        $ventaId = ((int) ($fila['venta_id'] ?? 0)) ?: null;
        if (CotGuiaFacturaIdentidadSupport::pareceRemito($tipo, $letra) || $numero < 1) {
            $desdeVenta = $this->identidadDesdeVentaId($ventaId);
            if ($desdeVenta !== null) {
                $tipo = $desdeVenta['tipo'];
                $letra = $desdeVenta['letra'];
                $sucursal = $desdeVenta['sucursal'];
                $numero = $desdeVenta['numero'];
            } else {
                // No usar PV/letra del remito (Ferli REM R 9 vs FAC A 12).
                $tipo = $tipo === 'REM' ? 'FAC' : $tipo;
                $letra = '';
                $sucursal = 0;
                $numero = (int) ($fila['numero_remito'] ?? $numero);
            }
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

        $datos = $this->resolverLineaGuia($linea);
        if ($datos === null) {
            return null;
        }

        $numeroRemito = (int) ($datos['numero_remito'] ?? $linea->numero);
        if ($numeroRemito < 1) {
            $numeroRemito = (int) $linea->numero;
        }

        $tipoFactura = (string) ($datos['tipo'] ?? $linea->tipo);
        $letraFactura = (string) ($datos['letra'] ?? $linea->letra);
        $sucursalFactura = (int) ($datos['sucursal'] ?? $linea->sucursal);
        $numeroFactura = (int) ($datos['numero'] ?? $linea->numero);

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
            'remito_id' => ((int) ($datos['remito_id'] ?? 0)) ?: null,
            'venta_id' => ((int) ($linea->venta_id ?: ($datos['venta_id'] ?? 0))) ?: null,
            'anita' => [
                'fuente' => 'guia',
                'tipo' => $tipoFactura,
                'letra' => $letraFactura,
                'sucursal_factura' => $sucursalFactura,
                'nro_fact' => $numeroFactura,
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
            'factura_codigo' => (string) ($datos['etiqueta'] ?? $linea->etiquetaFactura()),
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

        return $this->lineaDesdeVentaErp($venta);
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

    /**
     * @return array<string, mixed>|null
     */
    private function resolverLineaGuia(CotGuiaLinea $linea): ?array
    {
        $ventaId = (int) ($linea->venta_id ?? 0);
        if ($ventaId > 0) {
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
                ->find($ventaId);
            if ($venta !== null) {
                return $this->lineaDesdeVentaErp($venta);
            }
        }

        $resuelto = $this->resolverFactura(
            (string) $linea->tipo,
            (string) $linea->letra,
            (int) $linea->sucursal,
            (int) $linea->numero,
        );
        if ($resuelto['ok'] ?? false) {
            return $resuelto['linea'];
        }

        if (CotGuiaFacturaIdentidadSupport::pareceRemito((string) $linea->tipo, (string) $linea->letra)) {
            $resuelto = $this->resolverFactura('FAC', '', 0, (int) $linea->numero);
            if ($resuelto['ok'] ?? false) {
                return $resuelto['linea'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $linea
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function aplicarIdentidadResuelta(array $linea, array $datos): array
    {
        foreach (['tipo', 'letra', 'sucursal', 'numero', 'etiqueta', 'venta_id', 'entrega', 'cliente_codigo', 'cliente_nombre'] as $campo) {
            if (isset($datos[$campo]) && $datos[$campo] !== '' && $datos[$campo] !== null) {
                $linea[$campo] = $datos[$campo];
            }
        }
        $linea['bultos'] = (float) ($datos['bultos'] ?? $linea['bultos']);
        $linea['cantidad'] = (float) ($datos['cantidad'] ?? 0);
        if ((float) ($datos['valor_declarado'] ?? 0) > 0) {
            $linea['valor_declarado'] = (float) $datos['valor_declarado'];
        }

        return $linea;
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, numero: int}|null
     */
    private function identidadDesdeVentaId(?int $ventaId): ?array
    {
        if ($ventaId === null || $ventaId < 1) {
            return null;
        }

        $venta = Venta::query()
            ->with(['puntoventas', 'tipotransacciones'])
            ->find($ventaId);

        return $venta !== null ? CotGuiaFacturaIdentidadSupport::desdeVenta($venta) : null;
    }

    private function hidratarIdentidadFacturaDesdeVenta(CotGuia $guia): void
    {
        $guia->loadMissing(['lineas']);
        foreach ($guia->lineas as $linea) {
            if (! CotGuiaFacturaIdentidadSupport::pareceRemito((string) $linea->tipo, (string) $linea->letra)) {
                continue;
            }
            $identidad = $this->identidadDesdeVentaId((int) ($linea->venta_id ?? 0) ?: null);
            if ($identidad === null) {
                continue;
            }
            $linea->tipo = $identidad['tipo'];
            $linea->letra = $identidad['letra'];
            $linea->sucursal = $identidad['sucursal'];
            $linea->numero = $identidad['numero'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function lineaDesdeVentaErp(Venta $venta): array
    {
        $cliente = $venta->clientes;
        $transporte = $venta->transportes;
        $identidad = CotGuiaFacturaIdentidadSupport::desdeVenta($venta)
            ?? CotGuiaFacturaIdentidadSupport::normalizar(
                (string) ($venta->tipotransacciones->abreviatura ?? 'FAC'),
                'A',
                (int) ($venta->puntoventas->codigo ?? 0),
                (int) $venta->numerocomprobante
            );
        $pares = $this->paresDeFactura(
            $identidad['tipo'],
            $identidad['letra'],
            $identidad['sucursal'],
            $identidad['numero'],
            (int) $venta->id
        );

        return [
            'tipo' => $identidad['tipo'],
            'letra' => $identidad['letra'],
            'sucursal' => $identidad['sucursal'],
            'numero' => $identidad['numero'],
            'cliente_codigo' => (string) ($cliente->codigo ?? ''),
            'cliente_nombre' => (string) ($cliente->nombre ?? ''),
            'cliente_id' => (int) ($cliente->id ?? 0) ?: null,
            'bultos' => (float) ($venta->cantidadbulto ?? 0),
            'cantidad' => $pares,
            'valor_declarado' => (float) ($venta->total ?? 0),
            'transporte_id' => (int) ($transporte->id ?? 0) ?: null,
            'transporte_codigo' => (string) ($transporte->codigo ?? ''),
            'entrega' => (string) ($venta->lugarentrega ?? ''),
            'venta_id' => (int) $venta->id,
            'remito_id' => (int) ($venta->remito_id ?? 0) ?: null,
            'numero_remito' => (int) ($venta->numeroremito ?: $venta->numerocomprobante),
            'etiqueta' => sprintf(
                '%s %s-%04d-%08d',
                $identidad['tipo'],
                $identidad['letra'],
                $identidad['sucursal'],
                $identidad['numero']
            ),
            'destinatario' => $this->consultaService->destinatarioPublicoDesdeCliente(
                $cliente,
                (string) ($cliente->codigo ?? '')
            ),
        ];
    }
}
