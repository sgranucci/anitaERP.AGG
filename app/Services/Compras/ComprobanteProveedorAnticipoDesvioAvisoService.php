<?php

namespace App\Services\Compras;

use App\Mail\Compras\ComprobanteProveedorAnticipoDesvioPendienteMail;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Anticipo_Desvio;
use App\Services\Configuracion\AnitaNotificacionService;
use App\Support\Compras\ComprobanteProveedorAnticipoPctSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ComprobanteProveedorAnticipoDesvioAvisoService
{
    public function sincronizarBorrador(Comprobante_Proveedor $comprobante): void
    {
        if (! Schema::hasTable('comprobante_proveedor_anticipo_desvio')) {
            return;
        }
        if ((string) ($comprobante->estado ?? '') === ComprobanteProveedorEstados::CONTABILIZADO) {
            return;
        }

        $analisis = $this->analizarComprobante($comprobante);
        if (! ($analisis['requiere_aprobacion'] ?? false)) {
            $this->borrarPendientes((int) $comprobante->id);

            return;
        }

        $this->guardarPendiente($comprobante, $analisis, false);
    }

    public function assertPuedeContabilizar(Comprobante_Proveedor $comprobante): void
    {
        if (! Schema::hasTable('comprobante_proveedor_anticipo_desvio')) {
            return;
        }

        $analisis = $this->analizarComprobante($comprobante);
        if (! ($analisis['aplica'] ?? false)) {
            return;
        }
        if (! empty($analisis['errores'])) {
            throw new RuntimeException(implode(' ', $analisis['errores']));
        }
        if (! ($analisis['requiere_aprobacion'] ?? false)) {
            return;
        }
        if (ComprobanteProveedorAnticipoPctSupport::estaAprobada((int) $comprobante->id)) {
            return;
        }

        $this->guardarPendiente($comprobante, $analisis, false);

        throw new RuntimeException(
            'El importe de la factura de anticipo difiere del % contractual de la OC en '
            .number_format((float) $analisis['desvio_pct'], 2, ',', '.')
            .'%, por encima del '.number_format((float) $analisis['tolerancia'], 2, ',', '.')
            .'%. La factura queda en borrador hasta que se libere en Mis aprobaciones.'
        );
    }

    public function marcarContabilizado(Comprobante_Proveedor $comprobante): void
    {
        if (! Schema::hasTable('comprobante_proveedor_anticipo_desvio')) {
            return;
        }

        Comprobante_Proveedor_Anticipo_Desvio::query()
            ->where('comprobante_proveedor_id', (int) $comprobante->id)
            ->whereIn('estado', [
                Comprobante_Proveedor_Anticipo_Desvio::ESTADO_PENDIENTE,
                Comprobante_Proveedor_Anticipo_Desvio::ESTADO_APROBADA,
            ])
            ->get()
            ->each(function (Comprobante_Proveedor_Anticipo_Desvio $fila) {
                $fila->update([
                    'estado' => Comprobante_Proveedor_Anticipo_Desvio::ESTADO_CONTABILIZADO,
                ]);
            });
    }

    public function aprobar(int $comprobanteId, int $usuarioId, string $motivo = ''): Comprobante_Proveedor
    {
        $this->assertDestinatario($usuarioId);
        $comprobante = $this->comprobantePendiente($comprobanteId);
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new RuntimeException('Indique el motivo de la liberación del desvío de anticipo.');
        }

        Comprobante_Proveedor_Anticipo_Desvio::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->where('estado', Comprobante_Proveedor_Anticipo_Desvio::ESTADO_PENDIENTE)
            ->get()
            ->each(function (Comprobante_Proveedor_Anticipo_Desvio $fila) use ($usuarioId, $motivo) {
                $fila->update([
                    'estado' => Comprobante_Proveedor_Anticipo_Desvio::ESTADO_APROBADA,
                    'usuario_aprobacion_id' => $usuarioId,
                    'aprobado_at' => now(),
                    'motivo' => $motivo,
                ]);
            });

        try {
            return app(ComprobanteProveedorContabilizarService::class)->contabilizar($comprobanteId);
        } catch (\Throwable $e) {
            Comprobante_Proveedor_Anticipo_Desvio::query()
                ->where('comprobante_proveedor_id', $comprobanteId)
                ->where('estado', Comprobante_Proveedor_Anticipo_Desvio::ESTADO_APROBADA)
                ->get()
                ->each(function (Comprobante_Proveedor_Anticipo_Desvio $fila) {
                    $fila->update(['estado' => Comprobante_Proveedor_Anticipo_Desvio::ESTADO_PENDIENTE]);
                });

            throw $e;
        }
    }

    public function rechazar(int $comprobanteId, int $usuarioId, string $motivo = ''): void
    {
        $this->assertDestinatario($usuarioId);
        $this->comprobantePendiente($comprobanteId);
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new RuntimeException('Indique el motivo del rechazo del desvío de anticipo.');
        }

        Comprobante_Proveedor_Anticipo_Desvio::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->where('estado', Comprobante_Proveedor_Anticipo_Desvio::ESTADO_PENDIENTE)
            ->get()
            ->each(function (Comprobante_Proveedor_Anticipo_Desvio $fila) use ($usuarioId, $motivo) {
                $fila->update([
                    'estado' => Comprobante_Proveedor_Anticipo_Desvio::ESTADO_RECHAZADA,
                    'usuario_aprobacion_id' => $usuarioId,
                    'aprobado_at' => now(),
                    'motivo' => $motivo,
                ]);
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function comprobantesImplicados(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing(['proveedores', 'empresas', 'ordencompras']);
        $fila = Comprobante_Proveedor_Anticipo_Desvio::query()
            ->where('comprobante_proveedor_id', (int) $comprobante->id)
            ->first();

        $consulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];

        return [
            'comprobante' => $comprobante,
            'fila' => $fila,
            'url_factura' => route('editar_comprobante_proveedor', ['id' => $comprobante->id] + $consulta),
            'url_oc' => (int) ($comprobante->ordencompra_id ?? 0) > 0
                ? route('editar_ordencompra', ['id' => (int) $comprobante->ordencompra_id] + $consulta)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analizarComprobante(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing('ordencompras');

        return ComprobanteProveedorAnticipoPctSupport::analizar(
            $comprobante->ordencompras,
            (string) ($comprobante->modo_carga ?? ''),
            (float) ($comprobante->total ?? 0),
            (int) $comprobante->id,
        );
    }

    /**
     * @param  array<string, mixed>  $analisis
     */
    private function guardarPendiente(Comprobante_Proveedor $comprobante, array $analisis, bool $avisar): void
    {
        $habia = Comprobante_Proveedor_Anticipo_Desvio::query()
            ->where('comprobante_proveedor_id', (int) $comprobante->id)
            ->where('estado', Comprobante_Proveedor_Anticipo_Desvio::ESTADO_PENDIENTE)
            ->exists();

        Comprobante_Proveedor_Anticipo_Desvio::query()->updateOrCreate(
            ['comprobante_proveedor_id' => (int) $comprobante->id],
            [
                'ordencompra_id' => (int) ($comprobante->ordencompra_id ?? 0) ?: null,
                'empresa_id' => (int) ($comprobante->empresa_id ?? 0),
                'proveedor_id' => (int) ($comprobante->proveedor_id ?? 0) ?: null,
                'pct_anticipo_oc' => (float) ($analisis['pct_anticipo'] ?? 0),
                'total_oc' => (float) ($analisis['total_oc'] ?? 0),
                'importe_esperado' => (float) ($analisis['importe_esperado'] ?? 0),
                'importe_ingresado' => (float) ($analisis['importe_ingresado'] ?? 0),
                'desvio_pct' => (float) ($analisis['desvio_pct'] ?? 0),
                'tolerancia_pct' => (float) ($analisis['tolerancia'] ?? 0),
                'estado' => Comprobante_Proveedor_Anticipo_Desvio::ESTADO_PENDIENTE,
            ]
        );

        if ($avisar || ! $habia) {
            $this->avisar($comprobante, $analisis);
        }
    }

    /**
     * @param  array<string, mixed>  $analisis
     */
    private function avisar(Comprobante_Proveedor $comprobante, array $analisis): void
    {
        $comprobante->loadMissing(['proveedores', 'empresas', 'ordencompras']);
        $links = $this->links($comprobante);
        $titulo = 'Factura '.$comprobante->letra.' '.$comprobante->sucursal.'-'.$comprobante->numerocomprobante
            .' con desvío de anticipo';

        foreach (ComprobanteProveedorAnticipoPctSupport::destinatarios() as $usuario) {
            try {
                if (trim((string) $usuario->email) !== '') {
                    Mail::to($usuario->email)->send(
                        new ComprobanteProveedorAnticipoDesvioPendienteMail($comprobante, $analisis, $links)
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('anticipo desvio: no se pudo enviar el mail', [
                    'comprobante_id' => $comprobante->id,
                    'usuario' => $usuario->usuario,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                app(AnitaNotificacionService::class)->crear(
                    (int) $usuario->id,
                    $titulo,
                    'Quedó en borrador. Liberála en Mis aprobaciones para contabilizarla.',
                    $links['bandeja'],
                    AnitaNotificacionService::TIPO_APROBACION,
                    [
                        'comprobante_proveedor_id' => (int) $comprobante->id,
                        'fuente' => 'anticipo_desvio_factura',
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('anticipo desvio: no se pudo avisar en la bandeja', [
                    'comprobante_id' => $comprobante->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function links(Comprobante_Proveedor $comprobante): array
    {
        $consulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];

        return [
            'bandeja' => urlAppAbsoluta('mis-aprobaciones?fuente=anticipo_desvio_factura'),
            'visualizar' => urlAppAbsoluta('mis-aprobaciones/anticipo-desvio-factura/'.$comprobante->id),
            'factura' => route('editar_comprobante_proveedor', ['id' => $comprobante->id] + $consulta),
            'ordencompra' => (int) ($comprobante->ordencompra_id ?? 0) > 0
                ? route('editar_ordencompra', ['id' => (int) $comprobante->ordencompra_id] + $consulta)
                : null,
        ];
    }

    private function borrarPendientes(int $comprobanteId): void
    {
        EloquentAuditDeleteSupport::each(
            Comprobante_Proveedor_Anticipo_Desvio::query()
                ->where('comprobante_proveedor_id', $comprobanteId)
                ->whereIn('estado', [
                    Comprobante_Proveedor_Anticipo_Desvio::ESTADO_PENDIENTE,
                    Comprobante_Proveedor_Anticipo_Desvio::ESTADO_RECHAZADA,
                    Comprobante_Proveedor_Anticipo_Desvio::ESTADO_APROBADA,
                ])
        );
    }

    private function assertDestinatario(int $usuarioId): void
    {
        if (! ComprobanteProveedorAnticipoPctSupport::esDestinatario($usuarioId)) {
            throw new RuntimeException('Esta liberación corresponde a los usuarios configurados para desvío de anticipo.');
        }
    }

    private function comprobantePendiente(int $comprobanteId): Comprobante_Proveedor
    {
        $comprobante = Comprobante_Proveedor::query()->find($comprobanteId);
        if (! $comprobante) {
            throw new RuntimeException('Comprobante de proveedor inexistente.');
        }
        $pendiente = Comprobante_Proveedor_Anticipo_Desvio::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->where('estado', Comprobante_Proveedor_Anticipo_Desvio::ESTADO_PENDIENTE)
            ->exists();
        if (! $pendiente) {
            throw new RuntimeException('La factura no está pendiente de liberación por desvío de anticipo.');
        }

        return $comprobante;
    }
}
