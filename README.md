# LeanCommerce CategoryProductOrderApi

Módulo Magento 2 instalable por Composer que expone:

- `GET /V1/leancommerce/category-products/order` para consultar el orden visible de productos por categoría.
- `PUT /V1/leancommerce/category-products/order` para actualizar la posición de un SKU dentro de una categoría y devolver el estado administrativo/frontend.

## Instalación por Composer

### Opción A: repositorio VCS

```bash
composer config repositories.leancommerce-category-product-order-api vcs git@github.com:TU_ORG/module-category-product-order-api.git
composer require leancommerce/module-category-product-order-api
bin/magento module:enable LeanCommerce_CategoryProductOrderApi
bin/magento setup:upgrade
bin/magento cache:flush
```

### Opción B: paquete ZIP local

```bash
mkdir -p packages/leancommerce/module-category-product-order-api
composer config repositories.leancommerce-category-product-order-api path packages/leancommerce/module-category-product-order-api
composer require leancommerce/module-category-product-order-api
bin/magento module:enable LeanCommerce_CategoryProductOrderApi
bin/magento setup:upgrade
bin/magento cache:flush
```

## Dependencias

- Magento Catalog
- Magento Webapi
- Smile ElasticSuite (`smile/elasticsuite`)

El módulo declara ElasticSuite como dependencia obligatoria porque el objetivo del contrato es alinear el orden con el PLP indexado por ElasticSuite.

## ACL

El endpoint usa el recurso propio:

```text
LeanCommerce_CategoryProductOrderApi::category_product_order
```

Esto evita depender del permiso genérico `Magento_Catalog::products`.

## GET

```http
GET /rest/V1/leancommerce/category-products/order?categoryId=123&storeId=1
```

## PUT

```http
PUT /rest/V1/leancommerce/category-products/order
```

Payload:

```json
{
  "category_id": 123,
  "sku": "ABC-123",
  "target_position": 15,
  "store_id": 1
}
```

Respuesta:

```json
{
  "category_id": 123,
  "sku": "ABC-123",
  "requested_position": 15,
  "applied_position_source": "visual_merchandiser",
  "admin_position": 15,
  "frontend_position": 15,
  "message": "Position updated"
}
```

## Reglas de negocio

1. Si existe estado de Visual Merchandiser / ElasticSuite Virtual Category para la categoría, se usa `visual_merchandiser`.
2. Si no existe estado aplicable, se usa `magento_native` sobre `catalog_category_product.position`.
3. Si una categoría virtual no tiene estado manual en Visual Merchandiser, se responde `409`, porque la posición manual puede ser incompatible con reglas dinámicas.
4. El producto debe existir y pertenecer al set administrativo resuelto.
5. Después de persistir, se reindexan `catalog_category_product` y `catalogsearch_fulltext` de forma síncrona best-effort.
6. Se revalida `frontend_position` usando el provider de lectura existente.

## Códigos de error

- `404`: categoría o SKU inexistente.
- `422`: payload inválido o `target_position <= 0`.
- `409`: reglas de merchandising no compatibles o producto fuera del set resuelto.
- `500`: fallo técnico no esperado.

## Pruebas recomendadas

- Categoría nativa con productos asignados.
- Categoría nativa con estado de Visual Merchandiser.
- Categoría virtual con posiciones manuales existentes.
- Categoría virtual sin posiciones manuales: debe responder `409`.
- Multi-store con `store_id` específico.
- Comparación del `frontend_position` contra PLP después de reindex.
