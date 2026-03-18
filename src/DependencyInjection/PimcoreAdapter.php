<?php

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;

class PimcoreAdapter
{
    public function getConcreteById(int $id): ?DataObject
    {
        return DataObject::getById($id);
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
