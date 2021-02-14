<?php
namespace Byancode\Artifice\Modificator;

class ClassModifier
{
    public $file;
    public $content;
    public function __contructor(string $file)
    {
        $this->file = $file;
        $this->content = file_get_contents($file);
    }
    public function setExtends(string $class)
    {
        $this->addClass($class);
        $name = $this->getClassName($class);
        $this->replace('/ extends\s+(\w+)/', "extends $name");
        return $this;
    }
    public function match(string $regexp)
    {
        preg_match($regexp, $this->content, $match);
        return empty($match) === false;
    }
    public function replace(string $regexp, string $value, int $limit = -1)
    {
        $this->content = preg_replace($regexp, $value, $this->content, $limit);
        return $this;
    }
    public function getClassName(string $class)
    {
        $parts = explode('\\', $class);
        return end($parts);
    }
    public function addClass(string $class)
    {
        return $this->replace('/(\vuse )/', "$1$class$1", 1);
    }
    public function save()
    {
        file_put_contents($this->file, $this->content);
    }
}