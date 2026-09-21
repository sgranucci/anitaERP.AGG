<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\CambioDevolucionMarketplaceArchivo;
use App\Models\Ventas\CambioDevolucionMarketplaceEstado;
use App\Models\Ventas\CambioDevolucionMarketplaceLinea;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Venta;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceEstadosSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceLiquidacionSupport;
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
                'motivo_codigo' => $data['motivo_codigo'] ?? null,
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
            $cambio->motivo_codigo = $data['motivo_codigo'] ?? $cambio->motivo_codigo;
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
        if ($cambio->lineas->where('tipo', CambioDevolucionMarketplaceCatalogoSupport::TIPO_REEMPLAZO)->isEmpty()) {
            throw new InvalidArgumentException('Debe cargar al menos una línea de reemplazo antes de confirmar.');
        }
        if ((int) $cambio->venta_original_id <= 0) {
            throw new InvalidArgumentException('Debe indicar la factura original.');
        }

        $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::ABIERTO;
        $cambio->save();
        $this->registrarEstado($cambio, CambioDevolucionMarketplaceEstadosSupport::ABIERTO, 'Legajo confirmado');

        return $cambio->fresh();
    }

    /**
     * @return array{ok:bool,error?:string,cambio?:CambioDevolucionMarketplace,mensaje?:string}
     */
    public function emitirFacReemplazo(CambioDevolucionMarketplace $cambio): array
    {
        $this->assertTransicion($cambio, CambioDevolucionMarketplaceEstadosSupport::FACTURA_REEMPLAZO);
        $resultado = $this->emisionService->emitirFacReemplazo($cambio);
        if (empty($resultado['ok'])) {
            return $resultado;
        }

        $cambio->venta_reemplazo_id = (int) $resultado['venta_id'];
        $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::FACTURA_REEMPLAZO;
        $cambio->save();
        $this->registrarEstado(
            $cambio,
            CambioDevolucionMarketplaceEstadosSupport::FACTURA_REEMPLAZO,
            'FAC reemplazo '.$resultado['factura'].' (medio puente NCD)'
        );

        $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION;
        $cambio->save();
        $this->registrarEstado(
            $cambio,
            CambioDevolucionMarketplaceEstadosSupport::AGUARDANDO_RECEPCION,
            'Aguardando llegada del calzado a devolver'
        );

        return [
            'ok' => true,
            'cambio' => $cambio->fresh(['lineas', 'estados', 'ventaReemplazo']),
            'mensaje' => $resultado['mensaje'] ?? 'Factura de reemplazo emitida.',
            'venta_id' => $resultado['venta_id'] ?? null,
            'pdf_urls' => $resultado['pdf_urls'] ?? [],
        ];
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
    public function emitirNcOriginal(CambioDevolucionMarketplace $cambio): array
    {
        $this->assertTransicion($cambio, CambioDevolucionMarketplaceEstadosSupport::NC_ORIGINAL);
        $resultado = $this->ncService->emitirNcOriginal($cambio);
        if (empty($resultado['ok'])) {
            return $resultado;
        }

        $cambio->venta_nc_id = (int) $resultado['venta_id'];
        $cambio->estado = CambioDevolucionMarketplaceEstadosSupport::NC_ORIGINAL;
        $cambio->save();
        $this->registrarEstado(
            $cambio,
            CambioDevolucionMarketplaceEstadosSupport::NC_ORIGINAL,
            'NC original '.$resultado['factura'].' (medio puente NCD)'
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
