<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Models\Solicitudpago\Concepto_Solicitudpago_Usuario;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Reemplazo genérico de firmantes en matrices de árbol:
 * - Niveles globales (RE/OC/OV/RS/PE y shell SP si hubiera)
 * - Conceptos SP (concepto_solicitudpago_usuario)
 * - Movimientos pendientes (reasigna destinatario + regenera hashes / mail opcional)
 */
class ArbolReemplazoFirmanteService
{
    /** Tipos de árbol global (ABM). SP en matriz global suele ser shell vacío; el árbol real está en conceptos. */
    public const TIPOS_GLOBALES = ['RE', 'OC', 'OV', 'RS', 'PE', 'SP'];

    public function __construct(
        private UsuarioRepositoryInterface $usuarioRepository,
        private ArbolaprobacionService $arbolaprobacionService,
    ) {
    }

    /**
     * @param  array{
     *   incluir_globales?: bool,
     *   incluir_conceptos_sp?: bool,
     *   actualizar_pendientes?: bool,
     *   reenviar_correo?: bool,
     *   tipos?: list<string>
     * }  $opciones
     * @return array<string, mixed>
     */
    public function previsualizar(int $usuarioOrigenId, int $usuarioDestinoId, array $opciones = []): array
    {
        $ctx = $this->resolverContexto($usuarioOrigenId, $usuarioDestinoId, $opciones);

        $niveles = $ctx['incluir_globales']
            ? $this->queryNivelesGlobales($usuarioOrigenId, $ctx['tipos_nombres'])->limit(200)->get()
            : collect();
        $conceptos = $ctx['incluir_conceptos_sp']
            ? $this->queryConceptosSp($usuarioOrigenId)->limit(200)->get()
            : collect();
        $pendientes = $ctx['actualizar_pendientes']
            ? $this->queryPendientes($usuarioOrigenId)->limit(200)->get()
            : collect();

        $omitidosNivel = 0;
        $muestrasNivel = [];
        foreach ($niveles as $nivel) {
            if ($this->nivelDestinoYaExiste($nivel, $usuarioDestinoId)) {
                $omitidosNivel++;

                continue;
            }
            if (count($muestrasNivel) < 25) {
                $muestrasNivel[] = [
                    'id' => (int) $nivel->id,
                    'arbol' => (string) (optional($nivel->arbolaprobaciones)->nombre ?? ''),
                    'tipo' => (string) (optional($nivel->arbolaprobaciones)->tipoarbol ?? ''),
                    'nivel' => (int) $nivel->nivel,
                    'centrocosto' => (string) (optional($nivel->centrocosto_ids)->codigo ?? ''),
                ];
            }
        }

        $omitidosConcepto = 0;
        $muestrasConcepto = [];
        foreach ($conceptos as $fila) {
            if ($this->conceptoDestinoYaExiste($fila, $usuarioDestinoId)) {
                $omitidosConcepto++;

                continue;
            }
            if (count($muestrasConcepto) < 25) {
                $muestrasConcepto[] = [
                    'id' => (int) $fila->id,
                    'concepto' => trim((string) (optional($fila->conceptos)->codigo ?? '').' '.(optional($fila->conceptos)->nombre ?? '')),
                    'nivel' => (int) $fila->nivel,
                    'desde_monto' => (float) ($fila->desde_monto ?? 0),
                ];
            }
        }

        $muestrasPend = [];
        foreach ($pendientes as $mov) {
            if (count($muestrasPend) >= 25) {
                break;
            }
            $ref = $this->referenciaMovimiento($mov);
            $muestrasPend[] = [
                'id' => (int) $mov->id,
                'tipo' => $ref['tipo'],
                'comprobante_id' => $ref['comprobante_id'],
                'nivel' => (int) $mov->nivel,
                'fechaenvio' => (string) ($mov->fechaenvio ?? ''),
            ];
        }

        $conteoNiveles = $ctx['incluir_globales']
            ? $this->queryNivelesGlobales($usuarioOrigenId, $ctx['tipos_nombres'])->count()
            : 0;
        $conteoConceptos = $ctx['incluir_conceptos_sp']
            ? $this->queryConceptosSp($usuarioOrigenId)->count()
            : 0;
        $conteoPendientes = $ctx['actualizar_pendientes']
            ? $this->queryPendientes($usuarioOrigenId)->count()
            : 0;

        return [
            'usuario_origen' => $this->resumenUsuario($usuarioOrigenId),
            'usuario_destino' => $this->resumenUsuario($usuarioDestinoId),
            'opciones' => [
                'incluir_globales' => $ctx['incluir_globales'],
                'incluir_conceptos_sp' => $ctx['incluir_conceptos_sp'],
                'actualizar_pendientes' => $ctx['actualizar_pendientes'],
                'reenviar_correo' => $ctx['reenviar_correo'],
                'tipos' => $ctx['tipos_codigos'],
            ],
            'conteos' => [
                'niveles_globales' => $conteoNiveles,
                'niveles_omitidos_duplicado' => $omitidosNivel,
                'conceptos_sp' => $conteoConceptos,
                'conceptos_omitidos_duplicado' => $omitidosConcepto,
                'pendientes' => $conteoPendientes,
                'total_aplicable' => max(0, $conteoNiveles - $omitidosNivel)
                    + max(0, $conteoConceptos - $omitidosConcepto)
                    + $conteoPendientes,
            ],
            'muestras' => [
                'niveles_globales' => $muestrasNivel,
                'conceptos_sp' => $muestrasConcepto,
                'pendientes' => $muestrasPend,
            ],
        ];
    }

    /**
     * @param  array{
     *   incluir_globales?: bool,
     *   incluir_conceptos_sp?: bool,
     *   actualizar_pendientes?: bool,
     *   reenviar_correo?: bool,
     *   tipos?: list<string>
     * }  $opciones
     * @return array<string, mixed>
     */
    public function aplicar(int $usuarioOrigenId, int $usuarioDestinoId, array $opciones = []): array
    {
        $ctx = $this->resolverContexto($usuarioOrigenId, $usuarioDestinoId, $opciones);

        return DB::transaction(function () use ($usuarioOrigenId, $usuarioDestinoId, $ctx) {
            $reemplazadosNivel = 0;
            $omitidosNivel = 0;
            $reemplazadosConcepto = 0;
            $omitidosConcepto = 0;
            $reemplazadosPend = 0;
            $correosEnviados = 0;
            $erroresMail = [];

            if ($ctx['incluir_globales']) {
                $niveles = $this->queryNivelesGlobales($usuarioOrigenId, $ctx['tipos_nombres'])->lockForUpdate()->get();
                foreach ($niveles as $nivel) {
                    if ($this->nivelDestinoYaExiste($nivel, $usuarioDestinoId)) {
                        $omitidosNivel++;

                        continue;
                    }
                    if (empty($nivel->usuario_orig_id)) {
                        $nivel->usuario_orig_id = $usuarioOrigenId;
                    }
                    // Vuelta al titular: destino = original → limpia rastro de suplencia.
                    if ((int) $nivel->usuario_orig_id === $usuarioDestinoId) {
                        $nivel->usuario_orig_id = null;
                    }
                    $nivel->usuario_id = $usuarioDestinoId;
                    $nivel->save();
                    $reemplazadosNivel++;
                }
            }

            if ($ctx['incluir_conceptos_sp']) {
                $filas = $this->queryConceptosSp($usuarioOrigenId)->lockForUpdate()->get();
                foreach ($filas as $fila) {
                    if ($this->conceptoDestinoYaExiste($fila, $usuarioDestinoId)) {
                        $omitidosConcepto++;

                        continue;
                    }
                    if (empty($fila->usuario_orig_id)) {
                        $fila->usuario_orig_id = $usuarioOrigenId;
                    }
                    if ((int) $fila->usuario_orig_id === $usuarioDestinoId) {
                        $fila->usuario_orig_id = null;
                    }
                    $fila->usuario_id = $usuarioDestinoId;
                    $fila->save();
                    $reemplazadosConcepto++;
                }
            }

            if ($ctx['actualizar_pendientes']) {
                $pendientes = $this->queryPendientes($usuarioOrigenId)->lockForUpdate()->get();
                foreach ($pendientes as $mov) {
                    $ok = $this->reasignarPendiente($mov, $usuarioDestinoId, $ctx['reenviar_correo'], $correosEnviados, $erroresMail);
                    if ($ok) {
                        $reemplazadosPend++;
                    }
                }
            }

            $logId = (int) DB::table('arbol_reemplazo_firmante_log')->insertGetId([
                'usuario_ejecutor_id' => Auth::id(),
                'usuario_origen_id' => $usuarioOrigenId,
                'usuario_destino_id' => $usuarioDestinoId,
                'operacion' => 'reemplazo',
                'incluir_globales' => $ctx['incluir_globales'] ? 1 : 0,
                'incluir_conceptos_sp' => $ctx['incluir_conceptos_sp'] ? 1 : 0,
                'actualizar_pendientes' => $ctx['actualizar_pendientes'] ? 1 : 0,
                'reenviar_correo' => $ctx['reenviar_correo'] ? 1 : 0,
                'tipos_json' => json_encode($ctx['tipos_codigos'], JSON_UNESCAPED_UNICODE),
                'conteo_niveles' => $reemplazadosNivel,
                'conteo_conceptos_sp' => $reemplazadosConcepto,
                'conteo_pendientes' => $reemplazadosPend,
                'conteo_correos' => $correosEnviados,
                'detalle_json' => json_encode([
                    'omitidos_nivel' => $omitidosNivel,
                    'omitidos_concepto' => $omitidosConcepto,
                    'errores_mail' => $erroresMail,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'ok' => true,
                'operacion' => 'reemplazo',
                'log_id' => $logId,
                'usuario_origen' => $this->resumenUsuario($usuarioOrigenId),
                'usuario_destino' => $this->resumenUsuario($usuarioDestinoId),
                'conteos' => [
                    'niveles_globales' => $reemplazadosNivel,
                    'niveles_omitidos_duplicado' => $omitidosNivel,
                    'conceptos_sp' => $reemplazadosConcepto,
                    'conceptos_omitidos_duplicado' => $omitidosConcepto,
                    'pendientes' => $reemplazadosPend,
                    'correos' => $correosEnviados,
                ],
                'errores_mail' => $erroresMail,
                'mensaje' => $this->mensajeResultado(
                    $reemplazadosNivel,
                    $reemplazadosConcepto,
                    $reemplazadosPend,
                    $correosEnviados,
                    $omitidosNivel + $omitidosConcepto
                ),
            ];
        });
    }

    /**
     * Devuelve al titular las posiciones donde figura como usuario_orig_id
     * (fin de vacaciones / vuelta al puesto).
     *
     * @param  array{
     *   incluir_globales?: bool,
     *   incluir_conceptos_sp?: bool,
     *   actualizar_pendientes?: bool,
     *   reenviar_correo?: bool,
     *   tipos?: list<string>
     * }  $opciones
     * @return array<string, mixed>
     */
    public function previsualizarRestaurar(int $titularId, array $opciones = []): array
    {
        $ctx = $this->resolverContextoRestaurar($titularId, $opciones);

        $niveles = $ctx['incluir_globales']
            ? $this->queryNivelesPorTitularOrig($titularId, $ctx['tipos_nombres'])->limit(200)->get()
            : collect();
        $conceptos = $ctx['incluir_conceptos_sp']
            ? $this->queryConceptosPorTitularOrig($titularId)->limit(200)->get()
            : collect();

        $suplentes = [];
        foreach ($niveles as $n) {
            $sid = (int) $n->usuario_id;
            if ($sid > 0 && $sid !== $titularId) {
                $suplentes[$sid] = true;
            }
        }
        foreach ($conceptos as $c) {
            $sid = (int) $c->usuario_id;
            if ($sid > 0 && $sid !== $titularId) {
                $suplentes[$sid] = true;
            }
        }
        $suplenteIds = array_map('intval', array_keys($suplentes));

        $pendientes = ($ctx['actualizar_pendientes'] && $suplenteIds !== [])
            ? $this->queryPendientesDeUsuarios($suplenteIds)->limit(200)->get()
            : collect();

        $muestrasNivel = [];
        foreach ($niveles as $nivel) {
            if (count($muestrasNivel) >= 25) {
                break;
            }
            $muestrasNivel[] = [
                'id' => (int) $nivel->id,
                'arbol' => (string) (optional($nivel->arbolaprobaciones)->nombre ?? ''),
                'tipo' => (string) (optional($nivel->arbolaprobaciones)->tipoarbol ?? ''),
                'nivel' => (int) $nivel->nivel,
                'centrocosto' => (string) (optional($nivel->centrocosto_ids)->codigo ?? ''),
                'suplente' => (string) (optional($nivel->usuarios)->nombre ?? ''),
            ];
        }

        $muestrasConcepto = [];
        foreach ($conceptos as $fila) {
            if (count($muestrasConcepto) >= 25) {
                break;
            }
            $muestrasConcepto[] = [
                'id' => (int) $fila->id,
                'concepto' => trim((string) (optional($fila->conceptos)->codigo ?? '').' '.(optional($fila->conceptos)->nombre ?? '')),
                'nivel' => (int) $fila->nivel,
                'desde_monto' => (float) ($fila->desde_monto ?? 0),
                'suplente' => (string) (optional($fila->usuarios)->nombre ?? ''),
            ];
        }

        $muestrasPend = [];
        foreach ($pendientes as $mov) {
            if (count($muestrasPend) >= 25) {
                break;
            }
            $ref = $this->referenciaMovimiento($mov);
            $muestrasPend[] = [
                'id' => (int) $mov->id,
                'tipo' => $ref['tipo'],
                'comprobante_id' => $ref['comprobante_id'],
                'nivel' => (int) $mov->nivel,
                'fechaenvio' => (string) ($mov->fechaenvio ?? ''),
                'suplente_id' => (int) $mov->destinatariousuario_id,
            ];
        }

        $conteoNiveles = $ctx['incluir_globales']
            ? $this->queryNivelesPorTitularOrig($titularId, $ctx['tipos_nombres'])->count()
            : 0;
        $conteoConceptos = $ctx['incluir_conceptos_sp']
            ? $this->queryConceptosPorTitularOrig($titularId)->count()
            : 0;
        $conteoPendientes = ($ctx['actualizar_pendientes'] && $suplenteIds !== [])
            ? $this->queryPendientesDeUsuarios($suplenteIds)->count()
            : 0;

        return [
            'operacion' => 'restaurar',
            'usuario_titular' => $this->resumenUsuario($titularId),
            'suplentes' => array_map(fn ($id) => $this->resumenUsuario($id), $suplenteIds),
            'opciones' => [
                'incluir_globales' => $ctx['incluir_globales'],
                'incluir_conceptos_sp' => $ctx['incluir_conceptos_sp'],
                'actualizar_pendientes' => $ctx['actualizar_pendientes'],
                'reenviar_correo' => $ctx['reenviar_correo'],
                'tipos' => $ctx['tipos_codigos'],
            ],
            'conteos' => [
                'niveles_globales' => $conteoNiveles,
                'niveles_omitidos_duplicado' => 0,
                'conceptos_sp' => $conteoConceptos,
                'conceptos_omitidos_duplicado' => 0,
                'pendientes' => $conteoPendientes,
                'total_aplicable' => $conteoNiveles + $conteoConceptos + $conteoPendientes,
            ],
            'muestras' => [
                'niveles_globales' => $muestrasNivel,
                'conceptos_sp' => $muestrasConcepto,
                'pendientes' => $muestrasPend,
            ],
        ];
    }

    /**
     * @param  array{
     *   incluir_globales?: bool,
     *   incluir_conceptos_sp?: bool,
     *   actualizar_pendientes?: bool,
     *   reenviar_correo?: bool,
     *   tipos?: list<string>
     * }  $opciones
     * @return array<string, mixed>
     */
    public function restaurarTitular(int $titularId, array $opciones = []): array
    {
        $ctx = $this->resolverContextoRestaurar($titularId, $opciones);

        return DB::transaction(function () use ($titularId, $ctx) {
            $reemplazadosNivel = 0;
            $omitidosNivel = 0;
            $reemplazadosConcepto = 0;
            $omitidosConcepto = 0;
            $reemplazadosPend = 0;
            $correosEnviados = 0;
            $erroresMail = [];
            $suplentes = [];

            if ($ctx['incluir_globales']) {
                $niveles = $this->queryNivelesPorTitularOrig($titularId, $ctx['tipos_nombres'])->lockForUpdate()->get();
                foreach ($niveles as $nivel) {
                    $suplenteId = (int) $nivel->usuario_id;
                    if ($suplenteId > 0 && $suplenteId !== $titularId) {
                        $suplentes[$suplenteId] = true;
                    }
                    if ($this->nivelDestinoYaExiste($nivel, $titularId)) {
                        $nivel->delete();
                        $omitidosNivel++;

                        continue;
                    }
                    $nivel->usuario_id = $titularId;
                    $nivel->usuario_orig_id = null;
                    $nivel->save();
                    $reemplazadosNivel++;
                }
            }

            if ($ctx['incluir_conceptos_sp']) {
                $filas = $this->queryConceptosPorTitularOrig($titularId)->lockForUpdate()->get();
                foreach ($filas as $fila) {
                    $suplenteId = (int) $fila->usuario_id;
                    if ($suplenteId > 0 && $suplenteId !== $titularId) {
                        $suplentes[$suplenteId] = true;
                    }
                    if ($this->conceptoDestinoYaExiste($fila, $titularId)) {
                        $fila->delete();
                        $omitidosConcepto++;

                        continue;
                    }
                    $fila->usuario_id = $titularId;
                    $fila->usuario_orig_id = null;
                    $fila->save();
                    $reemplazadosConcepto++;
                }
            }

            $suplenteIds = array_map('intval', array_keys($suplentes));
            if ($ctx['actualizar_pendientes'] && $suplenteIds !== []) {
                $pendientes = $this->queryPendientesDeUsuarios($suplenteIds)->lockForUpdate()->get();
                foreach ($pendientes as $mov) {
                    $ok = $this->reasignarPendiente(
                        $mov,
                        $titularId,
                        $ctx['reenviar_correo'],
                        $correosEnviados,
                        $erroresMail,
                        'Reasignado por restauración de titular'
                    );
                    if ($ok) {
                        $reemplazadosPend++;
                    }
                }
            }

            $logId = (int) DB::table('arbol_reemplazo_firmante_log')->insertGetId([
                'usuario_ejecutor_id' => Auth::id(),
                'usuario_origen_id' => $suplenteIds[0] ?? 0,
                'usuario_destino_id' => $titularId,
                'operacion' => 'restaurar',
                'incluir_globales' => $ctx['incluir_globales'] ? 1 : 0,
                'incluir_conceptos_sp' => $ctx['incluir_conceptos_sp'] ? 1 : 0,
                'actualizar_pendientes' => $ctx['actualizar_pendientes'] ? 1 : 0,
                'reenviar_correo' => $ctx['reenviar_correo'] ? 1 : 0,
                'tipos_json' => json_encode($ctx['tipos_codigos'], JSON_UNESCAPED_UNICODE),
                'conteo_niveles' => $reemplazadosNivel,
                'conteo_conceptos_sp' => $reemplazadosConcepto,
                'conteo_pendientes' => $reemplazadosPend,
                'conteo_correos' => $correosEnviados,
                'detalle_json' => json_encode([
                    'titular_id' => $titularId,
                    'suplentes' => $suplenteIds,
                    'omitidos_nivel' => $omitidosNivel,
                    'omitidos_concepto' => $omitidosConcepto,
                    'errores_mail' => $erroresMail,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'ok' => true,
                'operacion' => 'restaurar',
                'log_id' => $logId,
                'usuario_titular' => $this->resumenUsuario($titularId),
                'conteos' => [
                    'niveles_globales' => $reemplazadosNivel,
                    'niveles_omitidos_duplicado' => $omitidosNivel,
                    'conceptos_sp' => $reemplazadosConcepto,
                    'conceptos_omitidos_duplicado' => $omitidosConcepto,
                    'pendientes' => $reemplazadosPend,
                    'correos' => $correosEnviados,
                ],
                'errores_mail' => $erroresMail,
                'mensaje' => 'Restauración del titular: '.$this->mensajeResultado(
                    $reemplazadosNivel,
                    $reemplazadosConcepto,
                    $reemplazadosPend,
                    $correosEnviados,
                    $omitidosNivel + $omitidosConcepto
                ),
            ];
        });
    }

    /**
     * @return list<array{codigo: string, nombre: string, fuente: string}>
     */
    public function opcionesTipoArbol(): array
    {
        $out = [];
        foreach (Arbolaprobacion::$enumTipoArbol as $item) {
            $codigo = (string) $item['valor'];
            $nombre = (string) $item['nombre'];
            $fuente = $codigo === 'SP'
                ? 'Conceptos SP (matriz real) + shell global si existiera'
                : 'Árbol global (arbolaprobacion_nivel)';
            $out[] = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'fuente' => $fuente,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $opciones
     * @return array{
     *   incluir_globales: bool,
     *   incluir_conceptos_sp: bool,
     *   actualizar_pendientes: bool,
     *   reenviar_correo: bool,
     *   tipos_codigos: list<string>,
     *   tipos_nombres: list<string>
     * }
     */
    private function resolverContexto(int $usuarioOrigenId, int $usuarioDestinoId, array $opciones): array
    {
        if ($usuarioOrigenId <= 0 || $usuarioDestinoId <= 0) {
            throw new InvalidArgumentException('Debe indicar usuario origen y destino.');
        }
        if ($usuarioOrigenId === $usuarioDestinoId) {
            throw new InvalidArgumentException('El usuario origen y destino deben ser distintos.');
        }

        $origen = $this->usuarioRepository->find($usuarioOrigenId);
        if (! $origen) {
            throw new InvalidArgumentException('Usuario origen no encontrado.');
        }
        $destinoIds = $this->usuarioRepository->filtrarIdsOperativos([$usuarioDestinoId]);
        if ($destinoIds === []) {
            throw new InvalidArgumentException('El usuario destino no está operativo (activo).');
        }

        return $this->resolverAlcance($opciones);
    }

    /**
     * @param  array<string, mixed>  $opciones
     * @return array{
     *   incluir_globales: bool,
     *   incluir_conceptos_sp: bool,
     *   actualizar_pendientes: bool,
     *   reenviar_correo: bool,
     *   tipos_codigos: list<string>,
     *   tipos_nombres: list<string>
     * }
     */
    private function resolverContextoRestaurar(int $titularId, array $opciones): array
    {
        if ($titularId <= 0) {
            throw new InvalidArgumentException('Debe indicar el titular a restaurar.');
        }
        $titular = $this->usuarioRepository->find($titularId);
        if (! $titular) {
            throw new InvalidArgumentException('Titular no encontrado.');
        }
        $ops = $this->usuarioRepository->filtrarIdsOperativos([$titularId]);
        if ($ops === []) {
            throw new InvalidArgumentException('El titular a restaurar no está operativo (activo). Reactívelo antes de devolverle el árbol.');
        }

        return $this->resolverAlcance($opciones);
    }

    /**
     * @param  array<string, mixed>  $opciones
     * @return array{
     *   incluir_globales: bool,
     *   incluir_conceptos_sp: bool,
     *   actualizar_pendientes: bool,
     *   reenviar_correo: bool,
     *   tipos_codigos: list<string>,
     *   tipos_nombres: list<string>
     * }
     */
    private function resolverAlcance(array $opciones): array
    {
        $incluirGlobales = (bool) ($opciones['incluir_globales'] ?? true);
        $incluirConceptos = (bool) ($opciones['incluir_conceptos_sp'] ?? true);
        $actualizarPendientes = (bool) ($opciones['actualizar_pendientes'] ?? true);
        $reenviarCorreo = $actualizarPendientes && (bool) ($opciones['reenviar_correo'] ?? true);

        if (! $incluirGlobales && ! $incluirConceptos && ! $actualizarPendientes) {
            throw new InvalidArgumentException('Seleccione al menos un alcance (árboles globales, conceptos SP o pendientes).');
        }

        $tiposIn = $opciones['tipos'] ?? self::TIPOS_GLOBALES;
        if (! is_array($tiposIn) || $tiposIn === []) {
            $tiposIn = self::TIPOS_GLOBALES;
        }
        $tiposCodigos = [];
        foreach ($tiposIn as $t) {
            $t = strtoupper(trim((string) $t));
            if (in_array($t, self::TIPOS_GLOBALES, true)) {
                $tiposCodigos[] = $t;
            }
        }
        $tiposCodigos = array_values(array_unique($tiposCodigos));
        if ($tiposCodigos === []) {
            $tiposCodigos = self::TIPOS_GLOBALES;
        }

        $tiposNombres = [];
        foreach (Arbolaprobacion::$enumTipoArbol as $item) {
            if (in_array($item['valor'], $tiposCodigos, true)) {
                $tiposNombres[] = $item['nombre'];
            }
        }

        return [
            'incluir_globales' => $incluirGlobales,
            'incluir_conceptos_sp' => $incluirConceptos,
            'actualizar_pendientes' => $actualizarPendientes,
            'reenviar_correo' => $reenviarCorreo,
            'tipos_codigos' => $tiposCodigos,
            'tipos_nombres' => $tiposNombres,
        ];
    }

    private function queryNivelesGlobales(int $usuarioOrigenId, array $tiposNombres)
    {
        return Arbolaprobacion_Nivel::query()
            ->where('usuario_id', $usuarioOrigenId)
            ->whereHas('arbolaprobaciones', function ($q) use ($tiposNombres) {
                $q->whereIn('tipoarbol', $tiposNombres);
            })
            ->with(['arbolaprobaciones:id,nombre,tipoarbol', 'centrocosto_ids:id,codigo'])
            ->orderBy('arbolaprobacion_id')
            ->orderBy('nivel')
            ->orderBy('id');
    }

    private function queryNivelesPorTitularOrig(int $titularId, array $tiposNombres)
    {
        return Arbolaprobacion_Nivel::query()
            ->where('usuario_orig_id', $titularId)
            ->where('usuario_id', '!=', $titularId)
            ->whereHas('arbolaprobaciones', function ($q) use ($tiposNombres) {
                $q->whereIn('tipoarbol', $tiposNombres);
            })
            ->with(['arbolaprobaciones:id,nombre,tipoarbol', 'centrocosto_ids:id,codigo', 'usuarios:id,nombre,usuario'])
            ->orderBy('arbolaprobacion_id')
            ->orderBy('nivel')
            ->orderBy('id');
    }

    private function queryConceptosSp(int $usuarioOrigenId)
    {
        return Concepto_Solicitudpago_Usuario::query()
            ->where('usuario_id', $usuarioOrigenId)
            ->with(['conceptos:id,codigo,nombre'])
            ->orderBy('concepto_solicitudpago_id')
            ->orderBy('nivel')
            ->orderBy('id');
    }

    private function queryConceptosPorTitularOrig(int $titularId)
    {
        return Concepto_Solicitudpago_Usuario::query()
            ->where('usuario_orig_id', $titularId)
            ->where('usuario_id', '!=', $titularId)
            ->with(['conceptos:id,codigo,nombre', 'usuarios:id,nombre,usuario'])
            ->orderBy('concepto_solicitudpago_id')
            ->orderBy('nivel')
            ->orderBy('id');
    }

    private function queryPendientes(int $usuarioOrigenId)
    {
        return $this->queryPendientesDeUsuarios([$usuarioOrigenId]);
    }

    /**
     * @param  list<int>  $usuarioIds
     */
    private function queryPendientesDeUsuarios(array $usuarioIds)
    {
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        return Arbolaprobacion_Movimiento::query()
            ->whereIn('destinatariousuario_id', $usuarioIds)
            ->where('estado', $nombrePendiente)
            ->orderBy('id');
    }

    private function nivelDestinoYaExiste(Arbolaprobacion_Nivel $nivel, int $destinoId): bool
    {
        return Arbolaprobacion_Nivel::query()
            ->where('arbolaprobacion_id', $nivel->arbolaprobacion_id)
            ->where('nivel', $nivel->nivel)
            ->where('centrocosto_id', $nivel->centrocosto_id)
            ->where('usuario_id', $destinoId)
            ->where('id', '!=', $nivel->id)
            ->exists();
    }

    private function conceptoDestinoYaExiste(Concepto_Solicitudpago_Usuario $fila, int $destinoId): bool
    {
        return Concepto_Solicitudpago_Usuario::query()
            ->where('concepto_solicitudpago_id', $fila->concepto_solicitudpago_id)
            ->where('nivel', $fila->nivel)
            ->where('usuario_id', $destinoId)
            ->where('id', '!=', $fila->id)
            ->exists();
    }

    private function reasignarPendiente(
        Arbolaprobacion_Movimiento $mov,
        int $destinoId,
        bool $reenviarCorreo,
        int &$correosEnviados,
        array &$erroresMail,
        string $obsSuffix = 'Reasignado por reemplazo de firmante'
    ): bool {
        $ref = $this->referenciaMovimiento($mov);
        if ($ref['tipo'] === '' || $ref['comprobante_id'] <= 0) {
            $erroresMail[] = 'Movimiento #'.$mov->id.': sin comprobante asociado.';

            return false;
        }

        $hashAprobacion = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make(
            $ref['tipo'].'A'.$ref['comprobante_id'].'N'.$mov->nivel.'U'.$destinoId.'R'.uniqid('', true)
        ));
        $hashRechazo = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make(
            $ref['tipo'].'R'.$ref['comprobante_id'].'N'.$mov->nivel.'U'.$destinoId.'R'.uniqid('', true)
        ));
        $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make(
            'VIS'.$ref['tipo'].$ref['comprobante_id'].'U'.$destinoId.'R'.uniqid('', true)
        ));

        $mov->destinatariousuario_id = $destinoId;
        $mov->hashaprobacion = $hashAprobacion;
        $mov->hashrechazo = $hashRechazo;
        $mov->hashvisualizar = $hashVisualizar;
        if ($reenviarCorreo) {
            $mov->fechaenvio = Carbon::now();
        }
        $mov->observacion = trim((string) ($mov->observacion ?? ''));
        if ($mov->observacion !== '') {
            $mov->observacion .= ' | ';
        }
        $mov->observacion .= $obsSuffix;
        $mov->save();

        if (! $reenviarCorreo) {
            return true;
        }

        try {
            $this->enviarCorreoPendiente($mov, $ref, $hashAprobacion, $hashRechazo, $hashVisualizar, $destinoId);
            $correosEnviados++;
        } catch (\Throwable $e) {
            $erroresMail[] = 'Movimiento #'.$mov->id.': '.$e->getMessage();
        }

        return true;
    }

    /**
     * @param  array{tipo: string, comprobante_id: int, ruta_visualizar: string}  $ref
     */
    private function enviarCorreoPendiente(
        Arbolaprobacion_Movimiento $mov,
        array $ref,
        string $hashAprobacion,
        string $hashRechazo,
        string $hashVisualizar,
        int $destinoId
    ): void {
        $documento = $this->arbolaprobacionService->cargarDocumentoArbol($ref['tipo'], $ref['comprobante_id']);
        if (! $documento) {
            throw new InvalidArgumentException('Comprobante no encontrado para mail.');
        }

        $tipoNombre = $this->nombreTipoArbol($ref['tipo']);
        $ip = (string) config('arbolaprobacion.ip_link');
        $linkAprobacion = ArbolAprobacionEnlaceSupport::enlaceAprobar($ip, $ref['tipo'], $ref['comprobante_id'], $hashAprobacion);
        $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo($ip, $ref['tipo'], $ref['comprobante_id'], $hashRechazo);
        $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar($ip, $ref['ruta_visualizar'], $ref['comprobante_id'], $hashVisualizar);

        $extras = [];
        if ($ref['tipo'] === 'SP' && isset($documento->monto)) {
            $extras['monto_items'] = (float) $documento->monto;
            $extras['moneda_abrev_items'] = optional($documento->monedas)->abreviatura ?? '';
        }

        $this->arbolaprobacionService->enviaCorreo(
            $destinoId,
            $tipoNombre,
            $documento,
            $linkAprobacion,
            $linkRechazo,
            $linkVisualizar,
            $extras !== [] ? $extras : null
        );
    }

    /**
     * @return array{tipo: string, comprobante_id: int, ruta_visualizar: string}
     */
    private function referenciaMovimiento(Arbolaprobacion_Movimiento $mov): array
    {
        if ((int) ($mov->requisicion_id ?? 0) > 0) {
            return ['tipo' => 'RE', 'comprobante_id' => (int) $mov->requisicion_id, 'ruta_visualizar' => 'compras/requisicion'];
        }
        if ((int) ($mov->ordencompra_id ?? 0) > 0) {
            return ['tipo' => 'OC', 'comprobante_id' => (int) $mov->ordencompra_id, 'ruta_visualizar' => 'compras/ordencompra'];
        }
        if ((int) ($mov->solicitudpago_id ?? 0) > 0) {
            return ['tipo' => 'SP', 'comprobante_id' => (int) $mov->solicitudpago_id, 'ruta_visualizar' => 'solicitudpago/solicitudpago'];
        }
        if ((int) ($mov->ordenventa_id ?? 0) > 0) {
            return ['tipo' => 'OV', 'comprobante_id' => (int) $mov->ordenventa_id, 'ruta_visualizar' => 'ordenventa/ordenventa'];
        }
        if ((int) ($mov->requisicion_sala_id ?? 0) > 0) {
            return ['tipo' => 'RS', 'comprobante_id' => (int) $mov->requisicion_sala_id, 'ruta_visualizar' => 'sala/requisicion_sala'];
        }
        if ((int) ($mov->pedido_id ?? 0) > 0) {
            return ['tipo' => 'PE', 'comprobante_id' => (int) $mov->pedido_id, 'ruta_visualizar' => 'ventas/pedido'];
        }

        return ['tipo' => '', 'comprobante_id' => 0, 'ruta_visualizar' => ''];
    }

    private function nombreTipoArbol(string $codigo): string
    {
        $idx = array_search($codigo, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'));

        return $idx === false ? $codigo : (string) Arbolaprobacion::$enumTipoArbol[$idx]['nombre'];
    }

    /**
     * @return array{id: int, usuario: string, nombre: string}
     */
    private function resumenUsuario(int $id): array
    {
        $u = $this->usuarioRepository->find($id);

        return [
            'id' => $id,
            'usuario' => (string) ($u->usuario ?? ''),
            'nombre' => (string) ($u->nombre ?? ''),
        ];
    }

    private function mensajeResultado(
        int $niveles,
        int $conceptos,
        int $pendientes,
        int $correos,
        int $omitidos
    ): string {
        $partes = [];
        if ($niveles > 0) {
            $partes[] = $niveles.' nivel(es) de árbol global';
        }
        if ($conceptos > 0) {
            $partes[] = $conceptos.' firmante(s) en conceptos SP';
        }
        if ($pendientes > 0) {
            $partes[] = $pendientes.' movimiento(s) pendiente(s)';
        }
        if ($correos > 0) {
            $partes[] = $correos.' correo(s) reenviado(s)';
        }
        $msg = $partes === []
            ? 'No había firmantes aplicables con los criterios indicados.'
            : 'Se actualizó: '.implode(', ', $partes).'.';
        if ($omitidos > 0) {
            $msg .= ' Se omitieron/limpiaron '.$omitidos.' fila(s) por duplicado en el mismo nivel.';
        }

        return $msg;
    }
}
