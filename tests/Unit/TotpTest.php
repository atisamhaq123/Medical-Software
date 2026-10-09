<?php

namespace Tests\Unit;

use App\Services\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_rfc_6238_reference_vectors(): void
    {
        $totp = new Totp;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037'] as $time => $expected) {
            $this->assertSame($expected, $totp->code($secret, intdiv($time, 30)));
        }
    }

    public function test_a_previously_used_step_cannot_be_replayed(): void
    {
        $totp = new Totp;
        $secret = $totp->generateSecret();
        $step = intdiv(time(), 30);
        $code = $totp->code($secret, $step);
        $this->assertSame($step, $totp->verify($secret, $code));
        $this->assertNull($totp->verify($secret, $code, $step));
    }
}
