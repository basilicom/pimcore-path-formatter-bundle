# Basilicom Extended Path Formatter Bundle for Pimcore

## Usecase / Summary
If you want to display specific informations of a dataObject when it's listed in a relation-field, you can use this plugin to easily configure a display-pattern.  
Without creating a new PathFormatter in your project to displaying the name, price and currency of a product-class you can just configure it like:
```
# app/config/config.yml
basilicom_path_formatter:
  pattern: 
    Pimcore\Model\DataObject\Product: "{name} {price}{currency}"   
```
Or you might want to define a specific pattern for a product in the relation-field of a specific class.
```
# app/config/config.yml
basilicom_path_formatter:
  pattern: 
    Pimcore\Model\DataObject\Product: "{name} {price}{currency}" # global product format
    Pimcore\Model\DataObject\ProductList::products: 
       patternOverwrites:
          Pimcore\Model\DataObject\Product: "#{id} {name}"
```
While the product will be formatted like ``Sneakers 19.99EUR`` in all relation-fields with the formatter, the ProductList-Class will show them in like ``#13 - Sneakers`` 


----------

## Version information

| Bundle Version | PHP  | Pimcore |
|----------------|------|---------|
| &lt; 2.0       | ^7.3 | ^6.0    |
| &gt;= 2.0      | ^8.1 | ^10.0   |
| &gt;= 3.0      | ^8.1 | ^11.0   |

## Installation
Install the bundle using ``composer require basilicom/pimcore-path-formatter-bundle``  

Execute ``bin/console pimcore:bundle:enable BasilicomPathFormatterBundle``  
**or**  
add the following lines to `AppKernel::registerBundlesToCollection()` (**recommended**)
```php
if (class_exists('\\Basilicom\\PathFormatterBundle\\BasilicomPathFormatterBundle')) {
      $collection->addBundle(new \Basilicom\PathFormatterBundle\BasilicomPathFormatterBundle);
}
```

## Configuration
1. Add the ``basilicom_path_formatter`` key to your Pimcore ``app/config/config.yml``
2. Configure a pattern by adding the full qualified dataObject class-name as key the pattern-string as value.  
   Use class-property-names, accessible by public getter methods, surrounded by curly brackets.  
   This also enables you to reference basic Pimcore ``Concrete``/``AbstractObject`` methods like: 
    - ``fullPath`` for ``\Pimcore\Model\DataObject\AbstractObject::getFullPath())`` 
    - ``className`` for ``\Pimcore\Model\DataObject\AbstractObject::getClassName())``
    - ...  
    
    **Example:**
    ```
    basilicom_path_formatter:
      pattern: 
        # output of e.g. "Product: Sneakers (/dataObject/Products/Sneakers)"
        
        Pimcore\Model\DataObject\Product: "Product: {name} ({fullPath})" 
    ```
    **Note:** If no getter exists for the property, the placeholder will stay untouched.

3. Add ``@Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter`` to the Formatter-Field in the relation-fieldType.  
   **Note:** The ``@`` is important, as the formatter is registered as a service, including dependency injection.
    

## Advanced configuration
### Contextual pattern overwrites 
It is possible to configure a context-based pattern, so that a dataObject in a relation-field of a specific class will be formatted differently.  

**Example:**
```yaml
# app/config/config.yml
basilicom_path_formatter:
  pattern: 
    # global product format
    Pimcore\Model\DataObject\Product: "{name} {price}{currency}"

    
    # format-overwrite in the context of a ProductList for the relation-field "products"
    Pimcore\Model\DataObject\ProductList::products: 
       patternOverwrites:
          # both classes are extending the Pimcore\Model\DataObject\Product so we still can overwrite the "Product" pattern.
          Pimcore\Model\DataObject\BasicProduct: "#{id} {name}"
          Pimcore\Model\DataObject\PremiumProduct: "#{id} {name} (premium-only!)"
```

While the product will be formatted like ``Sneakers 19.99EUR`` in all relation-fields, the ProductList-Class will show them like ``#13 - Sneakers`` or ``#13 - Sneakers (premium-only!)``, based on the product class.

### Formatting documents and assets
```yaml
basilicom_path_formatter:
  pattern:
    Pimcore\Model\Asset: "{id} {key}"
    Pimcore\Model\Document: "{id} {key}"

    Pimcore\Model\DataObject\Car::files:
      patternOverwrites:
        Pimcore\Model\Asset: "{key}"
        Pimcore\Model\Document: "{key}"
```

## Additional features

### Toggle inherited values in DataObjects
Inherited values from DataObjects will be used by default. In order to avoid this, just disable the configuration:
```
basilicom_path_formatter:
  enable_inheritance: true|false # default true
```

### Showing images
As soon as you reference a property in the pattern, which is a ``Pimcore\ModelAsset\Image``, it will be rendered as small preview in the relation-list.
This feature can be disabled by setting the value of ``enable_asset_preview`` to ``false``.

```
basilicom_path_formatter:
  enable_asset_preview: true|false # default true
```

-------

**Author:** Alexander Heidrich (Basilicom GmbH)  
**License:** GPL v3
