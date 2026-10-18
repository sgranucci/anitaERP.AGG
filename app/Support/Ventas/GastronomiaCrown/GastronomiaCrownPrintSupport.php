<?php

namespace App\Support\Ventas\GastronomiaCrown;

use App\Models\Configuracion\Salida;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\GastronomiaCrown\GastroCrownComanda;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuenta;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Impresión de comandas Crown: térmica (salida.comando / ncjetdirect) + PDF opcional.
 */
final class GastronomiaCrownPrintSupport
{
    public const CANAL_TERMICA = 'termica';

    public const CANAL_PDF = 'pdf';

    public const CANAL_AMBOS = 'ambos';

    /**
     * @return array{ok: bool, canal: string, ruta_pdf: ?string, mensaje: string}
     */
    public static function imprimirComanda(GastroCrownComanda $comanda, string $canal = self::CANAL_AMBOS): array
    {
        $rutaPdf = null;
        $mensajes = [];

        if (in_array($canal, [self::CANAL_PDF, self::CANAL_AMBOS], true)) {
            $rutaPdf = self::guardarPdfComanda($comanda);
            $mensajes[] = $rutaPdf ? 'PDF generado' : 'PDF no generado';
        }

        if (in_array($canal, [self::CANAL_TERMICA, self::CANAL_AMBOS], true)) {
            $okTermica = self::enviarTermica($comanda);
            $mensajes[] = $okTermica ? 'térmica OK' : 'térmica falló o sin salida';
            if (! $okTermica && $canal === self::CANAL_TERMICA) {
                return [
                    'ok' => false,
                    'canal' => $canal,
                    'ruta_pdf' => $rutaPdf,
                    'mensaje' => implode('; ', $mensajes),
                ];
            }
        }

        if ($rutaPdf) {
            $comanda->ruta_pdf = $rutaPdf;
            $comanda->canal_impresion = $canal;
            $comanda->save();
        }

        return [
            'ok' => true,
            'canal' => $canal,
            'ruta_pdf' => $rutaPdf,
            'mensaje' => implode('; ', $mensajes),
        ];
    }

    public static function guardarPdfComanda(GastroCrownComanda $comanda): ?string
    {
        $comanda->loadMissing(['lineas', 'mesa', 'mozo', 'areaComanda', 'cuenta']);

        $html = view('ventas.gastronomia_crown.comanda.pdf', [
            'comanda' => $comanda,
        ])->render();

        $dir = 'pdf/comandas_crown';
        Storage::disk('local')->makeDirectory($dir);
        $nombre = sprintf('comanda_%s_%s.pdf', $comanda->numero, $comanda->id);
        $relativo = $dir.'/'.$nombre;
        $absoluto = storage_path('app/'.$relativo);

        try {
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait')->save($absoluto);
            } elseif (app()->bound('dompdf.wrapper')) {
                app('dompdf.wrapper')->loadHTML($html)->setPaper('a4')->save($absoluto);
            } else {
                file_put_contents($absoluto, $html);

                return $relativo.'.html';
            }
        } catch (\Throwable $e) {
            Log::warning('Crown comanda PDF: '.$e->getMessage(), ['comanda_id' => $comanda->id]);

            return null;
        }

        return $relativo;
    }

    public static function enviarTermica(GastroCrownComanda $comanda): bool
    {
        $salida = $comanda->salida_id
            ? Salida::query()->find($comanda->salida_id)
            : null;

        if ($salida === null && $comanda->areaComanda) {
            $salida = $comanda->areaComanda->salida;
        }

        if ($salida === null || trim((string) $salida->comando) === '') {
            Log::info('Crown comanda sin comando de salida', ['comanda_id' => $comanda->id]);

            return false;
        }

        $texto = self::textoEscPos($comanda);
        $tmp = tempnam(sys_get_temp_dir(), 'gc_comanda_');
        if ($tmp === false) {
            return false;
        }
        file_put_contents($tmp, $texto);

        $cmdPlantilla = (string) $salida->comando;
        if (! str_contains($cmdPlantilla, '%s')) {
            $cmdPlantilla .= ' "%s"';
        }
        $cmd = sprintf($cmdPlantilla, $tmp);

        try {
            exec($cmd.' 2>&1', $out, $code);
            @unlink($tmp);
            if ($code !== 0) {
                Log::warning('Crown ncjetdirect/salida falló', [
                    'code' => $code,
                    'out' => implode("\n", $out),
                    'comanda_id' => $comanda->id,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            @unlink($tmp);
            Log::warning('Crown print: '.$e->getMessage());

            return false;
        }
    }

    public static function textoEscPos(GastroCrownComanda $comanda): string
    {
        $comanda->loadMissing(['lineas', 'mesa', 'mozo']);
        $nl = "\n";
        $lines = [];
        $lines[] = 'COMANDA '.$comanda->numero;
        $lines[] = strtoupper((string) $comanda->tipo);
        if ($comanda->mesa) {
            $lines[] = 'Mesa: '.($comanda->mesa->codigo ?: $comanda->mesa->nombre);
        }
        if ($comanda->mozo) {
            $lines[] = 'Mozo: '.$comanda->mozo->nombre;
        }
        $lines[] = date('d/m/Y H:i');
        $lines[] = str_repeat('-', 32);
        foreach ($comanda->lineas as $linea) {
            $lines[] = sprintf('%s x %s', rtrim(rtrim(number_format((float) $linea->cantidad, 3, '.', ''), '0'), '.'), $linea->descripcion);
            if ($linea->observacion) {
                $lines[] = '  * '.$linea->observacion;
            }
        }
        $lines[] = str_repeat('-', 32);
        $lines[] = '';
        $lines[] = '';

        return implode($nl, $lines);
    }

    /**
     * Ticket de cargo a habitación (salida_hotel del PV).
     *
     * @return array{ok: bool, mensaje: string, ruta_pdf: ?string}
     */
    public static function imprimirTicketHotel(GastroCrownCuenta $cuenta, ConfiguracionPuntoventaGastronomia $cfg): array
    {
        $cuenta->loadMissing(['lineas', 'mesa', 'mozo', 'cliente']);
        $rutaPdf = null;

        try {
            $html = view('ventas.gastronomia_crown.hotel.ticket', [
                'cuenta' => $cuenta,
            ])->render();
            $dir = 'pdf/tickets_hotel_crown';
            Storage::disk('local')->makeDirectory($dir);
            $relativo = $dir.'/hotel_'.$cuenta->id.'_'.time().'.pdf';
            $absoluto = storage_path('app/'.$relativo);
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait')->save($absoluto);
                $rutaPdf = $relativo;
            }
        } catch (\Throwable $e) {
            Log::warning('Crown hotel PDF: '.$e->getMessage(), ['cuenta_id' => $cuenta->id]);
        }

        $salida = $cfg->salida_hotel_id
            ? Salida::query()->find($cfg->salida_hotel_id)
            : null;
        $okTermica = false;
        if ($salida && trim((string) $salida->comando) !== '') {
            $texto = self::textoEscPosHotel($cuenta);
            $tmp = tempnam(sys_get_temp_dir(), 'gc_hotel_');
            if ($tmp !== false) {
                file_put_contents($tmp, $texto);
                $cmdPlantilla = (string) $salida->comando;
                if (! str_contains($cmdPlantilla, '%s')) {
                    $cmdPlantilla .= ' "%s"';
                }
                $cmd = sprintf($cmdPlantilla, $tmp);
                exec($cmd.' 2>&1', $out, $code);
                @unlink($tmp);
                $okTermica = $code === 0;
                if (! $okTermica) {
                    Log::warning('Crown hotel térmica falló', ['code' => $code, 'out' => implode("\n", $out)]);
                }
            }
        }

        return [
            'ok' => $okTermica || $rutaPdf !== null,
            'mensaje' => ($okTermica ? 'térmica OK' : 'térmica n/d').'; '.($rutaPdf ? 'PDF OK' : 'PDF n/d'),
            'ruta_pdf' => $rutaPdf,
        ];
    }

    public static function textoEscPosHotel(GastroCrownCuenta $cuenta): string
    {
        $nl = "\n";
        $lines = [];
        $lines[] = 'CARGO HOTEL';
        $lines[] = 'Hab: '.($cuenta->habitacion ?: '-');
        if ($cuenta->reserva_hotel) {
            $lines[] = 'Reserva: '.$cuenta->reserva_hotel;
        }
        if ($cuenta->mesa) {
            $lines[] = 'Mesa: '.($cuenta->mesa->codigo ?: $cuenta->mesa->nombre);
        }
        $lines[] = date('d/m/Y H:i');
        $lines[] = str_repeat('-', 32);
        $total = 0.0;
        foreach ($cuenta->lineas as $linea) {
            if ($linea->es_separador) {
                continue;
            }
            $imp = (float) $linea->cantidad * (float) $linea->precio - (float) $linea->descuento_importe;
            $total += $imp;
            $lines[] = sprintf(
                '%s x %s',
                rtrim(rtrim(number_format((float) $linea->cantidad, 3, '.', ''), '0'), '.'),
                $linea->descripcion
            );
            $lines[] = '  $ '.number_format($imp, 2, ',', '.');
        }
        $pct = (float) ($cuenta->porcentaje_descuento ?? 0);
        if ($pct > 0) {
            $dto = round($total * ($pct / 100), 2);
            $lines[] = 'Dto '.$pct.'% -$ '.number_format($dto, 2, ',', '.');
            $total -= $dto;
        }
        $lines[] = str_repeat('-', 32);
        $lines[] = 'TOTAL $ '.number_format(max(0, $total), 2, ',', '.');
        $lines[] = '';
        $lines[] = '';

        return implode($nl, $lines);
    }
}
