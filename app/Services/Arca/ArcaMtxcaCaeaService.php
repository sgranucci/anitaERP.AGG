<?php

namespace App\Services\Arca;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ArcaCaea;
use App\Models\Ventas\Puntoventa;
use App\Support\Ventas\ArcaCaeaSolicitudSupport;
use App\Support\Ventas\CaeaQuincenaSupport;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArcaMtxcaCaeaService
{
    public function __construct(
        private ArcaMtxcaFacturaElectronicaService $mtxca,
        private ArcaCaeaLocalService $caeaLocal,
    ) {}

    /**
     * @return Collection<int, Empresa>
     */
    public function empresasElegibles(): Collection
    {
        $idsUsuarios = DB::table('usuario_empresa')->distinct()->pluck('empresa_id')->map(fn ($id) => (int) $id)->all();
        $idsMtxca = array_map('intval', array_keys(config('arca_mtxca.empresas', [])));

        if ($idsUsuarios === [] || $idsMtxca === []) {
            return collect();
        }

        $idsConPvCaea = Puntoventa::query()
            ->whereIn('empresa_id', $idsUsuarios)
            ->where('modofacturacion', 'A')
            ->where('webservice', 'wsmtxca')
            ->where('estado', 'A')
            ->distinct()
            ->pluck('empresa_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ids = array_values(array_intersect($idsUsuarios, $idsMtxca, $idsConPvCaea));

        return Empresa::query()
            ->whereIn('id', $ids)
            ->whereNotNull('nroinscripcion')
            ->where('nroinscripcion', '!=', '')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{ok: bool, registro: ?ArcaCaea, mensaje: string}
     */
    public function solicitarYGuardar(
        int $empresaId,
        int $periodo,
        int $orden,
        string $origen = ArcaCaea::ORIGEN_AUTOMATICO,
        ?int $usuarioId = null,
        bool $forzarConsulta = false,
    ): array {
        $empresa = Empresa::query()->find($empresaId);
        if ($empresa === null) {
            return ['ok' => false, 'registro' => null, 'mensaje' => "Empresa {$empresaId} inexistente."];
        }

        $cuit = preg_replace('/\D+/', '', (string) $empresa->nroinscripcion) ?? '';
        if ($cuit === '') {
            return ['ok' => false, 'registro' => null, 'mensaje' => 'La empresa no tiene CUIT cargado.'];
        }

        if (! $this->empresaEsElegible($empresaId)) {
            return ['ok' => false, 'registro' => null, 'mensaje' => 'Empresa sin certificado MTXCA o sin punto de venta CAEA activo (wsmtxca).'];
        }

        $existente = ArcaCaea::query()
            ->where('empresa_id', $empresaId)
            ->where('periodo', $periodo)
            ->where('orden', $orden)
            ->first();

        if ($existente !== null && $existente->estaAutorizado() && ! $forzarConsulta) {
            return ['ok' => true, 'registro' => $existente, 'mensaje' => 'CAEA ya registrado en anitaERP.'];
        }

        $registro = $existente ?? new ArcaCaea([
            'empresa_id' => $empresaId,
            'periodo' => $periodo,
            'orden' => $orden,
            'cuit' => $cuit,
        ]);

        $registro->origen = $origen;
        $registro->solicitado_por_usuario_id = $usuarioId;
        $registro->estado = ArcaCaea::ESTADO_PENDIENTE;
        $registro->save();

        try {
            $obtenido = $this->obtenerCaeaDesdeArca($empresaId, $periodo, $orden, $forzarConsulta);
            $this->aplicarRespuestaArca($registro, $obtenido['resp']);

            return [
                'ok' => $registro->estaAutorizado(),
                'registro' => $registro->fresh(),
                'mensaje' => $registro->estaAutorizado()
                    ? ($obtenido['via_consulta']
                        ? 'CAEA recuperado por consulta ARCA (MTXCA; ya otorgado previamente, p. ej. Anita).'
                        : 'CAEA obtenido correctamente (MTXCA).')
                    : ($registro->mensaje_error ?? 'No se pudo autorizar el CAEA.'),
            ];
        } catch (Exception $e) {
            $registro->estado = ArcaCaea::ESTADO_ERROR;
            $registro->mensaje_error = $e->getMessage();
            $registro->codigo_error = $this->extraerCodigoError($e->getMessage());
            $registro->save();

            Log::warning('CAEA MTXCA falló', [
                'empresa_id' => $empresaId,
                'periodo' => $periodo,
                'orden' => $orden,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'registro' => $registro->fresh(), 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * @return list<array{empresa_id: int, periodo: int, orden: int, ok: bool, mensaje: string}>
     */
    public function procesarQuincenasEnVentana(): array
    {
        $quincenas = CaeaQuincenaSupport::quincenasEnVentanaSolicitud();
        $resultados = [];

        foreach ($this->empresasElegibles() as $empresa) {
            foreach ($quincenas as $q) {
                $r = $this->solicitarYGuardar(
                    (int) $empresa->id,
                    (int) $q['periodo'],
                    (int) $q['orden'],
                    ArcaCaea::ORIGEN_AUTOMATICO,
                );
                $resultados[] = [
                    'empresa_id' => (int) $empresa->id,
                    'periodo' => (int) $q['periodo'],
                    'orden' => (int) $q['orden'],
                    'ok' => $r['ok'],
                    'mensaje' => $r['mensaje'],
                ];
            }
        }

        return $resultados;
    }

    public function buscarCaeaVigentePorCuit(string $nroinscripcion, Carbon|string $fechaFactura): ?ArcaCaea
    {
        return $this->caeaLocal->buscarRegistroVigente($nroinscripcion, $fechaFactura);
    }

    public function empresaEsElegible(int $empresaId): bool
    {
        return $this->empresasElegibles()->contains(fn (Empresa $e) => (int) $e->id === $empresaId);
    }

    /**
     * @param  array<string, mixed>  $resp
     */
    private function aplicarRespuestaArca(ArcaCaea $registro, array $resp): void
    {
        $registro->nro_caea = (string) $resp['caea'];
        $registro->fecha_vigencia_desde = CaeaQuincenaSupport::parseFechaArca($resp['fch_vig_desde'] ?? null);
        $registro->fecha_vigencia_hasta = CaeaQuincenaSupport::parseFechaArca($resp['fch_vig_hasta'] ?? null);
        $registro->fecha_tope_informe = CaeaQuincenaSupport::parseFechaArca($resp['fch_tope_inf'] ?? null);
        $registro->fecha_proceso = CaeaQuincenaSupport::parseFechaHoraArca($resp['fch_proceso'] ?? null);
        $registro->estado = ($resp['tiene_observaciones'] ?? false)
            ? ArcaCaea::ESTADO_OBSERVACION
            : ArcaCaea::ESTADO_OK;
        $registro->codigo_error = null;
        $registro->mensaje_error = null;
        $registro->observaciones = ($resp['tiene_observaciones'] ?? false)
            ? ['texto' => (string) ($resp['observaciones'] ?? ''), 'webservice' => 'wsmtxca']
            : ['webservice' => 'wsmtxca'];
        $registro->save();
    }

    /**
     * @return array{resp: array<string, mixed>, via_consulta: bool}
     */
    private function obtenerCaeaDesdeArca(int $empresaId, int $periodo, int $orden, bool $soloConsultar): array
    {
        if ($soloConsultar) {
            return [
                'resp' => $this->mtxca->consultarCaea($empresaId, $periodo, $orden),
                'via_consulta' => true,
            ];
        }

        try {
            return [
                'resp' => $this->mtxca->solicitarCaea($empresaId, $periodo, $orden),
                'via_consulta' => false,
            ];
        } catch (Exception $e) {
            if (! ArcaCaeaSolicitudSupport::debeConsultarTrasFalloSolicitud($e->getMessage(), 'wsmtxca')) {
                throw $e;
            }

            Log::info('CAEA MTXCA: solicitud sin CAEA o ya otorgado; consultando en ARCA', [
                'empresa_id' => $empresaId,
                'periodo' => $periodo,
                'orden' => $orden,
                'error_solicitud' => $e->getMessage(),
            ]);

            return [
                'resp' => $this->mtxca->consultarCaea($empresaId, $periodo, $orden),
                'via_consulta' => true,
            ];
        }
    }

    private function extraerCodigoError(string $mensaje): ?string
    {
        if (preg_match('/\[(\d+)\]/', $mensaje, $m)) {
            return $m[1];
        }

        return null;
    }
}
