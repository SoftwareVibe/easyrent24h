<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;

/**
 * QR code del link affiliato (ex cartella /qrcode + Endroid):
 * il QR punta al catalogo con il coupon precompilato.
 */
class QrCodeController extends Controller
{
    public function coupon(string $code): Response
    {
        $coupon = Coupon::where('code', $code)->firstOrFail();

        $url = rtrim(config('app.frontend_url'), '/').'/catalog?coupon='.urlencode($coupon->code);

        $writer = new Writer(new ImageRenderer(
            new RendererStyle(400),
            new SvgImageBackEnd(),
        ));

        return response($writer->writeString($url), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="coupon-'.$coupon->code.'.svg"',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
