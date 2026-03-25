<?php

namespace Pimcore\Model\DataObject;

use Pimcore\Model\Asset;
use Pimcore\Model\Element\AbstractElement;

class Product extends Concrete
{
    private Asset\Image $image;
    protected ?AbstractElement $parent;

    public function setParent($parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getParent(): ?AbstractObject
    {
        return $this->parent instanceof AbstractObject ? $this->parent : null;
    }

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

    public function setImage(Asset\Image $image): void
    {
        $this->image = $image;
    }

    public function getImage(): Asset\Image
    {
        return $this->image;
    }
}
