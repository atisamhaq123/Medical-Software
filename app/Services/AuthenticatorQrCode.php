<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class AuthenticatorQrCode
{
    public function provisioningUri(string $email, string $secret): string
    {
        return 'otpauth://totp/Medica:'.rawurlencode($email).'?'.http_build_query([
            'secret' => $secret,
            'issuer' => 'Medica',
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function dataUri(string $email, string $secret): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(256, 20), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($this->provisioningUri($email, $secret)));
    }
}
