<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Puntoventa;
use App\Services\Ventas\Gastronomia\GastronomiaImportarCabeceraAnitaErpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class GastronomiaImportarCabeceraAnitaErp extends Command
{
    protected $signature = 'gastronomia:importar-cabecera-anita-erp
                            {--puntoventa=00031 : Código PV (Kandiko CAEA 00031)}
                            {--empresa=2 : empresa_id}
                            {--numero= : Número de comprobante (ej. 14041)}
                            {--fecha-jornada= : Fecha jornada Y-m-d (opcional, default Anita ven_fecha_vto)}
                            {--usuario= : usuario_id (default: primer usuario)}';

    protected $description = 'Importa al ERP una cabecera Anita (FAK/FAC) ausente en anitaERP (PV CAEA Kandiko 00031)';

    public function handle(GastronomiaImportarCabeceraAnitaErpService $service): int
    {
        $numero = (int) $this->option('numero');
        if ($numero <= 0) {
            $this->error('Indique --numero=NNNNN');

            return self::FAILURE;
        }

        $empresaId = (int) $this->option('empresa');
        $codigoPv = trim((string) $this->option('puntoventa'));
        $pv = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigoPv)
            ->first();
        if ($pv === null) {
            $this->error('PV '.$codigoPv.' no encontrado para empresa '.$empresaId);

            return self::FAILURE;
        }

        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $fechaJornada = trim((string) ($this->option('fecha-jornada') ?? ''));
        $fechaJornada = $fechaJornada !== '' ? $fechaJornada : null;

        try {
            $resultado = $service->importarPorComprobante(
                (int) $pv->id,
                $numero,
                $fechaJornada,
                $usuarioId,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Importación OK: '.$resultado['codigo'].' (venta_id '.$resultado['venta_id'].')');

        return self::SUCCESS;
    }
}
