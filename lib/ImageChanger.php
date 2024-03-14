<?php

namespace Utils\Resize;

abstract class ImageChanger
{
    abstract public function getChangerHandle($path): ImageChangerHandle;

    public function resize($path, $source, $destination)
    {
        $handler = $this->getChangerHandle($path);
        return $handler->resize($source, $destination);
    }
}