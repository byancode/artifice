<?php
namespace Byancode\Artifice\Modificators;

class ClassModifier
{
    public $file;
    public $content;
    public function __construct(string $file)
    {
        $this->file = $file;
        $this->content = file_get_contents($file);
    }
    public function setExtends(string $class, string $as = null)
    {
        $this->addClass($class, $as);
        $name = $this->getClassName($class);
        if (empty($as) === true) {
            $as = $name;
        }
        $this->replace('/( extends\s+)(\w+)/', "$1$as");
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
    public function addClass(string $class, string $as = null)
    {
        if (isset($as) === true) {
            $class = "$class as $as";
        }
        return $this->replace("/\vuse $class;(?:[ \r]+)?/", '', 1)
            ->replace('/(\vuse )/', "$1$class;$1", 1);
    }
    public function addTrait(string $class, string $as = null)
    {
        $this->addClass($class, $as);
        $name = $this->getClassName($class);
        if (empty($as) === true) {
            $as = $name;
        }
        $this->replace('/(\v\s{2,}use [^\;]+)/s', "$1, $as");
        return $this;
    }
    public function save()
    {
        file_put_contents($this->file, $this->content);
    }

    public function append(string $content)
    {
        return $this->replace('/(\}\s+)$/s', "$content\n\n$1");
    }

    public function insertAfterTrait(string $content)
    {
        return $this->replace('/(\vclass .* use[^\;]+;)/s', "$1\n\n$content");
    }
}