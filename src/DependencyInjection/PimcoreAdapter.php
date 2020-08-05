<?php

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Pimcore\Model\DataObject\Concrete;

class PimcoreAdapter
{
    public function getConcreteById(int $id): ?Concrete
    {
        return Concrete::getById($id);
    }
}
