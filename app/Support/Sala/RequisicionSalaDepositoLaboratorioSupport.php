<?php

namespace App\Support\Sala;

use App\Models\Stock\Depmae;

/**
 * Depósito de laboratorio para el circuito de requisición de sala (código 406).
 * El laboratorio opera sobre el depósito de Biyemas aunque la requisición sea de otra empresa.
 */
final class RequisicionSalaDepositoLaboratorioSupport
{
    public static function codigoConfigurado(): string
    {
        return trim((string) config('sala.requisicion_deposito_laboratorio_codigo', '406'));
    }

    /**
     * Empresa dueña del depósito laboratorio compartido (default Biyemas = 1).
     */
    public static function empresaIdConfigurada(): int
    {
        return (int) config('sala.requisicion_deposito_laboratorio_empresa_id', 1);
    }

    public static function resolverId(): int
    {
        $dep = self::resolver();

        return $dep ? (int) $dep->id : 0;
    }

    public static function resolver(): ?Depmae
    {
        $codigo = self::codigoConfigurado();
        if ($codigo === '') {
            return null;
        }

        $query = Depmae::query()
            ->with('empresas:id,nombre')
            ->where('codigo', $codigo);

        $empresaId = self::empresaIdConfigurada();
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->orderBy('id')->first();
    }

    public static function etiqueta(): string
    {
        $dep = self::resolver();
        if (! $dep) {
            $codigo = self::codigoConfigurado();

            return $codigo !== '' ? 'Depósito '.$codigo : '—';
        }

        return self::descripcionConEmpresa($dep);
    }

    public static function descripcionConEmpresa(Depmae $dep): string
    {
        $dep->loadMissing('empresas:id,nombre');
        $nombre = trim((string) ($dep->nombre ?? ''));
        $empresa = trim((string) (optional($dep->empresas)->nombre ?? ''));

        if ($nombre === '') {
            $nombre = Depmae::etiquetaDesdePartes((string) ($dep->codigo ?? ''), '', (int) $dep->id);
        }

        if ($empresa === '') {
            return $nombre;
        }

        return $nombre.' ('.$empresa.')';
    }
}
