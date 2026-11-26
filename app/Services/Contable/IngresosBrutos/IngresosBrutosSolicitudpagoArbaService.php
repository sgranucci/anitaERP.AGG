<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\Models\Contable\IibbLiquidacionArba;
use App\Models\Contable\IibbSolicitudpagoConfig;
use App\Models\Solicitudpago\Solicitudpago;
use App\Repositories\Contable\IibbSolicitudpagoConfigRepositoryInterface;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Contable\Sicore\SicoreLiquidacionTipoSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Solicitud de pago del VEP ARBA (IIBB retenciones) a partir de la liquidación vigente.
 *
 * Monto = VEP de la liquidación (DJ truncada a 10 centavos).
 * Imputación: D cuenta 214010014 (por empresa) / H banco 111050009 (por empresa).
 * Adjunto: constancia de la liquidación (PDF) + VEP opcional subido por el operador.
 */
final class IngresosBrutosSolicitudpagoArbaService
{
    public function __construct(
        private readonly SolicitudpagoRepositoryInterface $solicitudpagoRepository,
        private readonly IibbSolicitudpagoConfigRepositoryInterface $configRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(int $empresaId, string $periodo, int $quincena, ?IibbLiquidacionArba $liq): array
    {
        $config = $this->config($empresaId);
        if ($config === null || ! $config->configCompleta()) {
            return $this->error('Falta configurar la solicitud de pago ARBA de esta empresa.');
        }
        if ($liq === null) {
            return $this->error('Genere primero la liquidación ARBA de esta quincena.');
        }
        if (! $liq->conciliacion_ok) {
            return $this->error('La última liquidación no cierra contra el mayor. Revísela antes de generar la solicitud.');
        }
        if ($liq->ruta_pdf === null || ! is_file((string) $liq->ruta_pdf)) {
            return $this->error('No se encuentra el PDF de la liquidación. Genere la liquidación nuevamente.');
        }

        $monto = round((float) $liq->vep_esperado, 2);
        if ($monto < 0.01) {
            return $this->error('El VEP es cero; no se genera solicitud.');
        }

        $empresa = $config->empresa;
        $empresaCorta = SicoreLiquidacionTipoSupport::empresaCorta($empresa?->nombre);
        $detalle = $this->detalle((string) $config->plantilla_detalle, $quincena, $periodo, $empresaCorta);
        $duplicado = $this->buscarDuplicado(
            $empresaId,
            (int) $config->concepto_solicitudpago_id,
            $monto,
            $detalle,
            (int) $config->dias_duplicado,
        );

        $ccLabel = trim(($config->centrocosto->codigo ?? '').' — '.($config->centrocosto->nombre ?? ''));
        $cuentaDebe = $config->cuentacontableDebe;
        $cuentaBanco = $config->cuentacontableBanco;

        return [
            'error' => null,
            'puede_generar' => true,
            'empresa_id' => $empresaId,
            'empresa_label' => trim(($empresa->codigo ?? '').' '.($empresa->nombre ?? '')),
            'proveedor_label' => trim(($config->proveedor->codigo ?? '').' '.($config->proveedor->nombre ?? '')),
            'sector_label' => trim(($config->sectorSolicitudpago->codigo ?? '').' — '.($config->sectorSolicitudpago->nombre ?? '')),
            'concepto_label' => trim(($config->conceptoSolicitudpago->codigo ?? '').' '.($config->conceptoSolicitudpago->nombre ?? '')),
            'formapago_label' => trim(($config->formapagosol->codigo ?? '').' — '.($config->formapagosol->nombre ?? '')),
            'moneda_label' => (string) ($config->moneda->nombre ?? 'PESOS'),
            'centrocosto_label' => $ccLabel,
            'monto' => $monto,
            'monto_fmt' => number_format($monto, 2, ',', '.'),
            'dj' => round((float) $liq->total_dj, 2),
            'dj_fmt' => number_format((float) $liq->total_dj, 2, ',', '.'),
            'detalle' => $detalle,
            'periodo' => $periodo,
            'quincena' => $quincena,
            'liquidacion_id' => (int) $liq->id,
            'asiento' => [
                [
                    'cuenta' => trim(($cuentaDebe->codigo ?? '').' '.($cuentaDebe->nombre ?? '')),
                    'centrocosto' => $ccLabel,
                    'dh' => 'D',
                    'debe' => $monto,
                    'haber' => null,
                ],
                [
                    'cuenta' => trim(($cuentaBanco->codigo ?? '').' '.($cuentaBanco->nombre ?? '')),
                    'centrocosto' => $ccLabel,
                    'dh' => 'H',
                    'debe' => null,
                    'haber' => $monto,
                ],
            ],
            'nombre_adjunto_dj' => $this->nombreAdjuntoDj($periodo, $quincena, $empresaCorta),
            'duplicado' => $duplicado,
        ];
    }

    /**
     * @param  list<UploadedFile>  $adjuntosUsuario  VEP u otros comprobantes del operador
     * @return array{solicitud: Solicitudpago, codigo: int}
     */
    public function confirmar(
        int $empresaId,
        string $periodo,
        int $quincena,
        ?IibbLiquidacionArba $liq,
        string $fechaVencimiento,
        ?string $observacion,
        bool $generarIgual,
        array $adjuntosUsuario = [],
    ): array {
        $prev = $this->preview($empresaId, $periodo, $quincena, $liq);
        if (! empty($prev['error'])) {
            throw new RuntimeException((string) $prev['error']);
        }

        if (! empty($prev['duplicado']) && ! $generarIgual) {
            throw new RuntimeException('Hay una solicitud similar. Marque «Generar igual» para continuar.');
        }

        $vto = $this->normalizarFecha($fechaVencimiento);
        if ($vto === null) {
            throw new RuntimeException('El vencimiento es obligatorio.');
        }

        /** @var IibbSolicitudpagoConfig $config */
        $config = $this->config($empresaId);
        $monto = (float) $prev['monto'];
        $ccId = (int) $config->centrocosto_id;

        $rutaDj = $this->copiarConstancia($liq);
        $uploaded = [];
        try {
            if ($rutaDj !== null) {
                $uploaded[] = new UploadedFile(
                    $rutaDj,
                    (string) $prev['nombre_adjunto_dj'],
                    'application/pdf',
                    null,
                    true,
                );
            }
            foreach ($adjuntosUsuario as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $uploaded[] = $file;
                }
            }

            $payload = [
                'empresa_id' => $empresaId,
                'fecha' => now()->toDateString(),
                'tratamiento' => SolicitudpagoTratamientos::NORMAL,
                'proveedor_id' => (int) $config->proveedor_id,
                'concepto_solicitudpago_id' => (int) $config->concepto_solicitudpago_id,
                'formapagosol_id' => (int) $config->formapagosol_id,
                'moneda_id' => (int) $config->moneda_id,
                'fecha_vencimiento' => $vto,
                'fecha_entrega' => now()->toDateString(),
                'monto' => $monto,
                'estado' => SolicitudpagoEstados::EMITIDA,
                'sector_solicitudpago_id' => (int) $config->sector_solicitudpago_id,
                'centrocosto_id' => $ccId,
                'detalle' => (string) $prev['detalle'],
                'observacion' => $observacion !== null && trim($observacion) !== ''
                    ? mb_substr(trim($observacion), 0, 160)
                    : null,
                'empresa_ids' => [$empresaId, $empresaId],
                'cuentacontable_ids' => [(int) $config->cuentacontable_debe_id, (int) $config->cuentacontable_banco_id],
                'centrocosto_ids' => [$ccId, $ccId],
                'debe_haberes' => ['D', 'H'],
                'montos_cuenta' => [$monto, $monto],
                'archivos_nuevos' => $uploaded,
            ];

            $sp = $this->solicitudpagoRepository->create($payload);
            $codigo = (int) ($sp->codigo ?? 0);
            if ($codigo <= 0) {
                throw new RuntimeException('No se pudo generar la solicitud de pago.');
            }

            return [
                'solicitud' => $sp,
                'codigo' => $codigo,
            ];
        } finally {
            if ($rutaDj !== null && is_file($rutaDj)) {
                @unlink($rutaDj);
            }
        }
    }

    private function config(int $empresaId): ?IibbSolicitudpagoConfig
    {
        return $this->configRepository->vigentePorEmpresa($empresaId);
    }

    /**
     * Plantilla: {1Q|2Q}, {MM.AAAA}, {EMP}.
     */
    private function detalle(string $plantilla, int $quincena, string $periodo, string $empresaCorta): string
    {
        $tpl = trim($plantilla) !== '' ? $plantilla : IibbSolicitudpagoConfig::PLANTILLA_DETALLE_DEFAULT;
        $mm = substr($periodo, 4, 2);
        $aaaa = substr($periodo, 0, 4);

        $out = strtr($tpl, [
            '{1Q|2Q}' => $quincena === 1 ? '1Q' : '2Q',
            '{MM.AAAA}' => $mm.'.'.$aaaa,
            '{EMP}' => $empresaCorta,
        ]);

        return mb_substr(trim($out), 0, 255);
    }

    private function nombreAdjuntoDj(string $periodo, int $quincena, string $empresaCorta): string
    {
        $nombre = 'ARBA_DJ_constancia_'.$periodo.'_'.$quincena.'Q_'.$empresaCorta.'.pdf';

        return preg_replace('/[^\w.\-]+/u', '_', $nombre) ?: 'ARBA_DJ_constancia.pdf';
    }

    private function copiarConstancia(?IibbLiquidacionArba $liq): ?string
    {
        if ($liq === null || $liq->ruta_pdf === null || ! is_file((string) $liq->ruta_pdf)) {
            return null;
        }

        $dir = storage_path('app/tmp/iibb_arba_solicitudpago');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $destino = $dir.'/'.uniqid('arba_', true).'_dj.pdf';

        return @copy((string) $liq->ruta_pdf, $destino) ? $destino : null;
    }

    /**
     * Mismo criterio que SICORE: mismo concepto y (monto ±0,01 o detalle igual) en los últimos N días.
     * N = dias_duplicado de la configuración ARBA de la empresa.
     *
     * @return array<string, mixed>|null
     */
    private function buscarDuplicado(int $empresaId, int $conceptoId, float $monto, string $detalle, int $diasDuplicado): ?array
    {
        $desde = now()->subDays($diasDuplicado)->toDateString();
        $detalleNorm = mb_strtolower(trim($detalle));

        $query = Solicitudpago::query()
            ->where('empresa_id', $empresaId)
            ->where('concepto_solicitudpago_id', $conceptoId)
            ->whereDate('fecha', '>=', $desde)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        $sp = (clone $query)
            ->whereRaw('ABS(monto - ?) < 0.01', [$monto])
            ->first(['id', 'codigo', 'fecha', 'monto', 'estado', 'detalle']);

        if ($sp === null && $detalleNorm !== '') {
            $sp = (clone $query)
                ->whereRaw('LOWER(TRIM(detalle)) = ?', [$detalleNorm])
                ->first(['id', 'codigo', 'fecha', 'monto', 'estado', 'detalle']);
        }

        if ($sp === null) {
            return null;
        }

        $fecha = $sp->fecha ? Carbon::parse($sp->fecha)->format('d/m/Y') : '';

        return [
            'id' => (int) $sp->id,
            'codigo' => (int) $sp->codigo,
            'fecha' => $fecha,
            'monto' => (float) $sp->monto,
            'monto_fmt' => number_format((float) $sp->monto, 2, ',', '.'),
            'estado' => (string) $sp->estado,
            'estado_label' => SolicitudpagoEstados::label((string) $sp->estado),
            'url' => route('editar_solicitudpago', [
                'id' => (int) $sp->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
            'mensaje' => sprintf(
                'Posible duplicado: ya existe la solicitud #%d del %s por %s (%s).',
                (int) $sp->codigo,
                $fecha,
                number_format((float) $sp->monto, 2, ',', '.'),
                SolicitudpagoEstados::label((string) $sp->estado)
            ),
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

    /**
     * @return array<string, mixed>
     */
    private function error(string $mensaje): array
    {
        return ['error' => $mensaje, 'puede_generar' => false];
    }
}
