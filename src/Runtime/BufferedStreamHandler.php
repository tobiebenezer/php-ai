<?php

namespace Tobiebenezer\Ai\Runtime;

use Tobiebenezer\Ai\Contracts\StreamHandler;
use Tobiebenezer\Ai\DTO\AiStreamChunk;

class BufferedStreamHandler implements StreamHandler
{
    protected $chunks = [];

    public function handle(AiStreamChunk $chunk)
    {
        $this->chunks[] = $chunk;
    }

    public function chunks()
    {
        return $this->chunks;
    }

    public function text()
    {
        $text = '';

        foreach ($this->chunks as $chunk) {
            if ($chunk->type === AiStreamChunk::TYPE_TEXT) {
                $text .= $chunk->content;
            }
        }

        return $text;
    }
}
