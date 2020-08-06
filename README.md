# Basilicom Extended Path Formatter Bundle for Pimcore

### Usecase
If you want to display specific informations of a dataObject when it's listed in a relation-field, you can use this plugin to configure a pattern of data.
E.g. displaying the name, price and currency of a product could realise by configuring:
```
basilicom_path_formatter:
  pattern: 
    Pimcore\Model\DataObject\Product: "{name} {price}{currency}"    # specific dataObject pattern (overwrites generic one)
```

### Installation and configuration
1. Install the bundle using ``composer require basilicom/path-formatter-bundle 1.0``
2. Execute ``bin/console pimcore:bundle:enable BasilicomPathFormatterBundle``
3. Add the following config snippet to your Pimcore ``app/config/config.yml``
   ```
   basilicom_path_formatter:
     pattern: 
   ```
4. Configure a pattern by adding the full qualified dataObject class-name as key the pattern-string as value.  
   Use class-property-names, accessible by public getter methods, surrounded by curly brackets.  
   This also enables you to reference basic Pimcore ``Concrete``/``AbstractObject`` methods like: 
    - ``fullPath`` for ``\Pimcore\Model\DataObject\AbstractObject::getFullPath())`` 
    - ``className`` for ``\Pimcore\Model\DataObject\AbstractObject::getClassName())``
    - ...  
    
   **Note:** If no getter exists for the property, the placeholder will stay untouched.
5. Add ``@Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter`` to the Formatter-Field in the relation-fieldType.  
   **Note:** The ``@`` is important, as the formatter is registered as a service, including dependency injection.


#### Showing images

As soon the value of a class property is a ``Pimcore\ModelAsset\Image`` it will be rendered as small preview in the relation-list.

### Example
Product class ``var/classes/DataObject/Product.php``
```
class Product extends Concrete
{
    protected $o_classId = "1";
    protected $o_className = "Product";
    protected $name;
    protected $price;
    protected $currency;
    
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
     * Get currency
     *
     * @return string
     */
    public function getCurrency()
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
    Pimcore\Model\DataObject\Product: "{name} {price}{currency}"    # specific dataObject pattern (overwrites generic one)
    Pimcore\Model\DataObject\Concrete: "{fullPath}"             # generic pattern
```

### Todos
- overwrite patterns by adding a ``context`` configuration
    - based on class
    - based on class and fieldname
- implement helper methods for simple string modifications
