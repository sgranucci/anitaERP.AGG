<?php

namespace App\Services\Solicitudpago;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Solicitudpago\Concepto_Solicitudpago_Usuario;
use App\Models\Solicitudpago\Solicitudpago;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Árbol de aprobación de SP: vive en el concepto (concepto_solicitudpago_usuario),
 * no en el ABM global de árboles. Anita: concsolusu / SOLPM_procesa_arbol.
 */
class SolicitudpagoArbolIntegracionService
{
    public const TIPO_COMPROBANTE = 'SP';

    public function __construct(
        private Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacionMovimientoRepository,
        private SolicitudpagoRepositoryInterface $solicitudpagoRepository,
        private UsuarioRepositoryInterface $usuarioRepository,
    ) {
    }

    public function nombreTipoArbol(): string
    {
        $idx = array_search(self::TIPO_COMPROBANTE, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'));

        return Arbolaprobacion::$enumTipoArbol[$idx]['nombre'];
    }

    public function findPorSolicitudpago(int $solicitudpagoId)
    {
        return Arbolaprobacion_Movimiento::query()
            ->where('solicitudpago_id', $solicitudpagoId)
            ->whereNull('deleted_at')
            ->orderBy('nivel')
            ->orderBy('id')
            ->with('enviousuarios')
            ->with('destinatariousuarios')
            ->get();
    }

    public function dispararAlGuardar(int $solicitudpagoId): int
    {
        return app(\App\Services\Configuracion\ArbolaprobacionService::class)
            ->procesaArbolaprobacion(self::TIPO_COMPROBANTE, $solicitudpagoId, 'insert');
    }

    /**
     * @return array{ok: bool, mensaje: string, nivel?: int}
     */
    public function reenviarAlArbolAprobacion(int $solicitudpagoId): array
    {
        $sp = $this->solicitudpagoRepository->findOrFail($solicitudpagoId);

        if ($sp->estado === SolicitudpagoEstados::PAGADA) {
            return [
                'ok' => false,
                'mensaje' => 'No se puede reenviar al árbol una solicitud en estado PAGADA.',
            ];
        }

        return DB::transaction(function () use ($sp, $solicitudpagoId) {
            Arbolaprobacion_Movimiento::query()
                ->where('solicitudpago_id', $solicitudpagoId)
                ->delete();

            $estadosAptosSinReset = [
                SolicitudpagoEstados::EMITIDA,
                SolicitudpagoEstados::CONTROLADA,
            ];
            if (! in_array($sp->estado, $estadosAptosSinReset, true)) {
                $this->solicitudpagoRepository->cambiarEstado(
                    $solicitudpagoId,
                    SolicitudpagoEstados::EMITIDA,
                    'Reenvío al árbol de aprobación'
                );
            }

            $nivel = app(\App\Services\Configuracion\ArbolaprobacionService::class)
                ->procesaArbolaprobacion(self::TIPO_COMPROBANTE, $solicitudpagoId, 'insert');

            if ($nivel === -1) {
                return [
                    'ok' => true,
                    'mensaje' => 'No había niveles pendientes en el árbol del concepto; la solicitud quedó AUTORIZADA.',
                    'nivel' => -1,
                ];
            }

            if ($nivel <= 0) {
                return [
                    'ok' => false,
                    'mensaje' => 'No se pudo reenviar: la SP no tiene concepto, o el concepto no tiene firmantes '
                        .'operativos (solapa Usuarios del concepto) para el monto de la solicitud. '
                        .'Se limpiaron los movimientos previos.',
                    'nivel' => 0,
                ];
            }

            return [
                'ok' => true,
                'mensaje' => 'La solicitud se envió nuevamente al árbol del concepto (nivel '.$nivel.').',
                'nivel' => (int) $nivel,
            ];
        });
    }

    /**
     * @return array{ok: bool, mensaje: string, enviados?: int, nivel?: int}
     */
    public function reenviarCorreoNivelPendiente(int $solicitudpagoId): array
    {
        $sp = $this->solicitudpagoRepository->findOrFail($solicitudpagoId);

        if ($sp->estado === SolicitudpagoEstados::PAGADA) {
            return [
                'ok' => false,
                'mensaje' => 'No se puede reenviar el correo de una solicitud PAGADA.',
            ];
        }

        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $pendientes = Arbolaprobacion_Movimiento::query()
            ->where('solicitudpago_id', $solicitudpagoId)
            ->where('estado', $nombrePendiente)
            ->whereNotNull('destinatariousuario_id')
            ->where('destinatariousuario_id', '>', 0)
            ->orderBy('nivel')
            ->orderBy('id')
            ->get();

        if ($pendientes->isEmpty()) {
            return [
                'ok' => false,
                'mensaje' => 'No hay aprobaciones pendientes con destinatario para reenviar el correo. '
                    .'Si la SP recién se creó y nadie recibió mail, use «Reenviar al árbol» '
                    .'(firmantes del concepto de la solicitud).',
            ];
        }

        $nivelActual = (int) $pendientes->max('nivel');
        $delNivel = $pendientes->where('nivel', $nivelActual)->values();

        $spMail = Solicitudpago::query()->with(['monedas', 'empresas', 'proveedores'])->find($solicitudpagoId);
        if (! $spMail) {
            return [
                'ok' => false,
                'mensaje' => 'Solicitud de pago no encontrada.',
            ];
        }

        $tipoarbol = $this->nombreTipoArbol();
        $ip = (string) config('arbolaprobacion.ip_link');
        $arbolService = app(\App\Services\Configuracion\ArbolaprobacionService::class);
        $extras = [
            'monto_items' => (float) $spMail->monto,
            'moneda_abrev_items' => optional($spMail->monedas)->abreviatura ?? '',
        ];

        $enviados = 0;
        $errores = [];
        foreach ($delNivel as $mov) {
            $uid = (int) $mov->destinatariousuario_id;
            if ($uid <= 0) {
                continue;
            }
            if (trim((string) ($mov->hashaprobacion ?? '')) === ''
                || trim((string) ($mov->hashrechazo ?? '')) === '') {
                $errores[] = 'Movimiento #'.$mov->id.' sin hashes de aprobación/rechazo.';

                continue;
            }

            $linkAprobacion = ArbolAprobacionEnlaceSupport::enlaceAprobar(
                $ip,
                self::TIPO_COMPROBANTE,
                $solicitudpagoId,
                (string) $mov->hashaprobacion
            );
            $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo(
                $ip,
                self::TIPO_COMPROBANTE,
                $solicitudpagoId,
                (string) $mov->hashrechazo
            );
            $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar(
                $ip,
                'solicitudpago/solicitudpago',
                $solicitudpagoId,
                (string) ($mov->hashvisualizar ?? $mov->hashaprobacion)
            );

            try {
                $arbolService->enviaCorreo(
                    $uid,
                    $tipoarbol,
                    $spMail,
                    $linkAprobacion,
                    $linkRechazo,
                    $linkVisualizar,
                    $extras
                );
                $mov->update(['fechaenvio' => Carbon::now()]);
                $enviados++;
            } catch (\Throwable $e) {
                $errores[] = 'Usuario '.$uid.': '.$e->getMessage();
            }
        }

        if ($enviados === 0) {
            return [
                'ok' => false,
                'mensaje' => 'No se pudo reenviar el correo. '
                    .($errores !== [] ? implode(' | ', $errores) : 'Sin destinatarios válidos.'),
                'enviados' => 0,
                'nivel' => $nivelActual,
            ];
        }

        $mensaje = 'Se reenvió el correo del nivel '.$nivelActual.' a '.$enviados.' destinatario(s).';
        if ($errores !== []) {
            $mensaje .= ' Observaciones: '.implode(' | ', $errores);
        }

        return [
            'ok' => true,
            'mensaje' => $mensaje,
            'enviados' => $enviados,
            'nivel' => $nivelActual,
        ];
    }

    public function tienePendientesConCorreo(int $solicitudpagoId): bool
    {
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        return Arbolaprobacion_Movimiento::query()
            ->where('solicitudpago_id', $solicitudpagoId)
            ->where('estado', $nombrePendiente)
            ->whereNotNull('destinatariousuario_id')
            ->where('destinatariousuario_id', '>', 0)
            ->exists();
    }

    public function procesaArbol(
        int $comprobanteId,
        string $operacion,
        callable $leeAprobacionComprobante,
        callable $buscaProximoNivel,
        callable $enviaCorreo,
    ): int {
        unset($buscaProximoNivel); // El árbol SP no usa niveles del ABM global.

        $sp = Solicitudpago::query()
            ->with(['monedas', 'empresas', 'proveedores', 'conceptos.usuarios'])
            ->find($comprobanteId);
        if (! $sp) {
            return 0;
        }

        if (in_array($sp->estado, [
            SolicitudpagoEstados::AUTORIZADA,
            SolicitudpagoEstados::PAGADA,
            SolicitudpagoEstados::RECHAZADA,
            SolicitudpagoEstados::TERMINADA,
            SolicitudpagoEstados::SUSPENDIDA,
        ], true)) {
            return 0;
        }

        if (! $sp->concepto_solicitudpago_id) {
            return 0;
        }

        $tipoarbol = $this->nombreTipoArbol();
        $arbolShell = $this->asegurarArbolShell((int) ($sp->empresa_id ?? 0));
        $monto = (float) $sp->monto;
        $empresaId = (int) ($sp->empresa_id ?? 0);

        while (true) {
            $estadoAprobacionActual = $leeAprobacionComprobante($tipoarbol, $comprobanteId);
            $proximoNivel = $this->buscaProximoNivelDesdeConcepto(
                $sp,
                (int) $estadoAprobacionActual['nivelactual'],
                $monto,
                $empresaId
            );

            if ($proximoNivel['proximonivel'] === -1) {
                $this->finalizaTrasArbolCompleto($comprobanteId, Auth::id() ?? (int) $sp->usuario_umod_id);

                return -1;
            }

            if ($proximoNivel['proximonivel'] <= 0) {
                return 0;
            }

            $uids = $proximoNivel['proximousuarios'] ?? [];
            if (! is_array($uids) || $uids === []) {
                return 0;
            }

            $ip = config('arbolaprobacion.ip_link');
            $ref = (string) ($sp->codigo ?? $sp->id);
            $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('VIS'.$comprobanteId.$sp->fecha.$ref));
            $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar($ip, 'solicitudpago/solicitudpago', (int) $comprobanteId, $hashVisualizar);

            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $ya = Arbolaprobacion_Movimiento::query()
                ->where('solicitudpago_id', $comprobanteId)
                ->where('nivel', $proximoNivel['proximonivel'])
                ->where('estado', $nombrePendiente)
                ->pluck('destinatariousuario_id')
                ->map(fn ($x) => (int) $x)
                ->all();

            $creados = 0;
            foreach ($uids as $uid) {
                $uid = (int) $uid;
                if ($uid <= 0 || in_array($uid, $ya, true)) {
                    continue;
                }

                $hashAprobacion = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make(
                    self::TIPO_COMPROBANTE.'A'.$comprobanteId.$sp->fecha.$ref.'N'.$estadoAprobacionActual['nivelactual'].'U'.$uid
                ));
                $hashRechazo = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make(
                    self::TIPO_COMPROBANTE.'R'.$comprobanteId.$sp->fecha.$ref.'N'.$estadoAprobacionActual['nivelactual'].'U'.$uid
                ));
                $linkAprobacion = ArbolAprobacionEnlaceSupport::enlaceAprobar($ip, self::TIPO_COMPROBANTE, (int) $comprobanteId, $hashAprobacion);
                $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo($ip, self::TIPO_COMPROBANTE, (int) $comprobanteId, $hashRechazo);

                $enviaCorreo($uid, $tipoarbol, $sp, $linkAprobacion, $linkRechazo, $linkVisualizar, [
                    'monto_items' => $monto,
                    'moneda_abrev_items' => optional($sp->monedas)->abreviatura ?? '',
                ]);

                $this->arbolaprobacionMovimientoRepository->create([
                    'arbolaprobacion_id' => $arbolShell->id,
                    'fechaenvio' => Carbon::now(),
                    'enviousuario_id' => Auth::id() ?? $sp->usuario_umod_id,
                    'requisicion_id' => null,
                    'ordencompra_id' => null,
                    'solicitudpago_id' => $comprobanteId,
                    'ordenventa_id' => null,
                    'pedido_id' => null,
                    'hashaprobacion' => $hashAprobacion,
                    'hashrechazo' => $hashRechazo,
                    'hashvisualizar' => $hashVisualizar,
                    'nivel' => $proximoNivel['proximonivel'],
                    'destinatariousuario_id' => $uid,
                    'fechaproceso' => null,
                    'estado' => $nombrePendiente,
                    'observacion' => '',
                ]);
                $creados++;
            }

            if ($creados === 0 && $ya === []) {
                return 0;
            }

            return (int) $proximoNivel['proximonivel'];
        }
    }

    public function finalizaTrasArbolCompleto(int $solicitudpagoId, $usuarioId): void
    {
        $this->solicitudpagoRepository->cambiarEstado(
            $solicitudpagoId,
            SolicitudpagoEstados::AUTORIZADA,
            'Autorizada por árbol del concepto'
        );
    }

    public function rechazaPorRechazo(int $solicitudpagoId, $usuarioId, string $observacion): void
    {
        $this->solicitudpagoRepository->cambiarEstado(
            $solicitudpagoId,
            SolicitudpagoEstados::RECHAZADA,
            mb_substr(trim($observacion) !== '' ? $observacion : 'Rechazada en árbol', 0, 80)
        );
    }

    /**
     * @return array{proximonivel: int, proximousuario: ?int, proximousuarios: list<int>}
     */
    private function buscaProximoNivelDesdeConcepto(
        Solicitudpago $sp,
        int $nivelActual,
        float $monto,
        int $empresaId
    ): array {
        $conceptoId = (int) ($sp->concepto_solicitudpago_id ?? 0);
        if ($conceptoId <= 0) {
            return ['proximonivel' => 0, 'proximousuario' => null, 'proximousuarios' => []];
        }

        $filas = Concepto_Solicitudpago_Usuario::query()
            ->where('concepto_solicitudpago_id', $conceptoId)
            ->where('nivel', '>', 0)
            ->where('usuario_id', '>', 0)
            ->where(function ($q) use ($monto) {
                $q->whereNull('desde_monto')
                    ->orWhere('desde_monto', '<=', $monto);
            })
            ->orderBy('nivel')
            ->orderBy('id')
            ->get(['nivel', 'usuario_id']);

        if ($filas->isEmpty()) {
            // Sin firmantes aplicables: no hay circuito → autoriza (como árbol vacío completo).
            return ['proximonivel' => -1, 'proximousuario' => null, 'proximousuarios' => []];
        }

        $porNivel = [];
        foreach ($filas as $fila) {
            $n = (int) $fila->nivel;
            if ($n <= $nivelActual) {
                continue;
            }
            $porNivel[$n][] = (int) $fila->usuario_id;
        }

        if ($porNivel === []) {
            return ['proximonivel' => -1, 'proximousuario' => null, 'proximousuarios' => []];
        }

        ksort($porNivel, SORT_NUMERIC);
        $nivel = (int) array_key_first($porNivel);
        $uids = array_values(array_unique(array_filter($porNivel[$nivel])));

        if ($empresaId > 0) {
            $uids = $this->usuarioRepository->filtrarIdsOperativosPorEmpresa($uids, $empresaId);
        } else {
            $uids = $this->usuarioRepository->filtrarIdsOperativos($uids);
        }
        $uids = array_values(array_filter(array_map('intval', $uids)));

        if ($uids === []) {
            // Nivel sin firmantes operativos: salta buscando siguientes.
            $resto = $porNivel;
            unset($resto[$nivel]);
            while ($resto !== []) {
                $nivel = (int) array_key_first($resto);
                $uids = array_values(array_unique(array_filter($resto[$nivel])));
                unset($resto[$nivel]);
                if ($empresaId > 0) {
                    $uids = $this->usuarioRepository->filtrarIdsOperativosPorEmpresa($uids, $empresaId);
                } else {
                    $uids = $this->usuarioRepository->filtrarIdsOperativos($uids);
                }
                $uids = array_values(array_filter(array_map('intval', $uids)));
                if ($uids !== []) {
                    return [
                        'proximonivel' => $nivel,
                        'proximousuario' => $uids[0],
                        'proximousuarios' => $uids,
                    ];
                }
            }

            return ['proximonivel' => -1, 'proximousuario' => null, 'proximousuarios' => []];
        }

        return [
            'proximonivel' => $nivel,
            'proximousuario' => $uids[0],
            'proximousuarios' => $uids,
        ];
    }

    /**
     * Contenedor FK para arbolaprobacion_movimiento (los niveles reales están en el concepto).
     */
    private function asegurarArbolShell(int $empresaId): Arbolaprobacion
    {
        $tipo = $this->nombreTipoArbol();
        $q = Arbolaprobacion::query()
            ->where('tipoarbol', $tipo)
            ->where('estado', 'Activo')
            ->orderBy('id');

        if ($empresaId > 0) {
            $arbol = (clone $q)->where('empresa_id', $empresaId)->first()
                ?? (clone $q)->whereNull('empresa_id')->first();
        } else {
            $arbol = $q->first();
        }

        if ($arbol) {
            return $arbol;
        }

        return Arbolaprobacion::query()->create([
            'nombre' => 'SP — árbol por concepto (contenedor)',
            'tipoarbol' => $tipo,
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'recordatorio' => 'N',
            'diasinrespuesta' => 2,
            'diavencimientorecordatorio' => 15,
            'estado' => 'Activo',
        ]);
    }
}
