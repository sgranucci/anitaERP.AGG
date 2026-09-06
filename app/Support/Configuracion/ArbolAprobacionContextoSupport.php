<?php

namespace App\Support\Configuracion;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Sector_Legajocompra;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Configuracion\Arbolaprobacion_OcTrigger;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Support\Configuracion\OcArbolTriggerEvaluators\CapexMesExcedidoEvaluator;

/**
 * Contexto de solo lectura para explicar un paso de árbol (cualquier tipocomprobante).
 * No dispara ni modifica el circuito.
 */
final class ArbolAprobacionContextoSupport
{
    /**
     * @param  array{
     *   tipocomprobante: string,
     *   documento_id: int,
     *   numero?: string|null,
     *   empresa_id?: int|null,
     *   centrocosto_id: int,
     *   fecha: mixed,
     *   monto: float|int,
     *   moneda_id: int,
     *   etiqueta_tipo?: string,
     *   documento?: object|null
     * }  $snapshot
     * @return array<string,mixed>
     */
    public static function construir(
        ArbolaprobacionService $arbolService,
        array $snapshot,
        ?Arbolaprobacion_Movimiento $movimientoPendiente,
        UsuarioRepositoryInterface $usuarioRepository,
        ?string $estadoTrasAprobar = null,
    ): array {
        $tipo = strtoupper(trim((string) ($snapshot['tipocomprobante'] ?? '')));
        $trigger = self::cargarTrigger($tipo, $movimientoPendiente);
        $capexExcesos = [];
        if ($tipo === 'OC' && ($snapshot['documento'] ?? null) instanceof Ordencompra) {
            if ($trigger && strtoupper((string) ($trigger->evaluador ?? '')) === OcArbolTriggerCatalog::EVALUADOR_CAPEX_MES_EXCEDIDO) {
                $capexExcesos = (new CapexMesExcedidoEvaluator)->detalleExcesos($snapshot['documento']);
            } elseif ($trigger === null) {
                $capexExcesos = (new CapexMesExcedidoEvaluator)->detalleExcesos($snapshot['documento']);
            }
        }

        $pasoActual = null;
        if ($movimientoPendiente) {
            $nivel = (int) ($movimientoPendiente->nivel ?? 0);
            $firmantes = self::firmantesPendientesDelNivel(
                $arbolService,
                $tipo,
                (int) ($snapshot['documento_id'] ?? 0),
                $nivel,
                $usuarioRepository,
            );
            if ($firmantes === []) {
                $uid = (int) ($movimientoPendiente->destinatariousuario_id ?? 0);
                if ($uid > 0) {
                    $firmantes[] = [
                        'id' => $uid,
                        'nombre' => self::nombreUsuario($usuarioRepository, $uid) ?? ('Usuario #'.$uid),
                    ];
                }
            }
            $nombres = array_values(array_filter(array_column($firmantes, 'nombre')));
            $pasoActual = [
                'movimiento_id' => (int) $movimientoPendiente->id,
                'nivel' => $nivel,
                'firmante_id' => (int) ($firmantes[0]['id'] ?? $movimientoPendiente->destinatariousuario_id ?? 0) ?: null,
                'firmante_nombre' => $nombres[0] ?? null,
                'firmantes' => $firmantes,
                'firmantes_nombres' => $nombres,
                'circuito_oc' => $movimientoPendiente->circuito_oc ?? null,
            ];
        }

        $siAprobas = self::previewSiAprobas(
            $arbolService,
            $snapshot,
            $movimientoPendiente,
            $trigger,
            $usuarioRepository,
            $estadoTrasAprobar,
        );

        $parrafos = self::armarParrafos($tipo, $trigger, $capexExcesos, $pasoActual, $siAprobas, $snapshot);
        $score = self::calcularScore($trigger, $capexExcesos, $siAprobas);

        return [
            'tipocomprobante' => $tipo,
            'etiqueta_tipo' => (string) ($snapshot['etiqueta_tipo'] ?? self::etiquetaTipo($tipo)),
            'documento_id' => (int) ($snapshot['documento_id'] ?? 0),
            'numero' => $snapshot['numero'] ?? null,
            'empresa_id' => isset($snapshot['empresa_id']) ? (int) $snapshot['empresa_id'] : null,
            'trigger' => $trigger ? self::serializarTrigger($trigger) : null,
            'capex_excesos' => $capexExcesos,
            'paso_actual' => $pasoActual,
            'si_aprobas' => $siAprobas,
            'parrafos' => $parrafos,
            'score' => $score,
            'resumen' => [
                'tiene_trigger' => $trigger !== null,
                'capex_excesos' => count($capexExcesos),
                'cierra_circuito' => (bool) ($siAprobas['cierra_circuito'] ?? false),
                'parrafos' => count($parrafos),
            ],
        ];
    }

    public static function etiquetaTipo(string $tipo): string
    {
        $idx = array_search($tipo, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'), true);
        if ($idx === false) {
            return $tipo;
        }

        return (string) (Arbolaprobacion::$enumTipoArbol[$idx]['nombre'] ?? $tipo);
    }

    public static function entidadTipoAi(string $tipo): string
    {
        return match (strtoupper($tipo)) {
            'OC', 'SU' => 'ordencompra',
            'RE' => 'requisicion',
            'RS' => 'requisicion_sala',
            'SP' => 'solicitudpago',
            'OV' => 'ordenventa',
            'PE' => 'pedido',
            'AR' => 'articulo',
            default => 'arbolaprobacion_'.$tipo,
        };
    }

    private static function cargarTrigger(string $tipo, ?Arbolaprobacion_Movimiento $mov): ?Arbolaprobacion_OcTrigger
    {
        if ($tipo !== 'OC' || ! $mov) {
            return null;
        }
        $triggerId = (int) ($mov->arbolaprobacion_oc_trigger_id ?? 0);
        if ($triggerId <= 0) {
            return null;
        }

        return Arbolaprobacion_OcTrigger::query()
            ->with(['accion_final_sector:id,nombre', 'sector_origen:id,nombre', 'sector_destino:id,nombre'])
            ->find($triggerId);
    }

    /**
     * @return array<string,mixed>
     */
    private static function serializarTrigger(Arbolaprobacion_OcTrigger $trigger): array
    {
        return [
            'id' => (int) $trigger->id,
            'nombre' => (string) ($trigger->nombre ?? ''),
            'tipo' => (string) ($trigger->tipo ?? ''),
            'tipo_etiqueta' => OcArbolTriggerCatalog::etiquetaTipo((string) ($trigger->tipo ?? '')),
            'evento' => (string) ($trigger->evento ?? ''),
            'evento_etiqueta' => $trigger->evento
                ? OcArbolTriggerCatalog::etiquetaEvento((string) $trigger->evento)
                : null,
            'evaluador' => (string) ($trigger->evaluador ?? ''),
            'evaluador_etiqueta' => $trigger->evaluador
                ? OcArbolTriggerCatalog::etiquetaEvaluador((string) $trigger->evaluador)
                : null,
            'prioridad' => (int) ($trigger->prioridad ?? 0),
            'accion_final' => (string) ($trigger->accion_final ?? OcArbolTriggerCatalog::ACCION_NINGUNA),
            'accion_final_etiqueta' => OcArbolTriggerCatalog::etiquetaAccionFinal(
                (string) ($trigger->accion_final ?? OcArbolTriggerCatalog::ACCION_NINGUNA)
            ),
        ];
    }

    /**
     * @param  array<string,mixed>  $snapshot
     * @return array<string,mixed>
     */
    private static function previewSiAprobas(
        ArbolaprobacionService $arbolService,
        array $snapshot,
        ?Arbolaprobacion_Movimiento $mov,
        ?Arbolaprobacion_OcTrigger $trigger,
        UsuarioRepositoryInterface $usuarioRepository,
        ?string $estadoTrasAprobar,
    ): array {
        $out = [
            'estado_tras_este_paso' => $estadoTrasAprobar,
            'proximo_nivel' => null,
            'proximos_firmantes' => [],
            'cierra_circuito' => false,
            'accion_final_preview' => null,
            'mensaje' => null,
        ];

        if (! $mov) {
            $out['mensaje'] = 'No hay movimiento pendiente de árbol para anticipar el próximo paso.';

            return $out;
        }

        $arbol = Arbolaprobacion::query()
            ->with('arbolaprobacion_niveles')
            ->find((int) $mov->arbolaprobacion_id);
        if (! $arbol) {
            $out['mensaje'] = 'No se pudo cargar el árbol de aprobación.';

            return $out;
        }

        $tipo = strtoupper((string) ($snapshot['tipocomprobante'] ?? ''));
        $centrocostoArbol = (int) ($snapshot['centrocosto_id'] ?? 0);
        if ($tipo === 'OC' && ($mov->circuito_oc ?? null) === ArbolaprobacionService::CIRCUITO_OC_CAMBIO_SECTOR) {
            $centrocostoArbol = (int) ($arbol->oc_sector_cambio_centrocosto_id ?? 0);
        }

        $prox = $arbolService->buscaProximoNivel(
            $arbol,
            $centrocostoArbol,
            (int) ($mov->nivel ?? 0),
            $snapshot['fecha'] ?? null,
            $snapshot['monto'] ?? 0,
            (int) ($snapshot['moneda_id'] ?? 0),
        );

        $proxNivel = (int) ($prox['proximonivel'] ?? 0);
        if ($proxNivel === -1) {
            $out['cierra_circuito'] = true;
            $out['mensaje'] = 'Con esta aprobación se completa el circuito de árbol.';
            $out['accion_final_preview'] = self::previewAccionFinal($tipo, $trigger);
        } elseif ($proxNivel > 0) {
            $out['proximo_nivel'] = $proxNivel;
            $firmantes = [];
            foreach ($prox['proximousuarios'] ?? [] as $uid) {
                $uid = (int) $uid;
                if ($uid <= 0) {
                    continue;
                }
                $firmantes[] = [
                    'id' => $uid,
                    'nombre' => self::nombreUsuario($usuarioRepository, $uid) ?? ('Usuario #'.$uid),
                ];
            }
            $out['proximos_firmantes'] = $firmantes;
            $out['mensaje'] = $firmantes === []
                ? 'Siguiente nivel '.$proxNivel.' (sin firmante humano; puede avanzar automático).'
                : 'Siguiente nivel '.$proxNivel.' → '.implode(', ', array_column($firmantes, 'nombre')).'.';
            if ($tipo === 'OC' && $trigger) {
                $out['accion_final_preview'] = self::previewAccionFinal($tipo, $trigger);
            }
        } else {
            $out['mensaje'] = 'No hay un próximo nivel aplicable tras este paso.';
        }

        return $out;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function previewAccionFinal(string $tipo, ?Arbolaprobacion_OcTrigger $trigger): ?array
    {
        if ($tipo !== 'OC' || ! $trigger) {
            return null;
        }
        $accion = strtoupper(trim((string) ($trigger->accion_final ?? OcArbolTriggerCatalog::ACCION_NINGUNA)));
        if ($accion === '' || $accion === OcArbolTriggerCatalog::ACCION_NINGUNA) {
            return [
                'accion' => OcArbolTriggerCatalog::ACCION_NINGUNA,
                'etiqueta' => OcArbolTriggerCatalog::etiquetaAccionFinal(OcArbolTriggerCatalog::ACCION_NINGUNA),
                'detalle' => 'Al cerrar el circuito no hay acción final automática.',
            ];
        }

        if ($accion === OcArbolTriggerCatalog::ACCION_CAMBIAR_SECTOR) {
            $sectorId = (int) ($trigger->accion_final_sector_id ?? 0);
            $nombre = $trigger->accion_final_sector->nombre ?? null;
            if ($nombre === null && $sectorId <= 0) {
                $nombre = Sector_Legajocompra::query()
                    ->whereRaw('UPPER(TRIM(nombre)) = ?', ['CUENTAS A PAGAR'])
                    ->value('nombre');
            }

            return [
                'accion' => $accion,
                'etiqueta' => OcArbolTriggerCatalog::etiquetaAccionFinal($accion),
                'sector_id' => $sectorId > 0 ? $sectorId : null,
                'sector_nombre' => $nombre,
                'detalle' => 'Al cerrar el circuito: cambiar sector legajo'
                    .($nombre ? ' → '.$nombre : ' (CUENTAS A PAGAR si está configurado).'),
            ];
        }

        if ($accion === OcArbolTriggerCatalog::ACCION_CAMBIAR_ESTADO) {
            $estado = trim((string) ($trigger->accion_final_estado ?? ''));

            return [
                'accion' => $accion,
                'etiqueta' => OcArbolTriggerCatalog::etiquetaAccionFinal($accion),
                'estado' => $estado !== '' ? $estado : null,
                'detalle' => $estado !== ''
                    ? 'Al cerrar el circuito: cambiar estado → '.$estado.'.'
                    : 'Al cerrar el circuito: cambiar estado (sin valor definido en el trigger).',
            ];
        }

        return [
            'accion' => $accion,
            'etiqueta' => OcArbolTriggerCatalog::etiquetaAccionFinal($accion),
            'detalle' => 'Acción final: '.$accion,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $capexExcesos
     * @param  array<string,mixed>|null  $pasoActual
     * @param  array<string,mixed>  $siAprobas
     * @param  array<string,mixed>  $snapshot
     * @return list<string>
     */
    private static function armarParrafos(
        string $tipo,
        ?Arbolaprobacion_OcTrigger $trigger,
        array $capexExcesos,
        ?array $pasoActual,
        array $siAprobas,
        array $snapshot,
    ): array {
        $out = [];
        $etiqueta = (string) ($snapshot['etiqueta_tipo'] ?? self::etiquetaTipo($tipo));
        $numero = $snapshot['numero'] ?? null;
        $out[] = 'Comprobante: '.$etiqueta.($numero ? ' '.$numero : ' #'.((int) ($snapshot['documento_id'] ?? 0))).'.';

        if ($trigger) {
            $linea = 'Disparó el trigger «'.($trigger->nombre ?: '#'.$trigger->id).'»';
            if ($trigger->tipo) {
                $linea .= ' ('.OcArbolTriggerCatalog::etiquetaTipo((string) $trigger->tipo);
                if ($trigger->evaluador) {
                    $linea .= ': '.OcArbolTriggerCatalog::etiquetaEvaluador((string) $trigger->evaluador);
                } elseif ($trigger->evento) {
                    $linea .= ': '.OcArbolTriggerCatalog::etiquetaEvento((string) $trigger->evento);
                }
                $linea .= ')';
            }
            $out[] = $linea.'.';
        } elseif ($tipo === 'OC') {
            $out[] = 'Circuito de árbol sin trigger OC asociado (flujo legacy o nivel sin OcTrigger).';
        }

        foreach (array_slice($capexExcesos, 0, 5) as $ex) {
            $nombre = $ex['capex_nombre'] ?? ('CAPEX #'.$ex['capex_id']);
            $out[] = sprintf(
                'CAPEX «%s» período %s: asignado %s · comprometido %s · esta línea %s → proyectado %s (excedente %s).',
                $nombre,
                $ex['periodo'],
                self::fmt($ex['asignado']),
                self::fmt($ex['comprometido']),
                self::fmt($ex['monto_linea']),
                self::fmt($ex['proyectado']),
                self::fmt($ex['excedente']),
            );
        }

        if ($pasoActual) {
            $nombres = $pasoActual['firmantes_nombres'] ?? [];
            if (! is_array($nombres) || $nombres === []) {
                $nombres = ! empty($pasoActual['firmante_nombre'])
                    ? [(string) $pasoActual['firmante_nombre']]
                    : [];
            }
            $lineaPaso = 'Paso actual: nivel '.(int) $pasoActual['nivel'];
            if (count($nombres) === 1) {
                $lineaPaso .= ' — '.$nombres[0];
            } elseif (count($nombres) > 1) {
                $lineaPaso .= ' — firmantes: '.implode(', ', $nombres);
            }
            $out[] = $lineaPaso.'.';
        }

        if (! empty($siAprobas['estado_tras_este_paso'])) {
            $out[] = 'Tras aprobar este paso, el estado pasa a: '.$siAprobas['estado_tras_este_paso'].'.';
        }

        if (! empty($siAprobas['mensaje'])) {
            $out[] = (string) $siAprobas['mensaje'];
        }

        if (! empty($siAprobas['cierra_circuito'])) {
            $accion = $siAprobas['accion_final_preview']['detalle'] ?? null;
            if (is_string($accion) && $accion !== '') {
                $out[] = $accion;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string,mixed>>  $capexExcesos
     * @param  array<string,mixed>  $siAprobas
     */
    private static function calcularScore(?Arbolaprobacion_OcTrigger $trigger, array $capexExcesos, array $siAprobas): float
    {
        $score = 0.55;
        if ($trigger) {
            $score += 0.2;
        }
        if ($capexExcesos !== []) {
            $score += 0.15;
        }
        if (! empty($siAprobas['proximo_nivel']) || ! empty($siAprobas['cierra_circuito'])) {
            $score += 0.1;
        }

        return max(0.0, min(1.0, round($score, 4)));
    }

    /**
     * Todos los firmantes con movimiento pendiente en el mismo nivel del comprobante.
     * Varios en un nivel: alcanza con que uno apruebe; en la consulta se listan todos.
     *
     * @return list<array{id: int, nombre: string}>
     */
    private static function firmantesPendientesDelNivel(
        ArbolaprobacionService $arbolService,
        string $tipo,
        int $documentoId,
        int $nivel,
        UsuarioRepositoryInterface $usuarioRepository,
    ): array {
        if ($documentoId <= 0 || $nivel <= 0 || $tipo === '') {
            return [];
        }

        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'] ?? 'Pendiente';

        $vistos = [];
        $firmantes = [];
        foreach ($arbolService->coleccionMovimientosPorTipo($tipo, $documentoId) as $mov) {
            if ((int) ($mov->nivel ?? 0) !== $nivel) {
                continue;
            }
            if (strcasecmp((string) ($mov->estado ?? ''), (string) $nombrePendiente) !== 0) {
                continue;
            }
            $uid = (int) ($mov->destinatariousuario_id ?? 0);
            if ($uid <= 0 || isset($vistos[$uid])) {
                continue;
            }
            $vistos[$uid] = true;
            $nombreRel = optional($mov->destinatariousuarios)->nombre;
            $firmantes[] = [
                'id' => $uid,
                'nombre' => $nombreRel
                    ? (string) $nombreRel
                    : (self::nombreUsuario($usuarioRepository, $uid) ?? ('Usuario #'.$uid)),
            ];
        }

        return $firmantes;
    }

    private static function nombreUsuario(UsuarioRepositoryInterface $usuarioRepository, int $usuarioId): ?string
    {
        if ($usuarioId <= 0) {
            return null;
        }
        $u = $usuarioRepository->findOperativo($usuarioId) ?? $usuarioRepository->find($usuarioId);

        return $u?->nombre ? (string) $u->nombre : null;
    }

    private static function fmt(float|int $n): string
    {
        return number_format((float) $n, 2, ',', '.');
    }
}
