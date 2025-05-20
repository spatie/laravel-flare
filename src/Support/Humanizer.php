<?php

namespace Spatie\LaravelFlare\Support;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\StreamInterface;
use Spatie\FlareClient\Support\Humanizer as BaseHumanizer;

class Humanizer extends BaseHumanizer
{
    protected static function getSizeOfContents(mixed $contents): ?int
    {
        $size = parent::getSizeOfContents($contents);

        if ($size !== null) {
            return $size;
        }

        if ($contents instanceof StreamInterface || $contents instanceof UploadedFile || $contents instanceof File) {
            $size = $contents->getSize();

            return is_bool($size) ? null : $size;
        }

        return null;
    }
}
