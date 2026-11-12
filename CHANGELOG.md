# @timbrix/sdk-php

## 0.1.2

### Patch Changes

- 4621664: Las API keys nuevas empiezan con `sk_test_` (sandbox) o `sk_live_` (producción), y el panel muestra el prefijo en la lista. Las keys anteriores (`sk_...`) siguen funcionando. Una key cuyo prefijo no coincide con su ambiente responde `401`. Los docs, los README de los SDK y el MCP usan los prefijos reales en sus ejemplos.

## 0.1.1

### Patch Changes

- bbd1993: README del SDK PHP con badges y un quickstart más corto. Los docs ya no hablan de prefijos `sk_test_`/`sk_live_` que las API keys todavía no tienen.

## 0.1.0

### Minor Changes

- f74d058: Primera versión del SDK de PHP (`timbrix/sdk-php`) con su guía en los docs, y corrección del ejemplo de verificación de firma de webhooks.
