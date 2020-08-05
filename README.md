# Basilicom Extended Path Formatter Bundle for Pimcore

### How it works
This plugin provides a custom Pimcore path formatter as well as a simple, yaml-file-based configuration for the printed path for multi-relation-fields.  

### Usage
Add ``@Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter`` to the Formatter-Field in the relation-fieldType.  
**Note:** The ``@`` is important, as the formatter is registered as a service, including dependency injection.

Configure the pathformatter in your ``config.yml`` by using the first-level class-property-names in camelcase and between ``{`` and ``}``. The key between the braces will be used to call data object getters.  
If no getter exists for the property, the pattern-part will stay untouched.

As soon as a class property is a ``Pimcore\ModelAsset\Image`` it will be visible as small preview in the relation-list.

#### Example
app/config/config.yml
```
basilicom_path_formatter:
  pattern: "{logo} {identifier}"
```

var/classes/DataObject/Client.php
```
class Client extends Concrete
{
    protected $o_classId = "1";
    protected $o_className = "Client";
    protected $identifier;
    protected $logo;
    
    /**
     * Get identifier
     *
     * @return string
     */
    public function getIdentifier()
    {
        // ...
    }
    
    /**
     * Get logo
     *
     * @return \Pimcore\Model\Asset\Image
     */
    public function getLogo()
    {
        // ...
    }
}
```

### Todos
- allow different patterns for different classes like e.g.:
  ```
  basilicom_path_formatter:
    pattern: 
        Pimcore\Model\DataObject\Client: "{logo} {identifier}"
        Pimcore\Model\DataObject\Product: "{price}{unit}"
  ```
- add button to relation-fields to prefill the formatter class
- implement helper methods for simple string modifications
- allow output of nested properties like ``{productImage.fullPath}``
