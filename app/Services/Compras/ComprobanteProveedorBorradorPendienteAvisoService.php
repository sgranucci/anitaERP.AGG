<?php

namespace App\Services\Compras;

use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\ComprobanteProveedorBorradorPendienteSupport;
use Illuminate\Support\Facades\Log;

class ComprobanteProveedorBorradorPendienteAvisoService
{
    public const MODULO = 'compras';

    public const CODIGO = 'comprobante_proveedor_borrador_pendiente';

    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {}

    /**
     * @return array{enviados: int, omitido: string|null, cantidad: int}
     */
    public function enviarResumen(bool $enviarMail = true): array
    {
        $tipo = ModuloAvisoTipo::query()
            ->where('modulo', self::MODULO)
            ->where('codigo', self::CODIGO)
            ->where('activo', true)
            ->first();

        if (! $tipo) {
            return ['enviados' => 0, 'omitido' => 'aviso_inactivo_o_inexistente', 'cantidad' => 0];
        }

        $destinatarios = $tipo->destinatarios()->where('activo', true)->with('usuarios')->get();
        if ($destinatarios->isEmpty()) {
            return ['enviados' => 0, 'omitido' => 'sin_destinatarios', 'cantidad' => 0];
        }

        $digestGlobal = null;
        $enviados = 0;
        $yaEnviados = [];
        $cantidadMax = 0;

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

            if ($empresaId === null) {
                $digestGlobal ??= ComprobanteProveedorBorradorPendienteSupport::recopilar(null);
                $digest = $digestGlobal;
            } else {
                $digest = ComprobanteProveedorBorradorPendienteSupport::recopilar($empresaId);
            }

            $cantidadMax = max($cantidadMax, (int) ($digest['cantidad'] ?? 0));
            if (! ComprobanteProveedorBorradorPendienteSupport::hayPendientes($digest)) {
                continue;
            }

            if ($enviarMail) {
                $this->moduloAvisoService->enviar(self::MODULO, self::CODIGO, 0, [
                    'digest' => $digest,
                    'emails' => [$email],
                    'empresa_id' => $empresaId,
                ]);
            }

            $yaEnviados[$claveEnvio] = true;
            $enviados++;
        }

        if ($enviados === 0) {
            Log::info('ComprobanteProveedorBorradorPendienteAviso: sin facturas en borrador', [
                'cantidad' => $cantidadMax,
            ]);

            return [
                'enviados' => 0,
                'omitido' => $cantidadMax > 0 ? 'sin_destinatarios_validos' : 'sin_borradores',
                'cantidad' => $cantidadMax,
            ];
        }

        return ['enviados' => $enviados, 'omitido' => null, 'cantidad' => $cantidadMax];
    }
}
