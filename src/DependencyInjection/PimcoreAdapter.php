<?php

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\DataObject\Concrete;

class PimcoreAdapter
{
    public function getConcreteById(int $id): ?Concrete
    {
        return Concrete::getById($id);
    }

    public function getAssetById(int $id): ?Asset
    {
        return Asset::getById($id);
    }

    public function getDocumentById(int $id): ?Document
    {
        return Document::getById($id);
    }
}
