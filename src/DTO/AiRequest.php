<?php

namespace Tobiebenezer\Ai\DTO;

class AiRequest
{
    public $messages;
    public $profile;
    public $user;
    public $metadata;
    public $options;

    public function __construct(array $attributes = [])
    {
        $this->messages = isset($attributes['messages']) ? $attributes['messages'] : [];
        $this->profile = isset($attributes['profile']) ? $attributes['profile'] : null;
        $this->user = isset($attributes['user']) ? $attributes['user'] : null;
        $this->metadata = isset($attributes['metadata']) ? $attributes['metadata'] : [];
        $this->options = isset($attributes['options']) ? $attributes['options'] : [];
    }

    public static function fromText($message, array $attributes = [])
    {
        $attributes['messages'] = [AiMessage::user($message)];

        return new static($attributes);
    }
}
