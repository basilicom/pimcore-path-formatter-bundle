# Basilicom Extended Path Formatter Bundle for Pimcore

### How it works
This plugin provides a custom Pimcore-Backend path formatter as well as a simple, yaml-file-based configuration for the shown path of dataObjects in multi-relation-fields.  

### Usage
Add ``@Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter`` to the Formatter-Field in the relation-fieldType.  
**Note:** The ``@`` is important, as the formatter is registered as a service, including dependency injection.

Add the following config to your ``app/config/config.yml``
```
basilicom_path_formatter:
  pattern: 
```

Add the full qualified class-name, that should be formatted, as key to the pattern list and define the pattern as value.
Configure the pattern placing class-property-names, accessible by public getter methods, between curly brackets.  
You also can reference basic dataObject methods like: 
- ``fullPath`` for ``\Pimcore\Model\DataObject\AbstractObject::getFullPath())`` 
- ``className`` for ``\Pimcore\Model\DataObject\AbstractObject::getClassName())``
- ...

```
basilicom_path_formatter:
  pattern: 
    Pimcore\Model\DataObject\Product: "{fullPath} - {price}{unit}"
    
    # the class should have a getPrice() and getUnit() method
    # getFullPath() is a basic method from the Pimcore Concrete 
```

If no getter exists for the property, the placeholder will stay untouched.

### Showing images

As soon the value of a class property is a ``Pimcore\ModelAsset\Image`` it will be rendered as small preview in the relation-list.

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
  pattern: 
    Pimcore\Model\DataObject\Product: "{name} {price}{unit}"    # specific dataObject pattern (overwrites generic one)
    Pimcore\Model\DataObject\Concrete: "{fullPath}"             # generic pattern
```

### Todos
- add button to relation-fields to prefill the formatter class
- implement helper methods for simple string modifications
