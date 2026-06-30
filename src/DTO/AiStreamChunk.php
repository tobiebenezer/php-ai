<?php

namespace Tobiebenezer\Ai\DTO;

class AiStreamChunk
{
    const TYPE_TEXT = 'text';
    const TYPE_TOOL_CALL = 'tool_call';
    const TYPE_DONE = 'done';

    public $type;
    public $content;
    public $metadata;

    public function __construct($type, $content = null, array $metadata = [])
    {
        $this->type = $type;
        $this->content = $content;
        $this->metadata = $metadata;
    }

    public static function text($content, array $metadata = [])
    {
        return new static(self::TYPE_TEXT, $content, $metadata);
    }

    public static function toolCall($content = null, array $metadata = [])
    {
        return new static(self::TYPE_TOOL_CALL, $content, $metadata);
    }

    public static function done(array $metadata = [])
    {
        return new static(self::TYPE_DONE, null, $metadata);
    }
}
