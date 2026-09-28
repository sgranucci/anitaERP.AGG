<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Caja\InterbankingTransferencia;
use App\Models\Compras\Pagoproveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\InterbankingTransferenciaComprobanteSupport;
use App\Support\Compras\CbuSupport;
use App\Support\Compras\PagoproveedorEnvioMasivoClasificacionSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PagoproveedorEnvioMasivoService
{
    public function __construct(
        private PagoproveedorEnvioProveedorService $envioProveedorService,
        private InterbankingTransferenciaComprobanteSupport $comprobanteSupport,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @return array{
     *     empresa_query: Collection,
     *     filtros: array<string, mixed>,
     *     consultado: bool,
     *     filas: list<array<string, mixed>>,
     *     error: string|null
     * }
     */
    public function consultar(Request $request): array
    {
        $empresas = $this->empresaRepository->allFiltrado();
        $filtros = $this->filtrosDesdeRequest($request, $empresas);
        $consultado = $request->boolean('consultar');
        $filas = [];
        $error = null;

        if ($consultado) {
            $error = $this->validarFiltros($filtros);
            if ($error === null && (int) $filtros['empresa_id'] > 0) {
                session(['empresa_id' => (int) $filtros['empresa_id']]);
                $filas = $this->listar($filtros);
            }
        }

        return [
            'empresa_query' => $empresas,
            'filtros' => $filtros,
            'consultado' => $consultado,
            'filas' => $filas,
            'error' => $error,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array{enviadas: int, errores: list<array{etiqueta: string, mensaje: string}>, detalle: list<array{etiqueta: string, ok: bool, mensaje: string}>}
     */
    public function enviarSeleccionadas(Request $request, array $ids, ?string $mensaje): array
    {
        $empresas = $this->empresaRepository->allFiltrado();
        $filtros = $this->filtrosDesdeRequest($request, $empresas);
        $error = $this->validarFiltros($filtros);
        if ($error !== null) {
            return [
                'enviadas' => 0,
                'errores' => [['etiqueta' => '', 'mensaje' => $error]],
                'detalle' => [],
            ];
        }

        session(['empresa_id' => (int) $filtros['empresa_id']]);

        $porId = [];
        foreach ($this->listar($filtros) as $fila) {
            $porId[(int) $fila['id']] = $fila;
        }

        $detalle = [];
        $errores = [];
        $enviadas = 0;
        $mensaje = trim((string) $mensaje);
        $mensaje = $mensaje !== '' ? $mensaje : null;

        foreach ($ids as $id) {
            $id = (int) $id;
            $fila = $porId[$id] ?? null;
            if ($fila === null) {
                $errores[] = ['etiqueta' => 'OP #'.$id, 'mensaje' => 'No está en el filtro de empresa, fecha y medio.'];
                $detalle[] = ['etiqueta' => 'OP #'.$id, 'ok' => false, 'mensaje' => 'Fuera del filtro.'];

                continue;
            }

            $resultado = $this->enviarUna($fila, $mensaje);
            $detalle[] = $resultado;
            if ($resultado['ok']) {
                $enviadas++;
            } else {
                $errores[] = ['etiqueta' => $resultado['etiqueta'], 'mensaje' => $resultado['mensaje']];
            }
        }

        return [
            'enviadas' => $enviadas,
            'errores' => $errores,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $empresas
     * @return array<string, mixed>
     */
    public function filtrosDesdeRequest(Request $request, Collection $empresas): array
    {
        $permitidas = $empresas->pluck('id')->map(static fn ($id) => (int) $id)->all();
        if ($request->filled('empresa_id')) {
            $empresaId = (int) $request->input('empresa_id');
            if (! in_array($empresaId, $permitidas, true)) {
                $empresaId = 0;
            }
        } else {
            $sesion = (int) session('empresa_id');
            $empresaId = in_array($sesion, $permitidas, true)
                ? $sesion
                : (int) ($permitidas[0] ?? 0);
        }

        $hoy = now()->toDateString();
        $medio = (string) $request->input('medio', 'ambas');
        if (! in_array($medio, ['ambas', 'transferencia', 'cheque'], true)) {
            $medio = 'ambas';
        }

        return [
            'empresa_id' => $empresaId,
            'fecha_desde' => (string) $request->input('fecha_desde', $hoy),
            'fecha_hasta' => (string) $request->input('fecha_hasta', $hoy),
            'medio' => $medio,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function validarFiltros(array $filtros): ?string
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            return 'Seleccione una empresa asignada a su usuario.';
        }
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if (! $this->fechaValida($desde) || ! $this->fechaValida($hasta)) {
            return 'Indique la fecha de la orden de pago.';
        }
        $inicio = Carbon::createFromFormat('Y-m-d', $desde)->startOfDay();
        $fin = Carbon::createFromFormat('Y-m-d', $hasta)->startOfDay();
        if ($inicio->gt($fin)) {
            return 'La fecha hasta no puede ser anterior a la fecha desde.';
        }
        if ($inicio->diffInDays($fin) > 31) {
            return 'El rango de fechas no puede superar 31 días.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function listar(array $filtros): array
    {
        $pagos = Pagoproveedor::query()
            ->with([
                'proveedores:id,nombre,email,emailoc,nroinscripcion',
                'cheques:id,pagoproveedor_id',
                'interbanking_transferencias',
                'pagoproveedor_retenciones:id,pagoproveedor_id,importe',
                'caja_movimientos.caja_movimiento_cuentacajas.cuentacajas:id,nombre,banco_id,cbu,cuenta_interbanking',
                'caja_movimientos.cheques:id,caja_movimiento_id',
            ])
            ->where('empresa_id', (int) $filtros['empresa_id'])
            ->whereBetween('fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->whereRaw("UPPER(TRIM(COALESCE(estado, ''))) NOT IN ('BAJA', 'REVERTIDA')")
            ->orderBy('fecha')
            ->orderBy('numerotransaccion')
            ->get();

        [$candidatos, $modelos] = $this->transferenciasPersistidas(
            (int) $filtros['empresa_id'],
            (string) $filtros['fecha_desde'],
            (string) $filtros['fecha_hasta']
        );
        $usadas = Pagoproveedor::query()
            ->whereNotNull('interbanking_transferencia_id')
            ->pluck('interbanking_transferencia_id')
            ->mapWithKeys(static fn ($id) => [(int) $id => true])
            ->all();

        $filas = [];
        foreach ($pagos as $pago) {
            $fila = $this->filaDesdePago($pago, $candidatos, $modelos, $usadas);
            if ($fila === null) {
                continue;
            }
            if (! PagoproveedorEnvioMasivoClasificacionSupport::filtroIncluye((string) $filtros['medio'], $fila['medio'])) {
                continue;
            }
            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * @param  list<array{id: int, amount: float, cbu: string, cuit: string, fecha: string}>  $candidatos
     * @param  array<int, InterbankingTransferencia>  $modelos
     * @param  array<int, true>  $usadas
     * @return array<string, mixed>|null
     */
    private function filaDesdePago(Pagoproveedor $pago, array $candidatos, array $modelos, array &$usadas): ?array
    {
        $snap = is_array($pago->anita_impresion_json) ? $pago->anita_impresion_json : null;
        $tieneCheque = $pago->cheques->isNotEmpty()
            || $pago->caja_movimientos->contains(static fn ($mov) => $mov->cheques->isNotEmpty())
            || PagoproveedorEnvioMasivoClasificacionSupport::snapshotTieneCheques($snap);
        $senialTransferencia = (int) $pago->interbanking_transferencia_id > 0
            || strlen(CbuSupport::normalizar((string) $pago->cbu_pago)) >= 22
            || PagoproveedorEnvioMasivoClasificacionSupport::snapshotTieneTransferencia($snap);
        $medio = PagoproveedorEnvioMasivoClasificacionSupport::medio(
            $tieneCheque,
            $senialTransferencia,
            $this->tieneCuentaBancaria($pago)
        );
        if ($medio === null) {
            return null;
        }

        $transferencia = $medio === 'cheque' ? null : $pago->interbanking_transferencias;
        $transferenciaId = (int) ($pago->interbanking_transferencia_id ?? 0);
        $cbuCredito = '';
        if ($transferenciaId > 0 && $transferencia instanceof InterbankingTransferencia) {
            $cbuCredito = $this->comprobanteSupport->cbuCuenta(
                $transferencia->credit_account_json,
                $transferencia->credit_account
            );
            $asociacion = PagoproveedorEnvioMasivoClasificacionSupport::evaluarAsociacion(
                $medio,
                $transferenciaId,
                true,
                (int) $pago->empresa_id,
                (int) $transferencia->empresa_id,
                (float) $transferencia->amount,
                $pago->netoAPagar(),
                (float) $pago->monto,
                (string) ($pago->cbu_pago ?? ''),
                $cbuCredito
            );
        } elseif ($medio !== 'cheque') {
            $proveedorCuit = preg_replace('/\D+/', '', (string) ($pago->proveedores->nroinscripcion ?? '')) ?? '';
            $elegida = PagoproveedorEnvioMasivoClasificacionSupport::elegirPersistida([
                'neto' => $pago->netoAPagar(),
                'bruto' => (float) $pago->monto,
                'cbu' => (string) ($pago->cbu_pago ?? ''),
                'cuit' => $proveedorCuit,
                'fecha' => $pago->fecha ? $pago->fecha->format('Y-m-d') : '',
            ], $candidatos, $usadas);
            $asociacion = [
                'estado' => $elegida['estado'],
                'etiqueta' => $elegida['etiqueta'],
            ];
            $elegidaId = (int) ($elegida['transferencia_id'] ?? 0);
            if ($elegidaId > 0 && isset($modelos[$elegidaId])) {
                $transferencia = $modelos[$elegidaId];
                if ($elegida['estado'] === 'ok') {
                    $usadas[$elegidaId] = true;
                }
                $cbuCredito = $this->comprobanteSupport->cbuCuenta(
                    $transferencia->credit_account_json,
                    $transferencia->credit_account
                );
            } else {
                $transferencia = null;
            }
        } else {
            $asociacion = PagoproveedorEnvioMasivoClasificacionSupport::evaluarAsociacion(
                'cheque',
                null,
                false,
                (int) $pago->empresa_id,
                0,
                0,
                $pago->netoAPagar(),
                (float) $pago->monto,
                '',
                ''
            );
        }

        $proveedor = $pago->proveedores;
        $emails = $proveedor ? PagoproveedorEnvioProveedorService::emailsProveedor($proveedor) : [];
        $tieneEmail = $emails !== [];

        return [
            'id' => (int) $pago->id,
            'empresa_id' => (int) $pago->empresa_id,
            'etiqueta' => $pago->etiquetaComprobante(),
            'fecha' => $pago->fecha ? $pago->fecha->format('d/m/Y') : '',
            'proveedor' => (string) ($proveedor->nombre ?? ''),
            'email' => implode(', ', $emails),
            'tiene_email' => $tieneEmail,
            'medio' => $medio,
            'medio_etiqueta' => PagoproveedorEnvioMasivoClasificacionSupport::etiquetaMedio($medio),
            'importe' => $pago->netoAPagar(),
            'estado_op' => (string) ($pago->estado ?? ''),
            'advertencia_estado' => PagoproveedorEnvioProveedorService::advertenciaEstadoParaEnvio((string) ($pago->estado ?? '')),
            'asociacion_estado' => $asociacion['estado'],
            'asociacion_etiqueta' => $asociacion['etiqueta'],
            'asociacion_detalle' => $this->detalleAsociacion($asociacion['estado'], $pago, $transferencia, $cbuCredito),
            'asociacion_badge' => PagoproveedorEnvioMasivoClasificacionSupport::claseBadge($asociacion['estado']),
            'transferencia_id' => $transferencia instanceof InterbankingTransferencia ? (int) $transferencia->id : null,
            'transferencia_nro' => $transferencia instanceof InterbankingTransferencia
                ? (string) ($transferencia->transfer_id ?? $transferencia->id)
                : '',
            'transferencia_fecha' => $transferencia instanceof InterbankingTransferencia && $transferencia->request_date
                ? $transferencia->request_date->format('d/m/Y')
                : '',
            'transferencia_importe' => $transferencia instanceof InterbankingTransferencia
                ? (float) $transferencia->amount
                : null,
            'adjunta_comprobante' => PagoproveedorEnvioMasivoClasificacionSupport::adjuntaComprobante($asociacion['estado']),
            'seleccionada' => PagoproveedorEnvioMasivoClasificacionSupport::seleccionadaPorDefecto(
                $tieneEmail,
                $medio,
                $asociacion['estado']
            ),
        ];
    }

    private function tieneCuentaBancaria(Pagoproveedor $pago): bool
    {
        foreach ($pago->caja_movimientos as $movimiento) {
            foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
                $cuenta = $linea->cuentacajas;
                if ($cuenta === null) {
                    continue;
                }
                if ((int) ($cuenta->banco_id ?? 0) > 0) {
                    return true;
                }
                if (trim((string) ($cuenta->cbu ?? '')) !== '') {
                    return true;
                }
                if (trim((string) ($cuenta->cuenta_interbanking ?? '')) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{0: list<array{id: int, amount: float, cbu: string, cuit: string, fecha: string}>, 1: array<int, InterbankingTransferencia>}
     */
    private function transferenciasPersistidas(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $desde = Carbon::parse($fechaDesde)->subDays(3)->startOfDay();
        $hasta = Carbon::parse($fechaHasta)->addDays(3)->endOfDay();
        $registros = InterbankingTransferencia::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('request_date', [$desde, $hasta])
            ->get();

        $candidatos = [];
        $modelos = [];
        foreach ($registros as $transferencia) {
            $resumen = $this->comprobanteSupport->cuentaResumen(
                $transferencia->credit_account_json,
                $transferencia->credit_account
            );
            $id = (int) $transferencia->id;
            $modelos[$id] = $transferencia;
            $candidatos[] = [
                'id' => $id,
                'amount' => (float) $transferencia->amount,
                'cbu' => (string) ($resumen['cbu'] ?? ''),
                'cuit' => (string) ($resumen['cuit'] ?? ''),
                'fecha' => $transferencia->request_date ? $transferencia->request_date->format('Y-m-d') : '',
            ];
        }

        return [$candidatos, $modelos];
    }

    private function detalleAsociacion(string $estado, Pagoproveedor $pago, ?InterbankingTransferencia $transferencia, string $cbuCredito): string
    {
        $neto = number_format($pago->netoAPagar(), 2, ',', '.');
        $importeIb = $transferencia instanceof InterbankingTransferencia
            ? number_format((float) $transferencia->amount, 2, ',', '.')
            : '';
        $nro = $transferencia instanceof InterbankingTransferencia
            ? (string) ($transferencia->transfer_id ?? $transferencia->id)
            : '';

        return match ($estado) {
            'ok' => 'Transferencia '.$nro.' por '.$importeIb.' coincide con la OP (neta '.$neto.').',
            'sin_vincular' => 'No hay una transferencia persistida de Interbanking con el mismo importe y el CBU o CUIT del proveedor.',
            'ambigua' => 'Hay más de una transferencia persistida con el mismo importe y titular.',
            'solo_importe' => 'Hay una transferencia por el mismo importe, sin coincidir CBU ni CUIT.',
            'no_encontrada' => 'El vínculo no está en las transferencias persistidas de Interbanking.',
            'otra_empresa' => 'La transferencia persistida pertenece a otra empresa.',
            'importe_distinto' => 'OP neta '.$neto.' y transferencia '.$importeIb.' no coinciden.',
            'cbu_distinto' => 'El CBU de la OP no coincide con el CBU crédito '.$cbuCredito.'.',
            'importe_y_cbu' => 'Difieren el importe (OP neta '.$neto.' / transferencia '.$importeIb.') y el CBU crédito.',
            default => 'Pago con cheque. Se envía la orden de pago, sin comprobante de transferencia.',
        };
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{etiqueta: string, ok: bool, mensaje: string}
     */
    private function enviarUna(array $fila, ?string $mensaje): array
    {
        $etiqueta = (string) $fila['etiqueta'];
        if (! ($fila['tiene_email'] ?? false)) {
            return ['etiqueta' => $etiqueta, 'ok' => false, 'mensaje' => 'El proveedor no tiene email.'];
        }

        $pdf = null;
        $adjuntos = [];
        try {
            if (($fila['adjunta_comprobante'] ?? false) && (int) ($fila['transferencia_id'] ?? 0) > 0) {
                $transferencia = InterbankingTransferencia::query()->find((int) $fila['transferencia_id']);
                if ($transferencia instanceof InterbankingTransferencia
                    && (int) $transferencia->empresa_id === (int) $fila['empresa_id']
                ) {
                    $pdf = $this->generarComprobanteTransferencia($transferencia);
                    $adjuntos[] = $pdf;
                }
            }

            $ret = $this->envioProveedorService->enviar((int) $fila['id'], null, $mensaje, $adjuntos);
            if (($ret['mensaje'] ?? '') !== 'ok') {
                return [
                    'etiqueta' => $etiqueta,
                    'ok' => false,
                    'mensaje' => (string) ($ret['errores'] ?? 'No se pudo enviar.'),
                ];
            }

            $aviso = $adjuntos !== []
                ? 'Enviada con comprobante de transferencia.'
                : ($fila['medio'] === 'cheque'
                    ? 'Enviada.'
                    : 'Enviada sin comprobante de transferencia.');

            return ['etiqueta' => $etiqueta, 'ok' => true, 'mensaje' => $aviso];
        } catch (\Throwable $e) {
            report($e);

            return ['etiqueta' => $etiqueta, 'ok' => false, 'mensaje' => 'No se pudo armar el correo: '.$e->getMessage()];
        } finally {
            if ($pdf !== null && is_file($pdf['ruta'])) {
                @unlink($pdf['ruta']);
            }
        }
    }

    /**
     * @return array{ruta: string, nombre: string}
     */
    private function generarComprobanteTransferencia(InterbankingTransferencia $transferencia): array
    {
        $datos = $this->comprobanteSupport->datosDesdeModelo($transferencia);
        $nombre = $this->comprobanteSupport->nombreArchivoPdf($transferencia);
        $html = view('caja.interbanking.comprobante_transferencia', compact('datos'))->render();

        $dir = storage_path('pdf/pagoproveedor');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ruta = $dir.'/ib_'.$transferencia->id.'_'.uniqid('', true).'.pdf';
        Pdf::loadHTML($html, 'UTF-8')->setPaper('a4', 'portrait')->save($ruta);

        return ['ruta' => $ruta, 'nombre' => $nombre];
    }

    private function fechaValida(string $fecha): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            return false;
        }
        try {
            $carbon = Carbon::createFromFormat('Y-m-d', $fecha);

            return $carbon instanceof Carbon && $carbon->format('Y-m-d') === $fecha;
        } catch (\Throwable) {
            return false;
        }
    }
}
