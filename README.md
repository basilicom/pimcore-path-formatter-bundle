# Basilicom Extended Path Formatter Bundle for Pimcore

### How it works
This plugin provides a custom Pimcore path formatter as well as a simple, yaml-file-based configuration for the printed path for multi-relation-fields.  

### Usage
Add ``@Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter`` to the Formatter-Field in the relation-fieldType.  
**Note:** The ``@`` is important, as the formatter is registered as a service, including dependency injection.

Add the following config to your ``app/config/config.yml``
```
basilicom_path_formatter:
  pattern: "{name} {price}{unit}"
```

Configure the the pattern by using the first-level class-property-names in camelcase and between ``{`` and ``}``. The key between the braces will be used to call data object getters.
This means, you also can use basic DataObject methods like: 
- ``fullPath`` for ``\Pimcore\Model\DataObject\AbstractObject::getFullPath())`` 
- ``className`` for ``\Pimcore\Model\DataObject\AbstractObject::getClassName())``
- ...

If no getter exists for the property, the placeholder will stay visible.

**Extra:** As soon the value of a class property is a ``Pimcore\ModelAsset\Image`` it will be rendered as small preview in the relation-list.

#### Example
Product class ``var/classes/DataObject/Product.php``
```
class Product extends Concrete
{
    protected $o_classId = "1";
    protected $o_className = "Product";
    protected $name;
    protected $price;
    protected $unit;
    
    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        // Sneakers 
    }
    
    /**
     * Get price
     *
     * @return float
     */
    public function getPrice()
    {
        // 39.99
    }
    
    /**
     * Get unit
     *
     * @return string
     */
    public function getUnit()
    {
        // EUR
    }
}
```

Wanted display-name in the relations list: ``Sneakers 39EUR``

Necessary config in ``app/config/config.yml``
```
basilicom_path_formatter:
  pattern: "{name} {price}{unit}"
```


### Todos
- allow different patterns for different classes like e.g.:
  ```
  basilicom_path_formatter:
    pattern: 
        Pimcore\Model\DataObject\Client: "{logo} {identifier}"
        Pimcore\Model\DataObject\Product: "{name} {price}{unit}"
  ```
- add button to relation-fields to prefill the formatter class
- implement helper methods for simple string modifications
- allow output of nested properties like ``{productImage.fullPath}``
