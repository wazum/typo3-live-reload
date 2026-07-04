<?php

declare(strict_types=1);

namespace Wazum\ContentLiveReload\Runtime;

use Throwable;

final readonly class SapiResponseDetacher implements ResponseDetacher
{
    public function detach(): bool
    {
        try {
            if (\PHP_SESSION_ACTIVE === session_status()) {
                session_write_close();
            }

            if (!\function_exists('fastcgi_finish_request') || !fastcgi_finish_request()) {
                return false;
            }

            ignore_user_abort(true);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
