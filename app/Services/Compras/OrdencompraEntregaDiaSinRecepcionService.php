<?php

namespace App\Services\Compras;

use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\OrdencompraEntregaDiaSinRecepcionSupport;
use Illuminate\Support\Facades\Log;

class OrdencompraEntregaDiaSinRecepcionService
{
    public const MODULO = 'compras';

    public const CODIGO = 'entrega_dia_sin_recepcion';

    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * Envía el aviso si el tipo está activo, hay destinatarios y hay artículos pendientes.
     *
     * @return array{enviados: int, omitido: string|null, total: int}
     */
    public function enviar(?string $fechaYmd = null): array
    {
        $tipo = ModuloAvisoTipo::query()
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->where('activo', true)
            ->first();

        if (! $tipo) {
            return ['enviados' => 0, 'omitido' => 'aviso_inactivo_o_inexistente', 'total' => 0];
        }

        $destinatarios = $tipo->destinatarios()->where('activo', true)->with('usuarios')->get();
        if ($destinatarios->isEmpty()) {
            return ['enviados' => 0, 'omitido' => 'sin_destinatarios', 'total' => 0];
        }

        $enviados = 0;
        $yaEnviados = [];
        $total = 0;
        $resumenes = [];

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

            $claveResumen = $empresaId ?? 'all';
            if (! isset($resumenes[$claveResumen])) {
                $resumenes[$claveResumen] = OrdencompraEntregaDiaSinRecepcionSupport::recopilar($empresaId, $fechaYmd);
            }
            $resumen = $resumenes[$claveResumen];
            $total = max($total, (int) $resumen['total']);
            if (! OrdencompraEntregaDiaSinRecepcionSupport::hayPendientes($resumen)) {
                continue;
            }

            $this->moduloAvisoService->enviar(self::MODULO, self::CODIGO, 0, [
                'resumen' => $resumen,
                'emails' => [$email],
                'empresa_id' => $empresaId,
            ]);

            $yaEnviados[$claveEnvio] = true;
            $enviados++;
        }

        if ($enviados === 0) {
            Log::info('OrdencompraEntregaDiaSinRecepcionService: sin pendientes para enviar');

            return ['enviados' => 0, 'omitido' => 'sin_pendientes', 'total' => $total];
        }

        return ['enviados' => $enviados, 'omitido' => null, 'total' => $total];
    }
}
