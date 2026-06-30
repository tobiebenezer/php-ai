<?php

namespace Tobiebenezer\Ai\DTO;

class AiToolResult
{
    public $id;
    public $name;
    public $result;

    public function __construct($id, $name, array $result)
    {
        $this->id = $id;
        $this->name = $name;
        $this->result = $result;
    }

    public function content()
    {
        return json_encode($this->result);
    }
}
