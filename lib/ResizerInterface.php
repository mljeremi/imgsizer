<?php
namespace Utils\Resize;


interface ResizerInterface
{
    public function resize($fid, $sizes) : string;
}