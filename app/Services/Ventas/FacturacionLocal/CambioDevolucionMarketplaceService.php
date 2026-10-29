<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\CambioDevolucionMarketplaceArchivo;
use App\Models\Ventas\CambioDevolucionMarketplaceComprobante;
use App\Models\Ventas\CambioDevolucionMarketplaceEstado;
use App\Models\Ventas\CambioDevolucionMarketplaceLinea;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Venta;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
use App\Support\Ventas\FacturacionLocal\MotivoDevolucionSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceEstadosSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceLiquidacionSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePrecioSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceVentaConsultaSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Alta, edición, transiciones y adjuntos del legajo marketplace (Ferli FL).
 */
final class CambioDevolucionMarketplaceService
{
    public function __construct(
        private readonly CambioDevolucionMarketplaceEmisionService $emisionService,
        private readonly CambioDevolucionMarketplaceNcService $ncService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lineas
     */
    public function crear(array $data, array $lineas): CambioDevolucionMarketplace
    {
        return DB::transaction(function () use ($data, $lineas) {
            $local = LocalVenta::query()->findOrFail((int) $data['local_venta_id']);
            $ventaOriginal = Venta::query()->findOrFail((int) $data['venta_original_id']);

            $empresaId = (int) ($data['empresa_id'] ?? $local->empresa_id ?? 0);
            $camposMotivo = $this->camposMotivo($data);
            $cambio = CambioDevolucionMarketplace::query()->create([
                'numero' => $this->siguienteNumero(),
                'canal' => (string) ($data['canal'] ?? CambioDevolucionMarketplaceCatalogoSupport::CANAL_TIENDANUBE),
                'local_venta_id' => (int) $local->id,
                'empresa_id' => $empresaId > 0 ? $empresaId : null,
                'usuario_alta_id' => (int) (Auth::id() ?: 0),
                'tiendanube_pedido_id' => ((int) ($data['tiendanube_pedido_id'] ?? 0)) ?: null,
                'venta_original_id' => (int) $ventaOriginal->id,
                'cliente_id' => ((int) ($data['cliente_id'] ?? $ventaOriginal->cliente_id ?? 0)) ?: null,
                'receptor_nombre' => $data['receptor_nombre'] ?? null,
                'receptor_documento' => $data['receptor_documento'] ?? null,
                'estado' => CambioDevolucionMarketplaceEstadosSupport::BORRADOR,
                'motivo_devolucion_id' => $camposMotivo['motivo_devolucion_id'],
                'motivo_codigo' => $camposMotivo['motivo_codigo'],
                'motivo' => $data['motivo'] ?? null,
                'observacion' => $data['observacion'] ?? null,
            ]);

            $this->sincronizarLineas($cambio, $lineas);
            $this->registrarEstado($cambio, CambioDevolucionMarketplaceEstadosSupport::BORRADOR, 'Alta de legajo');

            return $cambio->fresh(['lineas', 'estados', 'archivos']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lineas
     */
    public function actualizar(CambioDevolucionMarketplace $cambio, array $data, array $lineas): CambioDevolucionMarketplace
    {
        if (! CambioDevolucionMarketplaceEstadosSupport::esEditable($cambio->estado)) {
            throw new InvalidArgumentException('El legajo no se puede editar en el estado actual.');
        }

        return DB::transaction(function () use ($cambio, $data, $lineas) {
            $cambio->canal = (string) ($data['canal'] ?? $cambio->canal);
            if (isset($data['local_venta_id'])) {
                $cambio->local_venta_id = (int) $data['local_venta_id'];
            }
            if (isset($data['venta_original_id'])) {
                $cambio->venta_original_id = (int) $data['venta_original_id'];
            }
            $cambio->tiendanube_pedido_id = ((int) ($data['tiendanube_pedido_id'] ?? 0)) ?: null;
            $cambio->cliente_id = ((int) ($data['cliente_id'] ?? 0)) ?: null;
            $cambio->receptor_nombre = $data['receptor_nombre'] ?? $cambio->receptor_nombre;
            $cambio->receptor_documento = $data['receptor_documento'] ?? $cambio->receptor_documento;
            $camposMotivo = $this->camposMotivo($data);
            $cambio->motivo_devolucion_id = $camposMotivo['motivo_devolucion_id'];
            $cambio->motivo_codigo = $camposMotivo['motivo_codigo'];
            $cambio->motivo = $data['motivo'] ?? $cambio->motivo;
            $cambio->observacion = $data['observacion'] ?? $cambio->observacion;
            $cambio->save();

            $this->sincronizarLineas($cambio, $lineas);

            return $cambio->fresh(['lineas', 'estados', 'archivos']);
        });
    }

    public function confirmar(CambioDevolucionMarketplace $cambio): CambioDevolucionMarketplace
    {
        $this->assertTransicion($cambio, CambioDevolucionMarketplaceEstadosSupport::ABIERTO);
        $cambio->loadMissing('lineas');
        if ($cambio->lineas->where('tipo', CambioDevolucionMarketplaceCatalogoSupport::TIPO_DEVOLVER)->isEmpty()) {
            throw new InvalidArgumentException('Debe cargar al menos una línea «A devolver» antes de confirmar.');
        }
        if ((int) $cambio->venta_original_id <= 0) {
            throw new InvalidArgumentException('Debe indicar la factura original.');
        }
        if (! MotivoDevolucionSupport::resolverDeCambio($cambio)) {
            throw new InvalidArgumentException('Elegí un motivo de devolución activo.');
        }

        $soloDevolucion = CambioDevolucionMarketplaceCatalogoSupport::esSoloDevolucion($cambio->lineas);
        $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::ABIERTO;
        $cambio->save();
        $this->registrarEstado(
            $cambio,
            CambioDevolucionMarketplaceEstadosSupport::ABIERTO,
            $soloDevolucion
                ? 'Legajo confirmado (solo devolución / reembolso)'
                : 'Legajo confirmado (cambio con reemplazo)'
        );

        return $cambio->fresh();
    }

    /**
     * Solo devolución: saltea la FAC de reemplazo y pasa a aguardar la mercadería a devolver.
     */
    public function continuarSoloDevolucion(CambioDevolucionMarketplace $cambio): CambioDevolucionMarketplace
    {
        $this->assertTransicion($cambio, CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION);
        $cambio->loadMissing('lineas');
        if (! CambioDevolucionMarketplaceCatalogoSupport::esSoloDevolucion($cambio->lineas)) {
            throw new InvalidArgumentException(
                'Este legajo tiene líneas de reemplazo. Emití la FAC de reemplazo o quitá esas líneas.'
            );
        }
        if ($cambio->lineas->where('tipo', CambioDevolucionMarketplaceCatalogoSupport::TIPO_DEVOLVER)->isEmpty()) {
            throw new InvalidArgumentException('Debe haber al menos una línea «A devolver».');
        }

        $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION;
        $cambio->save();
        $this->registrarEstado(
            $cambio,
            CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION,
            'Solo devolución: aguardando recepción (sin FAC de reemplazo)'
        );

        return $cambio->fresh();
    }

    /**
     * @return array{ok:bool,error?:string,cambio?:CambioDevolucionMarketplace,mensaje?:string}
     */
    public function emitirFacReemplazo(CambioDevolucionMarketplace $cambio, ?string $modoPrecio = null): array
    {
        $modo = CambioDevolucionMarketplacePrecioSupport::normalizar($modoPrecio);
        $this->assertPuedeFacturarReemplazo($cambio);
        $cambio->loadMissing('lineas');
        if ($cambio->lineas->where('tipo', CambioDevolucionMarketplaceCatalogoSupport::TIPO_REEMPLAZO)->isEmpty()) {
            throw new InvalidArgumentException(
                'No hay líneas de reemplazo. Si es solo devolución, usá «Continuar sin reemplazo».'
            );
        }
        $resultado = $this->emisionService->emitirFacReemplazo($cambio, $modo);
        if (empty($resultado['ok'])) {
            return $resultado;
        }

        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        $etiquetaPrecio = CambioDevolucionMarketplacePrecioSupport::etiqueta($modo);
        $nota = 'FAC reemplazo '.($resultado['factura'] ?? '').' ('.$etiquetaPrecio.')';
        $primera = (string) $cambio->estado === CambioDevolucionMarketplaceEstadosSupport::ABIERTO
            || (string) $cambio->estado === CambioDevolucionMarketplaceEstadosSupport::FACTURA_REEMPLAZO;

        $cambio->venta_reemplazo_id = $ventaId;
        $this->registrarComprobante($cambio, CambioDevolucionMarketplaceComprobante::TIPO_FAC_REEMPLAZO, $ventaId);

        if ($primera && (string) $cambio->estado === CambioDevolucionMarketplaceEstadosSupport::ABIERTO) {
            $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::FACTURA_REEMPLAZO;
            $cambio->save();
            $this->registrarEstado($cambio, CambioDevolucionMarketplaceEstadosSupport::FACTURA_REEMPLAZO, $nota);
            $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION;
            $cambio->save();
            $this->registrarEstado(
                $cambio,
                CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION,
                'Aguardando llegada del calzado a devolver'
            );
        } else {
            $cambio->save();
            $this->registrarEstado($cambio, (string) $cambio->estado, $nota);
            if ((string) $cambio->estado === CambioDevolucionMarketplaceEstadosSupport::FACTURA_REEMPLAZO) {
                $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION;
                $cambio->save();
                $this->registrarEstado(
                    $cambio,
                    CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION,
                    'Aguardando llegada del calzado a devolver'
                );
            } else {
                $this->recalcularDiferenciaSiPendiente($cambio);
            }
        }

        return [
            'ok' => true,
            'cambio' => $cambio->fresh(['lineas', 'estados', 'ventaReemplazo', 'comprobantes']),
            'mensaje' => $resultado['mensaje'] ?? 'Factura de reemplazo emitida.',
            'venta_id' => $resultado['venta_id'] ?? null,
            'pdf_urls' => $resultado['pdf_urls'] ?? [],
        ];
    }

    /**
     * @return array{ok:bool,error?:string,cambio?:CambioDevolucionMarketplace,mensaje?:string}
     */
    public function emitirNcReemplazo(CambioDevolucionMarketplace $cambio): array
    {
        $this->assertPuedeAnularReemplazo($cambio);
        $ventaFacId = (int) $cambio->venta_reemplazo_id;
        $this->registrarComprobante($cambio, CambioDevolucionMarketplaceComprobante::TIPO_FAC_REEMPLAZO, $ventaFacId);

        $resultado = $this->ncService->emitirNcReemplazo($cambio);
        if (empty($resultado['ok'])) {
            return $resultado;
        }

        $ventaNcId = (int) ($resultado['venta_id'] ?? 0);
        $this->registrarComprobante($cambio, CambioDevolucionMarketplaceComprobante::TIPO_NC_REEMPLAZO, $ventaNcId);
        $cambio->venta_reemplazo_id = null;
        $cambio->save();
        $this->registrarEstado(
            $cambio,
            (string) $cambio->estado,
            'NC del reemplazo '.($resultado['factura'] ?? '').' por error de carga. Se puede facturar de nuevo.'
        );
        $this->recalcularDiferenciaSiPendiente($cambio);

        return [
            'ok' => true,
            'cambio' => $cambio->fresh(['lineas', 'estados', 'comprobantes']),
            'mensaje' => trim(($resultado['mensaje'] ?? 'Nota de crédito del reemplazo emitida.').' El legajo sigue su curso: podés corregir los artículos y facturarlos de nuevo.'),
            'venta_id' => $ventaNcId,
            'pdf_urls' => $resultado['pdf_urls'] ?? [],
        ];
    }

    /**
     * Suma a «A devolver» renglones de la factura original que todavía no están en el legajo.
     *
     * @param  list<int>  $emisionIds
     */
    public function agregarDevolucionesDesdeFactura(CambioDevolucionMarketplace $cambio, array $emisionIds): CambioDevolucionMarketplace
    {
        $this->assertPuedeAgregarDevolucion($cambio);
        $ids = array_values(array_unique(array_filter(array_map('intval', $emisionIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            throw new InvalidArgumentException('Elegí al menos un artículo de la factura.');
        }

        $detalle = CambioDevolucionMarketplaceVentaConsultaSupport::detalle((int) $cambio->venta_original_id);
        if ($detalle === null) {
            throw new InvalidArgumentException('No se pudo leer la factura original.');
        }

        $cambio->loadMissing('lineas');
        $ya = $cambio->lineas
            ->where('tipo', CambioDevolucionMarketplaceCatalogoSupport::TIPO_DEVOLVER)
            ->pluck('venta_emision_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $pedidos = array_flip($ids);
        $agregados = 0;
        foreach ($detalle['lineas'] ?? [] as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $emisionId = (int) ($linea['venta_emision_id'] ?? 0);
            if ($emisionId <= 0 || ! isset($pedidos[$emisionId]) || in_array($emisionId, $ya, true)) {
                continue;
            }
            CambioDevolucionMarketplaceLinea::query()->create([
                'cambio_id' => (int) $cambio->id,
                'tipo' => CambioDevolucionMarketplaceCatalogoSupport::TIPO_DEVOLVER,
                'articulo_id' => (int) ($linea['articulo_id'] ?? 0),
                'talle_id' => ((int) ($linea['talle_id'] ?? 0)) ?: null,
                'color_id' => ((int) ($linea['color_id'] ?? 0)) ?: null,
                'combinacion_id' => ((int) ($linea['combinacion_id'] ?? 0)) ?: null,
                'cantidad' => max(0.0001, (float) ($linea['cantidad'] ?? 1)),
                'precio_unitario' => (float) ($linea['precio_unitario'] ?? 0),
                'venta_emision_id' => $emisionId,
                'descripcion' => $linea['descripcion'] ?? null,
            ]);
            $ya[] = $emisionId;
            $agregados++;
        }

        if ($agregados === 0) {
            throw new InvalidArgumentException('Esos artículos ya están en el legajo.');
        }

        $this->registrarEstado(
            $cambio,
            (string) $cambio->estado,
            'Se agregaron '.$agregados.' artículo(s) de la factura original para devolver.'
        );

        return $cambio->fresh(['lineas']);
    }

    /**
     * Corrige las líneas de reemplazo cuando la factura vigente ya fue acreditada por error.
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    public function actualizarReemplazos(CambioDevolucionMarketplace $cambio, array $lineas): CambioDevolucionMarketplace
    {
        $this->assertPuedeCorregirReemplazo($cambio);

        return DB::transaction(function () use ($cambio, $lineas) {
            EloquentAuditDeleteSupport::each(
                CambioDevolucionMarketplaceLinea::query()
                    ->where('cambio_id', $cambio->id)
                    ->where('tipo', CambioDevolucionMarketplaceCatalogoSupport::TIPO_REEMPLAZO)
            );

            $guardadas = 0;
            foreach ($lineas as $linea) {
                if ((string) ($linea['tipo'] ?? '') !== CambioDevolucionMarketplaceCatalogoSupport::TIPO_REEMPLAZO) {
                    continue;
                }
                $articuloId = (int) ($linea['articulo_id'] ?? 0);
                if ($articuloId <= 0) {
                    continue;
                }
                CambioDevolucionMarketplaceLinea::query()->create([
                    'cambio_id' => (int) $cambio->id,
                    'tipo' => CambioDevolucionMarketplaceCatalogoSupport::TIPO_REEMPLAZO,
                    'articulo_id' => $articuloId,
                    'talle_id' => ((int) ($linea['talle_id'] ?? 0)) ?: null,
                    'color_id' => ((int) ($linea['color_id'] ?? 0)) ?: null,
                    'combinacion_id' => ((int) ($linea['combinacion_id'] ?? 0)) ?: null,
                    'cantidad' => max(0.0001, (float) ($linea['cantidad'] ?? 1)),
                    'precio_unitario' => (float) ($linea['precio_unitario'] ?? 0),
                    'venta_emision_id' => ((int) ($linea['venta_emision_id'] ?? 0)) ?: null,
                    'descripcion' => $linea['descripcion'] ?? null,
                ]);
                $guardadas++;
            }

            $this->registrarEstado(
                $cambio,
                (string) $cambio->estado,
                'Artículos de reemplazo actualizados ('.$guardadas.').'
            );

            return $cambio->fresh(['lineas']);
        });
    }

    public function registrarRecepcion(CambioDevolucionMarketplace $cambio, string $disposicion, ?string $observacion = null): CambioDevolucionMarketplace
    {
        $this->assertTransicion($cambio, CambioDevolucionMarketplaceEstadosSupport::RECIBIDO);
        if (! isset(CambioDevolucionMarketplaceCatalogoSupport::DISPOSICIONES[$disposicion])) {
            throw new InvalidArgumentException('Disposición inválida.');
        }

        $cambio->disposicion = $disposicion;
        $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::RECIBIDO;
        if ($observacion !== null && $observacion !== '') {
            $cambio->observacion = trim((string) $cambio->observacion."\n".$observacion);
        }
        $cambio->save();
        $this->registrarEstado(
            $cambio,
            CambioDevolucionMarketplaceEstadosSupport::RECIBIDO,
            'Recepción registrada ('.$disposicion.')'.($observacion ? ': '.$observacion : '')
        );

        return $cambio->fresh();
    }

    /**
     * @return array{ok:bool,error?:string,cambio?:CambioDevolucionMarketplace,mensaje?:string}
     */
    public function emitirNcOriginal(CambioDevolucionMarketplace $cambio, ?string $modoPrecio = null): array
    {
        $modo = CambioDevolucionMarketplacePrecioSupport::normalizar($modoPrecio);
        $this->assertTransicion($cambio, CambioDevolucionMarketplaceEstadosSupport::NC_ORIGINAL);
        $resultado = $this->ncService->emitirNcOriginal($cambio, $modo);
        if (empty($resultado['ok'])) {
            return $resultado;
        }

        $cambio->venta_nc_id = (int) $resultado['venta_id'];
        $this->registrarComprobante(
            $cambio,
            CambioDevolucionMarketplaceComprobante::TIPO_NC_ORIGINAL,
            (int) $resultado['venta_id']
        );
        $ventaNc = Venta::query()->find((int) $resultado['venta_id']);
        if ($ventaNc) {
            app(DevolucionHistorialService::class)->registrarTiendanube($cambio, $ventaNc);
        }
        $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::NC_ORIGINAL;
        $cambio->save();
        $this->registrarEstado(
            $cambio,
            CambioDevolucionMarketplaceEstadosSupport::NC_ORIGINAL,
            'NC de lo devuelto '.$resultado['factura'].' ('.CambioDevolucionMarketplacePrecioSupport::etiqueta($modo).')'
        );

        CambioDevolucionMarketplaceLiquidacionSupport::aplicarALegajo($cambio);
        $cambio->save();

        if ($cambio->diferencia_sentido === CambioDevolucionMarketplaceLiquidacionSupport::SENTIDO_SIN_DIFERENCIA) {
            $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::CERRADO;
            $cambio->compensacion_registrada_at = now();
            $cambio->compensacion_observacion = 'Sin diferencia a compensar';
            $cambio->save();
            $this->registrarEstado($cambio, CambioDevolucionMarketplaceEstadosSupport::CERRADO, 'Cerrado sin diferencia');
        } else {
            $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::PENDIENTE_COMPENSACION;
            $cambio->save();
            $etiqueta = CambioDevolucionMarketplaceLiquidacionSupport::etiquetaSentido($cambio->diferencia_sentido);
            $this->registrarEstado(
                $cambio,
                CambioDevolucionMarketplaceEstadosSupport::PENDIENTE_COMPENSACION,
                sprintf('Pendiente compensación: %s $%s', $etiqueta, number_format((float) $cambio->diferencia_importe, 2, ',', '.'))
            );
        }

        return [
            'ok' => true,
            'cambio' => $cambio->fresh(['lineas', 'estados', 'ventaNc', 'ventaReemplazo']),
            'mensaje' => $resultado['mensaje'] ?? 'Nota de crédito emitida.',
            'venta_id' => $resultado['venta_id'] ?? null,
            'pdf_urls' => $resultado['pdf_urls'] ?? [],
        ];
    }

    public function registrarCompensacion(CambioDevolucionMarketplace $cambio, string $observacion): CambioDevolucionMarketplace
    {
        $this->assertTransicion($cambio, CambioDevolucionMarketplaceEstadosSupport::CERRADO);
        $cambio->compensacion_registrada_at = now();
        $cambio->compensacion_observacion = $observacion;
        $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::CERRADO;
        $cambio->save();
        $this->registrarEstado(
            $cambio,
            CambioDevolucionMarketplaceEstadosSupport::CERRADO,
            'Compensación registrada: '.$observacion
        );

        return $cambio->fresh();
    }

    public function anular(CambioDevolucionMarketplace $cambio, string $observacion): CambioDevolucionMarketplace
    {
        $this->assertTransicion($cambio, CambioDevolucionMarketplaceEstadosSupport::ANULADO);
        if ((int) ($cambio->venta_nc_id ?? 0) > 0) {
            throw new InvalidArgumentException('No se puede anular el legajo: ya tiene NC emitida. Los comprobantes fiscales no se anulan desde aquí.');
        }

        $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::ANULADO;
        $cambio->observacion = trim((string) $cambio->observacion."\nAnulación: ".$observacion);
        $cambio->save();
        $this->registrarEstado($cambio, CambioDevolucionMarketplaceEstadosSupport::ANULADO, $observacion);

        return $cambio->fresh();
    }

    /**
     * @param  list<UploadedFile|null>  $archivosNuevos
     * @param  list<string>  $nombresConservar
     */
    public function sincronizarArchivos(
        CambioDevolucionMarketplace $cambio,
        array $archivosNuevos,
        array $nombresConservar
    ): void {
        $dir = $this->directorioArchivos((int) $cambio->id);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $conservar = array_values(array_filter(array_map('strval', $nombresConservar)));
        $existentes = $cambio->archivos()->get();
        foreach ($existentes as $archivo) {
            if (! in_array((string) $archivo->nombrearchivo, $conservar, true)) {
                $path = $dir.'/'.$archivo->nombrearchivo;
                if (is_file($path)) {
                    @unlink($path);
                }
                $archivo->delete();
            }
        }

        foreach ($archivosNuevos as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $nombre = $this->nombreArchivoSeguro($file->getClientOriginalName());
            $file->move($dir, $nombre);
            CambioDevolucionMarketplaceArchivo::query()->create([
                'cambio_id' => (int) $cambio->id,
                'nombrearchivo' => $nombre,
            ]);
        }
    }

    public function directorioArchivos(int $cambioId): string
    {
        return storage_path('app/public/cambio_devolucion_marketplace/'.$cambioId);
    }

    private function siguienteNumero(): int
    {
        $max = (int) CambioDevolucionMarketplace::query()->max('numero');

        return $max + 1;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function sincronizarLineas(CambioDevolucionMarketplace $cambio, array $lineas): void
    {
        EloquentAuditDeleteSupport::each(
            CambioDevolucionMarketplaceLinea::query()->where('cambio_id', $cambio->id)
        );

        foreach ($lineas as $linea) {
            $tipo = (string) ($linea['tipo'] ?? '');
            $articuloId = (int) ($linea['articulo_id'] ?? 0);
            if (! isset(CambioDevolucionMarketplaceCatalogoSupport::TIPOS_LINEA[$tipo]) || $articuloId <= 0) {
                continue;
            }
            CambioDevolucionMarketplaceLinea::query()->create([
                'cambio_id' => (int) $cambio->id,
                'tipo' => $tipo,
                'articulo_id' => $articuloId,
                'talle_id' => ((int) ($linea['talle_id'] ?? 0)) ?: null,
                'color_id' => ((int) ($linea['color_id'] ?? 0)) ?: null,
                'combinacion_id' => ((int) ($linea['combinacion_id'] ?? 0)) ?: null,
                'cantidad' => max(0.0001, (float) ($linea['cantidad'] ?? 1)),
                'precio_unitario' => (float) ($linea['precio_unitario'] ?? 0),
                'venta_emision_id' => ((int) ($linea['venta_emision_id'] ?? 0)) ?: null,
                'descripcion' => $linea['descripcion'] ?? null,
            ]);
        }
    }

    private function registrarEstado(CambioDevolucionMarketplace $cambio, string $estado, ?string $observacion = null): void
    {
        CambioDevolucionMarketplaceEstado::query()->create([
            'cambio_id' => (int) $cambio->id,
            'fecha' => now(),
            'estado' => $estado,
            'usuario_id' => Auth::id(),
            'observacion' => $observacion,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{motivo_devolucion_id:?int,motivo_codigo:?string}
     */
    private function camposMotivo(array $data): array
    {
        $motivo = MotivoDevolucionSupport::exigir((int) ($data['motivo_devolucion_id'] ?? 0));

        return [
            'motivo_devolucion_id' => (int) $motivo->id,
            'motivo_codigo' => (string) $motivo->codigo,
        ];
    }

    private function assertLegajoAbierto(CambioDevolucionMarketplace $cambio): void
    {
        if (CambioDevolucionMarketplaceEstadosSupport::esTerminal((string) $cambio->estado)) {
            throw new InvalidArgumentException('El legajo está cerrado.');
        }
        if ((int) ($cambio->caja_movimiento_id ?? 0) > 0) {
            throw new InvalidArgumentException('El legajo ya tiene la OPP de reembolso. No se puede cambiar la facturación.');
        }
    }

    private function assertPuedeFacturarReemplazo(CambioDevolucionMarketplace $cambio): void
    {
        $this->assertLegajoAbierto($cambio);
        if ((int) ($cambio->venta_reemplazo_id ?? 0) > 0) {
            throw new InvalidArgumentException('Ya hay una factura de reemplazo vigente. Si se cargó con error, emití primero su nota de crédito.');
        }
        $estado = (string) $cambio->estado;
        $permitidos = [
            CambioDevolucionMarketplaceEstadosSupport::ABIERTO,
            CambioDevolucionMarketplaceEstadosSupport::FACTURA_REEMPLAZO,
            CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION,
            CambioDevolucionMarketplaceEstadosSupport::RECIBIDO,
            CambioDevolucionMarketplaceEstadosSupport::NC_ORIGINAL,
            CambioDevolucionMarketplaceEstadosSupport::PENDIENTE_COMPENSACION,
        ];
        if (! in_array($estado, $permitidos, true)) {
            throw new InvalidArgumentException('En este estado no se puede facturar el reemplazo.');
        }
    }

    private function assertPuedeAnularReemplazo(CambioDevolucionMarketplace $cambio): void
    {
        $this->assertLegajoAbierto($cambio);
        if ((int) ($cambio->venta_reemplazo_id ?? 0) <= 0) {
            throw new InvalidArgumentException('No hay factura de reemplazo vigente para acreditar.');
        }
    }

    private function assertPuedeAgregarDevolucion(CambioDevolucionMarketplace $cambio): void
    {
        if (CambioDevolucionMarketplaceEstadosSupport::esTerminal((string) $cambio->estado)) {
            throw new InvalidArgumentException('El legajo está cerrado.');
        }
        if ((int) ($cambio->venta_nc_id ?? 0) > 0) {
            throw new InvalidArgumentException('La nota de crédito de lo devuelto ya se emitió. No se pueden agregar artículos.');
        }
        if ((int) ($cambio->venta_original_id ?? 0) <= 0) {
            throw new InvalidArgumentException('El legajo no tiene factura original.');
        }
    }

    private function assertPuedeCorregirReemplazo(CambioDevolucionMarketplace $cambio): void
    {
        $this->assertLegajoAbierto($cambio);
        if (CambioDevolucionMarketplaceEstadosSupport::esEditable((string) $cambio->estado)) {
            throw new InvalidArgumentException('Guardá el legajo con Actualizar.');
        }
        if ((int) ($cambio->venta_reemplazo_id ?? 0) > 0) {
            throw new InvalidArgumentException('Hay una factura de reemplazo vigente. Emití su nota de crédito antes de cambiar los artículos.');
        }
    }

    private function registrarComprobante(CambioDevolucionMarketplace $cambio, string $tipo, int $ventaId): void
    {
        if ($ventaId <= 0) {
            return;
        }
        $existe = CambioDevolucionMarketplaceComprobante::query()
            ->where('cambio_id', $cambio->id)
            ->where('tipo', $tipo)
            ->where('venta_id', $ventaId)
            ->exists();
        if ($existe) {
            return;
        }
        CambioDevolucionMarketplaceComprobante::query()->create([
            'cambio_id' => (int) $cambio->id,
            'tipo' => $tipo,
            'venta_id' => $ventaId,
        ]);
    }

    private function recalcularDiferenciaSiPendiente(CambioDevolucionMarketplace $cambio): void
    {
        if ((string) $cambio->estado !== CambioDevolucionMarketplaceEstadosSupport::PENDIENTE_COMPENSACION) {
            return;
        }
        if ((int) ($cambio->venta_nc_id ?? 0) <= 0) {
            return;
        }

        CambioDevolucionMarketplaceLiquidacionSupport::aplicarALegajo($cambio);
        $cambio->save();
        if ($cambio->diferencia_sentido === CambioDevolucionMarketplaceLiquidacionSupport::SENTIDO_SIN_DIFERENCIA) {
            $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::CERRADO;
            $cambio->compensacion_registrada_at = now();
            $cambio->compensacion_observacion = 'Sin diferencia a compensar';
            $cambio->save();
            $this->registrarEstado($cambio, CambioDevolucionMarketplaceEstadosSupport::CERRADO, 'Cerrado sin diferencia');

            return;
        }

        $etiqueta = CambioDevolucionMarketplaceLiquidacionSupport::etiquetaSentido($cambio->diferencia_sentido);
        $this->registrarEstado(
            $cambio,
            CambioDevolucionMarketplaceEstadosSupport::PENDIENTE_COMPENSACION,
            sprintf('Diferencia actualizada: %s $%s', $etiqueta, number_format((float) $cambio->diferencia_importe, 2, ',', '.'))
        );
    }

    private function assertTransicion(CambioDevolucionMarketplace $cambio, string $hacia): void
    {
        if (! CambioDevolucionMarketplaceEstadosSupport::puedeTransicionar($cambio->estado, $hacia)) {
            throw new InvalidArgumentException(sprintf(
                'No se puede pasar de %s a %s.',
                CambioDevolucionMarketplaceEstadosSupport::etiqueta($cambio->estado),
                CambioDevolucionMarketplaceEstadosSupport::etiqueta($hacia)
            ));
        }
    }

    private function nombreArchivoSeguro(string $original): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $base = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) $base) ?: 'archivo';
        $ext = preg_replace('/[^A-Za-z0-9]+/', '', (string) $ext);
        $nombre = $base.'_'.uniqid();
        if ($ext !== '') {
            $nombre .= '.'.$ext;
        }

        return $nombre;
    }
}
