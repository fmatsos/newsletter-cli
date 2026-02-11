<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Domain\Model;

final readonly class Category
{
    public function __construct(
        private string $id,
        private string $label,
        private string $headerBg,
        private string $headerColor,
        private string $headerGradient,
        private string $headerGradientEnd,
        private string $headerImage,
        private string $accentColor,
        private string $tagBg,
        private string $tagColor,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function headerBg(): string
    {
        return $this->headerBg;
    }

    public function headerColor(): string
    {
        return $this->headerColor;
    }

    public function headerGradient(): string
    {
        return $this->headerGradient;
    }

    public function headerGradientEnd(): string
    {
        return $this->headerGradientEnd;
    }

    public function headerImage(): string
    {
        return $this->headerImage;
    }

    public function accentColor(): string
    {
        return $this->accentColor;
    }

    public function tagBg(): string
    {
        return $this->tagBg;
    }

    public function tagColor(): string
    {
        return $this->tagColor;
    }
}
