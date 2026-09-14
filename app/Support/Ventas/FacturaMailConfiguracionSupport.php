<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Factura_Mail_Configuracion;
use Illuminate\Support\Facades\Schema;

final class FacturaMailConfiguracionSupport
{
    public static function paraEmpresa(int $empresaId): Factura_Mail_Configuracion
    {
        if (! Schema::hasTable('factura_mail_configuracion')) {
            return self::defaults($empresaId);
        }

        $cfg = Factura_Mail_Configuracion::query()->where('empresa_id', $empresaId)->first();
        if ($cfg) {
            return $cfg;
        }

        return self::defaults($empresaId);
    }

    public static function defaults(int $empresaId): Factura_Mail_Configuracion
    {
        $cfg = new Factura_Mail_Configuracion([
            'empresa_id' => $empresaId,
            'habilitado' => false,
            'envio_automatico' => false,
            'incluir_remito' => true,
            'incluir_envio' => false,
            'asunto' => 'Comprobante {codigo} - {empresa}',
            'cuerpo' => "Estimado/a {cliente}:\n\nAdjuntamos el comprobante {codigo} emitido el {fecha}.\n\nSaludos cordiales.",
            'bcc' => '',
            'exigir_flag_cliente' => true,
        ]);
        $cfg->exists = false;

        return $cfg;
    }
}
