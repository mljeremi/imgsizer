<?php


namespace Utils\Resize;


class GdImageChanger extends ImageChanger
{

    public function __construct()
    {

    }

    public function getChangerHandle($path): ImageChangerHandle
    {
        $this->engine = new GdChangerHandle($path);
        return $this->engine;
    }

    public function save($destination, $quantity = 95, $format = null) {
        $this->engine->save($destination, $quantity, $format);
    }

    public function getError() {
        return $this->engine->getError();
    }

}