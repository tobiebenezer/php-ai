<?php

namespace Tobiebenezer\Ai\Tools;

class ToolDiscovery
{
    public function discover(array $paths, array $namespaces)
    {
        $tools = [];

        foreach (array_values($paths) as $index => $path) {
            $namespace = isset($namespaces[$index]) ? trim($namespaces[$index], '\\').'\\' : '';

            foreach ($this->files($path) as $file) {
                $class = $namespace.$this->className($file);

                if (class_exists($class) && is_subclass_of($class, \Tobiebenezer\Ai\Contracts\Tool::class)) {
                    $reflection = new \ReflectionClass($class);

                    if (! $reflection->isInstantiable()) {
                        continue;
                    }

                    $tools[] = $class;
                }
            }
        }

        return array_values(array_unique($tools));
    }

    protected function files($path)
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (substr($file->getFilename(), -4) === '.php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    protected function className($file)
    {
        return pathinfo($file, PATHINFO_FILENAME);
    }
}
