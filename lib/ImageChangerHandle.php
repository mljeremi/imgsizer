<?php


namespace Utils\Resize;


interface ImageChangerHandle
{
    public function resize($source, $destination);
    public function convert($format);
    public function process() : bool;
    public function getError();
}