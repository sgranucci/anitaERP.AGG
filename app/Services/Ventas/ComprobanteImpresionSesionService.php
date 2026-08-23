<?php

namespace App\Services\Ventas;

use App\Models\Configuracion\Salida;
use App\Models\Ventas\ComprobanteImpresionLog;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Remito;
use App\Models\Ventas\Venta;
use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use App\Support\Ventas\ComprobanteImpresionDespachoSupport;
use App\Support\Ventas\ComprobanteImpresionFormulario;
use App\Support\Ventas\ComprobanteImpresionNasPathSupport;
use App\Support\Ventas\ComprobanteImpresionResolverSupport;
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
    ) {
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
        $docs = $this->documentosDesdePedido($pedido, $packCompleto);
        $formulario = $packCompleto ? $soloFormulario : ($soloFormulario ?? ComprobanteImpresionFormulario::PEDIDO);
        $pack = $this->packDesdeContexto($contexto, $docs, $modo, $formulario);

        return $this->payload($contexto, $pack, 'PEDIDO', (int) $pedido->id, $modo, $formulario, $docs);
    }

    /**
     * @return array<string, mixed>
     */
    public function armarDesdeRemito(Remito $remito, string $modo = 'OPERATIVO', ?string $soloFormulario = null, bool $packCompleto = false): array
    {
        $contexto = ComprobanteImpresionResolverSupport::contextoDesdeRemito($remito);
        $docs = $this->documentosDesdeRemito($remito, $packCompleto);
        $formulario = $packCompleto ? $soloFormulario : ($soloFormulario ?? ComprobanteImpresionFormulario::REMITO);
        $pack = $this->packDesdeContexto($contexto, $docs, $modo, $formulario);

        return $this->payload($contexto, $pack, 'REMITO', (int) $remito->id, $modo, $formulario, $docs);
    }

    /**
     * @param  array<string, mixed>  $sesion
     * @return array<string, mixed>
     */
    public function ejecutar(array $sesion): array
    {
        ini_set('memory_limit', '512M');
        $modo = (string) ($sesion['modo'] ?? 'OPERATIVO');
        $resultados = [];
        $pdfsSesion = [];
        $usuarioId = Auth::id();

        $pdfsLote = $this->generarPdfsLoteFactura($sesion);
        foreach ($sesion['pack'] ?? [] as $idx => $linea) {
            $resultado = $this->ejecutarLinea($linea, $modo, $usuarioId, null, $pdfsLote[$idx] ?? null);
            $resultados[] = $resultado;
            if (! empty($resultado['pdf_path']) && is_file($resultado['pdf_path'])) {
                $pdfsSesion[] = $resultado['pdf_path'];
            }
        }

        $pdfSesion = $this->fusionar($pdfsSesion, $sesion);

        return [
            'origen_tipo' => $sesion['origen_tipo'] ?? null,
            'origen_id' => $sesion['origen_id'] ?? null,
            'resultados' => $resultados,
            'pdf_sesion' => $pdfSesion,
            'ok' => collect($resultados)->contains(fn ($r) => ! empty($r['ok'])),
        ];
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

        $seteo = $this->seteosalidaRepository->buscaSeteo($usuarioId, SeteoSalidaProgramaSupport::VENTAS_FACTURA);
        if (! $seteo || ! $seteo->disparar_al_grabar) {
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
            ],
        ];
        if (! $packCompleto) {
            return $docs;
        }
        $venta = Venta::query()->where('pedido_id', $pedido->id)->orderByDesc('id')->first();
        if ($venta) {
            $docs = array_merge($this->documentosDesdeVenta($venta), $docs);
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
            ],
        ];
        if (! $packCompleto) {
            return $docs;
        }
        $venta = $remito->venta_id
            ? Venta::query()->find($remito->venta_id)
            : Venta::query()->where('remito_id', $remito->id)->orderByDesc('id')->first();
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
                : $this->generarPdf($formulario, $documentoId, $leyenda);
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
    private function generarPdfsLoteFactura(array $sesion): array
    {
        $trabajos = [];
        foreach ($sesion['pack'] ?? [] as $idx => $linea) {
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
        if ($ventaId > 0) {
            return $ventaId;
        }

        return (int) (Remito::query()->whereKey($remitoId)->value('venta_id') ?? 0);
    }

    private function generarPdf(string $formulario, int $documentoId, string $leyenda): string
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
            default => throw new \RuntimeException('Formulario no implementado: '.$formulario),
        };
    }

    private function resolverSalida(array $linea): ?Salida
    {
        $salidaId = (int) ($linea['salida_id'] ?? 0);
        if ($salidaId > 0) {
            return Salida::query()->find($salidaId);
        }

        $usuarioId = Auth::id();
        if (! $usuarioId) {
            return null;
        }

        $programa = match ($linea['formulario'] ?? '') {
            ComprobanteImpresionFormulario::PEDIDO => SeteoSalidaProgramaSupport::VENTAS_PEDIDO,
            ComprobanteImpresionFormulario::REMITO => SeteoSalidaProgramaSupport::VENTAS_REMITO,
            default => SeteoSalidaProgramaSupport::VENTAS_FACTURA,
        };
        $seteo = $this->seteosalidaRepository->buscaSeteo($usuarioId, $programa);

        return $seteo?->salidas;
    }

    /**
     * @param  list<string>  $rutas
     * @param  array<string, mixed>  $sesion
     */
    private function fusionar(array $rutas, array $sesion): ?string
    {
        $rutas = array_values(array_filter($rutas, fn ($r) => is_string($r) && is_file($r)));
        if ($rutas === []) {
            return null;
        }

        $dir = storage_path('pdf/impresion_sesion');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return $rutas[0];
        }
        $nombre = sprintf(
            'sesion-%s-%s-%s.pdf',
            strtolower((string) ($sesion['origen_tipo'] ?? 'doc')),
            (int) ($sesion['origen_id'] ?? 0),
            date('YmdHis')
        );
        $destino = $dir.'/'.$nombre;

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
        $ventaId = (int) (Venta::query()->where('remito_id', $documentoId)->value('id') ?? 0);
        if ($ventaId <= 0) {
            $ventaId = (int) (Remito::query()->whereKey($documentoId)->value('venta_id') ?? 0);
        }
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
