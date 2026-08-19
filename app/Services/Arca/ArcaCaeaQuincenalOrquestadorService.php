<?php

namespace App\Services\Arca;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ArcaCaea;
use App\Models\Ventas\Puntoventa;
use App\Support\Ventas\ArcaPuntoventaWebserviceSupport;
use App\Support\Ventas\CaeaQuincenaSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pedido quincenal CAEA vía ARCA (WSFEv1 o WSMTXCA según punto de venta) → persiste en arca_caea.
 * No importa desde Anita (eso es solo arca:importar-caea-anita, una vez para histórico).
 */
class ArcaCaeaQuincenalOrquestadorService
{
    public function __construct(
        private ArcaWsfeCaeaService $wsfeCaea,
        private ArcaMtxcaCaeaService $mtxcaCaea,
    ) {}

    /**
     * @return list<array{empresa_id: int, periodo: int, orden: int, webservice: string, ok: bool, mensaje: string}>
     */
    public function procesarQuincenasEnVentana(): array
    {
        $quincenas = CaeaQuincenaSupport::quincenasEnVentanaSolicitud();
        if ($quincenas === []) {
            return [];
        }

        $resultados = [];

        foreach ($this->empresasConPvCaea() as $empresa) {
            $webservice = $this->webserviceCaeaEmpresa((int) $empresa->id);

            foreach ($quincenas as $q) {
                $periodo = (int) $q['periodo'];
                $orden = (int) $q['orden'];

                $r = $this->solicitarPorWebservice($webservice, (int) $empresa->id, $periodo, $orden);

                $resultados[] = [
                    'empresa_id' => (int) $empresa->id,
                    'periodo' => $periodo,
                    'orden' => $orden,
                    'webservice' => $webservice,
                    'ok' => $r['ok'],
                    'mensaje' => $r['mensaje'],
                ];

                if (! $r['ok']) {
                    Log::info('arca:caea-quincenal — solicitud ARCA falló', [
                        'empresa_id' => $empresa->id,
                        'webservice' => $webservice,
                        'periodo' => $periodo,
                        'orden' => $orden,
                        'mensaje' => $r['mensaje'],
                    ]);
                }
            }
        }

        return $resultados;
    }

    /**
     * Pedido (pantalla o cron) según el webservice de los PV CAEA de la empresa.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function solicitarYGuardar(
        int $empresaId,
        int $periodo,
        int $orden,
        string $origen = ArcaCaea::ORIGEN_AUTOMATICO,
        ?int $usuarioId = null,
        bool $forzarConsulta = false,
    ): array {
        return $this->solicitarPorWebservice(
            $this->webserviceCaeaEmpresa($empresaId),
            $empresaId,
            $periodo,
            $orden,
            $forzarConsulta,
            $origen,
            $usuarioId,
        );
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function solicitarPorWebservice(
        string $webservice,
        int $empresaId,
        int $periodo,
        int $orden,
        bool $forzarConsulta = false,
        string $origen = ArcaCaea::ORIGEN_AUTOMATICO,
        ?int $usuarioId = null,
    ): array {
        $webservice = ArcaPuntoventaWebserviceSupport::normalizar($webservice);

        if ($webservice === ArcaPuntoventaWebserviceSupport::WSMTXCA
            && (string) config('arca_mtxca.transporte', 'afip_php') === 'soap') {
            $r = $this->mtxcaCaea->solicitarYGuardar($empresaId, $periodo, $orden, $origen, $usuarioId, $forzarConsulta);

            return ['ok' => $r['ok'], 'mensaje' => $r['mensaje']];
        }

        if ($webservice === ArcaPuntoventaWebserviceSupport::WSFE
            && (string) config('arca_wsfe.transporte', 'afip_php') === 'soap') {
            $r = $this->wsfeCaea->solicitarYGuardar($empresaId, $periodo, $orden, $origen, $usuarioId, $forzarConsulta);

            return ['ok' => $r['ok'], 'mensaje' => $r['mensaje']];
        }

        return [
            'ok' => false,
            'mensaje' => "Webservice {$webservice} sin transporte SOAP activo (ARCA_WSFE_TRANSPORTE / ARCA_MTXCA_TRANSPORTE).",
        ];
    }

    /**
     * @return Collection<int, Empresa>
     */
    public function empresasConPvCaea(): Collection
    {
        $idsUsuarios = DB::table('usuario_empresa')->distinct()->pluck('empresa_id')->map(fn ($id) => (int) $id)->all();

        $idsPv = Puntoventa::query()
            ->whereIn('empresa_id', $idsUsuarios)
            ->where('modofacturacion', 'A')
            ->whereIn('webservice', ArcaPuntoventaWebserviceSupport::valoresWhereInSoapCaea())
            ->where('estado', 'A')
            ->distinct()
            ->pluck('empresa_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return Empresa::query()
            ->whereIn('id', $idsPv)
            ->whereNotNull('nroinscripcion')
            ->where('nroinscripcion', '!=', '')
            ->orderBy('id')
            ->get();
    }

    public function webserviceCaeaEmpresa(int $empresaId): string
    {
        $webservices = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('modofacturacion', 'A')
            ->whereIn('webservice', ArcaPuntoventaWebserviceSupport::valoresWhereInSoapCaea())
            ->where('estado', 'A')
            ->pluck('webservice');

        return ArcaPuntoventaWebserviceSupport::preferidoParaCaea($webservices);
    }
}
