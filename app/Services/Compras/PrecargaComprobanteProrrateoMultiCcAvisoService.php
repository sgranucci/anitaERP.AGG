<?php

namespace App\Services\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Services\Configuracion\AnitaNotificacionService;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa cuando una precarga API queda como tipo prorrateado multi-CC (FPB/…).
 */
class PrecargaComprobanteProrrateoMultiCcAvisoService
{
    public const AVISO_MODULO = 'compras';

    public const AVISO_CODIGO = 'precarga_prorrateo_multi_cc';

    /** Usuario Sergio (pruebas / ops). */
    public const USUARIO_AVISO_ID = 2;

    public function __construct(
        private ModuloAvisoService $moduloAvisoService,
        private AnitaNotificacionService $anitaNotificacionService,
    ) {}

    /**
     * @param  array{
     *   tipocomprobante?: string,
     *   tipos_origen?: list<string>,
     *   centros?: list<string>,
     *   pesos_por_fino?: array<string, float>
     * }  $meta
     */
    public function notificar(?Precarga_Comprobante_Proveedor $precarga, array $meta = []): void
    {
        if ($precarga === null || (int) $precarga->id <= 0) {
            return;
        }

        try {
            $this->moduloAvisoService->enviar(
                self::AVISO_MODULO,
                self::AVISO_CODIGO,
                (int) $precarga->id
            );
        } catch (Throwable $e) {
            Log::warning('precarga_prorrateo_multi_cc.modulo_aviso_error', [
                'precarga_id' => $precarga->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $precarga->loadMissing(['proveedores', 'tipotransaccion_compras', 'empresas']);
            $tipo = (string) ($precarga->tipotransaccion_compras->abreviatura
                ?? $meta['tipocomprobante']
                ?? '???');
            $centros = implode('/', $meta['centros'] ?? []);
            $origenes = implode('+', $meta['tipos_origen'] ?? []);
            $url = ModoConsultaUrlSupport::urlAbsolutaConConsulta(
                'compras/precarga_comprobante_proveedor/'.$precarga->id.'/editar'
            );

            $this->anitaNotificacionService->avisarSistema(
                self::USUARIO_AVISO_ID,
                'Precarga prorrateada multi-CC '.$tipo,
                sprintf(
                    'OC %s · CC %s · orígenes %s · %s. Revisar apertura de conceptos.',
                    (string) ($precarga->numeroordencompra ?? '—'),
                    $centros !== '' ? $centros : '—',
                    $origenes !== '' ? $origenes : '—',
                    (string) (optional($precarga->proveedores)->nombre ?? 'proveedor')
                ),
                $url,
                [
                    'origen' => 'precarga_prorrateo_multi_cc',
                    'precarga_id' => (int) $precarga->id,
                    'tipo' => $tipo,
                    'centros' => $meta['centros'] ?? [],
                    'tipos_origen' => $meta['tipos_origen'] ?? [],
                ]
            );
        } catch (Throwable $e) {
            Log::warning('precarga_prorrateo_multi_cc.campanita_error', [
                'precarga_id' => $precarga->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
