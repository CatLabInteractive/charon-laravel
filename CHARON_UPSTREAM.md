# Charon Upstream Changes Required

The following changes need to be implemented in the upstream `catlabinteractive/charon` package
to fully support bulk operations.

## 1. JsonBodyInputParser: X-Bulk-Request header support

In `CatLab\Charon\InputParsers\JsonBodyInputParser::getResources()`, when the `X-Bulk-Request: 1`
header is present, the returned `ResourceCollection` should have its `bulk` meta flag set to `true`.

This ensures that even single-item submissions return an array response when the client explicitly
requests bulk mode.

### Suggested implementation

In `JsonBodyInputParser::getResources()`, after building the `$resourceCollection`, add:

```php
if ($this->getHeader('X-Bulk-Request') == '1') {
    $resourceCollection->addMeta('bulk', true);
}
```

The `AbstractInputParser` already has a `getHeader()` method that can be used for this.

## 2. RouteCollection: Bulk destroy route registration

In `CatLab\Charon\Collections\RouteCollection`, the `resource()` and `childResource()` methods
should register a bulk destroy route when `destroy` is in the `only` list.

### Suggested implementation for `resource()`

After the existing destroy route registration block, add:

```php
if (in_array(self::OPTIONS_METHOD_DESTROY, $only)) {
    $group->delete($path, $controller . '@bulkDestroy', [], 'bulkDestroy')
        ->summary(function () use ($resourceDefinitionFactory) {
            $entityName = $resourceDefinitionFactory->getDefault()->getEntityName(true);
            return 'Bulk delete ' . $entityName;
        });
}
```

### Suggested implementation for `childResource()`

After the existing destroy route registration block, add:

```php
if (in_array(self::OPTIONS_METHOD_DESTROY, $only)) {
    $bulkDestroyRoute = $group->delete($parentPath, $controller . '@bulkDestroy', [], 'bulkDestroy')
        ->summary(function () use ($resourceDefinitionFactory) {
            $entityName = $resourceDefinitionFactory->getDefault()->getEntityName(true);
            return 'Bulk delete ' . $entityName;
        });

    $this->addIdParameterToRoutePath($bulkDestroyRoute, $parentId, $options);
}
```
