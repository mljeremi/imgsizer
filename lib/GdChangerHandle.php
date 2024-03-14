<?php


namespace Utils\Resize;


use Bitrix\Main\File\Image\Gd;
use Bitrix\Main\File\Image\Imagick;
use Bitrix\Main\File\Image\Rectangle;

class GdChangerHandle implements ImageChangerHandle
{
    public function __construct($path)
    {
        $this->file = $path;
        $this->engine = new Gd();
        $this->engine->setFile($this->file);
        $this->engine->load();
    }

    public function resize($source, $destination)
    {
        $this->isResize = $this->engine->resize($source, $destination);
    }

    public function convert($format)
    {
        // TODO: Implement convert() method.
    }

    public function process(): bool
    {
        // TODO: Implement process() method.
    }

    public function save($destination, $quantity = 95, $format = null) {
        $this->isSaved = $this->engine->save($destination, $quantity, $format);
    }

    public function getError()
    {
        return !$this->isResize || !$this->isSaved;
    }
}