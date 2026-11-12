<?php

declare(strict_types=1);

namespace Timbrix\Exceptions;

/** La firma o el payload de un webhook no son válidos. */
class SignatureVerificationException extends TimbrixException
{
}
