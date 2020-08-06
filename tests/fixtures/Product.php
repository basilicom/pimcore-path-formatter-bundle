<?php

namespace Pimcore\Model\DataObject;

use Pimcore\Model\Asset;

class Product extends Concrete
{
    private $image;

    protected $o_path = '/dataObjects/';
    protected $o_key = 'product';

    public function getName(): string
    {
        return 'Sneakers';
    }

    public function getCountryIso(): string
    {
        return 'de';
    }

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
