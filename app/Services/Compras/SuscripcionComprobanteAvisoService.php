<?php

namespace App\Services\Compras;

use App\Models\Compras\Suscripcion_Comprobante;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Seguridad\Usuario;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\SuscripcionComprobantePendienteSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Avisos diarios: digest al dueño del servicio + escalamiento a gerencia desde el umbral configurado.
 */
class SuscripcionComprobanteAvisoService
{
    public const MODULO = 'compras';

    public const CODIGO_DUENO = 'suscripcion_comprobante_faltante';

    public const CODIGO_ESCALA = 'suscripcion_comprobante_escalamiento';

    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {}

    /**
     * @return array{enviados_dueno: int, enviados_escala: int, omitido: ?string}
     */
    public function procesar(?Carbon $hoy = null, bool $simular = false): array
    {
        $hoy = $hoy?->copy()->startOfDay() ?? Carbon::today();
        $enviadosDueno = 0;
        $enviadosEscala = 0;

        if (! $simular) {
            $enviadosDueno = $this->avisarDuenos($hoy);
            $enviadosEscala = $this->avisarEscalamiento($hoy);
        } else {
            $owners = $this->ownerIdsConPendientes();
            $escala = SuscripcionComprobantePendienteSupport::recopilarEscalamientos($hoy);

            return [
                'enviados_dueno' => 0,
                'enviados_escala' => 0,
                'omitido' => 'simulacion',
                'owners' => count($owners),
                'escala_cantidad' => (int) ($escala['cantidad'] ?? 0),
            ];
        }

        $omitido = null;
        if ($enviadosDueno === 0 && $enviadosEscala === 0) {
            $omitido = 'sin_envios';
        }

        return [
            'enviados_dueno' => $enviadosDueno,
            'enviados_escala' => $enviadosEscala,
            'omitido' => $omitido,
        ];
    }

    private function avisarDuenos(Carbon $hoy): int
    {
        $tipo = ModuloAvisoTipo::query()
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO_DUENO)
            ->where('activo', true)
            ->first();
        if (! $tipo) {
            return 0;
        }

        $enviados = 0;
        foreach ($this->ownerIdsConPendientes() as $ownerId) {
            $digest = SuscripcionComprobantePendienteSupport::recopilarParaOwner($ownerId);
            if (! SuscripcionComprobantePendienteSupport::hayPendientes($digest)) {
                continue;
            }

            // Anti-duplicado: no reavisar el mismo día los mismos pendientes.
            $idsSinAvisoHoy = Suscripcion_Comprobante::query()
                ->whereIn('id', $digest['ids'] ?? [])
                ->where(function ($q) use ($hoy) {
                    $q->whereNull('aviso_dueno_at')
                        ->orWhereDate('aviso_dueno_at', '<', $hoy->toDateString());
                })
                ->pluck('id');
            if ($idsSinAvisoHoy->isEmpty()) {
                continue;
            }

            $usuario = Usuario::query()->find($ownerId);
            $email = strtolower(trim((string) ($usuario->email ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::info('SuscripcionComprobanteAviso: dueño sin email', ['usuario_id' => $ownerId]);

                continue;
            }

            $this->moduloAvisoService->enviar(self::MODULO, self::CODIGO_DUENO, 0, [
                'digest' => $digest,
                'emails' => [$email],
            ]);

            Suscripcion_Comprobante::query()
                ->whereIn('id', $idsSinAvisoHoy->all())
                ->update(['aviso_dueno_at' => now()]);

            $enviados++;
        }

        return $enviados;
    }

    private function avisarEscalamiento(Carbon $hoy): int
    {
        $tipo = ModuloAvisoTipo::query()
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO_ESCALA)
            ->where('activo', true)
            ->first();
        if (! $tipo) {
            return 0;
        }

        $digest = SuscripcionComprobantePendienteSupport::recopilarEscalamientos($hoy);
        if (! SuscripcionComprobantePendienteSupport::hayPendientes($digest)) {
            return 0;
        }

        $ids = $digest['ids'] ?? [];
        $idsSinAvisoHoy = Suscripcion_Comprobante::query()
            ->whereIn('id', $ids)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('aviso_escala_at')
                    ->orWhereDate('aviso_escala_at', '<', $hoy->toDateString());
            })
            ->pluck('id');
        if ($idsSinAvisoHoy->isEmpty()) {
            return 0;
        }

        $this->moduloAvisoService->enviar(self::MODULO, self::CODIGO_ESCALA, 0, [
            'digest' => $digest,
        ]);

        Suscripcion_Comprobante::query()
            ->whereIn('id', $idsSinAvisoHoy->all())
            ->update(['aviso_escala_at' => now()]);

        return 1;
    }

    /** @return list<int> */
    private function ownerIdsConPendientes(): array
    {
        return Suscripcion_Comprobante::query()
            ->where('estado', Suscripcion_Comprobante::ESTADO_PENDIENTE)
            ->whereHas('ordencompras', fn ($q) => $q
                ->where('es_suscripcion', true)
                ->whereNotNull('suscripcion_owner_usuario_id'))
            ->with('ordencompras:id,suscripcion_owner_usuario_id')
            ->get()
            ->map(fn (Suscripcion_Comprobante $c) => (int) $c->ordencompras->suscripcion_owner_usuario_id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
