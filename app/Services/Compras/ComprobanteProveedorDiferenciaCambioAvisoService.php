<?php

namespace App\Services\Compras;

use App\Mail\Compras\ComprobanteProveedorDiferenciaCambioPendienteMail;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Difcambio;
use App\Services\Configuracion\AnitaNotificacionService;
use App\Support\Compras\ComprobanteProveedorDiferenciaCambioSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ComprobanteProveedorDiferenciaCambioAvisoService
{
    public function sincronizarBorrador(Comprobante_Proveedor $comprobante): void
    {
        if (! Schema::hasTable('comprobante_proveedor_difcambio')) {
            return;
        }
        if ((string) ($comprobante->estado ?? '') === \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO) {
            return;
        }

        $analisis = ComprobanteProveedorDiferenciaCambioSupport::analizar($comprobante);
        if (! ($analisis['requiere_aprobacion'] ?? false)) {
            $this->borrarPendientes((int) $comprobante->id);

            return;
        }

        $this->guardarPendientes($comprobante, $analisis, false);
    }

    public function assertPuedeContabilizar(Comprobante_Proveedor $comprobante): void
    {
        if (! Schema::hasTable('comprobante_proveedor_difcambio')) {
            return;
        }

        $analisis = ComprobanteProveedorDiferenciaCambioSupport::analizar($comprobante);
        if (! ($analisis['aplica'] ?? false)) {
            return;
        }
        if (! ($analisis['requiere_aprobacion'] ?? false)) {
            return;
        }
        if (ComprobanteProveedorDiferenciaCambioSupport::estaAprobada((int) $comprobante->id)) {
            return;
        }

        $this->guardarPendientes($comprobante, $analisis, false);

        throw new RuntimeException(
            'La diferencia de tipo de cambio contra una recepción de período cerrado es del '
            .number_format((float) $analisis['porcentaje_max'], 2, ',', '.')
            .'%, por encima del '.number_format((float) $analisis['tolerancia'], 2, ',', '.')
            .'%. La factura queda en borrador hasta que Contaduría la apruebe en Mis aprobaciones.'
        );
    }

    public function marcarContabilizado(Comprobante_Proveedor $comprobante, int $asientoId): void
    {
        if (! Schema::hasTable('comprobante_proveedor_difcambio')) {
            return;
        }

        $analisis = ComprobanteProveedorDiferenciaCambioSupport::analizar($comprobante);
        if (! ($analisis['aplica'] ?? false)) {
            return;
        }

        foreach ($analisis['tramos'] as $tramo) {
            if (! ($tramo['ajusta'] ?? false)) {
                continue;
            }
            Comprobante_Proveedor_Difcambio::query()->updateOrCreate(
                [
                    'comprobante_proveedor_id' => (int) $comprobante->id,
                    'recepcion_proveedor_id' => (int) $tramo['recepcion_id'],
                ],
                [
                    'ordencompra_id' => (int) ($tramo['ordencompra_id'] ?? 0) ?: null,
                    'empresa_id' => (int) $tramo['empresa_id'],
                    'proveedor_id' => (int) ($tramo['proveedor_id'] ?? 0) ?: null,
                    'tc_recepcion' => $tramo['tc_recepcion'],
                    'tc_factura' => $tramo['tc_factura'],
                    'nominal_me' => $tramo['nominal_me'],
                    'importe_pesos' => $tramo['importe_pesos'],
                    'porcentaje' => $tramo['porcentaje'],
                    'estado' => Comprobante_Proveedor_Difcambio::ESTADO_CONTABILIZADO,
                    'asiento_id' => $asientoId > 0 ? $asientoId : null,
                ]
            );
        }
    }

    public function aprobar(int $comprobanteId, int $usuarioId): Comprobante_Proveedor
    {
        $this->assertDestinatario($usuarioId);
        $comprobante = $this->comprobantePendiente($comprobanteId);

        Comprobante_Proveedor_Difcambio::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->where('estado', Comprobante_Proveedor_Difcambio::ESTADO_PENDIENTE)
            ->get()
            ->each(function (Comprobante_Proveedor_Difcambio $fila) use ($usuarioId) {
                $fila->update([
                    'estado' => Comprobante_Proveedor_Difcambio::ESTADO_APROBADA,
                    'usuario_aprobacion_id' => $usuarioId,
                    'aprobado_at' => now(),
                ]);
            });

        try {
            return app(ComprobanteProveedorContabilizarService::class)->contabilizar($comprobanteId);
        } catch (\Throwable $e) {
            Comprobante_Proveedor_Difcambio::query()
                ->where('comprobante_proveedor_id', $comprobanteId)
                ->where('estado', Comprobante_Proveedor_Difcambio::ESTADO_APROBADA)
                ->get()
                ->each(function (Comprobante_Proveedor_Difcambio $fila) {
                    $fila->update(['estado' => Comprobante_Proveedor_Difcambio::ESTADO_PENDIENTE]);
                });

            throw $e;
        }
    }

    public function rechazar(int $comprobanteId, int $usuarioId): void
    {
        $this->assertDestinatario($usuarioId);
        $this->comprobantePendiente($comprobanteId);

        Comprobante_Proveedor_Difcambio::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->where('estado', Comprobante_Proveedor_Difcambio::ESTADO_PENDIENTE)
            ->get()
            ->each(function (Comprobante_Proveedor_Difcambio $fila) use ($usuarioId) {
                $fila->update([
                    'estado' => Comprobante_Proveedor_Difcambio::ESTADO_RECHAZADA,
                    'usuario_aprobacion_id' => $usuarioId,
                    'aprobado_at' => now(),
                ]);
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function comprobantesImplicados(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing([
            'proveedores',
            'empresas',
            'ordencompras',
            'comprobante_proveedor_recepciones.recepcion_proveedores',
        ]);
        $filas = Comprobante_Proveedor_Difcambio::query()
            ->where('comprobante_proveedor_id', (int) $comprobante->id)
            ->orderBy('recepcion_proveedor_id')
            ->get();

        $consulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];

        return [
            'comprobante' => $comprobante,
            'filas' => $filas,
            'url_factura' => route('editar_comprobante_proveedor', ['id' => $comprobante->id] + $consulta),
            'url_oc' => (int) ($comprobante->ordencompra_id ?? 0) > 0
                ? route('editar_ordencompra', ['id' => (int) $comprobante->ordencompra_id] + $consulta)
                : null,
            'recepciones' => $comprobante->comprobante_proveedor_recepciones
                ->map(function ($vinculo) use ($consulta) {
                    $recepcion = $vinculo->recepcion_proveedores;
                    if (! $recepcion) {
                        return null;
                    }

                    return [
                        'id' => (int) $recepcion->id,
                        'fecha' => $recepcion->fecha ? $recepcion->fecha->format('d/m/Y') : '',
                        'tc' => (float) ($recepcion->cotizacion ?: 0),
                        'url' => route('editar_recepcion_proveedor', ['id' => $recepcion->id] + $consulta),
                    ];
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $analisis
     */
    private function guardarPendientes(Comprobante_Proveedor $comprobante, array $analisis, bool $avisar): void
    {
        $habia = Comprobante_Proveedor_Difcambio::query()
            ->where('comprobante_proveedor_id', (int) $comprobante->id)
            ->where('estado', Comprobante_Proveedor_Difcambio::ESTADO_PENDIENTE)
            ->exists();

        foreach ($analisis['tramos'] as $tramo) {
            if (! ($tramo['ajusta'] ?? false)) {
                continue;
            }
            Comprobante_Proveedor_Difcambio::query()->updateOrCreate(
                [
                    'comprobante_proveedor_id' => (int) $comprobante->id,
                    'recepcion_proveedor_id' => (int) $tramo['recepcion_id'],
                ],
                [
                    'ordencompra_id' => (int) ($tramo['ordencompra_id'] ?? 0) ?: null,
                    'empresa_id' => (int) $tramo['empresa_id'],
                    'proveedor_id' => (int) ($tramo['proveedor_id'] ?? 0) ?: null,
                    'tc_recepcion' => $tramo['tc_recepcion'],
                    'tc_factura' => $tramo['tc_factura'],
                    'nominal_me' => $tramo['nominal_me'],
                    'importe_pesos' => $tramo['importe_pesos'],
                    'porcentaje' => $tramo['porcentaje'],
                    'estado' => Comprobante_Proveedor_Difcambio::ESTADO_PENDIENTE,
                ]
            );
        }

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
        $links = $this->links($comprobante, $analisis);
        $bandeja = $links['bandeja'];
        $titulo = 'Factura '.$comprobante->letra.' '.$comprobante->sucursal.'-'.$comprobante->numerocomprobante
            .' con diferencia de cambio';

        foreach (ComprobanteProveedorDiferenciaCambioSupport::destinatarios() as $usuario) {
            try {
                if (trim((string) $usuario->email) !== '') {
                    Mail::to($usuario->email)->send(
                        new ComprobanteProveedorDiferenciaCambioPendienteMail($comprobante, $analisis, $links)
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('difcambio factura: no se pudo enviar el mail', [
                    'comprobante_id' => $comprobante->id,
                    'usuario' => $usuario->usuario,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                app(AnitaNotificacionService::class)->crear(
                    (int) $usuario->id,
                    $titulo,
                    'Quedó en borrador. Aprobala en Mis aprobaciones para contabilizarla.',
                    $bandeja,
                    AnitaNotificacionService::TIPO_APROBACION,
                    [
                        'comprobante_proveedor_id' => (int) $comprobante->id,
                        'fuente' => 'difcambio_factura',
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('difcambio factura: no se pudo avisar en la bandeja', [
                    'comprobante_id' => $comprobante->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $analisis
     * @return array<string, mixed>
     */
    private function links(Comprobante_Proveedor $comprobante, array $analisis): array
    {
        $consulta = ['origen' => 'modal_consulta', 'vista' => 'consulta'];
        $recepciones = [];
        foreach ($analisis['tramos'] as $tramo) {
            if (! ($tramo['ajusta'] ?? false)) {
                continue;
            }
            $id = (int) $tramo['recepcion_id'];
            $recepciones[] = [
                'id' => $id,
                'url' => urlAppAbsoluta('stock/recepcion-proveedor/'.$id.'/editar?origen=modal_consulta&vista=consulta'),
                'fecha' => '',
                'tc' => number_format((float) $tramo['tc_recepcion'], 4, ',', '.'),
            ];
        }

        return [
            'bandeja' => urlAppAbsoluta('mis-aprobaciones?fuente=difcambio_factura'),
            'visualizar' => urlAppAbsoluta('mis-aprobaciones/diferencia-cambio-factura/'.$comprobante->id),
            'factura' => route('editar_comprobante_proveedor', ['id' => $comprobante->id] + $consulta),
            'ordencompra' => (int) ($comprobante->ordencompra_id ?? 0) > 0
                ? route('editar_ordencompra', ['id' => (int) $comprobante->ordencompra_id] + $consulta)
                : null,
            'recepciones' => $recepciones,
        ];
    }

    private function borrarPendientes(int $comprobanteId): void
    {
        EloquentAuditDeleteSupport::each(
            Comprobante_Proveedor_Difcambio::query()
                ->where('comprobante_proveedor_id', $comprobanteId)
                ->whereIn('estado', [
                    Comprobante_Proveedor_Difcambio::ESTADO_PENDIENTE,
                    Comprobante_Proveedor_Difcambio::ESTADO_RECHAZADA,
                    Comprobante_Proveedor_Difcambio::ESTADO_APROBADA,
                ])
        );
    }

    private function assertDestinatario(int $usuarioId): void
    {
        if (! ComprobanteProveedorDiferenciaCambioSupport::esDestinatario($usuarioId)) {
            throw new RuntimeException('Esta aprobación corresponde a Contaduría (eguevara, rfogliatti, egalarza).');
        }
    }

    private function comprobantePendiente(int $comprobanteId): Comprobante_Proveedor
    {
        $comprobante = Comprobante_Proveedor::query()->find($comprobanteId);
        if (! $comprobante) {
            throw new RuntimeException('Comprobante de proveedor inexistente.');
        }
        $pendiente = Comprobante_Proveedor_Difcambio::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->where('estado', Comprobante_Proveedor_Difcambio::ESTADO_PENDIENTE)
            ->exists();
        if (! $pendiente) {
            throw new RuntimeException('La factura no está pendiente de aprobación por diferencia de cambio.');
        }

        return $comprobante;
    }
}
