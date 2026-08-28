<?php

namespace App\Services\Ventas;

use App\Models\Configuracion\Salida;
use App\Models\Ventas\ComprobanteImpresionLog;
use App\Models\Ventas\CotSesionEnvio;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Remito;
use App\Models\Ventas\Venta;
use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use App\Services\Ventas\CotElectronico\CotConstanciaPdfService;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use App\Support\Ventas\ComprobanteImpresionDespachoSupport;
use App\Support\Ventas\ComprobanteImpresionFormulario;
use App\Support\Ventas\ComprobanteImpresionNasPathSupport;
use App\Support\Ventas\ComprobanteImpresionPackSupport;
use App\Support\Ventas\ComprobanteImpresionResolverSupport;
use App\Support\Ventas\ComprobanteImpresionSalidaUsuarioSupport;
use App\Support\Ventas\CotConstanciaSupport;
use App\Support\Ventas\FacturaListadoFiltros;
use App\Support\Ventas\FacturaListadoSupport;
use App\Support\Ventas\PedidoFacturaAnitaArchivosSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Jurosh\PDFMerge\PDFMerger;

class ComprobanteImpresionSesionService
{
    public function __construct(
        private FacturacionService $facturacionService,
        private PedidoService $pedidoService,
        private RemitoService $remitoService,
        private SeteosalidaRepositoryInterface $seteosalidaRepository,
        private CotConstanciaPdfService $cotConstanciaPdfService,
    ) {
    }

    /**
     * Constancia COT de una sesión ARBA: una copia a la impresora del usuario.
     *
     * @return array<string, mixed>
     */
    public function armarDesdeCotSesion(int $sesionId, ?int $remitoEnvioId = null, string $modo = 'OPERATIVO'): array
    {
        $cotSesion = CotSesionEnvio::query()->find($sesionId);
        if ($cotSesion === null) {
            throw new \InvalidArgumentException('No se encontró la sesión de COT #'.$sesionId);
        }

        $remitos = CotConstanciaSupport::remitosImprimibles(
            $cotSesion->remitos()->orderBy('numero_remito')->get(),
            $remitoEnvioId
        );
        if ($remitos === []) {
            throw new \InvalidArgumentException(
                $remitoEnvioId
                    ? 'Ese remito no tiene COT emitido para imprimir.'
                    : 'La sesión #'.$sesionId.' no tiene COT emitidos para imprimir.'
            );
        }

        $titulo = CotConstanciaSupport::tituloSesion($cotSesion, count($remitos));
        $docs = [
            ComprobanteImpresionFormulario::COT => [
                'id' => (int) $cotSesion->id,
                'codigo' => $titulo,
                'nombre' => $cotSesion->etiquetaRepartos() !== ''
                    ? $cotSesion->etiquetaRepartos()
                    : (count($remitos).' remito(s)'),
                'fecha' => $this->fechaYmd($cotSesion->fecha_envio ?? $cotSesion->fecha_facturas),
            ],
        ];
        $pack = [[
            'formulario' => ComprobanteImpresionFormulario::COT,
            'formulario_etiqueta' => ComprobanteImpresionFormulario::etiquetas()[ComprobanteImpresionFormulario::COT],
            'copia_id' => null,
            'copia_codigo' => 'COT',
            'leyenda' => 'CONSTANCIA',
            'destinatario' => 'Chofer / ARBA',
            'salida_id' => null,
            'salida_nombre' => 'Heredar usuario',
            'incluir_en_pdf_sesion' => true,
            'medio' => 'IMPRESORA',
            'destino_path' => null,
            'documento_id' => (int) $cotSesion->id,
            'documento_codigo' => $titulo,
            'documento_fecha' => $this->fechaYmd($cotSesion->fecha_envio ?? $cotSesion->fecha_facturas),
            'remito_envio_id' => $remitoEnvioId,
        ]];

        $payload = $this->payload(
            [
                'programa' => null,
                'motivo' => 'Impresión directa de la constancia COT emitida ante ARBA',
                'empresa_id' => null,
                'transporte_id' => null,
                'provincia_entrega_id' => null,
            ],
            $pack,
            'COT',
            (int) $cotSesion->id,
            $modo,
            ComprobanteImpresionFormulario::COT,
            $docs
        );
        $payload['programa'] = [
            'id' => 0,
            'codigo' => 'COT',
            'nombre' => 'Constancia COT electrónico',
            'permite_disparo_al_grabar' => false,
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function armarDesdeVenta(Venta $venta, string $modo = 'OPERATIVO', ?string $soloFormulario = null): array
    {
        $venta->loadMissing(['puntoventas', 'pedidos', 'remitos']);
        $contexto = ComprobanteImpresionResolverSupport::contextoDesdeVenta($venta);
        $docs = $this->documentosDesdeVenta($venta);
        $pack = $this->packDesdeContexto($contexto, $docs, $modo, $soloFormulario);

        return $this->payload($contexto, $pack, 'FACTURA', (int) $venta->id, $modo, $soloFormulario, $docs);
    }

    /**
     * @return array<string, mixed>
     */
    public function armarDesdePedido(Pedido $pedido, string $modo = 'OPERATIVO', ?string $soloFormulario = null, bool $packCompleto = false): array
    {
        $contexto = ComprobanteImpresionResolverSupport::contextoDesdePedido($pedido);
        $incluirHermanos = $packCompleto || $soloFormulario === null;
        $docs = $this->documentosDesdePedido($pedido, $incluirHermanos);
        $formulario = $incluirHermanos ? $soloFormulario : ($soloFormulario ?? ComprobanteImpresionFormulario::PEDIDO);
        $pack = $this->packDesdeContexto($contexto, $docs, $modo, $formulario);

        return $this->payload($contexto, $pack, 'PEDIDO', (int) $pedido->id, $modo, $formulario, $docs);
    }

    /**
     * @return array<string, mixed>
     */
    public function armarDesdeRemito(Remito $remito, string $modo = 'OPERATIVO', ?string $soloFormulario = null, bool $packCompleto = false): array
    {
        $contexto = ComprobanteImpresionResolverSupport::contextoDesdeRemito($remito);
        $incluirHermanos = $packCompleto || $soloFormulario === null;
        $docs = $this->documentosDesdeRemito($remito, $incluirHermanos);
        $formulario = $incluirHermanos ? $soloFormulario : ($soloFormulario ?? ComprobanteImpresionFormulario::REMITO);
        $pack = $this->packDesdeContexto($contexto, $docs, $modo, $formulario);

        return $this->payload($contexto, $pack, 'REMITO', (int) $remito->id, $modo, $formulario, $docs);
    }

    /**
     * Sesión cabecera: una línea por copia de papel para imprimir N facturas del reparto.
     *
     * @param  list<int>  $ventaIds
     * @param  array<string, mixed>  $retorno
     * @return array<string, mixed>
     */
    public function armarDesdeReparto(array $ventaIds, string $modo = 'OPERATIVO', string $etiqueta = '', array $retorno = []): array
    {
        $ventaIds = array_values(array_unique(array_filter(array_map('intval', $ventaIds))));
        if ($ventaIds === []) {
            throw new \InvalidArgumentException('No hay comprobantes en este reparto para el filtro actual.');
        }

        $ventas = Venta::query()
            ->with(['puntoventas', 'pedidos', 'remitos', 'transportes', 'gastronomiaEmision', 'estacionamientoEmision'])
            ->whereIn('id', $ventaIds)
            ->get()
            ->keyBy('id');

        $idsOk = [];
        $primera = null;
        foreach ($ventaIds as $id) {
            $venta = $ventas->get($id);
            if (! $venta || $venta->gastronomiaEmision || $venta->estacionamientoEmision) {
                continue;
            }
            $idsOk[] = $id;
            $primera ??= $venta;
        }
        if ($primera === null || $idsOk === []) {
            throw new \InvalidArgumentException('No hay comprobantes imprimibles en este reparto.');
        }

        $base = $this->armarDesdeVenta($primera, $modo, ComprobanteImpresionFormulario::FACTURA);
        $packCabecera = [];
        $vistos = [];
        foreach ($base['pack'] as $linea) {
            if (ComprobanteImpresionPackSupport::esNas($linea)) {
                continue;
            }
            $clave = strtoupper((string) ($linea['copia_codigo'] ?? ''));
            if ($clave === '' || isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $packCabecera[] = $linea;
        }

        $transporteId = (int) ($primera->transporte_id ?? 0);
        if ($etiqueta === '') {
            $etiqueta = FacturaListadoSupport::etiquetaReparto($primera);
            if ($etiqueta === '') {
                $etiqueta = 'Sin reparto';
            }
        }

        $base['origen_tipo'] = 'REPARTO';
        $base['origen_id'] = $transporteId;
        $base['lote_venta_ids'] = $idsOk;
        $base['lote_etiqueta'] = $etiqueta;
        $base['lote_cantidad'] = count($idsOk);
        $base['lote_retorno'] = $retorno;
        $base['solo_formulario'] = ComprobanteImpresionFormulario::FACTURA;
        $base['pack'] = $this->aplicarImpresoraUsuarioAlPack($packCabecera);
        $base['faltante_impresora_papel'] = $this->packFaltaImpresoraPapel($base['pack']);
        $base['documentos'] = [
            'REPARTO' => [
                'id' => $transporteId,
                'codigo' => 'Reparto '.$etiqueta,
                'nombre' => count($idsOk).' comprobantes',
                'fecha' => '',
            ],
        ];
        $base['motivo'] = 'Impresión por reparto: elegí la copia y se envía a todas las facturas del filtro';
        $base['tiene_venta'] = false;

        return $base;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function armarDesdeRepartoPorFiltros(array $filtros, int $transporteId, string $modo = 'OPERATIVO'): array
    {
        $ids = $this->facturacionService->idsIndexPorReparto($filtros, $transporteId);

        return $this->armarDesdeReparto(
            $ids,
            $modo,
            '',
            FacturaListadoFiltros::paraQueryString($filtros)
        );
    }

    /**
     * @param  array<string, mixed>  $sesion
     * @return array<string, mixed>
     */
    /**
     * @param  list<int>|null  $soloPackIdxs
     */
    public function ejecutar(array $sesion, ?array $soloPackIdxs = null, bool $soloCopia = false): array
    {
        if (! empty($sesion['lote_venta_ids'])) {
            return $this->ejecutarLoteCabecera($sesion, $soloPackIdxs, $soloCopia);
        }

        ini_set('memory_limit', '512M');
        $modo = (string) ($sesion['modo'] ?? 'OPERATIVO');
        $resultados = [];
        $usuarioId = Auth::id() ? (int) Auth::id() : null;
        $pack = $sesion['pack'] ?? [];
        $soloPackIdxs = $this->normalizarPackIdxs($soloPackIdxs, $pack);
        $partes = ComprobanteImpresionPackSupport::idxsPapelYNas($pack, $soloPackIdxs, $soloCopia);

        $nasSync = $soloCopia && $partes['papel'] === [] && $partes['nas'] !== [];
        if ($nasSync) {
            foreach ($partes['nas'] as $idx) {
                $resultados[$idx] = $this->ejecutarLinea($pack[$idx] ?? [], $modo, $usuarioId);
            }

            return $this->payloadEjecucion($sesion, $resultados, null);
        }

        foreach ($partes['papel'] as $idx) {
            $resultados[$idx] = $this->resultadoPendiente(
                $pack[$idx] ?? [],
                'Imprimiendo en segundo plano…',
                ComprobanteImpresionLog::MEDIO_IMPRESORA
            );
        }
        foreach ($partes['nas'] as $idx) {
            $resultados[$idx] = $this->resultadoPendiente(
                $pack[$idx] ?? [],
                'Archivando en segundo plano (no va al PDF de Acrobat)',
                ComprobanteImpresionLog::MEDIO_ARCHIVO
            );
        }

        dispatch(function () use ($sesion, $partes, $modo, $usuarioId) {
            app(self::class)->ejecutarPapelYNasDiferido($sesion, $partes, $modo, $usuarioId);
        })->afterResponse();

        return $this->payloadEjecucion($sesion, $resultados, $this->rutaPdfSesionEstable($sesion));
    }

    /**
     * @param  list<int>|null  $soloPackIdxs
     * @param  array<string, mixed>  $sesion
     * @return array<string, mixed>
     */
    private function ejecutarLoteCabecera(array $sesion, ?array $soloPackIdxs, bool $soloCopia): array
    {
        $expandida = $this->expandirPackLote($sesion, $soloPackIdxs, $soloCopia);
        $cantidad = count($expandida['pack'] ?? []);
        unset($expandida['lote_venta_ids']);
        $this->ejecutar($expandida, null, false);

        $idxs = $this->normalizarPackIdxs($soloPackIdxs, $sesion['pack'] ?? []);
        $resultados = [];
        foreach ($sesion['pack'] ?? [] as $idx => $linea) {
            if ($idxs !== null && ! in_array((int) $idx, $idxs, true)) {
                continue;
            }
            $resultados[$idx] = $this->resultadoPendiente(
                $linea,
                $cantidad.' copias en segundo plano (todas las facturas del reparto)',
                ComprobanteImpresionLog::MEDIO_IMPRESORA
            );
        }

        return $this->payloadEjecucion($sesion, $resultados, $this->rutaPdfSesionEstable($sesion));
    }

    /**
     * @param  list<int>|null  $soloPackIdxs
     * @param  array<string, mixed>  $sesion
     * @return array<string, mixed>
     */
    private function expandirPackLote(array $sesion, ?array $soloPackIdxs, bool $soloCopia = false): array
    {
        $ventaIds = array_values(array_filter(array_map('intval', $sesion['lote_venta_ids'] ?? [])));
        $packCabecera = $sesion['pack'] ?? [];
        $idxs = $this->normalizarPackIdxs($soloPackIdxs, $packCabecera);
        $codigos = [];
        foreach ($packCabecera as $idx => $linea) {
            if ($idxs !== null && ! in_array((int) $idx, $idxs, true)) {
                continue;
            }
            if ($soloCopia && $idxs !== null && ! in_array((int) $idx, $idxs, true)) {
                continue;
            }
            $codigos[] = strtoupper((string) ($linea['copia_codigo'] ?? ''));
        }
        $codigos = array_values(array_unique(array_filter($codigos)));
        $pack = [];
        $modo = (string) ($sesion['modo'] ?? 'OPERATIVO');
        if ($codigos !== []) {
            foreach ($ventaIds as $ventaId) {
                $venta = Venta::query()
                    ->with(['puntoventas', 'pedidos', 'remitos', 'gastronomiaEmision', 'estacionamientoEmision'])
                    ->find($ventaId);
                if (! $venta || $venta->gastronomiaEmision || $venta->estacionamientoEmision) {
                    continue;
                }
                $una = $this->armarDesdeVenta($venta, $modo, ComprobanteImpresionFormulario::FACTURA);
                foreach ($una['pack'] as $linea) {
                    if (ComprobanteImpresionPackSupport::esNas($linea)) {
                        continue;
                    }
                    if (in_array(strtoupper((string) ($linea['copia_codigo'] ?? '')), $codigos, true)) {
                        $pack[] = $linea;
                    }
                }
            }
        }
        $sesion['pack'] = $this->aplicarImpresoraUsuarioAlPack($pack);

        return $sesion;
    }

    /**
     * @param  array{papel: list<int>, nas: list<int>}  $partes
     */
    public function ejecutarPapelYNasDiferido(array $sesion, array $partes, string $modo, ?int $usuarioId): void
    {
        ini_set('memory_limit', '512M');
        $pack = $sesion['pack'] ?? [];
        $resultados = [];
        $pdfsSesion = [];
        $idxsPapel = $partes['papel'] ?? [];
        $idxsNas = $partes['nas'] ?? [];

        try {
            $pdfsLote = $this->generarPdfsLoteFactura($sesion, $idxsPapel === [] ? [] : $idxsPapel);
            foreach ($idxsPapel as $idx) {
                $resultado = $this->ejecutarLinea($pack[$idx] ?? [], $modo, $usuarioId, null, $pdfsLote[$idx] ?? null);
                $resultados[$idx] = $resultado;
                if (
                    ! empty($resultado['pdf_path'])
                    && is_file($resultado['pdf_path'])
                    && ComprobanteImpresionPackSupport::vaAlPdfSesion($resultado)
                ) {
                    $pdfsSesion[] = $resultado['pdf_path'];
                }
            }
            $this->fusionar($pdfsSesion, $sesion);
            foreach ($idxsNas as $idx) {
                $linea = $pack[$idx] ?? [];
                $reuso = ComprobanteImpresionPackSupport::pdfReusoParaNas($linea, $resultados);
                $this->ejecutarLinea($linea, $modo, $usuarioId, null, $reuso);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $resultados
     * @return array<string, mixed>
     */
    private function payloadEjecucion(array $sesion, array $resultados, ?string $pdfSesion): array
    {
        return [
            'origen_tipo' => $sesion['origen_tipo'] ?? null,
            'origen_id' => $sesion['origen_id'] ?? null,
            'resultados' => $resultados,
            'pdf_sesion' => $pdfSesion,
            'ok' => collect($resultados)->contains(
                fn ($r) => ! empty($r['ok']) || ($r['estado'] ?? '') === ComprobanteImpresionLog::ESTADO_PENDIENTE
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return array<string, mixed>
     */
    private function resultadoPendiente(array $linea, string $mensaje, string $medio): array
    {
        return array_merge($linea, [
            'ok' => true,
            'mensaje' => $mensaje,
            'estado' => ComprobanteImpresionLog::ESTADO_PENDIENTE,
            'pdf_path' => null,
            'incluir_en_pdf_sesion' => $medio !== ComprobanteImpresionLog::MEDIO_ARCHIVO
                && (bool) ($linea['incluir_en_pdf_sesion'] ?? false),
            'es_nas' => $medio === ComprobanteImpresionLog::MEDIO_ARCHIVO,
        ]);
    }

    /**
     * PDF de papel para Acrobat. Siempre se regenera: descripciones, leyendas
     * y plantilla pueden haber cambiado sin tocar venta.updated_at.
     *
     * @param  list<int>|null  $soloPackIdxs
     */
    public function asegurarPdfSesionPapel(array $sesion, ?array $soloPackIdxs = null): ?string
    {
        if (! empty($sesion['lote_venta_ids'])) {
            $expandida = $this->expandirPackLote($sesion, $soloPackIdxs);
            unset($expandida['lote_venta_ids']);

            return $this->asegurarPdfSesionPapel($expandida, null);
        }

        $pack = $sesion['pack'] ?? [];
        $partes = ComprobanteImpresionPackSupport::idxsPapelYNas($pack, $soloPackIdxs, false);
        $idxsPapel = $partes['papel'];
        if ($idxsPapel === []) {
            return null;
        }

        $pdfsLote = $this->generarPdfsLoteFactura($sesion, $idxsPapel);
        $rutas = [];
        foreach ($idxsPapel as $idx) {
            $linea = $pack[$idx] ?? [];
            $path = $pdfsLote[$idx] ?? null;
            if (! is_string($path) || ! is_file($path)) {
                try {
                    $path = $this->generarPdf(
                        (string) ($linea['formulario'] ?? ''),
                        (int) ($linea['documento_id'] ?? 0),
                        (string) ($linea['leyenda'] ?? 'ORIGINAL'),
                        $linea
                    );
                } catch (\Throwable $e) {
                    report($e);
                    continue;
                }
            }
            if (is_string($path) && is_file($path) && ComprobanteImpresionPackSupport::vaAlPdfSesion($linea + ['incluir_en_pdf_sesion' => $linea['incluir_en_pdf_sesion'] ?? true])) {
                $rutas[] = $path;
            }
        }

        return $this->fusionar($rutas, $sesion, $idxsPapel);
    }

    /**
     * @param  list<int>|null  $idxsPapel
     */
    public function rutaPdfSesionEstable(array $sesion, ?array $idxsPapel = null): string
    {
        $dir = storage_path('pdf/impresion_sesion');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $marca = 'ultimo';
        if ($idxsPapel !== null) {
            $norm = array_values(array_unique(array_map('intval', $idxsPapel)));
            sort($norm);
            $marca = 'idx-'.implode('-', $norm);
        }

        return sprintf(
            '%s/sesion-%s-%d-%s.pdf',
            $dir,
            strtolower((string) ($sesion['origen_tipo'] ?? 'doc')),
            (int) ($sesion['origen_id'] ?? 0),
            $marca
        );
    }

    public function dispararAlGrabarVenta(int $ventaId): void
    {
        if ($ventaId <= 0) {
            return;
        }

        $venta = Venta::query()->with(['puntoventas', 'gastronomiaEmision', 'estacionamientoEmision'])->find($ventaId);
        if (! $venta) {
            return;
        }
        if ($venta->gastronomiaEmision || $venta->estacionamientoEmision) {
            return;
        }

        $sesion = $this->armarDesdeVenta($venta, 'OPERATIVO');
        $programa = $sesion['programa'] ?? null;
        if (! $programa || empty($programa['permite_disparo_al_grabar'])) {
            return;
        }

        $usuarioId = Auth::id();
        if (! $usuarioId) {
            return;
        }

        if (! $this->usuarioDisparaAlGrabar($usuarioId)) {
            return;
        }

        $this->ejecutar($sesion);
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return array<string, mixed>
     */
    public function reintentarLog(ComprobanteImpresionLog $log): array
    {
        $linea = [
            'formulario' => $log->formulario,
            'copia_codigo' => $log->copia_codigo,
            'leyenda' => $log->copia_leyenda,
            'salida_id' => $log->salida_id,
            'destino_path' => $log->destino_path,
            'incluir_en_pdf_sesion' => false,
            'documento_id' => $log->documento_id,
            'documento_tipo' => $log->documento_tipo,
            'medio' => $log->medio,
        ];
        $resultado = $this->ejecutarLinea($linea, $log->modo ?: 'OPERATIVO', $log->usuario_id, $log);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @param  array<string, array{id: int, codigo: string, fecha: string}>  $docs
     * @return list<array<string, mixed>>
     */
    private function packDesdeContexto(array $contexto, array $docs, string $modo, ?string $soloFormulario): array
    {
        $programa = $contexto['programa'] ?? null;
        if (! $programa) {
            return [];
        }

        return ComprobanteImpresionResolverSupport::pack(
            $programa,
            $docs,
            $modo,
            $soloFormulario,
            $modo === 'CONSULTA'
        );
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @param  list<array<string, mixed>>  $pack
     * @param  array<string, array{id: int, codigo: string, fecha: string}>  $docs
     * @return array<string, mixed>
     */
    private function payload(
        array $contexto,
        array $pack,
        string $origenTipo,
        int $origenId,
        string $modo,
        ?string $soloFormulario,
        array $docs
    ): array {
        $programa = $contexto['programa'] ?? null;
        $pack = $this->aplicarImpresoraUsuarioAlPack($pack);
        $impresoraUsuario = $this->resumenImpresoraUsuario();

        return [
            'origen_tipo' => $origenTipo,
            'origen_id' => $origenId,
            'modo' => $modo,
            'solo_formulario' => $soloFormulario,
            'motivo' => $contexto['motivo'] ?? '',
            'empresa_id' => $contexto['empresa_id'] ?? null,
            'transporte_id' => $contexto['transporte_id'] ?? null,
            'provincia_entrega_id' => $contexto['provincia_entrega_id'] ?? null,
            'programa' => $programa ? [
                'id' => $programa->id,
                'codigo' => $programa->codigo,
                'nombre' => $programa->nombre,
                'permite_disparo_al_grabar' => (bool) $programa->permite_disparo_al_grabar,
            ] : null,
            'pack' => $pack,
            'documentos' => $docs,
            'tiene_venta' => isset($docs[ComprobanteImpresionFormulario::FACTURA]),
            'impresora_usuario' => $impresoraUsuario,
            'faltante_impresora_papel' => $this->packFaltaImpresoraPapel($pack),
        ];
    }

    /**
     * @return array<string, array{id: int, codigo: string, fecha: string}>
     */
    private function documentosDesdeVenta(Venta $venta): array
    {
        $docs = [
            ComprobanteImpresionFormulario::FACTURA => [
                'id' => (int) $venta->id,
                'codigo' => (string) $venta->codigo,
                'fecha' => $this->fechaYmd($venta->fecha),
                'nombre' => (string) ($venta->nombre ?? ''),
            ],
        ];
        if ($venta->remito_id) {
            $remito = $venta->relationLoaded('remitos') ? $venta->remitos : Remito::query()->find($venta->remito_id);
            if ($remito) {
                $docs[ComprobanteImpresionFormulario::REMITO] = [
                    'id' => (int) $remito->id,
                    'codigo' => (string) ($remito->codigo ?: $remito->id),
                    'fecha' => $this->fechaYmd($remito->fecha),
                ];
            }
        }
        if ($venta->pedido_id) {
            $pedido = $venta->relationLoaded('pedidos') ? $venta->pedidos : Pedido::query()->find($venta->pedido_id);
            if ($pedido) {
                $docs[ComprobanteImpresionFormulario::PEDIDO] = [
                    'id' => (int) $pedido->id,
                    'codigo' => (string) ($pedido->codigo ?: $pedido->id),
                    'fecha' => $this->fechaYmd($pedido->fecha),
                ];
            }
        }

        return $docs;
    }

    /**
     * @return array<string, array{id: int, codigo: string, fecha: string}>
     */
    private function documentosDesdePedido(Pedido $pedido, bool $packCompleto): array
    {
        $docs = [
            ComprobanteImpresionFormulario::PEDIDO => [
                'id' => (int) $pedido->id,
                'codigo' => (string) ($pedido->codigo ?: $pedido->id),
                'fecha' => $this->fechaYmd($pedido->fecha),
                'nombre' => (string) (optional($pedido->clientes)->nombre ?? ''),
            ],
        ];
        if (! $packCompleto) {
            return $docs;
        }
        $ventas = Venta::query()->where('pedido_id', $pedido->id)->orderByDesc('id')->get();
        $ventaVisible = PedidoFacturaAnitaArchivosSupport::ventasVisiblesEnPedido($ventas)->first();
        if ($ventaVisible) {
            return array_merge($this->documentosDesdeVenta($ventaVisible), $docs);
        }
        $remito = Remito::query()->where('pedido_id', $pedido->id)->orderByDesc('id')->first();
        if ($remito) {
            $docs[ComprobanteImpresionFormulario::REMITO] = [
                'id' => (int) $remito->id,
                'codigo' => (string) ($remito->codigo ?: $remito->id),
                'fecha' => $this->fechaYmd($remito->fecha),
            ];
        }

        return $docs;
    }

    /**
     * @return array<string, array{id: int, codigo: string, fecha: string}>
     */
    private function documentosDesdeRemito(Remito $remito, bool $packCompleto): array
    {
        $docs = [
            ComprobanteImpresionFormulario::REMITO => [
                'id' => (int) $remito->id,
                'codigo' => (string) ($remito->codigo ?: $remito->id),
                'fecha' => $this->fechaYmd($remito->fecha),
                'nombre' => (string) (optional($remito->clientes)->nombre ?? ''),
            ],
        ];
        if (! $packCompleto) {
            return $docs;
        }
        $venta = $this->ventaOperativaDeRemito($remito);
        if ($venta) {
            $docs = array_merge($this->documentosDesdeVenta($venta), $docs);
        } elseif ($remito->pedido_id) {
            $pedido = Pedido::query()->find($remito->pedido_id);
            if ($pedido) {
                $docs[ComprobanteImpresionFormulario::PEDIDO] = [
                    'id' => (int) $pedido->id,
                    'codigo' => (string) ($pedido->codigo ?: $pedido->id),
                    'fecha' => $this->fechaYmd($pedido->fecha),
                ];
            }
        }

        return $docs;
    }

    private function ventaOperativaDeRemito(Remito $remito): ?Venta
    {
        $venta = $remito->venta_id
            ? Venta::query()->find($remito->venta_id)
            : Venta::query()->where('remito_id', $remito->id)->orderByDesc('id')->first();
        if (! $venta || ! PedidoFacturaAnitaArchivosSupport::esVentaVisible($venta)) {
            return null;
        }

        return $venta;
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return array<string, mixed>
     */
    private function ejecutarLinea(array $linea, string $modo, ?int $usuarioId, ?ComprobanteImpresionLog $logExistente = null, ?string $pdfPrevio = null): array
    {
        $formulario = (string) ($linea['formulario'] ?? '');
        $documentoId = (int) ($linea['documento_id'] ?? 0);
        $leyenda = (string) ($linea['leyenda'] ?? 'ORIGINAL');
        $pdfPath = null;
        $errorPdf = null;

        try {
            $pdfPath = ($pdfPrevio && is_file($pdfPrevio))
                ? $pdfPrevio
                : $this->generarPdf($formulario, $documentoId, $leyenda, $linea);
        } catch (\Throwable $e) {
            $errorPdf = $e->getMessage();
        }

        $salida = $this->resolverSalida($linea);
        $destino = $linea['destino_path'] ?? null;
        $medio = (string) ($linea['medio'] ?? 'IMPRESORA');
        $estado = ComprobanteImpresionLog::ESTADO_ERROR;
        $mensaje = $errorPdf ?? 'Sin PDF';
        $ok = false;

        if ($pdfPath && is_file($pdfPath)) {
            if ($salida) {
                $despacho = ComprobanteImpresionDespachoSupport::despachar($pdfPath, $salida, $destino);
                $ok = $despacho['ok'];
                $mensaje = $despacho['mensaje'];
                $medio = $despacho['medio'];
                $estado = $ok
                    ? ComprobanteImpresionLog::ESTADO_OK
                    : ($medio === ComprobanteImpresionLog::MEDIO_ARCHIVO
                        ? ComprobanteImpresionLog::ESTADO_PENDIENTE
                        : ComprobanteImpresionLog::ESTADO_ERROR);
            } else {
                $ok = true;
                $mensaje = 'PDF generado (sin salida: no se imprimió ni archivó)';
                $estado = ComprobanteImpresionLog::ESTADO_OK;
            }
        }

        $payloadLog = [
            'documento_tipo' => $linea['documento_tipo'] ?? $formulario,
            'documento_id' => $documentoId,
            'formulario' => $formulario,
            'copia_codigo' => (string) ($linea['copia_codigo'] ?? ''),
            'copia_leyenda' => $leyenda,
            'salida_id' => $salida?->id,
            'destino_path' => $destino,
            'estado' => $estado,
            'mensaje' => $mensaje,
            'intentos' => $logExistente ? ((int) $logExistente->intentos + 1) : 1,
            'medio' => $medio,
            'modo' => $modo,
            'usuario_id' => $usuarioId,
        ];

        if ($logExistente) {
            $logExistente->update($payloadLog);
        } else {
            ComprobanteImpresionLog::query()->create($payloadLog);
        }

        return array_merge($linea, [
            'ok' => $ok,
            'mensaje' => $mensaje,
            'estado' => $estado,
            'pdf_path' => $pdfPath,
            'incluir_en_pdf_sesion' => (bool) ($linea['incluir_en_pdf_sesion'] ?? false),
        ]);
    }

    /**
     * @param  array<string, mixed>  $sesion
     * @return array<int, string>
     */
    /**
     * @param  list<int>|null  $soloPackIdxs
     */
    private function generarPdfsLoteFactura(array $sesion, ?array $soloPackIdxs = null): array
    {
        $trabajos = [];
        foreach ($sesion['pack'] ?? [] as $idx => $linea) {
            if ($soloPackIdxs !== null && ! in_array($idx, $soloPackIdxs, true)) {
                continue;
            }
            $formulario = (string) ($linea['formulario'] ?? '');
            if ($formulario === ComprobanteImpresionFormulario::FACTURA) {
                $trabajos[$idx] = [
                    'leyenda' => (string) ($linea['leyenda'] ?? 'ORIGINAL'),
                    'omitir_remito' => true,
                    'solo_remito' => false,
                ];
            } elseif ($formulario === ComprobanteImpresionFormulario::REMITO) {
                $ventaId = $this->ventaIdDeRemito((int) ($linea['documento_id'] ?? 0));
                if ($ventaId > 0) {
                    $trabajos[$idx] = [
                        'leyenda' => (string) ($linea['leyenda'] ?? 'ORIGINAL'),
                        'omitir_remito' => false,
                        'solo_remito' => true,
                    ];
                }
            }
        }
        if ($trabajos === []) {
            return [];
        }

        $ventaIdsPack = [];
        foreach ($sesion['pack'] ?? [] as $linea) {
            if (($linea['formulario'] ?? '') === ComprobanteImpresionFormulario::FACTURA) {
                $vid = (int) ($linea['documento_id'] ?? 0);
                if ($vid > 0) {
                    $ventaIdsPack[$vid] = true;
                }
            }
        }
        if (count($ventaIdsPack) > 1) {
            return [];
        }

        $ventaId = (int) (($sesion['documentos'][ComprobanteImpresionFormulario::FACTURA]['id'] ?? 0)
            ?: (($sesion['origen_tipo'] ?? '') === 'FACTURA' ? ($sesion['origen_id'] ?? 0) : 0));
        if ($ventaId <= 0) {
            foreach ($sesion['pack'] ?? [] as $linea) {
                if (($linea['formulario'] ?? '') === ComprobanteImpresionFormulario::FACTURA) {
                    $ventaId = (int) ($linea['documento_id'] ?? 0);
                    break;
                }
            }
        }
        if ($ventaId <= 0) {
            foreach ($sesion['pack'] ?? [] as $linea) {
                if (($linea['formulario'] ?? '') === ComprobanteImpresionFormulario::REMITO) {
                    $ventaId = $this->ventaIdDeRemito((int) ($linea['documento_id'] ?? 0));
                    if ($ventaId > 0) {
                        break;
                    }
                }
            }
        }
        if ($ventaId <= 0) {
            return [];
        }

        try {
            $ctx = $this->facturacionService->prepararContextoPdfFactura($ventaId);

            return $this->facturacionService->renderPdfFacturaLote($ctx, $trabajos);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    private function ventaIdDeRemito(int $remitoId): int
    {
        if ($remitoId <= 0) {
            return 0;
        }
        $ventaId = (int) (Venta::query()->where('remito_id', $remitoId)->value('id') ?? 0);
        if ($ventaId <= 0) {
            $ventaId = (int) (Remito::query()->whereKey($remitoId)->value('venta_id') ?? 0);
        }
        if ($ventaId <= 0 || ! PedidoFacturaAnitaArchivosSupport::esVentaIdVisible($ventaId)) {
            return 0;
        }

        return $ventaId;
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private function generarPdf(string $formulario, int $documentoId, string $leyenda, array $linea = []): string
    {
        return match ($formulario) {
            ComprobanteImpresionFormulario::FACTURA => $this->facturacionService->generarPdfFacturaArchivo(
                $documentoId,
                $leyenda,
                true
            ),
            ComprobanteImpresionFormulario::PEDIDO => tap(
                $this->pedidoService->generarPdfPedidoArchivo($documentoId),
                static function (string $ruta) {
                    if ($ruta === '') {
                        throw new \RuntimeException('Pedido no encontrado');
                    }
                }
            ),
            ComprobanteImpresionFormulario::REMITO => $this->generarPdfRemito($documentoId, $leyenda),
            ComprobanteImpresionFormulario::COT => $this->cotConstanciaPdfService->generarPdf(
                $documentoId,
                isset($linea['remito_envio_id']) ? ((int) $linea['remito_envio_id'] ?: null) : null
            ),
            default => throw new \RuntimeException('Formulario no implementado: '.$formulario),
        };
    }

    private function resolverSalida(array $linea): ?Salida
    {
        $salidaId = (int) ($linea['salida_id'] ?? 0);
        if ($salidaId > 0) {
            return Salida::query()->find($salidaId);
        }

        $usuarioId = Auth::id() ? (int) Auth::id() : null;

        return $this->salidaUsuarioParaFormulario((string) ($linea['formulario'] ?? ''), $usuarioId);
    }

    /**
     * @param  list<array<string, mixed>>  $pack
     * @return list<array<string, mixed>>
     */
    private function aplicarImpresoraUsuarioAlPack(array $pack): array
    {
        $usuarioId = Auth::id() ? (int) Auth::id() : null;
        foreach ($pack as $i => $linea) {
            $esNas = ComprobanteImpresionPackSupport::esNas($linea);
            $pack[$i]['es_nas'] = $esNas;
            if ($esNas) {
                $pack[$i]['incluir_en_pdf_sesion'] = false;
            }
            $hereda = ComprobanteImpresionSalidaUsuarioSupport::heredaImpresoraUsuario($linea);
            $pack[$i]['hereda_usuario'] = $hereda;
            if (! $hereda) {
                $pack[$i]['salida_usuario_ok'] = true;
                continue;
            }
            $salida = $this->salidaUsuarioParaFormulario((string) ($linea['formulario'] ?? ''), $usuarioId);
            $pack[$i]['salida_nombre'] = $salida?->nombre ?? 'Sin impresora seteada';
            $pack[$i]['salida_usuario_ok'] = $salida !== null;
        }

        return $pack;
    }

    /**
     * @return array{programa: string, salida_id: int|null, nombre: ?string, ubicacion: string, disparar_al_grabar: bool}
     */
    private function resumenImpresoraUsuario(): array
    {
        $usuarioId = Auth::id() ? (int) Auth::id() : null;
        $seteo = $this->seteoUsuarioComprobantes($usuarioId);
        $salida = $seteo?->salidas;

        return [
            'programa' => ComprobanteImpresionSalidaUsuarioSupport::programaUnificado(),
            'salida_id' => $salida?->id ? (int) $salida->id : null,
            'nombre' => $salida?->nombre,
            'ubicacion' => trim((string) ($salida?->ubicacion ?? '')),
            'disparar_al_grabar' => $this->usuarioDisparaAlGrabar($usuarioId),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pack
     */
    private function packFaltaImpresoraPapel(array $pack): bool
    {
        foreach ($pack as $linea) {
            if (! empty($linea['hereda_usuario']) && empty($linea['salida_usuario_ok'])) {
                return true;
            }
        }

        return false;
    }

    private function salidaUsuarioParaFormulario(string $formulario, ?int $usuarioId): ?Salida
    {
        $seteo = $this->seteoUsuarioComprobantes($usuarioId, $formulario);

        return $seteo?->salidas;
    }

    private function seteoUsuarioComprobantes(?int $usuarioId, ?string $formulario = null): mixed
    {
        if (! $usuarioId) {
            return null;
        }

        foreach (ComprobanteImpresionSalidaUsuarioSupport::programasBusqueda($formulario) as $programa) {
            $seteo = $this->seteosalidaRepository->buscaSeteo($usuarioId, $programa);
            if ($seteo?->salidas) {
                return $seteo;
            }
        }

        return $this->seteosalidaRepository->buscaSeteo(
            $usuarioId,
            ComprobanteImpresionSalidaUsuarioSupport::programaUnificado()
        );
    }

    private function usuarioDisparaAlGrabar(?int $usuarioId): bool
    {
        if (! $usuarioId) {
            return false;
        }

        $unificado = $this->seteosalidaRepository->buscaSeteo(
            $usuarioId,
            ComprobanteImpresionSalidaUsuarioSupport::programaUnificado()
        );
        if ($unificado) {
            return (bool) $unificado->disparar_al_grabar;
        }

        $factura = $this->seteosalidaRepository->buscaSeteo($usuarioId, SeteoSalidaProgramaSupport::VENTAS_FACTURA);

        return (bool) ($factura?->disparar_al_grabar);
    }

    /**
     * @param  list<int>|null  $idxs
     * @param  array<int, mixed>  $pack
     * @return list<int>|null
     */
    private function normalizarPackIdxs(?array $idxs, array $pack): ?array
    {
        if ($idxs === null) {
            return null;
        }
        $normalizados = array_values(array_unique(array_map('intval', $idxs)));
        if ($normalizados === []) {
            throw new \InvalidArgumentException('Elegí al menos una copia para imprimir.');
        }
        foreach ($normalizados as $idx) {
            if (! array_key_exists($idx, $pack)) {
                throw new \InvalidArgumentException('La copia elegida no está en la ruta de impresión.');
            }
        }

        return $normalizados;
    }

    /**
     * @param  list<string>  $rutas
     * @param  array<string, mixed>  $sesion
     * @param  list<int>|null  $idxsPapel
     */
    private function fusionar(array $rutas, array $sesion, ?array $idxsPapel = null): ?string
    {
        $rutas = array_values(array_filter($rutas, fn ($r) => is_string($r) && is_file($r)));
        if ($rutas === []) {
            return null;
        }

        $destino = $this->rutaPdfSesionEstable($sesion, $idxsPapel);
        $dir = dirname($destino);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return $rutas[0];
        }

        $rutas = array_values(array_unique($rutas));
        if (count($rutas) === 1) {
            copy($rutas[0], $destino);

            return $destino;
        }

        $temporales = [];
        try {
            $merger = new PDFMerger;
            foreach ($rutas as $i => $ruta) {
                $tmp = $dir.'/src-'.$i.'-'.basename($ruta);
                if ($tmp !== $ruta) {
                    copy($ruta, $tmp);
                    $temporales[] = $tmp;
                    $ruta = $tmp;
                }
                $merger->addPDF($ruta, 'all', $this->orientacionPdf($ruta));
            }
            $merger->merge('file', $destino);
        } catch (\Throwable $e) {
            report($e);
            copy($rutas[0], $destino);
        } finally {
            foreach ($temporales as $tmp) {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }

        return is_file($destino) ? $destino : $rutas[0];
    }

    private function generarPdfRemito(int $documentoId, string $leyenda): string
    {
        $ventaId = $this->ventaIdDeRemito($documentoId);
        if ($ventaId > 0) {
            return $this->facturacionService->generarPdfFacturaArchivo($ventaId, $leyenda, false, true);
        }

        return $this->remitoService->generarPdfRemitoArchivo($documentoId);
    }

    private function orientacionPdf(string $ruta): string
    {
        try {
            $fpdi = new \setasign\Fpdi\Fpdi;
            $fpdi->setSourceFile($ruta);
            $template = $fpdi->importPage(1);
            $size = $fpdi->getTemplateSize($template);
            $ancho = (float) ($size['width'] ?? $size['w'] ?? 0);
            $alto = (float) ($size['height'] ?? $size['h'] ?? 0);

            return $ancho > $alto ? 'horizontal' : 'vertical';
        } catch (\Throwable $e) {
            return str_contains($ruta, '/remito/')
                ? 'horizontal'
                : 'vertical';
        }
    }

    private function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof Carbon) {
            return $fecha->format('Y-m-d');
        }
        $texto = trim((string) $fecha);
        if ($texto === '') {
            return date('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $texto)) {
            return substr($texto, 0, 10);
        }
        try {
            return Carbon::parse($texto)->format('Y-m-d');
        } catch (\Throwable $e) {
            return date('Y-m-d');
        }
    }
}
