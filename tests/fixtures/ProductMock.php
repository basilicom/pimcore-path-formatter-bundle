<?php

namespace Basilicom\PathFormatterBundle\Fixtures;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;

class ProductMock extends Concrete
{
    private $image;

    protected $o_path = '/dataObjects/';
    protected $o_key = 'product';

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
