# Basilicom Extended Path Formatter Bundle for Pimcore

## Usecase / Summary

If you want to display specific information of a DataObject when it's listed in a relation-field (Many-to-Many, Many-to-One, Advanced Many-to-Many, etc.), you can use this bundle to easily configure a display pattern.  

Instead of creating a new PathFormatter PHP class for every specific display requirement (e.g., showing the name, price, and currency of a product), you can simply define patterns in your configuration.

```yaml
# config/packages/basilicom_path_formatter.yaml
basilicom_path_formatter:
  pattern: 
    Pimcore\Model\DataObject\Product: "{name} {price}{currency}"   
```

You can also define specific pattern overwrites for a class when it is referenced in a specific field of another class:

```yaml
# config/packages/basilicom_path_formatter.yaml
basilicom_path_formatter:
  pattern: 
    Pimcore\Model\DataObject\Product: "{name} {price}{currency}" # global product format
    Pimcore\Model\DataObject\ProductList::products: 
       patternOverwrites:
          Pimcore\Model\DataObject\Product: "#{id} {name}"
```

While the product will be formatted like `Sneakers 19.99EUR` in most relation-fields, the `products` field in the `ProductList` class will show them like `#13 Sneakers`.

----------

## Version information

| Bundle Version | PHP           | Pimcore        |
|----------------|---------------|----------------|
| ^1.0           | ^7.3          | ^6.0           |
| ^2.0           | ^8.0          | ^10.0          |
| ^3.0           | ^8.1          | ^11.0          |
| ^4.0           | ^8.1 / ^8.3   | ^11.0 \|\| ^12.0 |

Works with the classic admin UI as well as with Pimcore Studio (via `pimcore/studio-backend-bundle`).
Pimcore 12 itself requires PHP 8.3 or 8.4.

## Installation

1. Install the bundle using composer:
   ```bash
   composer require basilicom/pimcore-path-formatter-bundle
   ```

2. Enable the bundle:
   - **Pimcore >= 10:** The bundle should be automatically registered. If not, add it to `config/bundles.php`.
   - **Pimcore < 10:** Add the following to `AppKernel::registerBundlesToCollection()`:
     ```php
     if (class_exists('\\Basilicom\\PathFormatterBundle\\BasilicomPathFormatterBundle')) {
           $collection->addBundle(new \Basilicom\PathFormatterBundle\BasilicomPathFormatterBundle);
     }
     ```

## Configuration

1. Create a configuration file (e.g., `config/packages/basilicom_path_formatter.yaml`).
2. Configure your patterns using the full qualified class names as keys.

### Basic Configuration

Use curly brackets `{}` to reference class properties. Any property accessible via a public getter (e.g., `{name}` calls `getName()`) can be used.

```yaml
basilicom_path_formatter:
  pattern: 
    # Output example: "Product: Sneakers (/dataObject/Products/Sneakers)"
    Pimcore\Model\DataObject\Product: "Product: {name} ({fullPath})" 
```

### Nested Properties

The bundle supports nested property access using dot notation. For example, if a Product has a Category, and you want to show the category name:

```yaml
basilicom_path_formatter:
  pattern: 
    Pimcore\Model\DataObject\Product: "{name} (Category: {category.name})" 
```
*Note: This will call `$product->getCategory()->getName()`.*

### Contextual Pattern Overwrites

You can override patterns based on the context (the parent object and the specific field).

```yaml
basilicom_path_formatter:
  pattern:
    # Global default
    Pimcore\Model\DataObject\Product: "{name}"

    # Overwrite when a Product is shown in the 'featuredProducts' field of a Category object
    Pimcore\Model\DataObject\Category::featuredProducts:
      patternOverwrites:
        Pimcore\Model\DataObject\Product: "FEATURED: {name} ({price})"
```

### Formatting Documents and Assets

The formatter is not limited to DataObjects; it also works for Assets and Documents.

```yaml
basilicom_path_formatter:
  pattern:
    Pimcore\Model\Asset: "{id} {filename}"
    Pimcore\Model\Document: "{id} {key}"
```

## Additional Features

### Optional Parts

Anything wrapped in `[[ … ]]` disappears when *all* placeholders inside it are empty. Without it,
a missing value leaves punctuation behind (`Sneakers ( )`):

```yaml
basilicom_path_formatter:
  pattern:
    Pimcore\Model\DataObject\Product: "{name}[[ ({price} {currency})]]"
```

`Sneakers (19.99 EUR)` when the price is set, `Sneakers` when it is not. Single brackets stay
literal, groups are not nested.

### Expressions

`{{ … }}` evaluates a [Symfony expression](https://symfony.com/doc/current/reference/formats/expression_language.html)
with the related element available as `element` and the editor's language as `locale`.
Use it for formatting; use `{…}` for plain values.

```yaml
basilicom_path_formatter:
  pattern:
    Pimcore\Model\DataObject\Product: "{name} — {{ number(element.getPrice(), 2) }} {{ upper(element.getCurrency()) }}"
```

Note that expressions read **methods**, not properties: `element.getPrice()`, not `element.price`.
Pimcore's properties are protected, so property syntax would silently yield nothing.

These functions are available:

| Function | Example |
|---|---|
| `number(value, decimals = 2)` | `number(element.getPrice(), 2)` → `19,99` (formatted for the editor's locale) |
| `date(value, format = 'd.m.Y')` | `date(element.getCreationDate(), 'Y-m-d')` |
| `upper(value)` / `lower(value)` / `trim(value)` | `upper(element.getKey())` |
| `truncate(value, length, suffix = '…')` | `truncate(element.getName(), 40)` |
| `trans(key, domain = 'admin')` | `trans('product.label')` |

Patterns are checked for syntax errors when the container is built, so a broken expression fails
the deployment instead of the editor's grid. An expression that fails at runtime (a `null` in the
middle of a call chain, for example) renders as empty and is logged.

### Translations

`{@some.key}` inserts an admin translation in the language of the logged-in editor. The same is
available inside expressions as `trans('some.key')`.

```yaml
basilicom_path_formatter:
  pattern:
    Pimcore\Model\DataObject\Product: "{@product.label}: {name}"
```

### Default Pattern

`default_pattern` is used for every element that no other pattern matches — DataObjects, Assets
and Documents alike.

```yaml
basilicom_path_formatter:
  default_pattern: "{key}"
```

### Pattern Precedence

When several patterns match a target, the bundle picks:

1. a contextual overwrite (`SourceClass::fieldName`) matching the current source object and field,
2. otherwise the pattern whose class is the most specific one the target is an instance of,
3. otherwise `default_pattern`.

So with `Product extends AbstractProduct`, a pattern configured for `Product` always wins over
one configured for `AbstractProduct`, regardless of the order in your YAML file.

### Inheritance in DataObjects

By default, inherited values are used when resolving placeholders:

```yaml
basilicom_path_formatter:
  enable_inheritance: false # default: true
```

### Caching

Formatted paths are cached in the Pimcore cache and tagged with every element that was
touched while resolving a pattern — the target itself and everything reached via dot
notation. Saving any of those elements invalidates the entry.

```yaml
basilicom_path_formatter:
  enable_cache: false # default: true
```

This matters in practice: both the classic UI and Pimcore Studio request formatted paths
per relation field *instance*, which means one HTTP request per FieldCollection entry.
The cache turns those repeated requests into cache hits instead of repeated object loads.

One limitation: `{…}` placeholders register a cache tag for every element they traverse, but
`{{ … }}` expressions cannot. If an expression reaches a *different* element, its value is only
refreshed when the target itself changes.

### Skipping Containers

If relation fields in a specific container are too expensive to format, exclude the container
instead of the field:

```yaml
basilicom_path_formatter:
  exclude_container_types: ['fieldcollection'] # object, localizedfield, objectbrick, fieldcollection, block
```

Those relations then show their plain path, and no element is loaded for them at all.

### Debugging

```bash
bin/console basilicom:path-formatter:debug <targetId> --source=<objectId> --field=<fieldName>
```

Prints the pattern that matches, the rendered result, the cache tags, and what each placeholder
resolved to — including the ones that came out empty.

### Asset Previews

If a referenced property resolves to a `Pimcore\Model\Asset\Image`, the bundle renders the
`pimcore-system-treepreview` thumbnail of it in the relation list.

```yaml
basilicom_path_formatter:
  enable_asset_preview: true # default: true
  pattern:
    Pimcore\Model\DataObject\Product: "{mainImage} {name}"
```

If the target element itself is an `Asset\Image`, a preview is also prepended to the pattern.

### Escaping

Resolved property values are HTML-escaped before they are inserted into the pattern, so
editor content cannot inject markup into the admin UI. The pattern itself and the asset
preview generated by this bundle are the only raw HTML in the output.

## Usage in Pimcore Admin

To apply the formatter to a field:

1. Open your **Class Definition**.
2. Select a relation field (e.g., Many-to-Many Relation).
3. In the **Formatter** input field, enter the service ID:
   `@Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter`

-------

**Author:** Alexander Heidrich (Basilicom GmbH)  
**License:** MIT — see [LICENSE](LICENSE)

