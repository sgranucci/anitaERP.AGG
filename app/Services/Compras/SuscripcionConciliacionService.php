<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Suscripcion_Cargo;
use App\Models\Compras\Suscripcion_ComercioAlias;
use App\Models\Compras\Suscripcion_Conciliacion;
use App\Models\Compras\Suscripcion_Tarjeta;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\SuscripcionComercioSupport;
use App\Support\Compras\SuscripcionSupport;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Conciliación mensual del resumen de tarjeta corporativa contra las OC de suscripción.
 *
 * El circuito es: importar el resumen del emisor, cruzar cada cargo con la suscripción
 * que lo debería explicar, y dejar cada línea en uno de tres lugares — conciliada,
 * con desvío (vuelve al gerente) o sin identificar (gasto sin orden, hay que emitirla
 * o dar de baja el servicio). La cobertura del período es la proporción del gasto que
 * llegó a tener una orden detrás.
 */
class SuscripcionConciliacionService
{
    /** Encabezados aceptados en el CSV, por campo lógico. */
    private const COLUMNAS = [
        'fecha' => ['fecha', 'fecha_operacion', 'fechaoperacion', 'date', 'fecha consumo'],
        'comercio' => ['comercio', 'descripcion', 'detalle', 'concepto', 'establecimiento', 'merchant'],
        'ult4' => ['ult4', 'tarjeta', 'ultimos4', 'ultimos_4', 'ultimos 4', 'card', 'numero tarjeta'],
        'monto' => ['monto', 'importe', 'amount', 'total', 'importe pesos'],
        'moneda' => ['moneda', 'currency', 'divisa'],
    ];

    public function __construct(
        private ArbolaprobacionService $arbolaprobacionService,
    ) {}

    // ---------------------------------------------------------------- períodos

    /** @return Collection<int, Suscripcion_Conciliacion> */
    public function periodos(?int $empresaId = null): Collection
    {
        return Suscripcion_Conciliacion::query()
            ->with('empresas')
            ->withCount('suscripcion_cargos')
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('periodo')
            ->orderBy('empresa_id')
            ->get();
    }

    public function abrirPeriodo(int $empresaId, string $periodo): Suscripcion_Conciliacion
    {
        $periodo = $this->normalizarPeriodo($periodo);
        $inicio = Carbon::createFromFormat('Y-m-d', $periodo.'-01')->startOfMonth();

        return Suscripcion_Conciliacion::query()->firstOrCreate(
            ['empresa_id' => $empresaId, 'periodo' => $periodo],
            [
                'fecha_desde' => $inicio->toDateString(),
                'fecha_hasta' => $inicio->copy()->endOfMonth()->toDateString(),
                'estado' => Suscripcion_Conciliacion::ESTADO_ABIERTA,
            ]
        );
    }

    public function cerrarPeriodo(Suscripcion_Conciliacion $conciliacion, int $usuarioId): array
    {
        $abiertos = $conciliacion->suscripcion_cargos()
            ->whereIn('estado', [
                Suscripcion_Cargo::ESTADO_SIN_IDENTIFICAR,
                Suscripcion_Cargo::ESTADO_DESVIO,
                Suscripcion_Cargo::ESTADO_PENDIENTE_APROBACION,
            ])
            ->count();

        if ($abiertos > 0) {
            return [
                'ok' => false,
                'mensaje' => "Quedan {$abiertos} cargos sin resolver. Asociá, marcá a regularizar o descartá antes de cerrar.",
            ];
        }

        $conciliacion->update([
            'estado' => Suscripcion_Conciliacion::ESTADO_CERRADA,
            'cerro_usuario_id' => $usuarioId,
            'cerrado_at' => now(),
        ]);

        return ['ok' => true, 'mensaje' => 'Período cerrado.'];
    }

    // ------------------------------------------------------------------ import

    /**
     * Carga el resumen del emisor. Reimportar el mismo archivo no duplica: cada línea
     * lleva un hash de fecha + comercio + tarjeta + monto dentro del período.
     *
     * @return array{ok: bool, mensaje: string, importadas?: int, repetidas?: int, ignoradas?: int}
     */
    public function importarResumen(Suscripcion_Conciliacion $conciliacion, UploadedFile $archivo): array
    {
        if (! $conciliacion->abierta()) {
            return ['ok' => false, 'mensaje' => 'El período está cerrado.'];
        }

        $lectura = $this->filasDelArchivo($archivo);
        if (! $lectura['ok']) {
            return ['ok' => false, 'mensaje' => $lectura['mensaje']];
        }

        $filas = $lectura['filas'];
        $encabezado = array_shift($filas);
        if ($encabezado === null) {
            return ['ok' => false, 'mensaje' => 'No se pudo leer el encabezado del archivo.'];
        }

        $mapa = $this->mapearColumnas($encabezado);
        foreach (['fecha', 'comercio', 'monto'] as $requerida) {
            if (! isset($mapa[$requerida])) {
                return [
                    'ok' => false,
                    'mensaje' => 'Falta la columna "'.$requerida.'". El archivo debe tener al menos fecha, comercio y monto. '
                        .'Se leyó el encabezado: '.implode(' | ', array_map('strval', array_slice($encabezado, 0, 8))),
                ];
            }
        }

        $tarjetas = $this->tarjetasPorUlt4($conciliacion->empresa_id);
        $monedaDefault = $this->monedaDefault($conciliacion->empresa_id);
        $importadas = 0;
        $repetidas = 0;
        $ignoradas = 0;

        DB::beginTransaction();
        try {
            foreach ($filas as $fila) {
                $linea = $this->parsearFila($fila, $mapa);
                if ($linea === null) {
                    $ignoradas++;

                    continue;
                }

                $hash = $this->hashLinea($linea);
                $yaEsta = Suscripcion_Cargo::query()
                    ->where('suscripcion_conciliacion_id', $conciliacion->id)
                    ->where('hash_linea', $hash)
                    ->exists();
                if ($yaEsta) {
                    $repetidas++;

                    continue;
                }

                $ult4 = $linea['ult4'];
                $tarjeta = $ult4 ? ($tarjetas[$ult4] ?? null) : null;

                Suscripcion_Cargo::query()->create([
                    'suscripcion_conciliacion_id' => (int) $conciliacion->id,
                    'fecha' => $linea['fecha'],
                    'comercio' => $linea['comercio'],
                    'comercio_normalizado' => SuscripcionComercioSupport::normalizar($linea['comercio']),
                    'tarjeta_ult4' => $ult4,
                    'suscripcion_tarjeta_id' => $tarjeta?->id,
                    'monto' => $linea['monto'],
                    'moneda_id' => $tarjeta?->moneda_id ?? $monedaDefault,
                    'estado' => Suscripcion_Cargo::ESTADO_SIN_IDENTIFICAR,
                    'hash_linea' => $hash,
                ]);
                $importadas++;
            }

            $conciliacion->update([
                'archivo_nombre' => $archivo->getClientOriginalName(),
                'filas_importadas' => (int) $conciliacion->filas_importadas + $importadas,
                'importo_usuario_id' => (int) Auth::id(),
                'importado_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['ok' => false, 'mensaje' => 'Error al importar: '.$e->getMessage()];
        }

        $auto = $this->matchearAutomatico($conciliacion);

        return [
            'ok' => true,
            'importadas' => $importadas,
            'repetidas' => $repetidas,
            'ignoradas' => $ignoradas,
            'mensaje' => "Se importaron {$importadas} cargos ({$repetidas} repetidos, {$ignoradas} sin formato válido). "
                ."El cruce automático resolvió {$auto['asociados']} de {$auto['evaluados']}.",
        ];
    }

    // ----------------------------------------------------------------- matcheo

    /**
     * Cruza los cargos sin identificar contra las suscripciones vigentes.
     *
     * @return array{evaluados: int, asociados: int}
     */
    public function matchearAutomatico(Suscripcion_Conciliacion $conciliacion): array
    {
        $pendientes = $conciliacion->suscripcion_cargos()
            ->where('estado', Suscripcion_Cargo::ESTADO_SIN_IDENTIFICAR)
            ->whereNull('ordencompra_id')
            ->get();

        if ($pendientes->isEmpty()) {
            return ['evaluados' => 0, 'asociados' => 0];
        }

        $candidatas = $this->suscripcionesConciliables((int) $conciliacion->empresa_id);
        $alias = $this->aliasPorEmpresa((int) $conciliacion->empresa_id);
        $asociados = 0;

        foreach ($pendientes as $cargo) {
            $origen = null;
            $oc = $this->mejorCandidata($cargo, $candidatas, $alias, $origen);
            if (! $oc) {
                continue;
            }

            $this->aplicarAsociacion($cargo, $oc, $origen, null);
            $asociados++;
        }

        return ['evaluados' => $pendientes->count(), 'asociados' => $asociados];
    }

    /**
     * Sugerencias ordenadas para resolver un cargo a mano.
     *
     * @return list<array{ordencompra: Ordencompra, puntaje: float, motivo: string}>
     */
    public function sugerenciasPara(Suscripcion_Cargo $cargo, int $maximo = 5): array
    {
        $conciliacion = $cargo->suscripcion_conciliaciones;
        $candidatas = $this->suscripcionesConciliables((int) ($conciliacion->empresa_id ?? 0));
        $comercio = (string) ($cargo->comercio_normalizado ?: SuscripcionComercioSupport::normalizar($cargo->comercio));

        $out = [];
        foreach ($candidatas as $oc) {
            $puntaje = $this->puntajeCandidata($cargo, $oc, $comercio, $motivo);
            if ($puntaje <= 0) {
                continue;
            }
            $out[] = ['ordencompra' => $oc, 'puntaje' => $puntaje, 'motivo' => $motivo];
        }

        usort($out, fn ($a, $b) => $b['puntaje'] <=> $a['puntaje']);

        return array_slice($out, 0, $maximo);
    }

    /**
     * Asociación manual desde la pantalla. Guarda el alias para que el mes que viene
     * el mismo comercio se resuelva solo.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function asociarManual(Suscripcion_Cargo $cargo, int $ordencompraId, int $usuarioId, bool $aprenderAlias = true): array
    {
        if (! $cargo->suscripcion_conciliaciones?->abierta()) {
            return ['ok' => false, 'mensaje' => 'El período está cerrado.'];
        }

        $oc = Ordencompra::query()->with('proveedores')->find($ordencompraId);
        if (! $oc || ! (bool) $oc->es_suscripcion) {
            return ['ok' => false, 'mensaje' => 'La orden elegida no es una suscripción.'];
        }
        if ((int) $oc->empresa_id !== (int) $cargo->suscripcion_conciliaciones->empresa_id) {
            return ['ok' => false, 'mensaje' => 'La suscripción pertenece a otra empresa.'];
        }

        $this->aplicarAsociacion($cargo, $oc, 'MANUAL', $usuarioId);

        if ($aprenderAlias) {
            $this->aprenderAlias($cargo, $oc, $usuarioId);
        }

        $estado = $cargo->fresh()->estado;

        return [
            'ok' => true,
            'mensaje' => $estado === Suscripcion_Cargo::ESTADO_DESVIO
                ? 'Cargo asociado, pero supera el tope autorizado: quedó marcado como desvío.'
                : 'Cargo conciliado.',
        ];
    }

    public function desasociar(Suscripcion_Cargo $cargo): void
    {
        $ocPrevia = (int) ($cargo->ordencompra_id ?? 0);

        $cargo->update([
            'ordencompra_id' => null,
            'estado' => Suscripcion_Cargo::ESTADO_SIN_IDENTIFICAR,
            'desvio_pct' => null,
            'origen_match' => null,
            'asocio_usuario_id' => null,
            'asociado_at' => null,
        ]);

        if ($ocPrevia > 0) {
            $this->refrescarDesvioAbierto($ocPrevia);
        }
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function marcarEstado(Suscripcion_Cargo $cargo, string $estado, ?string $observacion = null): array
    {
        $permitidos = [Suscripcion_Cargo::ESTADO_REGULARIZAR, Suscripcion_Cargo::ESTADO_DESCARTADO];
        if (! in_array($estado, $permitidos, true)) {
            return ['ok' => false, 'mensaje' => 'Estado no permitido desde esta acción.'];
        }
        if (! $cargo->suscripcion_conciliaciones?->abierta()) {
            return ['ok' => false, 'mensaje' => 'El período está cerrado.'];
        }

        $cargo->update([
            'estado' => $estado,
            'observacion' => $observacion !== null ? mb_substr(trim($observacion), 0, 255) : $cargo->observacion,
        ]);

        if ($cargo->ordencompra_id) {
            $this->refrescarDesvioAbierto((int) $cargo->ordencompra_id);
        }

        return ['ok' => true, 'mensaje' => 'Cargo marcado como '.SuscripcionSupport::etiquetaEstadoCargo($estado).'.'];
    }

    // ------------------------------------------------------------------ desvío

    /**
     * Manda el desvío de vuelta al gerente por el mismo árbol de Suscripciones.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function enviarDesvioAReaprobacion(Suscripcion_Cargo $cargo, int $usuarioId): array
    {
        if ($cargo->estado !== Suscripcion_Cargo::ESTADO_DESVIO) {
            return ['ok' => false, 'mensaje' => 'El cargo no está marcado como desvío.'];
        }
        $ocId = (int) ($cargo->ordencompra_id ?? 0);
        if ($ocId <= 0) {
            return ['ok' => false, 'mensaje' => 'El cargo no tiene una suscripción asociada.'];
        }

        $oc = Ordencompra::query()->find($ocId);
        $signo = ($cargo->desvio_pct ?? 0) >= 0 ? '+' : '';
        $obs = sprintf(
            'Revalidación por desvío: cargo del %s por %s (%s%s%% sobre el monto autorizado de %s).',
            optional($cargo->fecha)->format('d/m/Y') ?: '—',
            number_format((float) $cargo->monto, 2, ',', '.'),
            $signo,
            number_format((float) $cargo->desvio_pct, 2, ',', '.'),
            number_format((float) ($oc->suscripcion_monto_periodo ?? 0), 2, ',', '.')
        );

        DB::beginTransaction();
        try {
            $resultado = $this->arbolaprobacionService->procesaArbolaprobacion('SU', $ocId, 'insert', [
                'observacion_envio' => $obs,
                'permitir_estado_no_pendiente' => true,
                'reiniciar_circuito' => true,
            ]);

            // Cualquier resultado no positivo significa que no quedó nadie con el desvío
            // en la bandeja: sin nivel aplicable (0) o árbol dado por terminado (-1).
            if ((int) $resultado <= 0) {
                DB::rollBack();

                return [
                    'ok' => false,
                    'mensaje' => 'No hay gerente configurado para el área de esta suscripción. '
                        .'Cargalo en Compras › Suscripciones › Aprobadores.',
                ];
            }

            $cargo->update([
                'estado' => Suscripcion_Cargo::ESTADO_PENDIENTE_APROBACION,
                'asocio_usuario_id' => $usuarioId,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        return ['ok' => true, 'mensaje' => 'Desvío enviado al gerente del sector para revalidación.'];
    }

    /**
     * Cierra los desvíos de una suscripción cuando el gerente la vuelve a autorizar.
     * Se llama desde el circuito, no desde la pantalla.
     */
    public function resolverDesviosAprobados(int $ordencompraId, int $usuarioId): int
    {
        $cargos = Suscripcion_Cargo::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where('estado', Suscripcion_Cargo::ESTADO_PENDIENTE_APROBACION)
            ->get();

        foreach ($cargos as $cargo) {
            $cargo->update([
                'estado' => Suscripcion_Cargo::ESTADO_CONCILIADO,
                'observacion' => mb_substr('Desvío revalidado por el gerente. '.(string) $cargo->observacion, 0, 255),
                'asocio_usuario_id' => $usuarioId,
            ]);
        }

        $this->refrescarDesvioAbierto($ordencompraId);

        return $cargos->count();
    }

    // ----------------------------------------------------------------- resumen

    /**
     * Indicadores del período, con la cobertura como número de cabecera.
     *
     * @return array<string, float|int>
     */
    public function resumen(Suscripcion_Conciliacion $conciliacion): array
    {
        $cargos = $conciliacion->suscripcion_cargos()->get(['estado', 'monto', 'ordencompra_id']);

        $total = (float) $cargos->sum('monto');
        $conOrden = (float) $cargos->filter(fn ($c) => (int) $c->ordencompra_id > 0)->sum('monto');

        $porEstado = fn (string $estado) => $cargos->where('estado', $estado);

        return [
            'cargos' => $cargos->count(),
            'monto_total' => round($total, 2),
            'conciliados' => $porEstado(Suscripcion_Cargo::ESTADO_CONCILIADO)->count(),
            'monto_conciliado' => round((float) $porEstado(Suscripcion_Cargo::ESTADO_CONCILIADO)->sum('monto'), 2),
            'desvios' => $porEstado(Suscripcion_Cargo::ESTADO_DESVIO)->count(),
            'monto_desvio' => round((float) $porEstado(Suscripcion_Cargo::ESTADO_DESVIO)->sum('monto'), 2),
            'sin_identificar' => $porEstado(Suscripcion_Cargo::ESTADO_SIN_IDENTIFICAR)->count(),
            'monto_sin_identificar' => round((float) $porEstado(Suscripcion_Cargo::ESTADO_SIN_IDENTIFICAR)->sum('monto'), 2),
            'en_reaprobacion' => $porEstado(Suscripcion_Cargo::ESTADO_PENDIENTE_APROBACION)->count(),
            'a_regularizar' => $porEstado(Suscripcion_Cargo::ESTADO_REGULARIZAR)->count(),
            'cobertura_pct' => $total > 0 ? round(($conOrden / $total) * 100, 1) : 0.0,
        ];
    }

    // -------------------------------------------------------------- internos

    /**
     * Suscripciones que pueden explicar un cargo: aprobadas o cumplidas y no borrador.
     *
     * @return Collection<int, Ordencompra>
     */
    private function suscripcionesConciliables(int $empresaId): Collection
    {
        return Ordencompra::query()
            ->with('proveedores')
            ->where('es_suscripcion', true)
            ->where('suscripcion_borrador', false)
            ->where('empresa_id', $empresaId)
            ->whereIn('estadoordencompra', [OrdencompraEstados::APROBADA, OrdencompraEstados::CUMPLIDA])
            ->get();
    }

    /** @return array<string, Suscripcion_ComercioAlias> */
    private function aliasPorEmpresa(int $empresaId): array
    {
        return Suscripcion_ComercioAlias::query()
            ->where(fn ($q) => $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id'))
            ->orderByDesc('veces_usado')
            ->get()
            ->keyBy(fn (Suscripcion_ComercioAlias $a) => (string) $a->alias)
            ->all();
    }

    /** @return array<string, Suscripcion_Tarjeta> */
    private function tarjetasPorUlt4(int $empresaId): array
    {
        return Suscripcion_Tarjeta::query()
            ->where('empresa_id', $empresaId)
            ->get()
            ->keyBy(fn (Suscripcion_Tarjeta $t) => (string) $t->ult4)
            ->all();
    }

    /**
     * @param  Collection<int, Ordencompra>  $candidatas
     * @param  array<string, Suscripcion_ComercioAlias>  $alias
     */
    private function mejorCandidata(
        Suscripcion_Cargo $cargo,
        Collection $candidatas,
        array $alias,
        ?string &$origen
    ): ?Ordencompra {
        $comercio = (string) ($cargo->comercio_normalizado ?: SuscripcionComercioSupport::normalizar($cargo->comercio));
        if ($comercio === '') {
            return null;
        }

        // El diccionario manda: si alguien ya resolvió este comercio, se respeta.
        $entrada = $alias[$comercio] ?? null;
        if ($entrada) {
            $porAlias = $entrada->ordencompra_id
                ? $candidatas->firstWhere('id', (int) $entrada->ordencompra_id)
                : ($entrada->proveedor_id
                    ? $candidatas->firstWhere('proveedor_id', (int) $entrada->proveedor_id)
                    : null);

            if ($porAlias) {
                $entrada->increment('veces_usado');
                $entrada->update(['ultimo_uso_at' => now()]);
                $origen = 'ALIAS';

                return $porAlias;
            }
        }

        $mejor = null;
        $mejorPuntaje = 0.0;
        foreach ($candidatas as $oc) {
            $puntaje = $this->puntajeCandidata($cargo, $oc, $comercio, $motivo);
            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejor = $oc;
            }
        }

        if ($mejor === null || $mejorPuntaje < SuscripcionComercioSupport::UMBRAL_SIMILITUD) {
            return null;
        }

        $origen = 'AUTO';

        return $mejor;
    }

    /**
     * Puntaje 0-100 de una suscripción como explicación del cargo.
     *
     * El texto del comercio decide; la tarjeta y el importe cercano suman confianza,
     * y una tarjeta distinta la descarta porque el plástico es dato duro del resumen.
     */
    private function puntajeCandidata(Suscripcion_Cargo $cargo, Ordencompra $oc, string $comercio, ?string &$motivo = null): float
    {
        $ult4Cargo = (string) ($cargo->tarjeta_ult4 ?? '');
        $ult4Oc = (string) ($oc->suscripcion_tarjeta_ult4 ?? '');
        if ($ult4Cargo !== '' && $ult4Oc !== '' && $ult4Cargo !== $ult4Oc) {
            return 0.0;
        }

        $porServicio = SuscripcionComercioSupport::similitud($comercio, (string) $oc->suscripcion_nombre);
        $porProveedor = SuscripcionComercioSupport::similitud($comercio, (string) optional($oc->proveedores)->nombre);
        $texto = max($porServicio, $porProveedor);
        if ($texto <= 0) {
            return 0.0;
        }

        $puntaje = $texto;
        $motivos = [$porServicio >= $porProveedor ? 'nombre del servicio' : 'razón social del proveedor'];

        if ($ult4Cargo !== '' && $ult4Cargo === $ult4Oc) {
            $puntaje += 8;
            $motivos[] = 'misma tarjeta ••'.$ult4Cargo;
        }

        $esperado = (float) ($oc->suscripcion_monto_periodo ?? 0);
        if ($esperado > 0) {
            $delta = abs(SuscripcionSupport::desvioPct((float) $cargo->monto, $esperado));
            if ($delta <= (float) ($oc->suscripcion_tolerancia_pct ?? SuscripcionSupport::TOLERANCIA_DEFAULT_PCT)) {
                $puntaje += 6;
                $motivos[] = 'importe dentro de tolerancia';
            }
        }

        $motivo = 'Coincide por '.implode(' · ', $motivos);

        return round(min(100.0, $puntaje), 2);
    }

    private function aplicarAsociacion(Suscripcion_Cargo $cargo, Ordencompra $oc, ?string $origen, ?int $usuarioId): void
    {
        $esperado = (float) ($oc->suscripcion_monto_periodo ?? 0);
        $tolerancia = (float) ($oc->suscripcion_tolerancia_pct ?? SuscripcionSupport::TOLERANCIA_DEFAULT_PCT);
        $desvio = SuscripcionSupport::desvioPct((float) $cargo->monto, $esperado);

        // Solo el exceso rompe la tolerancia: pagar menos de lo previsto no vuelve al gerente.
        $estado = $desvio > $tolerancia
            ? Suscripcion_Cargo::ESTADO_DESVIO
            : Suscripcion_Cargo::ESTADO_CONCILIADO;

        $cargo->update([
            'ordencompra_id' => (int) $oc->id,
            'estado' => $estado,
            'desvio_pct' => $desvio,
            'origen_match' => $origen,
            'asocio_usuario_id' => $usuarioId,
            'asociado_at' => now(),
        ]);

        $this->refrescarDesvioAbierto((int) $oc->id);
    }

    /**
     * Mantiene el flag que el listado usa para pintar el estado Desvío.
     */
    private function refrescarDesvioAbierto(int $ordencompraId): void
    {
        $abierto = Suscripcion_Cargo::query()
            ->where('ordencompra_id', $ordencompraId)
            ->whereIn('estado', [Suscripcion_Cargo::ESTADO_DESVIO, Suscripcion_Cargo::ESTADO_PENDIENTE_APROBACION])
            ->exists();

        DB::table('ordencompra')
            ->where('id', $ordencompraId)
            ->update(['suscripcion_desvio_abierto' => $abierto, 'updated_at' => now()]);
    }

    private function aprenderAlias(Suscripcion_Cargo $cargo, Ordencompra $oc, int $usuarioId): void
    {
        $alias = (string) ($cargo->comercio_normalizado ?: SuscripcionComercioSupport::normalizar($cargo->comercio));
        if ($alias === '') {
            return;
        }

        $empresaId = (int) ($cargo->suscripcion_conciliaciones->empresa_id ?? 0);

        Suscripcion_ComercioAlias::query()->updateOrCreate(
            ['empresa_id' => $empresaId, 'alias' => $alias],
            [
                'proveedor_id' => (int) $oc->proveedor_id ?: null,
                'ordencompra_id' => (int) $oc->id,
                'ultimo_uso_at' => now(),
                'creousuario_id' => $usuarioId,
            ]
        );
    }

    /**
     * @param  list<string>  $encabezado
     * @return array<string, int>
     */
    /**
     * Devuelve el archivo como lista de filas, sea CSV o planilla.
     *
     * Los emisores mandan lo que quieren: algunos un CSV con punto y coma, otros un XLS
     * que en realidad es un CSV renombrado, otros un XLSX de verdad. Se decide por
     * contenido y no por extensión, y si la planilla falla se reintenta como texto.
     *
     * @return array{ok: bool, filas?: list<list<mixed>>, mensaje?: string}
     */
    private function filasDelArchivo(UploadedFile $archivo): array
    {
        $ruta = $archivo->getRealPath();
        if (! $ruta || ! is_readable($ruta)) {
            return ['ok' => false, 'mensaje' => 'No se pudo leer el archivo subido.'];
        }

        if (filesize($ruta) === 0) {
            return ['ok' => false, 'mensaje' => 'El archivo está vacío.'];
        }

        $extension = strtolower((string) $archivo->getClientOriginalExtension());
        $esPlanilla = in_array($extension, ['xls', 'xlsx', 'ods'], true);

        if ($esPlanilla) {
            $filas = $this->filasDePlanilla($ruta, $extension);
            if ($filas !== null) {
                return ['ok' => true, 'filas' => $filas];
            }
            // Un .xls que no abre como planilla suele ser un CSV con la extensión cambiada.
        }

        $filas = $this->filasDeTexto($ruta);
        if ($filas !== null) {
            return ['ok' => true, 'filas' => $filas];
        }

        if (! $esPlanilla) {
            // Y al revés: un .csv que no parsea puede ser una planilla mal nombrada.
            $filas = $this->filasDePlanilla($ruta, $extension);
            if ($filas !== null) {
                return ['ok' => true, 'filas' => $filas];
            }
        }

        return [
            'ok' => false,
            'mensaje' => 'No se pudo interpretar el archivo. Se aceptan CSV (coma o punto y coma) y planillas XLS, XLSX u ODS.',
        ];
    }

    /**
     * @return list<list<mixed>>|null null si el archivo no abre como planilla
     */
    private function filasDePlanilla(string $ruta, string $extension): ?array
    {
        try {
            $hojas = Excel::toArray(null, $ruta, null, match ($extension) {
                'xls' => \Maatwebsite\Excel\Excel::XLS,
                'ods' => \Maatwebsite\Excel\Excel::ODS,
                default => \Maatwebsite\Excel\Excel::XLSX,
            });
        } catch (\Throwable $e) {
            try {
                // Sin forzar tipo: deja que la librería lo detecte sola.
                $hojas = Excel::toArray(null, $ruta);
            } catch (\Throwable) {
                Log::info('SuscripcionConciliacionService: el archivo no abre como planilla', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        $filas = $hojas[0] ?? [];
        $filas = array_values(array_filter(
            $filas,
            // Las planillas arrastran filas vacías al final y renglones de subtotales en blanco.
            fn ($fila) => is_array($fila) && array_filter($fila, fn ($c) => trim((string) $c) !== '') !== []
        ));

        return $filas === [] ? null : $filas;
    }

    /**
     * @return list<list<string>>|null null si no hay separador reconocible
     */
    private function filasDeTexto(string $ruta): ?array
    {
        $handle = fopen($ruta, 'rb');
        if ($handle === false) {
            return null;
        }

        $primera = fgets($handle);
        if ($primera === false) {
            fclose($handle);

            return null;
        }

        // Un binario de planilla no tiene separadores en la primera línea.
        $puntoYComa = substr_count($primera, ';');
        $comas = substr_count($primera, ',');
        $tabs = substr_count($primera, "\t");
        if ($puntoYComa === 0 && $comas === 0 && $tabs === 0) {
            fclose($handle);

            return null;
        }

        $separador = match (max($puntoYComa, $comas, $tabs)) {
            $puntoYComa => ';',
            $tabs => "\t",
            default => ',',
        };

        rewind($handle);
        $filas = [];
        while (($fila = fgetcsv($handle, 0, $separador)) !== false) {
            if (array_filter($fila, fn ($c) => trim((string) $c) !== '') === []) {
                continue;
            }
            $filas[] = $fila;
        }
        fclose($handle);

        return $filas === [] ? null : $filas;
    }

    private function mapearColumnas(array $encabezado): array
    {
        $mapa = [];
        foreach ($encabezado as $indice => $titulo) {
            $limpio = mb_strtolower(trim((string) $titulo));
            $limpio = trim((string) preg_replace('/[^a-z0-9 _]/', '', $this->sinAcentos($limpio)));
            if ($limpio === '') {
                continue;
            }
            foreach (self::COLUMNAS as $campo => $sinonimos) {
                if (isset($mapa[$campo])) {
                    continue;
                }
                if (in_array($limpio, $sinonimos, true)) {
                    $mapa[$campo] = $indice;
                }
            }
        }

        return $mapa;
    }

    /**
     * @param  list<string>  $fila
     * @param  array<string, int>  $mapa
     * @return array{fecha: string, comercio: string, ult4: ?string, monto: float}|null
     */
    private function parsearFila(array $fila, array $mapa): ?array
    {
        $comercio = trim((string) ($fila[$mapa['comercio']] ?? ''));
        $fechaCruda = trim((string) ($fila[$mapa['fecha']] ?? ''));
        $montoCrudo = trim((string) ($fila[$mapa['monto']] ?? ''));

        if ($comercio === '' || $fechaCruda === '' || $montoCrudo === '') {
            return null;
        }

        $fecha = $this->parsearFecha($fechaCruda);
        $monto = $this->parsearMonto($montoCrudo);
        if ($fecha === null || $monto === null || abs($monto) < 0.0001) {
            return null;
        }

        return [
            'fecha' => $fecha,
            'comercio' => mb_substr($comercio, 0, 180),
            'ult4' => isset($mapa['ult4'])
                ? SuscripcionComercioSupport::ult4((string) ($fila[$mapa['ult4']] ?? ''))
                : null,
            'monto' => $monto,
        ];
    }

    private function parsearFecha(string $valor): ?string
    {
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'Y/m/d'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, $valor)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Acepta 1.234,56 (es-AR) y 1,234.56 (en-US): decide por el último separador.
     */
    private function parsearMonto(string $valor): ?float
    {
        $limpio = (string) preg_replace('/[^\d,.\-]/', '', $valor);
        if ($limpio === '' || $limpio === '-') {
            return null;
        }

        $ultimaComa = strrpos($limpio, ',');
        $ultimoPunto = strrpos($limpio, '.');

        if ($ultimaComa !== false && ($ultimoPunto === false || $ultimaComa > $ultimoPunto)) {
            $limpio = str_replace('.', '', $limpio);
            $limpio = str_replace(',', '.', $limpio);
        } else {
            $limpio = str_replace(',', '', $limpio);
        }

        return is_numeric($limpio) ? round((float) $limpio, 4) : null;
    }

    /** @param array{fecha: string, comercio: string, ult4: ?string, monto: float} $linea */
    private function hashLinea(array $linea): string
    {
        return hash('sha256', implode('|', [
            $linea['fecha'],
            SuscripcionComercioSupport::normalizar($linea['comercio']),
            (string) $linea['ult4'],
            number_format($linea['monto'], 4, '.', ''),
        ]));
    }

    private function monedaDefault(int $empresaId): ?int
    {
        $id = (int) (DB::table('ordencompra')
            ->where('empresa_id', $empresaId)
            ->where('es_suscripcion', true)
            ->whereNotNull('contrato_moneda_id')
            ->value('contrato_moneda_id') ?? 0);

        if ($id <= 0) {
            $id = (int) (DB::table('moneda')->orderBy('id')->value('id') ?? 0);
        }

        return $id > 0 ? $id : null;
    }

    private function normalizarPeriodo(string $periodo): string
    {
        $p = trim($periodo);
        if (preg_match('/^\d{4}-\d{2}$/', $p)) {
            return $p;
        }

        return Carbon::parse($p)->format('Y-m');
    }

    private function sinAcentos(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
        ]);
    }
}
