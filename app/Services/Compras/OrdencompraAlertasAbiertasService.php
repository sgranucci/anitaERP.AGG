<?php

namespace App\Services\Compras;

use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\OrdencompraAlertasAbiertasSupport;
use Illuminate\Support\Facades\Log;

class OrdencompraAlertasAbiertasService
{
    public const MODULO = 'compras';

    public const CODIGO = 'ordencompra_alertas_abiertas';

    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * Envía el resumen diario si el tipo de aviso está activo y hay destinatarios.
     *
     * @return array{enviados: int, omitido: string|null}
     */
    public function enviarResumen(?int $diasSinRecepcion = null): array
    {
        $tipo = ModuloAvisoTipo::query()
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->where('activo', true)
            ->first();

        if (! $tipo) {
            return ['enviados' => 0, 'omitido' => 'aviso_inactivo_o_inexistente'];
        }

        $destinatarios = $tipo->destinatarios()->where('activo', true)->with('usuarios')->get();
        if ($destinatarios->isEmpty()) {
            return ['enviados' => 0, 'omitido' => 'sin_destinatarios'];
        }

        $empresasFiltro = $destinatarios
            ->pluck('empresa_id')
            ->map(fn ($id) => $id ? (int) $id : null)
            ->unique()
            ->values()
            ->all();

        // Si hay al menos un destinatario global (sin empresa), armamos el digest completo una vez.
        $incluyeGlobal = in_array(null, $empresasFiltro, true);
        $alertasGlobal = null;
        if ($incluyeGlobal) {
            $alertasGlobal = OrdencompraAlertasAbiertasSupport::recopilar(null, $diasSinRecepcion);
            if (! OrdencompraAlertasAbiertasSupport::hayAlertas($alertasGlobal)) {
                // Aun así puede haber filtros por empresa; no cortamos acá.
            }
        }

        $enviados = 0;
        $yaEnviados = [];

        foreach ($destinatarios as $dest) {
            $email = $dest->emailResuelto();
            if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $email = strtolower($email);
            $empresaId = $dest->empresa_id ? (int) $dest->empresa_id : null;

            $claveEnvio = $email.'|'.($empresaId ?? 'all');
            if (isset($yaEnviados[$claveEnvio])) {
                continue;
            }

            $alertas = $empresaId
                ? OrdencompraAlertasAbiertasSupport::recopilar($empresaId, $diasSinRecepcion)
                : ($alertasGlobal ?? OrdencompraAlertasAbiertasSupport::recopilar(null, $diasSinRecepcion));

            if (! OrdencompraAlertasAbiertasSupport::hayAlertas($alertas)) {
                continue;
            }

            $this->moduloAvisoService->enviar(self::MODULO, self::CODIGO, 0, [
                'alertas' => $alertas,
                'emails' => [$email],
                'empresa_id' => $empresaId,
            ]);

            $yaEnviados[$claveEnvio] = true;
            $enviados++;
        }

        if ($enviados === 0) {
            Log::info('OrdencompraAlertasAbiertasService: sin alertas para enviar', [
                'dias' => $diasSinRecepcion ?? config('compras.oc_alertas_abiertas.dias_sin_recepcion'),
            ]);

            return ['enviados' => 0, 'omitido' => 'sin_alertas'];
        }

        return ['enviados' => $enviados, 'omitido' => null];
    }
}
