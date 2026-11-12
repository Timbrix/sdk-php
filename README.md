# Timbrix PHP SDK

[![Packagist](https://img.shields.io/packagist/v/timbrix/sdk-php)](https://packagist.org/packages/timbrix/sdk-php)
[![PHP](https://img.shields.io/packagist/dependency-v/timbrix/sdk-php/php)](https://packagist.org/packages/timbrix/sdk-php)
[![Licencia](https://img.shields.io/packagist/l/timbrix/sdk-php)](LICENSE)
[![CFDI 4.0 México](https://img.shields.io/badge/CFDI-4.0%20M%C3%A9xico-006847)](https://docs.timbrix.mx)

Cliente oficial de PHP para la API de [Timbrix](https://timbrix.mx): facturación electrónica CFDI 4.0. Funciona con cualquier framework (Laravel, Symfony o PHP puro). Requiere PHP 8.1 o superior.

## Instalación

```bash
composer require timbrix/sdk-php
```

## Quickstart

```php
$timbrix = new Timbrix\TimbrixClient(getenv('TIMBRIX_API_KEY') ?: '');

$invoice = $timbrix->invoices->create([
    'type' => 'I', 'use' => 'G03', 'paymentForm' => '01', 'paymentMethod' => 'PUE',
    'date' => (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d\TH:i:s'),
    'customerId' => $customerId,
    'items' => [['productId' => $productId, 'quantity' => 1]],
    'idempotencyKey' => $orderId,
]);
file_put_contents('factura.pdf', $timbrix->invoices->getPdf($invoice->uuid));
```

Con una API key de sandbox (`sk_test_...`) el CFDI no tiene validez fiscal; con una de producción (`sk_live_...`), sí. Las keys creadas antes de este formato empiezan solo con `sk_`; su entorno se ve en el panel, en la lista de API keys. No expongas la key en código que corre en el navegador.

Pasa siempre una `idempotencyKey` estable (por ejemplo, el id de tu pedido) al timbrar: es lo que permite al SDK reintentar sin riesgo de duplicar un CFDI.

## Recursos y métodos

Todos los métodos aceptan arrays asociativos con las llaves camelCase de la API y devuelven modelos de solo lectura (`toArray()` conserva los campos que el SDK aún no declara). Cada API key pertenece a una sola organización; para emitir con otro RFC emisor, usa una API key de esa organización.

| Recurso               | Método                                          | Qué hace                                             |
| --------------------- | ----------------------------------------------- | ---------------------------------------------------- |
| `$timbrix->invoices`  | `create($data)`                                 | Crea y timbra un CFDI 4.0                            |
|                       | `registerPayment($data)`                        | Registra un pago PPD y timbra su Complemento de Pago |
|                       | `getPayments($uuid)`                            | Parcialidades y saldo de una factura PPD             |
|                       | `previewPdf($data)`                             | PDF de vista previa sin timbrar (bytes)              |
|                       | `list($params)`                                 | Lista facturas con filtros y paginación              |
|                       | `get($uuid)`                                    | Obtiene una factura por su folio fiscal              |
|                       | `getXml($uuid)` / `getPdf($uuid)`               | Descarga el XML o el PDF (bytes)                     |
|                       | `cancel($uuid, $data)`                          | Cancela un CFDI ante el SAT                          |
|                       | `sendEmail($uuid, $data)`                       | Envía el CFDI por correo                             |
| `$timbrix->customers` | `list`, `get`, `create`, `update`, `delete`     | Clientes (receptores)                                |
| `$timbrix->products`  | `list`, `get`, `create`, `update`, `delete`     | Productos y servicios                                |
| `$timbrix->sat`       | `regimenes($params)`                            | Regímenes fiscales                                   |
|                       | `usosCfdi($params)`                             | Usos de CFDI                                         |
|                       | `searchClavesProdServ($params)`                 | Busca claves de producto o servicio                  |
|                       | `searchClavesUnidad($params)`                   | Busca claves de unidad                               |
| `Timbrix\Webhook`     | `constructEvent($payload, $signature, $secret)` | Verifica la firma y devuelve el evento               |
|                       | `verify($payload, $signature, $secret)`         | Solo verifica la firma (booleano)                    |

Las series y los webhooks se configuran desde el panel; el SDK no los administra.

## Documentación

Guía completa, errores, reintentos y webhooks: [docs.timbrix.mx/sdk-php](https://docs.timbrix.mx/sdk-php).

## Licencia

MIT
