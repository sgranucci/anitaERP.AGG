<?php

namespace App\Services\Compras;

use App\Models\Compras\PropuestaPago;
use App\Models\Compras\PropuestaPagoLinea;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Repositories\Compras\PropuestaPagoRepositoryInterface;
use App\Support\Compras\PropuestaPagoBridgeBancarioSupport;
use App\Support\Compras\PropuestaPagoExcepcionSupport;
use App\Support\Compras\PropuestaPagoInstrumentoSupport;
use App\Support\Compras\PropuestaPagoLineaPresentacionSupport;
use App\Support\Compras\PropuestaPagoLoteBancarioSupport;
use App\Support\Compras\PropuestaPagoModoSupport;
use App\Support\Database\SqlDialectSupport;
use Auth;
use DB;
use Exception;
use Illuminate\Http\Request;

class PropuestaPagoService
{
    public function __construct(
        private PropuestaPagoRepositoryInterface $propuestaPagoRepository,
        private PagoproveedorService $pagoproveedorService,
        private PropuestaPagoArbolIntegracionService $arbolIntegracionService,
    ) {
    }

    /**
     * @return array{mensaje?:string,errores?:string,propuesta_pago_id?:int}
     */
    public function guardar(Request $request): array
    {
        try {
            $propuesta = DB::transaction(function () use ($request) {
                $data = $request->all();
                $empresaId = (int) ($data['empresa_id'] ?? 0);
                if ($empresaId <= 0) {
                    throw new Exception('Debe indicar la empresa.');
                }

                $propuesta = $this->propuestaPagoRepository->create([
                    'empresa_id' => $empresaId,
                    'fecha' => $data['fecha'] ?? date('Y-m-d'),
                    'fecha_vencimiento_desde' => $data['fecha_vencimiento_desde'] ?? null,
                    'fecha_vencimiento_hasta' => $data['fecha_vencimiento_hasta'] ?? null,
                    'moneda_id' => ($data['moneda_id'] ?? null) ?: null,
                    'estado' => 'BORRADOR',
                    'monto_total' => 0,
                    'detalle' => (string) ($data['detalle'] ?? ''),
                    'usuario_id' => Auth::id(),
                    'caja_id' => ($data['caja_id'] ?? null) ?: null,
                    'cuentacaja_id' => ($data['cuentacaja_id'] ?? null) ?: null,
                    'chequera_id' => ($data['chequera_id'] ?? null) ?: null,
                ]);

                $this->propuestaPagoRepository->cambiarEstado((int) $propuesta->id, 'BORRADOR', 'Alta de propuesta');
                $this->sincronizarLineasDesdeDeuda($propuesta, $data);
                $this->recalcularMontoTotal((int) $propuesta->id);

                return $propuesta;
            });

            return ['mensaje' => 'ok', 'propuesta_pago_id' => (int) $propuesta->id];
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * @return array{mensaje?:string,errores?:string}
     */
    public function actualizar(Request $request, int $id): array
    {
        try {
            DB::transaction(function () use ($request, $id) {
                $propuesta = $this->propuestaPagoRepository->findOrFail($id);
                $estado = (string) $propuesta->estado;
                $data = $request->all();

                // Autorizada: instrumentos + exclusión soft de líneas (no baja monto_autorizado)
                if ($estado === 'AUTORIZADA') {
                    $this->propuestaPagoRepository->update([
                        'caja_id' => ($data['caja_id'] ?? null) ?: null,
                        'cuentacaja_id' => ($data['cuentacaja_id'] ?? null) ?: null,
                        'chequera_id' => ($data['chequera_id'] ?? null) ?: null,
                        'detalle' => (string) ($data['detalle'] ?? $propuesta->detalle),
                    ], $id);
                    if (! empty($data['linea_ids'])) {
                        $this->actualizarMontosLineas($id, $data, soloIncluidos: true);
                        // Recalcula monto_total operativo; preserva monto_autorizado del árbol
                        $this->recalcularMontoTotal($id);
                    }

                    return;
                }

                if (! in_array($estado, PropuestaPago::estadosEditables(), true)) {
                    throw new Exception('La propuesta no es editable en estado '.$estado.'.');
                }

                $this->propuestaPagoRepository->update([
                    'fecha' => $data['fecha'] ?? $propuesta->fecha,
                    'fecha_vencimiento_desde' => $data['fecha_vencimiento_desde'] ?? null,
                    'fecha_vencimiento_hasta' => $data['fecha_vencimiento_hasta'] ?? null,
                    'moneda_id' => ($data['moneda_id'] ?? null) ?: null,
                    'detalle' => (string) ($data['detalle'] ?? $propuesta->detalle),
                    'caja_id' => ($data['caja_id'] ?? null) ?: null,
                    'cuentacaja_id' => ($data['cuentacaja_id'] ?? null) ?: null,
                    'chequera_id' => ($data['chequera_id'] ?? null) ?: null,
                    'estado' => 'BORRADOR',
                ], $id);

                if (! empty($data['rearmar_lineas'])) {
                    PropuestaPagoLinea::query()->where('propuesta_pago_id', $id)->delete();
                    $propuesta = $this->propuestaPagoRepository->findOrFail($id);
                    $this->sincronizarLineasDesdeDeuda($propuesta, $data);
                } else {
                    $this->actualizarMontosLineas($id, $data);
                }

                $this->recalcularMontoTotal($id);
            });

            return ['mensaje' => 'ok'];
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,mensaje:string}
     */
    public function enviarAprobacion(int $id): array
    {
        $propuesta = $this->propuestaPagoRepository->findOrFail($id);
        if (! in_array((string) $propuesta->estado, ['BORRADOR', 'RECHAZADA'], true)) {
            return ['ok' => false, 'mensaje' => 'Solo se puede enviar a aprobación una propuesta en BORRADOR o RECHAZADA.'];
        }

        $incluidas = $propuesta->lineas->where('incluido', true)->where('monto_propuesto', '>', 0);
        if ($incluidas->isEmpty()) {
            return ['ok' => false, 'mensaje' => 'No hay líneas incluidas con monto propuesto.'];
        }

        $empresaId = (int) $propuesta->empresa_id;

        // Modo light: sin árbol — pasa directo a AUTORIZADA
        if (! PropuestaPagoModoSupport::exigeArbol($empresaId)) {
            $this->propuestaPagoRepository->cambiarEstado(
                $id,
                'AUTORIZADA',
                'Modo light: autorizada sin árbol de aprobación'
            );
            PropuestaPagoBridgeBancarioSupport::fijarMontoAutorizado(
                $this->propuestaPagoRepository->findOrFail($id)
            );

            return ['ok' => true, 'mensaje' => 'Propuesta autorizada (modo light, sin árbol). Ya puede ejecutar → OP.'];
        }

        $this->propuestaPagoRepository->cambiarEstado($id, 'EN_APROBACION', 'Envío a árbol de aprobación');
        $nivel = $this->arbolIntegracionService->dispararAlEnviarAprobacion($id);

        if ($nivel === -1) {
            PropuestaPagoBridgeBancarioSupport::fijarMontoAutorizado(
                $this->propuestaPagoRepository->findOrFail($id)
            );

            return ['ok' => true, 'mensaje' => 'Sin niveles pendientes; la propuesta quedó AUTORIZADA.'];
        }
        if ($nivel <= 0) {
            $this->propuestaPagoRepository->cambiarEstado($id, 'BORRADOR', 'Sin árbol configurado; vuelve a borrador');

            return [
                'ok' => false,
                'mensaje' => 'No hay árbol de aprobación tipo PP activo para la empresa (o sin firmantes para el monto). Configure el árbol y reintente, o pase la empresa a modo light.',
            ];
        }

        return ['ok' => true, 'mensaje' => 'Propuesta enviada al árbol (nivel '.$nivel.').'];
    }

    /**
     * @return array{ok:bool,mensaje:string,ops?:list<int>}
     */
    public function ejecutar(int $id): array
    {
        $propuesta = $this->propuestaPagoRepository->findOrFail($id);
        if ((string) $propuesta->estado !== 'AUTORIZADA') {
            return ['ok' => false, 'mensaje' => 'Solo se puede ejecutar una propuesta AUTORIZADA.'];
        }

        $lineas = $propuesta->lineas
            ->where('incluido', true)
            ->filter(fn ($l) => (float) $l->monto_propuesto > 0 && empty($l->pagoproveedor_id));

        if ($lineas->isEmpty()) {
            return ['ok' => false, 'mensaje' => 'No hay líneas pendientes de ejecutar.'];
        }

        $ops = [];
        $errores = [];

        DB::transaction(function () use ($propuesta, $lineas, &$ops, &$errores) {
            $cajaId = (int) ($propuesta->caja_id ?: 0) ?: null;
            $cuentacajaId = (int) ($propuesta->cuentacaja_id ?: 0) ?: null;
            $chequeraId = (int) ($propuesta->chequera_id ?: 0) ?: null;
            $calcularRet = (bool) config('propuesta_pago.calcular_retenciones_al_ejecutar', true);

            // Una OP por proveedor + forma de pago (no mezclar Cheque con Transferencia)
            $porGrupo = $lineas->groupBy(function ($l) {
                return (int) $l->proveedor_id.'|'.(int) ($l->formapago_id ?: 0);
            });
            foreach ($porGrupo as $clave => $grupo) {
                $aplicaciones = [];
                $monto = 0.0;
                $monedaId = (int) ($grupo->first()->moneda_id ?: 1);
                $proveedorId = (int) $grupo->first()->proveedor_id;
                $formapagoId = (int) ($grupo->first()->formapago_id ?: 0) ?: null;
                $medios = [];
                $nombreProv = '';
                foreach ($grupo as $linea) {
                    $monto += (float) $linea->monto_propuesto;
                    $aplicaciones[] = [
                        'proveedor_cuentacorriente_id' => (int) $linea->proveedor_cuentacorriente_id,
                        'montoaplicado' => (float) $linea->monto_propuesto,
                        'moneda_id' => (int) ($linea->moneda_id ?: $monedaId),
                        'cotizacion' => 1,
                    ];
                    $p = PropuestaPagoLineaPresentacionSupport::enriquecer($linea, $propuesta);
                    if ($nombreProv === '') {
                        $nombreProv = $p['nombre_proveedor'];
                    }
                    $etiqueta = trim(($p['medio_pago'] ?: 'S/medio').($p['detalle_pago'] !== '' ? ' '.$p['detalle_pago'] : ''));
                    if ($etiqueta !== '' && ! in_array($etiqueta, $medios, true)) {
                        $medios[] = $etiqueta;
                    }
                }

                $fpProv = PropuestaPagoInstrumentoSupport::resolverFormapagoProveedor($proveedorId, $formapagoId);
                $textoInst = PropuestaPagoInstrumentoSupport::textoInstrumento(
                    $fpProv,
                    $medios !== [] ? implode('; ', $medios) : ''
                );
                $esCheque = PropuestaPagoInstrumentoSupport::esCheque(
                    $formapagoId,
                    $medios[0] ?? ($textoInst)
                );

                $detalleOp = 'Propuesta #'.$propuesta->id;
                if ($textoInst !== '') {
                    $detalleOp .= ' | '.$textoInst;
                }

                $cuentaEgreso = null;
                $chequeraOp = null;
                // Override por línea: primera cuenta de las líneas del grupo
                $cuentaLinea = (int) ($grupo->first(fn ($l) => (int) ($l->cuentacaja_id ?: 0) > 0)?->cuentacaja_id ?: 0);

                if ($esCheque && $chequeraId) {
                    $chequeraOp = $chequeraId;
                } elseif ($cuentaLinea > 0) {
                    $cuentaEgreso = $cuentaLinea;
                } elseif ($cuentacajaId && (
                    $formapagoId === null
                    || PropuestaPagoInstrumentoSupport::esTransferencia($formapagoId)
                    || ($fpProv && trim((string) $fpProv->cbu) !== '')
                )) {
                    $cuentaEgreso = $cuentacajaId;
                }

                $resultado = $this->pagoproveedorService->crearDesdePropuesta(
                    (int) $propuesta->empresa_id,
                    $proveedorId,
                    round($monto, 4),
                    $monedaId,
                    $propuesta->fecha?->format('Y-m-d') ?? date('Y-m-d'),
                    (int) $propuesta->id,
                    $aplicaciones,
                    $detalleOp,
                    PropuestaPagoModoSupport::ejecutarConfirmada((int) $propuesta->empresa_id),
                    $cajaId,
                    $cuentaEgreso,
                    $calcularRet,
                    $textoInst !== '' ? $textoInst : null,
                    $chequeraOp,
                    $nombreProv !== '' ? $nombreProv : null
                );

                if (! empty($resultado['errores'])) {
                    $errores[] = 'Proveedor '.$proveedorId.' ('.$clave.'): '.$resultado['errores'];
                    continue;
                }

                $opId = (int) $resultado['pagoproveedor_id'];
                $ops[] = $opId;
                foreach ($grupo as $linea) {
                    $linea->pagoproveedor_id = $opId;
                    $linea->estado_linea = 'EJECUTADA';
                    $linea->save();
                }
            }

            $propuestaFresh = $this->propuestaPagoRepository->findOrFail((int) $propuesta->id);
            $pendientes = $propuestaFresh->lineas
                ->where('incluido', true)
                ->filter(fn ($l) => (float) $l->monto_propuesto > 0 && empty($l->pagoproveedor_id));

            $estadoFinal = $pendientes->isEmpty() ? 'EJECUTADA' : 'EJECUTADA_PARCIAL';
            $this->propuestaPagoRepository->cambiarEstado(
                (int) $propuesta->id,
                $estadoFinal,
                'Ejecución: '.count($ops).' OP (proveedor+medio; retenciones='.($calcularRet ? 'sí' : 'no').')'
            );
        });

        if ($errores !== [] && $ops === []) {
            return ['ok' => false, 'mensaje' => implode(' | ', $errores)];
        }

        $msg = 'Ejecutada. OP: '.implode(', ', $ops);
        if ($errores !== []) {
            $msg .= ' — Parcial: '.implode(' | ', $errores);
        }

        $bridge = PropuestaPagoBridgeBancarioSupport::intentarConciliarLote($id);
        if ($bridge['conciliadas'] !== [] || PropuestaPagoBridgeBancarioSupport::habilitado()) {
            $msg .= ' | '.$bridge['mensaje'];
        }

        if ($ops !== []) {
            $lote = PropuestaPagoLoteBancarioSupport::generarDesdePropuesta($id);
            if ($lote['ok']) {
                $msg .= ' | '.$lote['mensaje'];
            }
        }

        return ['ok' => true, 'mensaje' => $msg, 'ops' => $ops];
    }

    /**
     * @return array{ok:bool,mensaje:string,lote_bancario_id?:int}
     */
    public function generarLoteBancario(int $id): array
    {
        return PropuestaPagoLoteBancarioSupport::generarDesdePropuesta($id);
    }

    /**
     * Reabre propuesta AUTORIZADA a BORRADOR (re-propuesta / gobierno).
     *
     * @return array{ok:bool,mensaje:string}
     */
    public function reabrir(int $id): array
    {
        $propuesta = $this->propuestaPagoRepository->findOrFail($id);
        $check = PropuestaPagoExcepcionSupport::puedeReabrirTotal($propuesta);
        if (! $check['ok']) {
            return $check;
        }

        $this->propuestaPagoRepository->cambiarEstado(
            $id,
            'BORRADOR',
            'Reabierta a borrador (re-propuesta). Monto autorizado previo: '.number_format((float) ($propuesta->monto_autorizado ?: $propuesta->monto_total), 2, ',', '.')
        );
        $this->propuestaPagoRepository->update(['monto_autorizado' => null], $id);

        return ['ok' => true, 'mensaje' => 'Propuesta reabierta en BORRADOR.'];
    }

    /**
     * Reabre parcial: líneas sin OP vuelven a AUTORIZADA para re-ejecutar / ajustar exclusión.
     * Las OP ya enviadas al banco permanecen bloqueadas.
     *
     * @return array{ok:bool,mensaje:string}
     */
    public function reabrirParcial(int $id): array
    {
        $propuesta = $this->propuestaPagoRepository->findOrFail($id);
        $check = PropuestaPagoExcepcionSupport::puedeReabrirParcial($propuesta);
        if (! $check['ok']) {
            return $check;
        }

        $pendienteMonto = (float) $propuesta->lineas
            ->where('incluido', true)
            ->filter(fn ($l) => (float) $l->monto_propuesto > 0 && empty($l->pagoproveedor_id))
            ->sum('monto_propuesto');

        $opsBloqueadas = $propuesta->pagoproveedores
            ->filter(fn ($op) => PropuestaPagoExcepcionSupport::opBloqueadaBanco($op))
            ->count();

        $this->propuestaPagoRepository->update([
            'monto_autorizado' => round($pendienteMonto, 4),
        ], $id);
        $this->propuestaPagoRepository->cambiarEstado(
            $id,
            'AUTORIZADA',
            'Reapertura parcial: pendiente '.number_format($pendienteMonto, 2, ',', '.')
            .($opsBloqueadas > 0 ? " | {$opsBloqueadas} OP bloqueadas (banco)" : '')
        );

        return [
            'ok' => true,
            'mensaje' => 'Propuesta reabierta parcial en AUTORIZADA. Puede ajustar exclusiones y re-ejecutar pendientes.',
        ];
    }

    /**
     * Nueva propuesta BORRADOR con líneas excluidas o pendientes (sin OP) de la origen.
     * No toca OP ya enviadas al banco.
     *
     * @return array{ok:bool,mensaje:string,propuesta_pago_id?:int}
     */
    public function clonarDelta(int $id): array
    {
        $origen = $this->propuestaPagoRepository->findOrFail($id);
        if (! in_array((string) $origen->estado, ['AUTORIZADA', 'EJECUTADA', 'EJECUTADA_PARCIAL'], true)) {
            return ['ok' => false, 'mensaje' => 'Solo se puede generar delta desde propuesta autorizada o ejecutada.'];
        }

        $candidatas = $origen->lineas->filter(function ($l) {
            if (! empty($l->pagoproveedor_id)) {
                return false;
            }

            return ! $l->incluido || (float) $l->monto_propuesto > 0;
        });

        if ($candidatas->isEmpty()) {
            return ['ok' => false, 'mensaje' => 'No hay líneas pendientes ni excluidas para el delta.'];
        }

        $nueva = DB::transaction(function () use ($origen, $candidatas) {
            $nueva = $this->propuestaPagoRepository->create([
                'empresa_id' => (int) $origen->empresa_id,
                'fecha' => date('Y-m-d'),
                'fecha_vencimiento_desde' => $origen->fecha_vencimiento_desde,
                'fecha_vencimiento_hasta' => $origen->fecha_vencimiento_hasta,
                'moneda_id' => $origen->moneda_id,
                'estado' => 'BORRADOR',
                'monto_total' => 0,
                'detalle' => trim('Delta de propuesta #'.$origen->id.' — '.(string) $origen->detalle),
                'usuario_id' => Auth::id(),
                'caja_id' => $origen->caja_id,
                'cuentacaja_id' => $origen->cuentacaja_id,
                'chequera_id' => $origen->chequera_id,
            ]);

            $this->propuestaPagoRepository->cambiarEstado(
                (int) $nueva->id,
                'BORRADOR',
                'Alta delta desde propuesta #'.$origen->id
            );

            foreach ($candidatas as $l) {
                PropuestaPagoLinea::query()->create([
                    'propuesta_pago_id' => $nueva->id,
                    'proveedor_id' => (int) $l->proveedor_id,
                    'proveedor_cuentacorriente_id' => (int) $l->proveedor_cuentacorriente_id,
                    'comprobante_proveedor_id' => $l->comprobante_proveedor_id,
                    'ordencompra_id' => $l->ordencompra_id,
                    'fechavencimiento' => $l->fechavencimiento,
                    'moneda_id' => (int) ($l->moneda_id ?: 1),
                    'formapago_id' => $l->formapago_id,
                    'detalle_pago' => $l->detalle_pago,
                    'cuentacaja_id' => $l->cuentacaja_id,
                    'saldo_deuda' => $l->saldo_deuda,
                    'monto_propuesto' => $l->monto_propuesto,
                    'incluido' => true,
                    'estado_linea' => 'PENDIENTE',
                ]);
            }

            $this->recalcularMontoTotal((int) $nueva->id);

            $this->propuestaPagoRepository->cambiarEstado(
                (int) $origen->id,
                (string) $origen->estado,
                'Generó propuesta delta #'.$nueva->id
            );

            return $nueva;
        });

        return [
            'ok' => true,
            'mensaje' => 'Propuesta delta #'.$nueva->id.' creada en BORRADOR.',
            'propuesta_pago_id' => (int) $nueva->id,
        ];
    }

    /**
     * Marca el lote vigente como enviado al banco (bloquea OP).
     *
     * @return array{ok:bool,mensaje:string}
     */
    public function marcarLoteEnviado(int $propuestaId): array
    {
        $lote = PropuestaPagoLoteBancarioSupport::ultimoLote($propuestaId);
        if (! $lote) {
            return ['ok' => false, 'mensaje' => 'No hay lote bancario vigente.'];
        }

        return PropuestaPagoLoteBancarioSupport::marcarEnviadoBanco((int) $lote->id);
    }

    /**
     * Conciliación bridge bancario a demanda.
     *
     * @return array{ok:bool,mensaje:string}
     */
    public function conciliarBridge(int $id): array
    {
        $r = PropuestaPagoBridgeBancarioSupport::intentarConciliarLote($id);

        return ['ok' => (bool) $r['ok'], 'mensaje' => (string) $r['mensaje']];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sincronizarLineasDesdeDeuda(PropuestaPago $propuesta, array $data): void
    {
        $empresaId = (int) $propuesta->empresa_id;
        $desde = $data['fecha_vencimiento_desde'] ?? $propuesta->fecha_vencimiento_desde;
        $hasta = $data['fecha_vencimiento_hasta'] ?? $propuesta->fecha_vencimiento_hasta;

        $query = Proveedor_Cuentacorriente::query()
            ->with([
                'comprobante_proveedores',
                'comprobante_proveedor_cuotas.formapagos',
                'comprobante_proveedor_cuotas.ordencompra_comprobante_cuotas.formapagos',
                'comprobante_proveedor_cuotas.ordencompra_comprobante_cuotas.ordencompra_comprobantes',
                'monedas',
            ])
            ->select('proveedor_cuentacorriente.*')
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->where('proveedor_cuentacorriente.empresa_id', $empresaId)
            ->whereNull('proveedor_cuentacorriente.deleted_at')
            ->whereNotNull('proveedor_cuentacorriente.comprobante_proveedor_id')
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteProveedorCc());

        if ($desde) {
            $query->where(function ($q) use ($desde) {
                $q->whereNull('fechavencimiento')
                    ->orWhereDate('fechavencimiento', '>=', $desde);
            });
        }
        if ($hasta) {
            $query->where(function ($q) use ($hasta) {
                $q->whereNull('fechavencimiento')
                    ->orWhereDate('fechavencimiento', '<=', $hasta);
            });
        }

        $deudas = $query->get();

        foreach ($deudas as $cc) {
            $aplicado = (float) ($cc->aplicado ?? 0);
            $saldo = abs((float) $cc->total + $aplicado);
            if ($saldo <= 0.0001) {
                continue;
            }

            $medio = PropuestaPagoLineaPresentacionSupport::resolverMedioDesdeCuentacorriente($cc);

            PropuestaPagoLinea::query()->create([
                'propuesta_pago_id' => $propuesta->id,
                'proveedor_id' => (int) $cc->proveedor_id,
                'proveedor_cuentacorriente_id' => (int) $cc->id,
                'comprobante_proveedor_id' => $cc->comprobante_proveedor_id ?? null,
                'ordencompra_id' => $medio['ordencompra_id'],
                'fechavencimiento' => $cc->fechavencimiento,
                'moneda_id' => (int) ($cc->moneda_id ?: 1),
                'formapago_id' => $medio['formapago_id'],
                'detalle_pago' => $medio['detalle_pago'] !== '' ? mb_substr($medio['detalle_pago'], 0, 255) : null,
                'saldo_deuda' => round($saldo, 4),
                'monto_propuesto' => round($saldo, 4),
                'incluido' => true,
                'estado_linea' => 'PENDIENTE',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function actualizarMontosLineas(int $propuestaId, array $data, bool $soloIncluidos = false): void
    {
        $ids = $data['linea_ids'] ?? [];
        $montos = $data['montos_propuestos'] ?? [];
        $incluidos = array_map('intval', (array) ($data['incluidos'] ?? []));
        $cuentas = $data['linea_cuentacaja_ids'] ?? [];

        foreach ($ids as $i => $lineaId) {
            $linea = PropuestaPagoLinea::query()
                ->where('propuesta_pago_id', $propuestaId)
                ->where('id', (int) $lineaId)
                ->first();
            if (! $linea) {
                continue;
            }
            if (! $soloIncluidos) {
                $linea->monto_propuesto = (float) ($montos[$i] ?? $linea->monto_propuesto);
                if (array_key_exists($i, $cuentas) || isset($cuentas[$lineaId])) {
                    $cid = (int) ($cuentas[$i] ?? $cuentas[$lineaId] ?? 0);
                    $linea->cuentacaja_id = $cid > 0 ? $cid : null;
                }
            } elseif (isset($cuentas[$i]) || isset($cuentas[(string) $lineaId])) {
                $cid = (int) ($cuentas[$i] ?? $cuentas[(string) $lineaId] ?? 0);
                $linea->cuentacaja_id = $cid > 0 ? $cid : null;
            }
            $linea->incluido = in_array((int) $lineaId, $incluidos, true);
            $linea->save();
        }
    }

    private function recalcularMontoTotal(int $id): void
    {
        $total = (float) PropuestaPagoLinea::query()
            ->where('propuesta_pago_id', $id)
            ->where('incluido', true)
            ->sum('monto_propuesto');

        $this->propuestaPagoRepository->update(['monto_total' => round($total, 4)], $id);
    }
}
