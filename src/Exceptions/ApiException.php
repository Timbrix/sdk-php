<?php

declare(strict_types=1);

namespace Timbrix\Exceptions;

/** Cualquier otro estado de error de la API (5xx, 418, etc.). */
class ApiException extends TimbrixException
{
}
