<?php

declare(strict_types=1);

/**
 * This file is part of simplysmart/polygon.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

namespace Simplysmart\Polygon\Tests\Enums;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simplysmart\Polygon\Enums\MailEncryption;

/**
 * Schemat transportu SMTP z zapisanej wartości szyfrowania skrzynki (Enums\MailEncryption::smtpScheme()).
 */
final class MailEncryptionTest extends TestCase
{
    #[Test]
    public function only_ssl_uses_the_smtps_scheme(): void
    {
        $this->assertSame('smtps', MailEncryption::smtpScheme('ssl'));
        $this->assertSame('smtp', MailEncryption::smtpScheme('tls'));
        $this->assertSame('smtp', MailEncryption::smtpScheme(''));
        $this->assertSame('smtp', MailEncryption::smtpScheme(null));
        $this->assertSame('smtp', MailEncryption::smtpScheme('unknown'));
    }
}
