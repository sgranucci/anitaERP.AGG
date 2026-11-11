<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\ModuloAvisoDestinatario;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Support\Database\EloquentAuditDeleteSupport;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reemplazo genérico de destinatarios de avisos por módulo (vacaciones / suplencia).
 * Guarda el titular en usuario_orig_id; restaura manual o por vence_el (cron).
 */
class ModuloAvisoReemplazoDestinatarioService
{
    public function __construct(
        private UsuarioRepositoryInterface $usuarioRepository,
    ) {}

    /**
     * @return list<array{codigo: string, nombre: string, cantidad_tipos: int}>
     */
    public function opcionesModulo(): array
    {
        $filas = ModuloAvisoTipo::query()
            ->selectRaw('modulo, COUNT(*) as cantidad_tipos')
            ->groupBy('modulo')
            ->orderBy('modulo')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $codigo = (string) $fila->modulo;
            $out[] = [
                'codigo' => $codigo,
                'nombre' => ucfirst($codigo),
                'cantidad_tipos' => (int) $fila->cantidad_tipos,
            ];
        }

        return $out;
    }

    /**
     * @param  array{modulos?: list<string>, con_fecha_tope?: bool, vence_el?: string|null}  $opciones
     * @return array<string, mixed>
     */
    public function previsualizar(int $usuarioOrigenId, int $usuarioDestinoId, array $opciones = []): array
    {
        $ctx = $this->resolverContexto($usuarioOrigenId, $usuarioDestinoId, $opciones);

        $filas = $this->queryDestinatariosPorUsuario($usuarioOrigenId, $ctx['modulos'])->limit(200)->get();
        $omitidos = 0;
        $muestras = [];
        foreach ($filas as $dest) {
            if ($this->destinoYaExiste($dest, $usuarioDestinoId)) {
                $omitidos++;

                continue;
            }
            if (count($muestras) < 40) {
                $muestras[] = $this->muestraDestinatario($dest);
            }
        }

        $conteo = $this->queryDestinatariosPorUsuario($usuarioOrigenId, $ctx['modulos'])->count();

        return [
            'operacion' => 'reemplazo',
            'usuario_origen' => $this->resumenUsuario($usuarioOrigenId),
            'usuario_destino' => $this->resumenUsuario($usuarioDestinoId),
            'opciones' => [
                'modulos' => $ctx['modulos'],
                'vence_el' => $ctx['vence_el'],
                'con_fecha_tope' => $ctx['vence_el'] !== null,
            ],
            'conteos' => [
                'destinatarios' => $conteo,
                'omitidos_duplicado' => $omitidos,
                'total_aplicable' => max(0, $conteo - $omitidos),
            ],
            'muestras' => [
                'destinatarios' => $muestras,
            ],
        ];
    }

    /**
     * @param  array{modulos?: list<string>, con_fecha_tope?: bool, vence_el?: string|null}  $opciones
     * @return array<string, mixed>
     */
    public function aplicar(int $usuarioOrigenId, int $usuarioDestinoId, array $opciones = []): array
    {
        $ctx = $this->resolverContexto($usuarioOrigenId, $usuarioDestinoId, $opciones);

        return DB::transaction(function () use ($usuarioOrigenId, $usuarioDestinoId, $ctx) {
            $reemplazados = 0;
            $omitidos = 0;

            $filas = $this->queryDestinatariosPorUsuario($usuarioOrigenId, $ctx['modulos'])->lockForUpdate()->get();
            foreach ($filas as $dest) {
                if ($this->destinoYaExiste($dest, $usuarioDestinoId)) {
                    $omitidos++;

                    continue;
                }
                if (empty($dest->usuario_orig_id)) {
                    $dest->usuario_orig_id = $usuarioOrigenId;
                }
                if ((int) $dest->usuario_orig_id === $usuarioDestinoId) {
                    $dest->usuario_orig_id = null;
                }
                $dest->usuario_id = $usuarioDestinoId;
                $dest->save();
                $reemplazados++;
            }

            $logId = (int) DB::table('modulo_aviso_reemplazo_log')->insertGetId([
                'usuario_ejecutor_id' => Auth::id(),
                'usuario_origen_id' => $usuarioOrigenId,
                'usuario_destino_id' => $usuarioDestinoId,
                'operacion' => 'reemplazo',
                'modulos_json' => json_encode($ctx['modulos'], JSON_UNESCAPED_UNICODE),
                'vence_el' => $ctx['vence_el'],
                'conteo_destinatarios' => $reemplazados,
                'conteo_omitidos' => $omitidos,
                'detalle_json' => json_encode(['omitidos' => $omitidos], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->marcarReemplazosPendientesAnteriores($usuarioOrigenId, $logId, 'supersedido');

            return [
                'ok' => true,
                'operacion' => 'reemplazo',
                'log_id' => $logId,
                'vence_el' => $ctx['vence_el'],
                'usuario_origen' => $this->resumenUsuario($usuarioOrigenId),
                'usuario_destino' => $this->resumenUsuario($usuarioDestinoId),
                'conteos' => [
                    'destinatarios' => $reemplazados,
                    'omitidos_duplicado' => $omitidos,
                ],
                'mensaje' => $this->mensajeResultado($reemplazados, $omitidos, $ctx['vence_el'], true),
            ];
        });
    }

    /**
     * @param  array{modulos?: list<string>}  $opciones
     * @return array<string, mixed>
     */
    public function previsualizarRestaurar(int $titularId, array $opciones = []): array
    {
        $ctx = $this->resolverContextoRestaurar($titularId, $opciones);

        $filas = $this->queryDestinatariosPorTitularOrig($titularId, $ctx['modulos'])->limit(200)->get();
        $suplentes = [];
        $muestras = [];
        foreach ($filas as $dest) {
            $sid = (int) $dest->usuario_id;
            if ($sid > 0 && $sid !== $titularId) {
                $suplentes[$sid] = true;
            }
            if (count($muestras) < 40) {
                $muestra = $this->muestraDestinatario($dest);
                $muestra['suplente'] = (string) (optional($dest->usuarios)->nombre ?? '');
                $muestras[] = $muestra;
            }
        }

        $conteo = $this->queryDestinatariosPorTitularOrig($titularId, $ctx['modulos'])->count();
        $suplenteIds = array_map('intval', array_keys($suplentes));

        return [
            'operacion' => 'restaurar',
            'usuario_titular' => $this->resumenUsuario($titularId),
            'suplentes' => array_map(fn ($id) => $this->resumenUsuario($id), $suplenteIds),
            'opciones' => [
                'modulos' => $ctx['modulos'],
                'vence_el' => null,
                'con_fecha_tope' => false,
            ],
            'conteos' => [
                'destinatarios' => $conteo,
                'omitidos_duplicado' => 0,
                'total_aplicable' => $conteo,
            ],
            'muestras' => [
                'destinatarios' => $muestras,
            ],
        ];
    }

    /**
     * @param  array{modulos?: list<string>, restaurado_modo?: string}  $opciones
     * @return array<string, mixed>
     */
    public function restaurarTitular(int $titularId, array $opciones = []): array
    {
        $ctx = $this->resolverContextoRestaurar($titularId, $opciones);
        $restauradoModo = (string) ($opciones['restaurado_modo'] ?? 'manual');
        if (! in_array($restauradoModo, ['manual', 'automatico'], true)) {
            $restauradoModo = 'manual';
        }

        return DB::transaction(function () use ($titularId, $ctx, $restauradoModo) {
            $restaurados = 0;
            $omitidos = 0;
            $suplentes = [];

            $filas = $this->queryDestinatariosPorTitularOrig($titularId, $ctx['modulos'])->lockForUpdate()->get();
            foreach ($filas as $dest) {
                $suplenteId = (int) $dest->usuario_id;
                if ($suplenteId > 0 && $suplenteId !== $titularId) {
                    $suplentes[$suplenteId] = true;
                }
                if ($this->destinoYaExiste($dest, $titularId)) {
                    EloquentAuditDeleteSupport::each(
                        ModuloAvisoDestinatario::query()->whereKey($dest->id)
                    );
                    $omitidos++;

                    continue;
                }
                $dest->usuario_id = $titularId;
                $dest->usuario_orig_id = null;
                $dest->save();
                $restaurados++;
            }

            $suplenteIds = array_map('intval', array_keys($suplentes));
            $logId = (int) DB::table('modulo_aviso_reemplazo_log')->insertGetId([
                'usuario_ejecutor_id' => Auth::id(),
                'usuario_origen_id' => $suplenteIds[0] ?? 0,
                'usuario_destino_id' => $titularId,
                'operacion' => 'restaurar',
                'modulos_json' => json_encode($ctx['modulos'], JSON_UNESCAPED_UNICODE),
                'conteo_destinatarios' => $restaurados,
                'conteo_omitidos' => $omitidos,
                'detalle_json' => json_encode([
                    'titular_id' => $titularId,
                    'suplentes' => $suplenteIds,
                    'omitidos' => $omitidos,
                    'restaurado_modo' => $restauradoModo,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->marcarReemplazosPendientesDelTitular($titularId, $restauradoModo, $logId);

            return [
                'ok' => true,
                'operacion' => 'restaurar',
                'log_id' => $logId,
                'usuario_titular' => $this->resumenUsuario($titularId),
                'conteos' => [
                    'destinatarios' => $restaurados,
                    'omitidos_duplicado' => $omitidos,
                ],
                'mensaje' => 'Restauración del titular: '.$this->mensajeResultado($restaurados, $omitidos),
            ];
        });
    }

    /**
     * @return array{procesados: int, ok: int, errores: list<string>, detalle: list<array<string, mixed>>}
     */
    public function procesarVencimientos(?Carbon $referencia = null): array
    {
        $referencia = $referencia ?? Carbon::now();
        $hoy = $referencia->copy()->startOfDay()->toDateString();

        $logs = DB::table('modulo_aviso_reemplazo_log')
            ->where('operacion', 'reemplazo')
            ->whereNotNull('vence_el')
            ->whereNull('restaurado_at')
            ->whereDate('vence_el', '<', $hoy)
            ->orderBy('id')
            ->get();

        $procesados = 0;
        $ok = 0;
        $errores = [];
        $detalle = [];

        foreach ($logs as $log) {
            $procesados++;
            $titularId = (int) $log->usuario_origen_id;
            $modulos = json_decode((string) ($log->modulos_json ?? '[]'), true);
            if (! is_array($modulos)) {
                $modulos = [];
            }

            try {
                $resultado = $this->restaurarTitular($titularId, [
                    'modulos' => $modulos,
                    'restaurado_modo' => 'automatico',
                ]);
                $ok++;
                $detalle[] = [
                    'log_id' => (int) $log->id,
                    'titular_id' => $titularId,
                    'vence_el' => (string) $log->vence_el,
                    'ok' => true,
                    'restauracion_log_id' => $resultado['log_id'] ?? null,
                    'mensaje' => $resultado['mensaje'] ?? '',
                ];
            } catch (\Throwable $e) {
                $errores[] = 'Log #'.$log->id.' (titular '.$titularId.'): '.$e->getMessage();
                $detalle[] = [
                    'log_id' => (int) $log->id,
                    'titular_id' => $titularId,
                    'vence_el' => (string) $log->vence_el,
                    'ok' => false,
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        return [
            'procesados' => $procesados,
            'ok' => $ok,
            'errores' => $errores,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  array<string, mixed>  $opciones
     * @return array{modulos: list<string>, vence_el: ?string}
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
     * @return array{modulos: list<string>, vence_el: ?string}
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
            throw new InvalidArgumentException('El titular a restaurar no está operativo (activo). Reactívelo antes de devolverle los avisos.');
        }

        return $this->resolverAlcance($opciones);
    }

    /**
     * @param  array<string, mixed>  $opciones
     * @return array{modulos: list<string>, vence_el: ?string}
     */
    private function resolverAlcance(array $opciones): array
    {
        $todos = array_column($this->opcionesModulo(), 'codigo');
        $modulosIn = $opciones['modulos'] ?? $todos;
        if (! is_array($modulosIn) || $modulosIn === []) {
            $modulosIn = $todos;
        }

        $modulos = [];
        foreach ($modulosIn as $m) {
            $m = strtolower(trim((string) $m));
            if ($m !== '' && in_array($m, $todos, true)) {
                $modulos[] = $m;
            }
        }
        $modulos = array_values(array_unique($modulos));
        if ($modulos === []) {
            $modulos = $todos;
        }

        return [
            'modulos' => $modulos,
            'vence_el' => $this->resolverVenceEl($opciones),
        ];
    }

    /**
     * @param  array<string, mixed>  $opciones
     */
    private function resolverVenceEl(array $opciones): ?string
    {
        $conFechaTope = (bool) ($opciones['con_fecha_tope'] ?? false);
        if (! $conFechaTope) {
            return null;
        }

        $raw = trim((string) ($opciones['vence_el'] ?? ''));
        if ($raw === '') {
            throw new InvalidArgumentException('Indicó fecha tope: complete la fecha de fin del reemplazo.');
        }

        try {
            $fecha = Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('La fecha tope no es válida (use AAAA-MM-DD).');
        }

        if ($fecha->lt(Carbon::today()->startOfDay())) {
            throw new InvalidArgumentException('La fecha tope no puede ser anterior a hoy.');
        }

        return $fecha->toDateString();
    }

    private function marcarReemplazosPendientesAnteriores(int $titularId, int $exceptoLogId, string $modo): void
    {
        if ($titularId <= 0) {
            return;
        }

        DB::table('modulo_aviso_reemplazo_log')
            ->where('operacion', 'reemplazo')
            ->where('usuario_origen_id', $titularId)
            ->whereNull('restaurado_at')
            ->where('id', '!=', $exceptoLogId)
            ->update([
                'restaurado_at' => now(),
                'restaurado_modo' => $modo,
                'updated_at' => now(),
            ]);
    }

    private function marcarReemplazosPendientesDelTitular(int $titularId, string $modo, int $restauracionLogId): void
    {
        if ($titularId <= 0) {
            return;
        }

        DB::table('modulo_aviso_reemplazo_log')
            ->where('operacion', 'reemplazo')
            ->where('usuario_origen_id', $titularId)
            ->whereNull('restaurado_at')
            ->update([
                'restaurado_at' => now(),
                'restaurado_modo' => $modo,
                'restauracion_log_id' => $restauracionLogId,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  list<string>  $modulos
     */
    private function queryDestinatariosPorUsuario(int $usuarioId, array $modulos)
    {
        return ModuloAvisoDestinatario::query()
            ->where('usuario_id', $usuarioId)
            ->whereHas('tipo', function ($q) use ($modulos) {
                if ($modulos !== []) {
                    $q->whereIn('modulo', $modulos);
                }
            })
            ->with(['tipo:id,modulo,codigo,nombre', 'empresas:id,nombre', 'centrocostos:id,codigo,nombre'])
            ->orderBy('modulo_aviso_tipo_id')
            ->orderBy('id');
    }

    /**
     * @param  list<string>  $modulos
     */
    private function queryDestinatariosPorTitularOrig(int $titularId, array $modulos)
    {
        return ModuloAvisoDestinatario::query()
            ->where('usuario_orig_id', $titularId)
            ->where('usuario_id', '!=', $titularId)
            ->whereHas('tipo', function ($q) use ($modulos) {
                if ($modulos !== []) {
                    $q->whereIn('modulo', $modulos);
                }
            })
            ->with([
                'tipo:id,modulo,codigo,nombre',
                'empresas:id,nombre',
                'centrocostos:id,codigo,nombre',
                'usuarios:id,nombre,usuario',
            ])
            ->orderBy('modulo_aviso_tipo_id')
            ->orderBy('id');
    }

    private function destinoYaExiste(ModuloAvisoDestinatario $dest, int $destinoId): bool
    {
        return ModuloAvisoDestinatario::query()
            ->where('modulo_aviso_tipo_id', $dest->modulo_aviso_tipo_id)
            ->where('usuario_id', $destinoId)
            ->where('id', '!=', $dest->id)
            ->where(function ($q) use ($dest) {
                if ($dest->empresa_id === null) {
                    $q->whereNull('empresa_id');
                } else {
                    $q->where('empresa_id', $dest->empresa_id);
                }
            })
            ->where(function ($q) use ($dest) {
                if ($dest->centrocosto_id === null) {
                    $q->whereNull('centrocosto_id');
                } else {
                    $q->where('centrocosto_id', $dest->centrocosto_id);
                }
            })
            ->exists();
    }

    /**
     * @return array{id: int, modulo: string, aviso: string, codigo: string, empresa: string, centrocosto: string, activo: bool}
     */
    private function muestraDestinatario(ModuloAvisoDestinatario $dest): array
    {
        $tipo = $dest->tipo;

        return [
            'id' => (int) $dest->id,
            'modulo' => (string) ($tipo->modulo ?? ''),
            'aviso' => (string) ($tipo->nombre ?? ''),
            'codigo' => (string) ($tipo->codigo ?? ''),
            'empresa' => (string) (optional($dest->empresas)->nombre ?? 'Todas'),
            'centrocosto' => (string) (optional($dest->centrocostos)->codigo ?? 'Todos'),
            'activo' => (bool) $dest->activo,
        ];
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
        int $destinatarios,
        int $omitidos,
        ?string $venceEl = null,
        bool $informarVigencia = false
    ): string {
        $msg = $destinatarios > 0
            ? 'Se actualizó '.$destinatarios.' destinatario(s) de aviso.'
            : 'No había destinatarios aplicables con los criterios indicados.';
        if ($omitidos > 0) {
            $msg .= ' Se omitieron/limpiaron '.$omitidos.' fila(s) por duplicado (mismo aviso + empresa + CC).';
        }
        if ($informarVigencia) {
            if ($venceEl !== null && $venceEl !== '') {
                $msg .= ' Vigente hasta el '.Carbon::parse($venceEl)->format('d/m/Y')
                    .' inclusive; restauración automática a las 00:00 del día siguiente.';
            } else {
                $msg .= ' Sin fecha tope: restaure el titular manualmente cuando corresponda.';
            }
        }

        return $msg;
    }
}
