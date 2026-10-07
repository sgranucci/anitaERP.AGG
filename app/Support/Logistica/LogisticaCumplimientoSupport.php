<?php

namespace App\Support\Logistica;

use App\Models\Logistica\SolicitudLogistica;
use App\Models\Logistica\SolicitudLogisticaArchivo;
use App\Models\Logistica\SolicitudLogisticaItem;
use App\Models\Stock\Depmae;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final class LogisticaCumplimientoSupport
{
    /**
     * @param  array{
     *     deposito_id?: int,
     *     deposito_destino_id?: int,
     *     modo?: string,
     *     numero_comprobante?: string,
     *     receptor_nombre?: string,
     *     lineas?: array<int, float>,
     *     archivo?: ?UploadedFile
     * }  $datos
     */
    public static function aplicar(SolicitudLogistica $solicitud, string $accion, array $datos = []): void
    {
        $esTrabajo = $solicitud->trabajo_tipo_id !== null;
        $estado = (string) $solicitud->estado;

        if ($accion === 'rechazar') {
            if (! in_array($estado, ['enviada', 'pendiente_aprobacion', 'aprobada'], true)) {
                throw new RuntimeException('Esta solicitud ya no se puede rechazar.');
            }
            $solicitud->estado = 'rechazada';
            $solicitud->save();

            return;
        }

        if ($accion === 'aprobar') {
            if ($estado !== 'pendiente_aprobacion') {
                throw new RuntimeException('Esta solicitud no está pendiente de aprobación.');
            }
            $solicitud->estado = 'aprobada';
            $solicitud->save();

            return;
        }

        if ($accion === 'preparar') {
            if (! in_array($estado, ['enviada', 'aprobada'], true)) {
                throw new RuntimeException('Esta solicitud no se puede pasar a preparación.');
            }
            if (! $esTrabajo) {
                self::aplicarModoInsumos(
                    $solicitud,
                    (string) ($datos['modo'] ?? ''),
                    (int) ($datos['deposito_id'] ?? 0),
                    (int) ($datos['deposito_destino_id'] ?? 0)
                );
                foreach ($solicitud->items as $item) {
                    $item->cantidad_preparada = (float) $item->cantidad;
                    $item->save();
                }
            }
            $solicitud->estado = 'en_preparacion';
            $solicitud->fecha_preparacion = now();
            $solicitud->save();

            return;
        }

        if ($accion === 'vincular') {
            if ($estado !== 'en_preparacion' || $esTrabajo) {
                throw new RuntimeException('El comprobante se vincula con la solicitud en preparación.');
            }
            LogisticaVinculoSupport::vincular($solicitud, (string) ($datos['numero_comprobante'] ?? ''));

            return;
        }

        if ($accion === 'entregar') {
            if ($estado !== 'en_preparacion') {
                throw new RuntimeException('La solicitud tiene que estar en preparación.');
            }
            self::registrarReceptor($solicitud, $datos);
            if ($esTrabajo) {
                $solicitud->estado = 'cerrada';
                $solicitud->fecha_entrega = now();
                $solicitud->save();

                return;
            }
            self::entregarLineas($solicitud, (array) ($datos['lineas'] ?? []));

            return;
        }

        throw new RuntimeException('Acción no reconocida.');
    }

    private static function aplicarModoInsumos(SolicitudLogistica $solicitud, string $modo, int $depositoId, int $depositoDestinoId): void
    {
        if (! in_array($modo, ['deposito', 'transferencia', 'compra'], true)) {
            throw new RuntimeException('Elegí si se cumple desde depósito, con transferencia o con una compra.');
        }
        if ($modo === 'deposito' || $modo === 'transferencia') {
            if (! Depmae::autorizadoParaUsuario($depositoId)) {
                throw new RuntimeException('Elegí un depósito de salida autorizado.');
            }
            $solicitud->deposito_id = $depositoId;
        }
        if ($modo === 'transferencia') {
            if ($depositoDestinoId === $depositoId || ! Depmae::autorizadoParaUsuario($depositoDestinoId)) {
                throw new RuntimeException('Elegí un depósito de destino distinto y autorizado.');
            }
            $solicitud->deposito_destino_id = $depositoDestinoId;
        }
        $solicitud->modo_cumplimiento = $modo;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function registrarReceptor(SolicitudLogistica $solicitud, array $datos): void
    {
        $nombre = trim((string) ($datos['receptor_nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('Indicá quién recibió.');
        }
        $solicitud->receptor_nombre = mb_substr($nombre, 0, 120);
        $solicitud->receptor_en = now();
        $archivo = $datos['archivo'] ?? null;
        if ($archivo instanceof UploadedFile) {
            self::guardarRecepcion($solicitud, $archivo);
        }
    }

    /**
     * @param  array<int|string, mixed>  $lineas
     */
    private static function entregarLineas(SolicitudLogistica $solicitud, array $lineas): void
    {
        $solicitud->loadMissing('items');
        $sumo = false;
        foreach ($solicitud->items as $item) {
            $pedida = (float) ($lineas[$item->id] ?? $lineas[(string) $item->id] ?? 0);
            if ($pedida <= 0) {
                continue;
            }
            $pendiente = (float) $item->cantidad - (float) $item->cantidad_entregada;
            if ($pedida - $pendiente > 0.0001) {
                $sku = (string) ($item->articulo->sku ?? $item->articulo_id);
                throw new RuntimeException('La cantidad a entregar de '.$sku.' supera lo pendiente.');
            }
            $item->cantidad_entregada = (float) $item->cantidad_entregada + $pedida;
            $item->save();
            $sumo = true;
        }
        if (! $sumo) {
            throw new RuntimeException('Indicá la cantidad que se entrega en al menos un ítem.');
        }

        $completa = $solicitud->items->every(function (SolicitudLogisticaItem $item) {
            return ((float) $item->cantidad - (float) $item->cantidad_entregada) <= 0.0001;
        });
        if ($completa) {
            $solicitud->estado = 'entregada';
            $solicitud->fecha_entrega = now();
        }
        $solicitud->save();
    }

    private static function guardarRecepcion(SolicitudLogistica $solicitud, UploadedFile $archivo): void
    {
        $extension = strtolower($archivo->getClientOriginalExtension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
            throw new RuntimeException('La constancia tiene que ser una imagen o un PDF.');
        }
        if ($archivo->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException('La constancia no puede superar 5 MB.');
        }
        $ruta = $archivo->store('logistica/solicitudes/'.$solicitud->id, 'local');
        SolicitudLogisticaArchivo::query()->create([
            'solicitud_logistica_id' => $solicitud->id,
            'nombre' => mb_substr($archivo->getClientOriginalName(), 0, 180),
            'ruta' => $ruta,
            'clase' => 'recepcion',
        ]);
    }
}
