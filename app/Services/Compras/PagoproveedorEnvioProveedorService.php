<?php

namespace App\Services\Compras;

use App\Mail\Compras\PagoproveedorOrdenPago;
use App\Models\Caja\InterbankingTransferencia;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Archivo;
use App\Models\Compras\Pagoproveedor_Correo;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Proveedor;
use App\Support\Caja\InterbankingTransferenciaComprobanteSupport;
use App\Support\Compras\CbuSupport;
use App\Support\Compras\PagoproveedorArchivoSupport;
use App\Support\Compras\PagoproveedorEnvioMasivoClasificacionSupport;
use App\Support\Mail\EmailsMultiplesSupport;
use Auth;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PagoproveedorEnvioProveedorService
{
    public function __construct(
        private PagoproveedorComprobantePdfService $pagoproveedorComprobantePdfService,
        private InterbankingTransferenciaComprobanteSupport $comprobanteTransferenciaSupport,
    ) {}

    /**
     * @return array{
     *     puede_enviar: bool,
     *     email: string,
     *     emails: list<string>,
     *     proveedor_nombre: string,
     *     etiqueta_op: string,
     *     estado: string,
     *     advertencia_estado: string|null,
     *     mensaje: string|null,
     *     archivos_asociados: int,
     *     adjunta_comprobante_transferencia: bool,
     *     aviso_comprobante_transferencia: string|null,
     *     envios: list<array{fecha: string, usuario: string, destinatarios: string, mensaje: string, texto_guardado: bool}>
     * }
     */
    public function datosEnvio(int $pagoproveedorId): array
    {
        $pago = Pagoproveedor::query()
            ->select([
                'id',
                'tipocomprobante',
                'sucursal',
                'numerotransaccion',
                'proveedor_id',
                'estado',
                'empresa_id',
                'fecha',
                'monto',
                'cbu_pago',
                'interbanking_transferencia_id',
                'anita_impresion_json',
            ])
            ->with([
                'proveedores:id,nombre,email,emailoc,nroinscripcion',
                'interbanking_transferencias',
                'cheques:id',
                'caja_movimientos.cheques:id',
                'caja_movimientos.caja_movimiento_cuentacajas.cuentacajas:id,nombre,banco_id,cbu,cuenta_interbanking',
            ])
            ->find($pagoproveedorId);

        if (! $pago) {
            return [
                'puede_enviar' => false,
                'email' => '',
                'emails' => [],
                'proveedor_nombre' => '',
                'etiqueta_op' => '',
                'estado' => '',
                'advertencia_estado' => null,
                'mensaje' => 'La orden de pago no existe.',
                'archivos_asociados' => 0,
                'adjunta_comprobante_transferencia' => false,
                'aviso_comprobante_transferencia' => null,
                'envios' => [],
            ];
        }

        $etiqueta = $pago->etiquetaComprobante();
        $infoTransferencia = $this->infoAdjuntoComprobanteTransferencia($pago);
        $proveedor = $pago->proveedores;
        if (! $proveedor) {
            return [
                'puede_enviar' => false,
                'email' => '',
                'emails' => [],
                'proveedor_nombre' => '',
                'etiqueta_op' => $etiqueta,
                'estado' => (string) ($pago->estado ?? ''),
                'advertencia_estado' => null,
                'mensaje' => 'La orden de pago no tiene proveedor asignado.',
                'archivos_asociados' => 0,
                'adjunta_comprobante_transferencia' => $infoTransferencia['adjunta'],
                'aviso_comprobante_transferencia' => $infoTransferencia['aviso'],
                'envios' => self::historialEnvios($pagoproveedorId),
            ];
        }

        $emails = self::emailsProveedor($proveedor);
        $sinEmailProveedor = $emails === [];

        return [
            'puede_enviar' => true,
            'email' => implode(', ', $emails),
            'emails' => $emails,
            'proveedor_nombre' => (string) ($proveedor->nombre ?? ''),
            'etiqueta_op' => $etiqueta,
            'estado' => (string) ($pago->estado ?? ''),
            'advertencia_estado' => self::advertenciaEstadoParaEnvio((string) ($pago->estado ?? '')),
            'mensaje' => $sinEmailProveedor
                ? 'El proveedor no tiene email configurado. Ingrese uno o más destinatarios.'
                : null,
            'archivos_asociados' => (int) Pagoproveedor_Archivo::query()
                ->where('pagoproveedor_id', $pago->id)
                ->count(),
            'adjunta_comprobante_transferencia' => $infoTransferencia['adjunta'],
            'aviso_comprobante_transferencia' => $infoTransferencia['aviso'],
            'envios' => self::historialEnvios($pago->id),
        ];
    }

    /**
     * @param  list<array{ruta: string, nombre: string}>  $adjuntosExtra
     * @param  list<UploadedFile|null>  $archivosNuevos
     * @return array{mensaje: string, errores?: string}
     */
    public function enviar(int $pagoproveedorId, ?string $emailOverride = null, ?string $mensajeAdicional = null, array $adjuntosExtra = [], array $archivosNuevos = []): array
    {
        $pagoExiste = Pagoproveedor::query()->whereKey($pagoproveedorId)->exists();
        if (! $pagoExiste) {
            return ['mensaje' => 'error', 'errores' => 'La orden de pago no existe.'];
        }

        $datos = $this->datosEnvio($pagoproveedorId);
        $emails = $emailOverride !== null && trim($emailOverride) !== ''
            ? self::parseEmails($emailOverride)
            : ($datos['emails'] ?? []);

        if ($emails === []) {
            $invalidos = self::emailsInvalidos((string) ($emailOverride ?? ''));
            if ($invalidos !== []) {
                return [
                    'mensaje' => 'error',
                    'errores' => 'Email(s) inválido(s): '.implode(', ', $invalidos),
                ];
            }

            return ['mensaje' => 'error', 'errores' => 'Indique al menos un email de destino válido.'];
        }

        $invalidosOverride = self::emailsInvalidos((string) ($emailOverride ?? ''));
        if ($emailOverride !== null && trim($emailOverride) !== '' && $invalidosOverride !== []) {
            return [
                'mensaje' => 'error',
                'errores' => 'Email(s) inválido(s): '.implode(', ', $invalidosOverride),
            ];
        }

        $pago = Pagoproveedor::query()
            ->with([
                'proveedores',
                'empresas',
                'interbanking_transferencias',
                'cheques:id',
                'caja_movimientos.cheques:id',
                'caja_movimientos.caja_movimiento_cuentacajas.cuentacajas:id,nombre,banco_id,cbu,cuenta_interbanking',
            ])
            ->find($pagoproveedorId);
        if (! $pago) {
            return ['mensaje' => 'error', 'errores' => 'La orden de pago no existe.'];
        }

        $pdf = null;
        $pdfTransferenciaAuto = null;
        $asociados = [];
        try {
            if ($adjuntosExtra === []) {
                $pdfTransferenciaAuto = $this->armarAdjuntoComprobanteTransferencia($pago);
                if ($pdfTransferenciaAuto !== null) {
                    $adjuntosExtra[] = $pdfTransferenciaAuto;
                }
            }

            $pdf = $this->pagoproveedorComprobantePdfService->generarArchivo($pagoproveedorId);
            if ($archivosNuevos !== []) {
                $asociados = PagoproveedorArchivoSupport::guardarSubidos($pagoproveedorId, $archivosNuevos);
            }
            $mailable = new PagoproveedorOrdenPago($pago, $mensajeAdicional);
            $mailable->incluyeComprobanteTransferencia = $adjuntosExtra !== [];
            $mailable->incluyeArchivosAdicionales = $asociados !== [];
            $mailable->attach($pdf['ruta'], [
                'as' => $pdf['nombre'],
                'mime' => 'application/pdf',
            ]);
            foreach ($adjuntosExtra as $adjunto) {
                if (! is_file($adjunto['ruta'])) {
                    continue;
                }
                $mailable->attach($adjunto['ruta'], [
                    'as' => $adjunto['nombre'],
                    'mime' => 'application/pdf',
                ]);
            }
            foreach ($asociados as $adjunto) {
                if (! is_file($adjunto['ruta'])) {
                    continue;
                }
                $mailable->attach($adjunto['ruta'], [
                    'as' => $adjunto['nombre'],
                    'mime' => $adjunto['mime'] !== '' ? $adjunto['mime'] : 'application/octet-stream',
                ]);
            }
            Mail::to($emails)->send($mailable);
        } catch (\Throwable $e) {
            if ($asociados !== []) {
                PagoproveedorArchivoSupport::eliminar($pagoproveedorId, $asociados);
            }
            report($e);

            return ['mensaje' => 'error', 'errores' => 'No se pudo enviar el correo: '.$e->getMessage()];
        } finally {
            if ($pdf !== null && is_file($pdf['ruta'])) {
                @unlink($pdf['ruta']);
            }
            if ($pdfTransferenciaAuto !== null && is_file($pdfTransferenciaAuto['ruta'])) {
                @unlink($pdfTransferenciaAuto['ruta']);
            }
        }

        $uid = Auth::id();
        $estadoId = null;
        $ahora = now();
        if ($uid) {
            $observacion = Pagoproveedor::PREFIJO_OBSERVACION_ENVIO_CORREO.' ('.implode(', ', $emails).')';
            if ($adjuntosExtra !== []) {
                $observacion .= ' + comprobante de transferencia';
            }
            $nAdj = count($asociados);
            if ($nAdj === 1) {
                $observacion .= ' + 1 archivo adjunto';
            } elseif ($nAdj > 1) {
                $observacion .= ' + '.$nAdj.' archivos adjuntos';
            }
            $estado = Pagoproveedor_Estado::query()->create([
                'pagoproveedor_id' => $pago->id,
                'fecha' => $ahora,
                'estado' => (string) ($pago->estado ?? ''),
                'usuario_id' => $uid,
                'observacion' => mb_substr($observacion, 0, 500),
            ]);
            $estadoId = (int) $estado->id;
        }

        Pagoproveedor_Correo::query()->create([
            'pagoproveedor_id' => $pago->id,
            'pagoproveedor_estado_id' => $estadoId,
            'fecha' => $ahora,
            'usuario_id' => $uid ?: null,
            'destinatarios' => mb_substr(implode(', ', $emails), 0, 500),
            'mensaje' => self::normalizarMensaje($mensajeAdicional),
        ]);

        return ['mensaje' => 'ok'];
    }

    /**
     * @return list<string>
     */
    public static function emailsProveedor(Proveedor $proveedor): array
    {
        $raw = trim((string) ($proveedor->email ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($proveedor->emailoc ?? ''));
        } elseif (trim((string) ($proveedor->emailoc ?? '')) !== '') {
            $raw .= ', '.(string) $proveedor->emailoc;
        }

        return self::parseEmails($raw);
    }

    /**
     * @return list<string>
     */
    public static function parseEmails(string $raw): array
    {
        return EmailsMultiplesSupport::parse($raw);
    }

    /**
     * Tokens no vacíos que no pasan validación de email.
     *
     * @return list<string>
     */
    public static function emailsInvalidos(string $raw): array
    {
        return EmailsMultiplesSupport::invalidos($raw);
    }

    public static function advertenciaEstadoParaEnvio(string $estado): ?string
    {
        $estado = strtoupper(trim($estado));
        if (in_array($estado, ['REVERTIDA', 'BAJA'], true)) {
            return 'La OP está en estado '.$estado.'. Revise si corresponde enviarla.';
        }
        if ($estado === 'PRE CARGA') {
            return 'La OP aún está en Pre carga. Puede enviarla igualmente si el proveedor debe recibirla.';
        }

        return null;
    }

    /**
     * @return array{adjunta: bool, aviso: string|null, transferencia: ?InterbankingTransferencia}
     */
    public function infoAdjuntoComprobanteTransferencia(Pagoproveedor $pago): array
    {
        $transferencia = $this->resolverTransferenciaParaAdjunto($pago);
        if (! $transferencia instanceof InterbankingTransferencia) {
            $medio = $this->medioEnvio($pago);
            if ($medio === 'cheque') {
                return [
                    'adjunta' => false,
                    'aviso' => null,
                    'transferencia' => null,
                ];
            }
            if (in_array($medio, ['transferencia', 'mixto'], true)) {
                return [
                    'adjunta' => false,
                    'aviso' => 'No se encontró el formulario de transferencia Interbanking para adjuntar. Se enviará solo la OP.',
                    'transferencia' => null,
                ];
            }

            return [
                'adjunta' => false,
                'aviso' => null,
                'transferencia' => null,
            ];
        }

        $nro = (string) ($transferencia->transfer_id ?? $transferencia->id);

        return [
            'adjunta' => true,
            'aviso' => 'Se adjuntará también el formulario/comprobante de transferencia Interbanking nº '.$nro.'.',
            'transferencia' => $transferencia,
        ];
    }

    /**
     * @return array{ruta: string, nombre: string}|null
     */
    public function armarAdjuntoComprobanteTransferencia(Pagoproveedor $pago): ?array
    {
        $info = $this->infoAdjuntoComprobanteTransferencia($pago);
        if (! $info['adjunta'] || ! $info['transferencia'] instanceof InterbankingTransferencia) {
            return null;
        }

        return $this->comprobanteTransferenciaSupport->generarPdfTemporal($info['transferencia']);
    }

    public function resolverTransferenciaParaAdjunto(Pagoproveedor $pago): ?InterbankingTransferencia
    {
        $medio = $this->medioEnvio($pago);
        if ($medio === null || $medio === 'cheque') {
            return null;
        }

        $transferenciaId = (int) ($pago->interbanking_transferencia_id ?? 0);
        if ($transferenciaId > 0) {
            $transferencia = $pago->relationLoaded('interbanking_transferencias')
                ? $pago->interbanking_transferencias
                : InterbankingTransferencia::query()->find($transferenciaId);

            if (! $transferencia instanceof InterbankingTransferencia) {
                return null;
            }

            $cbuCredito = $this->comprobanteTransferenciaSupport->cbuCuenta(
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

            return PagoproveedorEnvioMasivoClasificacionSupport::adjuntaComprobante($asociacion['estado'])
                ? $transferencia
                : null;
        }

        $fechaOp = $pago->fecha ? $pago->fecha->format('Y-m-d') : '';
        if ($fechaOp === '' || (int) $pago->empresa_id <= 0) {
            return null;
        }

        $desde = $pago->fecha->copy()->subDays(7)->format('Y-m-d');
        $hasta = $pago->fecha->copy()->addDays(7)->format('Y-m-d');
        $registros = InterbankingTransferencia::query()
            ->where('empresa_id', (int) $pago->empresa_id)
            ->whereDate('request_date', '>=', $desde)
            ->whereDate('request_date', '<=', $hasta)
            ->orderBy('request_date')
            ->orderBy('id')
            ->get();

        $candidatos = [];
        $modelos = [];
        foreach ($registros as $transferencia) {
            $id = (int) $transferencia->id;
            $modelos[$id] = $transferencia;
            $resumen = $this->comprobanteTransferenciaSupport->cuentaResumen(
                $transferencia->credit_account_json,
                $transferencia->credit_account
            );
            $candidatos[] = [
                'id' => $id,
                'amount' => (float) $transferencia->amount,
                'cbu' => (string) ($resumen['cbu'] ?? ''),
                'cuit' => (string) ($resumen['cuit'] ?? ''),
                'fecha' => $transferencia->request_date ? $transferencia->request_date->format('Y-m-d') : '',
            ];
        }

        $usadas = [];
        $elegida = PagoproveedorEnvioMasivoClasificacionSupport::elegirPersistida([
            'neto' => $pago->netoAPagar(),
            'bruto' => (float) $pago->monto,
            'cbu' => (string) ($pago->cbu_pago ?? ''),
            'cuit' => preg_replace('/\D+/', '', (string) ($pago->proveedores->nroinscripcion ?? '')) ?? '',
            'fecha' => $fechaOp,
        ], $candidatos, $usadas);

        $elegidaId = (int) ($elegida['transferencia_id'] ?? 0);
        if ($elegidaId <= 0 || ! isset($modelos[$elegidaId])) {
            return null;
        }
        if (! PagoproveedorEnvioMasivoClasificacionSupport::adjuntaComprobante($elegida['estado'])) {
            return null;
        }

        return $modelos[$elegidaId];
    }

    private function medioEnvio(Pagoproveedor $pago): ?string
    {
        $snap = is_array($pago->anita_impresion_json) ? $pago->anita_impresion_json : null;
        $tieneCheque = ($pago->relationLoaded('cheques') && $pago->cheques->isNotEmpty())
            || ($pago->relationLoaded('caja_movimientos')
                && $pago->caja_movimientos->contains(static fn ($mov) => $mov->cheques->isNotEmpty()))
            || PagoproveedorEnvioMasivoClasificacionSupport::snapshotTieneCheques($snap);
        $senialTransferencia = (int) ($pago->interbanking_transferencia_id ?? 0) > 0
            || strlen(CbuSupport::normalizar((string) ($pago->cbu_pago ?? ''))) >= 22
            || PagoproveedorEnvioMasivoClasificacionSupport::snapshotTieneTransferencia($snap);

        return PagoproveedorEnvioMasivoClasificacionSupport::medio(
            $tieneCheque,
            $senialTransferencia,
            $this->tieneCuentaBancaria($pago)
        );
    }

    private function tieneCuentaBancaria(Pagoproveedor $pago): bool
    {
        if (! $pago->relationLoaded('caja_movimientos')) {
            return false;
        }
        foreach ($pago->caja_movimientos as $movimiento) {
            foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
                $cuenta = $linea->cuentacajas;
                if ($cuenta === null) {
                    continue;
                }
                if (CbuSupport::normalizar((string) ($cuenta->cbu ?? '')) !== ''
                    || trim((string) ($cuenta->cuenta_interbanking ?? '')) !== ''
                    || (int) ($cuenta->banco_id ?? 0) > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function normalizarMensaje(?string $mensaje): ?string
    {
        $mensaje = trim((string) $mensaje);
        if ($mensaje === '') {
            return null;
        }

        return mb_substr($mensaje, 0, 4000);
    }

    /**
     * Correos guardados con destinatario y texto, más envíos viejos que solo
     * dejaron el destinatario en la historia de estados.
     *
     * @return list<array{fecha: string, usuario: string, destinatarios: string, mensaje: string, texto_guardado: bool}>
     */
    public static function historialEnvios(int $pagoproveedorId): array
    {
        $filas = [];
        $estadosCubiertos = [];

        $correos = Pagoproveedor_Correo::query()
            ->with('usuarios:id,nombre')
            ->where('pagoproveedor_id', $pagoproveedorId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        foreach ($correos as $correo) {
            if ($correo->pagoproveedor_estado_id) {
                $estadosCubiertos[(int) $correo->pagoproveedor_estado_id] = true;
            }
            $filas[] = [
                'orden' => optional($correo->fecha)->getTimestamp() ?: 0,
                'fecha' => optional($correo->fecha)->format('d/m/Y H:i') ?? '',
                'usuario' => (string) ($correo->usuarios->nombre ?? ''),
                'destinatarios' => (string) $correo->destinatarios,
                'mensaje' => (string) ($correo->mensaje ?? ''),
                'texto_guardado' => true,
            ];
        }

        $prefijo = Pagoproveedor::PREFIJO_OBSERVACION_ENVIO_CORREO.'%';
        $legados = DB::table('pagoproveedor_estado as pe')
            ->leftJoin('usuario as u', 'u.id', '=', 'pe.usuario_id')
            ->where('pe.pagoproveedor_id', $pagoproveedorId)
            ->where('pe.observacion', 'like', $prefijo)
            ->orderByDesc('pe.fecha')
            ->orderByDesc('pe.id')
            ->limit(30)
            ->get(['pe.id', 'pe.fecha', 'pe.observacion', 'u.nombre as usuario_nombre']);

        foreach ($legados as $legado) {
            if (isset($estadosCubiertos[(int) $legado->id])) {
                continue;
            }
            $fecha = $legado->fecha ? \Illuminate\Support\Carbon::parse($legado->fecha) : null;
            $filas[] = [
                'orden' => $fecha?->getTimestamp() ?: 0,
                'fecha' => $fecha?->format('d/m/Y H:i') ?? '',
                'usuario' => (string) ($legado->usuario_nombre ?? ''),
                'destinatarios' => self::destinatariosDesdeObservacion((string) ($legado->observacion ?? '')),
                'mensaje' => '',
                'texto_guardado' => false,
            ];
        }

        usort($filas, static fn (array $a, array $b) => $b['orden'] <=> $a['orden']);

        return array_map(static function (array $fila) {
            unset($fila['orden']);

            return $fila;
        }, array_slice($filas, 0, 20));
    }

    public static function destinatariosDesdeObservacion(string $observacion): string
    {
        $prefijo = preg_quote(Pagoproveedor::PREFIJO_OBSERVACION_ENVIO_CORREO, '/');
        if (preg_match('/^'.$prefijo.' \(([^)]*)\)/u', $observacion, $coincidencias) === 1) {
            return trim($coincidencias[1]);
        }

        return '';
    }
}
