<?php

declare(strict_types=1);

namespace App\Services\Contable\CanonEntidades;

use App\Models\Contable\Canon_Entidades_Config;
use App\Models\Solicitudpago\Solicitudpago;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Services\Solicitudpago\SolicitudpagoAnitaEscrituraService;
use App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService;
use App\Support\Contable\CanonEntidades\CanonEntidadesListadoFiltros;
use App\Support\Contable\CanonEntidades\CanonEntidadesReglasSupport;
use App\Support\Contable\CanonEntidades\CanonEntidadesSolicitudPlantillaSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Genera las solicitudes de pago del canon entidades (máquinas y bingo) desde la F2015.
 * Todo o nada: ambas se crean en una transacción; si falla, se compensa en Anita y se borra el PDF.
 * El árbol de aprobación se dispara recién después del commit.
 */
final class CanonEntidadesSolicitudpagoService
{
    public function __construct(
        private readonly CanonEntidadesReporteService $reporteService,
        private readonly SolicitudpagoRepositoryInterface $solicitudpagoRepository,
        private readonly SolicitudpagoAnitaEscrituraService $escrituraService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros  empresa_id + periodo (Ym)
     * @return array<string, mixed>
     */
    public function preview(array $filtros): array
    {
        $ctx = $this->resolverContexto($filtros);

        return $this->armarPreview($ctx);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<Solicitudpago>
     */
    public function confirmar(
        array $filtros,
        string $fechaVencimiento,
        ?string $observacion,
        bool $generarIgual,
        bool $confirmoDias,
    ): array {
        $ctx = $this->resolverContexto($filtros);
        $preview = $this->armarPreview($ctx);

        if (! empty($preview['error'])) {
            throw new RuntimeException((string) $preview['error']);
        }

        $vto = $this->normalizarFecha($fechaVencimiento);
        if ($vto === null) {
            throw new RuntimeException('El vencimiento es obligatorio.');
        }

        if ($this->hayDuplicados($preview) && ! $generarIgual) {
            throw new RuntimeException('Hay una solicitud similar. Marque «Generar igual» para continuar.');
        }

        if (! empty($preview['dias_excluidos']) && ! $confirmoDias) {
            throw new RuntimeException('Confirme que los días excluidos del canon de máquinas son correctos.');
        }

        $rutaPdf = $this->generarPdfFormulario($ctx);
        $creados = [];
        $sps = [];

        try {
            $sps = DB::transaction(function () use ($ctx, $preview, $vto, $observacion, $rutaPdf, &$creados): array {
                $out = [];
                foreach ($preview['solicitudes'] as $sol) {
                    $uploaded = new UploadedFile(
                        $rutaPdf,
                        (string) $preview['nombre_pdf'],
                        'application/pdf',
                        null,
                        true
                    );

                    $sp = $this->solicitudpagoRepository->create(
                        $this->payloadSolicitud($ctx, $sol, $vto, $observacion, $uploaded)
                    );
                    $creados[] = (int) $sp->codigo;

                    $sp->load('archivos');
                    if ($sp->archivos->isEmpty()) {
                        throw new RuntimeException('La solicitud #'.$sp->codigo.' no quedó con el formulario adjunto.');
                    }

                    $out[] = $sp;
                }

                return $out;
            });
        } catch (\Throwable $e) {
            // La transacción MySQL ya revirtió; Anita no es transaccional: se compensa lo que haya quedado.
            foreach ($creados as $codigo) {
                try {
                    $this->escrituraService->eliminar($codigo);
                } catch (\Throwable $compensa) {
                    Log::error('canon_entidades.compensacion_anita', [
                        'codigo' => $codigo,
                        'error' => $compensa->getMessage(),
                    ]);
                }
            }
            @unlink($rutaPdf);
            throw $e;
        }

        @unlink($rutaPdf);

        if (config('solicitudpago.arbol_al_crear', true)) {
            foreach ($sps as $sp) {
                try {
                    app(SolicitudpagoArbolIntegracionService::class)->dispararAlGuardar((int) $sp->id);
                } catch (\Throwable $e) {
                    Log::warning('canon_entidades.arbol', ['id' => $sp->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return $sps;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function resolverContexto(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            throw new RuntimeException('Falta la empresa.');
        }

        $periodo = (string) ($filtros['periodo'] ?? date('Ym'));
        [$desde, $hasta] = CanonEntidadesListadoFiltros::rangoMes($periodo);

        /** @var Canon_Entidades_Config|null $config */
        $config = Canon_Entidades_Config::query()
            ->with([
                'empresa:id,codigo,nombre,nroinscripcion',
                'proveedor:id,codigo,nombre',
                'sectorSolicitudpago:id,codigo,nombre',
                'conceptoMaquinas:id,codigo,nombre',
                'conceptoBingo:id,codigo,nombre',
                'formapagosol:id,codigo,nombre',
                'moneda:id,codigo,nombre',
                'centrocosto:id,codigo,nombre',
                'cuentacontableDebe:id,codigo,nombre',
                'cuentacontableHaber:id,codigo,nombre',
            ])
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        $filtrosRep = [
            'empresa_id' => $empresaId,
            'periodo' => $periodo,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ];
        $resultado = $this->reporteService->generar($filtrosRep);

        return [
            'filtros' => $filtrosRep,
            'periodo' => $periodo,
            'periodo_texto' => CanonEntidadesListadoFiltros::formatearPeriodoTexto($filtrosRep),
            'periodo_nombre' => Carbon::createFromFormat('Ym', $periodo)->locale('es')->isoFormat('MMMM YYYY'),
            'empresa_id' => $empresaId,
            'config' => $config,
            'resultado' => $resultado,
            'identidad' => $resultado['identidad'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function armarPreview(array $ctx): array
    {
        /** @var Canon_Entidades_Config|null $config */
        $config = $ctx['config'];
        $resultado = $ctx['resultado'];
        $identidad = $ctx['identidad'];
        $nombreEmpresa = (string) ($identidad['nombre'] ?? '');
        $codigoEmpresa = (string) ($identidad['codigo'] ?? '');

        $base = [
            'puede_generar' => false,
            'error' => null,
            'filtros' => CanonEntidadesListadoFiltros::paraQueryString($ctx['filtros']),
        ];

        if ($config === null || ! $config->configCompletaParaSolicitud()) {
            return array_merge($base, ['error' => 'Falta configurar el canon de '.$nombreEmpresa]);
        }

        if ($codigoEmpresa === '' || ! in_array($codigoEmpresa, [
            CanonEntidadesReglasSupport::CODIGO_BSA,
            CanonEntidadesReglasSupport::CODIGO_KSA,
            CanonEntidadesReglasSupport::CODIGO_RSA,
        ], true)) {
            return array_merge($base, ['error' => 'La empresa no tiene código BSA, KSA o RSA para el canon entidades.']);
        }

        $conciliacion = $resultado['conciliacion'] ?? [];
        if (empty($conciliacion['cuadra'])) {
            return array_merge($base, [
                'error' => 'La conciliación contra el pasivo no está conforme.',
            ]);
        }

        $canonMaq = round((float) ($resultado['totales']['canon_maq'] ?? 0), 2);
        $canonBin = round((float) ($resultado['totales']['canon_bin'] ?? 0), 2);
        if ($canonMaq < 0.01) {
            return array_merge($base, ['error' => 'El canon de máquinas es cero; no se puede generar la solicitud.']);
        }

        $periodo = $ctx['periodo'];
        $solicitudes = [];
        $solicitudes[] = $this->armarSolicitud(
            'maquinas',
            $config->conceptoMaquinas,
            $canonMaq,
            CanonEntidadesSolicitudPlantillaSupport::armarDetalle((string) $config->plantilla_maquinas, $codigoEmpresa, $periodo),
            $config,
            $ctx,
        );

        $binCero = $canonBin < 0.01;
        if (! $binCero) {
            $solicitudes[] = $this->armarSolicitud(
                'bingo',
                $config->conceptoBingo,
                $canonBin,
                CanonEntidadesSolicitudPlantillaSupport::armarDetalle((string) $config->plantilla_bingo, $codigoEmpresa, $periodo),
                $config,
                $ctx,
            );
        }

        return [
            'error' => null,
            'puede_generar' => true,
            'titulo' => 'Generar solicitudes de pago — Canon entidades '.$codigoEmpresa.' '.$ctx['periodo_nombre'],
            'empresa_label' => trim(($config->empresa->codigo ?? '').' '.$nombreEmpresa),
            'fecha' => now()->format('d/m/Y'),
            'proveedor_label' => trim(($config->proveedor->codigo ?? '').' '.($config->proveedor->nombre ?? '')),
            'sector_label' => trim(($config->sectorSolicitudpago->codigo ?? '').' — '.($config->sectorSolicitudpago->nombre ?? '')),
            'formapago_label' => trim(($config->formapagosol->codigo ?? '').' — '.($config->formapagosol->nombre ?? '')),
            'moneda_label' => (string) ($config->moneda->nombre ?? 'PESOS'),
            'nombre_pdf' => CanonEntidadesSolicitudPlantillaSupport::nombreArchivoNota($codigoEmpresa, $periodo),
            'solicitudes' => $solicitudes,
            'bingo_cero' => $binCero,
            'aviso_bingo_cero' => $binCero
                ? 'Sin ventas de bingo en el período: se genera solo la de máquinas.'
                : null,
            'dias_excluidos' => $this->diasExcluidos($resultado['filas'] ?? []),
            'filtros' => CanonEntidadesListadoFiltros::paraQueryString($ctx['filtros']),
            'url_pdf' => route(
                'exportar_canon_entidades_formulario',
                CanonEntidadesListadoFiltros::paraQueryString($ctx['filtros'])
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function armarSolicitud(
        string $tipo,
        ?object $concepto,
        float $monto,
        string $detalle,
        Canon_Entidades_Config $config,
        array $ctx,
    ): array {
        $ccLabel = trim(($config->centrocosto->codigo ?? '').' — '.($config->centrocosto->nombre ?? ''));
        $cuentaDebe = trim(($config->cuentacontableDebe->codigo ?? '').' '.($config->cuentacontableDebe->nombre ?? ''));
        $cuentaHaber = trim(($config->cuentacontableHaber->codigo ?? '').' '.($config->cuentacontableHaber->nombre ?? ''));

        $duplicado = $this->buscarDuplicado(
            (int) $ctx['empresa_id'],
            (int) ($concepto->id ?? 0),
            $detalle,
            (string) $ctx['periodo'],
        );

        return [
            'tipo' => $tipo,
            'concepto_id' => (int) ($concepto->id ?? 0),
            'concepto_label' => trim(($concepto->codigo ?? '').' '.($concepto->nombre ?? '')),
            'monto' => round($monto, 2),
            'monto_fmt' => number_format($monto, 2, ',', '.'),
            'detalle' => $detalle,
            'asiento' => [
                ['cuenta' => $cuentaDebe, 'centrocosto' => $ccLabel, 'dh' => 'D', 'debe' => round($monto, 2), 'haber' => null],
                ['cuenta' => $cuentaHaber, 'centrocosto' => $ccLabel, 'dh' => 'H', 'debe' => null, 'haber' => round($monto, 2)],
            ],
            'duplicado' => $duplicado,
        ];
    }

    /**
     * Misma empresa + mismo concepto y, o bien el mismo detalle, o bien fecha dentro del mes siguiente al período.
     *
     * @return array<string, mixed>|null
     */
    private function buscarDuplicado(int $empresaId, int $conceptoId, string $detalle, string $periodoYm): ?array
    {
        if ($conceptoId <= 0) {
            return null;
        }

        $inicioSig = Carbon::createFromFormat('Ym', $periodoYm)->startOfMonth()->addMonthNoOverflow();
        $finSig = $inicioSig->copy()->endOfMonth();

        $sp = Solicitudpago::query()
            ->where('empresa_id', $empresaId)
            ->where('concepto_solicitudpago_id', $conceptoId)
            ->where(function ($q) use ($detalle, $inicioSig, $finSig) {
                $q->where('detalle', $detalle)
                    ->orWhereBetween('fecha', [$inicioSig->toDateString(), $finSig->toDateString()]);
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first(['id', 'codigo', 'fecha', 'monto', 'estado']);

        if ($sp === null) {
            return null;
        }

        $fecha = $sp->fecha ? Carbon::parse($sp->fecha)->format('d/m/Y') : '';

        return [
            'id' => (int) $sp->id,
            'codigo' => (int) $sp->codigo,
            'fecha' => $fecha,
            'monto_fmt' => number_format((float) $sp->monto, 2, ',', '.'),
            'estado_label' => SolicitudpagoEstados::label((string) $sp->estado),
            'mensaje' => sprintf(
                'Ya existe la solicitud #%d del %s por %s (%s).',
                (int) $sp->codigo,
                $fecha,
                number_format((float) $sp->monto, 2, ',', '.'),
                SolicitudpagoEstados::label((string) $sp->estado)
            ),
            'url' => route('editar_solicitudpago', [
                'id' => (int) $sp->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function hayDuplicados(array $preview): bool
    {
        foreach ($preview['solicitudes'] as $sol) {
            if (! empty($sol['duplicado'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{mensaje: string, fechas: list<string>}|null
     */
    private function diasExcluidos(array $filas): ?array
    {
        $partes = [];
        foreach ($filas as $fila) {
            if (empty($fila['tiene_flash']) || empty($fila['excluido_maq'])) {
                continue;
            }
            $partes[] = ($fila['fecha'] ?? '').': '.number_format((float) ($fila['win_electronico'] ?? 0), 2, ',', '.');
        }

        if ($partes === []) {
            return null;
        }

        $n = count($partes);

        return [
            'mensaje' => sprintf(
                '%d %s con Win ≤ 0 excluido%s del canon de máquinas (%s).',
                $n,
                $n === 1 ? 'día' : 'días',
                $n === 1 ? '' : 's',
                implode('; ', $partes)
            ),
            'fechas' => $partes,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function generarPdfFormulario(array $ctx): string
    {
        $html = View::make('contable.canon_entidades.formulario', [
            'resultado' => $ctx['resultado'],
            'filtros' => $ctx['filtros'],
            'periodo_texto' => CanonEntidadesListadoFiltros::formatearPeriodoTexto($ctx['filtros']),
            'fecha_emision' => now(),
        ])->render();

        $dir = storage_path('app/tmp/canon_entidades_solicitudpago');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ruta = $dir.'/'.uniqid('canon_ent_', true).'.pdf';

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHTML($html)->save($ruta);

        if (! is_file($ruta) || filesize($ruta) < 100) {
            throw new RuntimeException('No se pudo generar el PDF del formulario canon entidades.');
        }

        return $ruta;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $sol
     * @return array<string, mixed>
     */
    private function payloadSolicitud(
        array $ctx,
        array $sol,
        string $vto,
        ?string $observacion,
        UploadedFile $uploaded,
    ): array {
        /** @var Canon_Entidades_Config $config */
        $config = $ctx['config'];
        $empresaId = (int) $ctx['empresa_id'];
        $monto = (float) $sol['monto'];
        $debeId = (int) $config->cuentacontable_debe_id;
        $haberId = (int) $config->cuentacontable_haber_id;
        $ccId = (int) $config->centrocosto_id;

        return [
            'empresa_id' => $empresaId,
            'fecha' => now()->toDateString(),
            'tratamiento' => SolicitudpagoTratamientos::NORMAL,
            'proveedor_id' => (int) $config->proveedor_id,
            'concepto_solicitudpago_id' => (int) $sol['concepto_id'],
            'formapagosol_id' => (int) $config->formapagosol_id,
            'moneda_id' => (int) $config->moneda_id,
            'fecha_vencimiento' => $vto,
            'fecha_entrega' => now()->toDateString(),
            'monto' => $monto,
            'estado' => SolicitudpagoEstados::EMITIDA,
            'sector_solicitudpago_id' => (int) $config->sector_solicitudpago_id,
            'centrocosto_id' => $ccId,
            'detalle' => (string) $sol['detalle'],
            'observacion' => $observacion !== null && trim($observacion) !== ''
                ? mb_substr(trim($observacion), 0, 160)
                : null,
            'empresa_ids' => [$empresaId, $empresaId],
            'cuentacontable_ids' => [$debeId, $haberId],
            'centrocosto_ids' => [$ccId, $ccId],
            'debe_haberes' => ['D', 'H'],
            'montos_cuenta' => [$monto, $monto],
            'archivos_nuevos' => [$uploaded],
            'omitir_arbol' => true,
        ];
    }

    private function normalizarFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $valor, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
