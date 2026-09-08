<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Suscripcion_Cargo;
use App\Models\Compras\Suscripcion_Comprobante;
use App\Support\Configuracion\ParametroSistemaSupport;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Facturas de portal pedidas al dueño del servicio cuando hay gasto real en tarjeta.
 */
class SuscripcionComprobanteService
{
    /**
     * Crea (o reutiliza) el pendiente al asociar un cargo a la OC.
     */
    public function asegurarPendienteAlAsociar(Suscripcion_Cargo $cargo, Ordencompra $oc): Suscripcion_Comprobante
    {
        $periodo = $this->periodoDesdeCargo($cargo);

        $existente = Suscripcion_Comprobante::query()
            ->where('suscripcion_cargo_id', $cargo->id)
            ->first();

        if ($existente) {
            if ((int) $existente->ordencompra_id !== (int) $oc->id) {
                $existente->update([
                    'ordencompra_id' => (int) $oc->id,
                    'periodo' => $periodo,
                ]);
            }

            return $existente->fresh();
        }

        return Suscripcion_Comprobante::query()->create([
            'ordencompra_id' => (int) $oc->id,
            'suscripcion_cargo_id' => (int) $cargo->id,
            'periodo' => $periodo,
            'estado' => Suscripcion_Comprobante::ESTADO_PENDIENTE,
        ]);
    }

    /**
     * Al desasociar: borra pendientes sin archivo; conserva cargados ligados a la OC.
     */
    public function alDesasociarCargo(Suscripcion_Cargo $cargo): void
    {
        $comp = Suscripcion_Comprobante::query()
            ->where('suscripcion_cargo_id', $cargo->id)
            ->first();

        if (! $comp) {
            return;
        }

        if ($comp->estado === Suscripcion_Comprobante::ESTADO_PENDIENTE && ! $comp->tieneArchivo()) {
            $comp->delete();

            return;
        }

        // Histórico: queda en la OC aunque el cargo se desvincule.
        $comp->update(['suscripcion_cargo_id' => null]);
    }

    /**
     * @return Collection<int, Suscripcion_Comprobante>
     */
    public function listarPendientes(?int $empresaId = null, ?int $ownerUsuarioId = null, ?string $area = null): Collection
    {
        $q = Suscripcion_Comprobante::query()
            ->with([
                'ordencompras.empresas',
                'ordencompras.suscripcion_owners',
                'suscripcion_cargos.suscripcion_conciliaciones',
            ])
            ->where('estado', Suscripcion_Comprobante::ESTADO_PENDIENTE)
            ->whereHas('ordencompras', function ($oc) use ($empresaId, $ownerUsuarioId, $area) {
                $oc->where('es_suscripcion', true);
                if ($empresaId) {
                    $oc->where('empresa_id', $empresaId);
                }
                if ($ownerUsuarioId) {
                    $oc->where('suscripcion_owner_usuario_id', $ownerUsuarioId);
                }
                if ($area !== null && $area !== '') {
                    $oc->where('suscripcion_area', $area);
                }
            })
            ->orderBy('periodo')
            ->orderBy('id');

        return $q->get();
    }

    /**
     * @return Collection<int, Suscripcion_Comprobante>
     */
    public function deSuscripcion(int $ordencompraId): Collection
    {
        return Suscripcion_Comprobante::query()
            ->with(['suscripcion_cargos', 'subido_usuarios'])
            ->where('ordencompra_id', $ordencompraId)
            ->orderByDesc('periodo')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function subir(Suscripcion_Comprobante $comp, UploadedFile $archivo, int $usuarioId): array
    {
        if (! in_array($comp->estado, [
            Suscripcion_Comprobante::ESTADO_PENDIENTE,
            Suscripcion_Comprobante::ESTADO_CARGADO,
        ], true)) {
            return ['ok' => false, 'mensaje' => 'Este comprobante no admite carga de archivo.'];
        }

        $ext = strtolower($archivo->getClientOriginalExtension() ?: 'pdf');
        if (! in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true)) {
            return ['ok' => false, 'mensaje' => 'Solo se admiten PDF o imágenes (png/jpg).'];
        }

        $dir = public_path(
            'storage/archivos/ordencompras/'.$comp->ordencompra_id.'/periodos/'.$comp->periodo
        );
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $nombreSeguro = 'factura_'.now()->format('Ymd_His').'_'.preg_replace(
            '/[^a-zA-Z0-9._-]/',
            '_',
            $archivo->getClientOriginalName()
        );
        $nombreSeguro = mb_substr($nombreSeguro, 0, 200);

        if ($comp->tieneArchivo()) {
            $viejo = $comp->rutaArchivo();
            if ($viejo && is_file($viejo)) {
                @unlink($viejo);
            }
        }

        $archivo->move($dir, $nombreSeguro);

        $comp->update([
            'archivo_nombre' => $nombreSeguro,
            'estado' => Suscripcion_Comprobante::ESTADO_CARGADO,
            'subido_usuario_id' => $usuarioId,
            'subido_at' => now(),
        ]);

        return ['ok' => true, 'mensaje' => 'Comprobante cargado.'];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function marcarNoAplica(Suscripcion_Comprobante $comp, int $usuarioId, ?string $observacion = null): array
    {
        $comp->update([
            'estado' => Suscripcion_Comprobante::ESTADO_NO_APLICA,
            'subido_usuario_id' => $usuarioId,
            'subido_at' => now(),
            'observacion' => $observacion !== null
                ? mb_substr(trim($observacion), 0, 255)
                : $comp->observacion,
        ]);

        return ['ok' => true, 'mensaje' => 'Marcado como no aplica.'];
    }

    public function puedeGestionar(Suscripcion_Comprobante $comp, ?int $usuarioId = null): bool
    {
        $uid = $usuarioId ?? (int) Auth::id();
        if ($uid <= 0) {
            return false;
        }
        if (can('configurar-suscripcion', false) || can('conciliar-suscripcion', false)) {
            return true;
        }

        $ownerId = (int) ($comp->ordencompras?->suscripcion_owner_usuario_id ?? 0);

        return $ownerId > 0 && $ownerId === $uid;
    }

    public function periodoDesdeCargo(Suscripcion_Cargo $cargo): string
    {
        $periodoConcil = optional($cargo->suscripcion_conciliaciones)->periodo;
        if (is_string($periodoConcil) && preg_match('/^\d{4}-\d{2}$/', $periodoConcil)) {
            return $periodoConcil;
        }

        $fecha = $cargo->fecha instanceof Carbon
            ? $cargo->fecha
            : Carbon::parse((string) $cargo->fecha);

        return $fecha->format('Y-m');
    }

    /**
     * Config: "ultimo" | 1..31.
     * Prioridad: Configuración general → config/compras.php / .env.
     */
    public function diaEscalamientoConfig(): string|int
    {
        $raw = ParametroSistemaSupport::suscripcionComprobanteDiaEscalamiento();
        $s = strtolower(trim((string) $raw));
        if ($s === '' || in_array($s, ['ultimo', 'fin', 'ultimo_dia', '0'], true)) {
            return 'ultimo';
        }
        if (ctype_digit($s)) {
            return max(1, min(31, (int) $s));
        }

        return 'ultimo';
    }

    /**
     * Texto corto para UI / mails (ej. "último día del mes" o "día 20").
     */
    public function etiquetaUmbralEscalamiento(): string
    {
        $cfg = $this->diaEscalamientoConfig();

        return $cfg === 'ultimo' ? 'último día del mes' : 'día '.$cfg;
    }

    /**
     * Primer día del mes del período en que ya corresponde escalar a gerencia.
     */
    public function umbralEscalamiento(string $periodo): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            return null;
        }
        try {
            $inicioMes = Carbon::createFromFormat('Y-m-d', $periodo.'-01')->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $cfg = $this->diaEscalamientoConfig();
        if ($cfg === 'ultimo') {
            return $inicioMes->copy()->endOfMonth()->startOfDay();
        }

        $dia = min((int) $cfg, $inicioMes->daysInMonth);

        return $inicioMes->copy()->day($dia)->startOfDay();
    }

    /**
     * Umbral de escalamiento según config (default: último día del mes del período).
     */
    public function debeEscalar(Suscripcion_Comprobante $comp, ?Carbon $hoy = null): bool
    {
        if (! $comp->pendiente()) {
            return false;
        }

        $hoy = $hoy?->copy()->startOfDay() ?? Carbon::today();
        $umbral = $this->umbralEscalamiento((string) $comp->periodo);
        if (! $umbral) {
            return false;
        }

        return $hoy->greaterThanOrEqualTo($umbral);
    }

    /**
     * Mapa cargo_id => estado de comprobante (para badges en conciliación).
     *
     * @param  list<int>  $cargoIds
     * @return array<int, string>
     */
    public function estadosPorCargoIds(array $cargoIds): array
    {
        if ($cargoIds === []) {
            return [];
        }

        return Suscripcion_Comprobante::query()
            ->whereIn('suscripcion_cargo_id', $cargoIds)
            ->pluck('estado', 'suscripcion_cargo_id')
            ->mapWithKeys(fn ($estado, $id) => [(int) $id => (string) $estado])
            ->all();
    }
}
