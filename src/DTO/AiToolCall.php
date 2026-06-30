<?php

namespace Tobiebenezer\Ai\DTO;

class AiToolCall
{
    public $id;
    public $name;
    public $arguments;

    public function __construct($id, $name, array $arguments = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->arguments = $arguments;
    }
}
