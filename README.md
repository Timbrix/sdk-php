# Timbrix PHP SDK

Cliente oficial de PHP para la API de [Timbrix](https://timbrix.mx): facturación electrónica CFDI 4.0. Funciona con cualquier framework (Laravel, Symfony o PHP puro). Requiere PHP 8.1 o superior.

## Instalación

```bash
composer require timbrix/sdk-php
```

## Quickstart

```php
use Timbrix\TimbrixClient;

$timbrix = new TimbrixClient(getenv('TIMBRIX_API_KEY') ?: ''); // sk_test_... para sandbox

$invoice = $timbrix->invoices->create([
    'type' => 'I',
    'date' => (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d\TH:i:s'),
    'paymentForm' => '01',
    'paymentMethod' => 'PUE',
    'use' => 'G03',
    'customerId' => $customer->id,
    'items' => [['productId' => $product->id, 'quantity' => 1]],
    'idempotencyKey' => $order->uuid,
]);

echo $invoice->uuid;
file_put_contents('factura.pdf', $timbrix->invoices->getPdf($invoice->uuid));
```

La API key `sk_test_...` timbra en sandbox (sin validez fiscal); `sk_live_...` timbra en producción. No expongas la key en código que corre en el navegador.

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
