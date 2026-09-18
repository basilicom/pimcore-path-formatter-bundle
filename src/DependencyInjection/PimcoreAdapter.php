<?php

/**
 * This source file is available under the terms of the MIT License.
 * Full copyright and license information is available in
 * LICENSE which is distributed with this source code.
 *
 * @copyright Copyright (c) Basilicom GmbH (https://basilicom.de)
 * @license   MIT
 */

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Pimcore\Cache;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;

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

    public function getElementById(string $type, int $id): ?ElementInterface
    {
        return Service::getElementById($type, $id);
    }

    public function loadFromCache(string $key): mixed
    {
        return Cache::load($key);
    }

    /** @param string[] $tags */
    public function saveToCache(string $key, string $value, array $tags): void
    {
        Cache::save($value, $key, $tags);
    }
}
