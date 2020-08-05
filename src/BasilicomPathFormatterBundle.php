<?php

namespace Basilicom\PathFormatterBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;

class BasilicomPathFormatterBundle extends AbstractPimcoreBundle
{
    public function getJsPaths()
    {
        return [
            '/bundles/basilicompathformatter/js/pimcore/startup.js'
        ];
    }
}