<?php

namespace Basilicom\PathFormatterBundle\Fixtures;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;

class ProductMock extends Concrete
{
    const PATH = '/images/';
    const FILENAME = 'some-file.png';

    private $image;

    public function getPrice(): int
    {
        return 10;
    }

    public function getUnit(): string
    {
        return '€';
    }

    public function setImage(Asset\Image $image)
    {
        $this->image = $image;
    }

    public function getImage(): Asset\Image
    {
        return $this->image;
    }
}
