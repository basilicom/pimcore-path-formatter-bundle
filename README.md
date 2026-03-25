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

| Bundle Version | PHP  | Pimcore |
|----------------|------|---------|
| ^1.0           | ^7.3 | ^6.0    |
| ^2.0           | ^8.0 | ^10.0   |
| ^3.0           | ^8.1 | ^11.0   |

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

### Inheritance in DataObjects

By default, inherited values are used when resolving placeholders. You can disable this globally:

```yaml
basilicom_path_formatter:
  enable_inheritance: false # default: true
```

### Asset Previews

If a referenced property resolves to a `Pimcore\Model\Asset\Image`, the bundle automatically renders a small preview thumbnail in the relation list.

```yaml
basilicom_path_formatter:
  enable_asset_preview: true # default: true
  pattern:
    Pimcore\Model\DataObject\Product: "{mainImage} {name}"
```

If the target element itself is an `Asset\Image`, a preview is also prepended to the pattern.

## Usage in Pimcore Admin

To apply the formatter to a field:

1. Open your **Class Definition**.
2. Select a relation field (e.g., Many-to-Many Relation).
3. In the **Formatter** input field, enter the service ID:
   `@Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter`

-------

**Author:** Alexander Heidrich (Basilicom GmbH)  
**License:** GPL v3

