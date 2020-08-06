<?php

namespace Basilicom\PathFormatterBundle\DependencyInjection\PathFormatter;

class Configuration
{
    private $enableAssetPreview;

    private $patternList;

    public function __construct(bool $enableAssetPreview, array $pattern)
    {
        $this->enableAssetPreview = $enableAssetPreview;
        $this->patternList = $pattern;
    }

    public function isAssetPreviewEnabled(): bool
    {
        return $this->enableAssetPreview;
    }

    public function getPatternList(): array
    {
        return $this->patternList;
    }
}
