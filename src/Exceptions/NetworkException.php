<?php

declare(strict_types=1);

namespace Timbrix\Exceptions;

/** No hubo respuesta: fallo de conexión, DNS, TLS o timeout. La causa va en el mensaje, sin el query string de la URL; no se encadena la excepción de Guzzle porque su traza lleva la API key. */
class NetworkException extends TimbrixException
{
}
