<?php
namespace Byancode\Artifice\Modificators;

class PivotModifier extends ModelModifier
{
    public static function create(string $name, array $data)
    {
        $file = self::getPath($name);
        $modifier = new static($file);
        $modifier->setModel($name, $data);
        $modifier->set('__model.pivot', true);
        $modifier->save();
    }
    public static function createMany(array $data)
    {
        foreach ($data as $key => $value) {
            static::create($key, $value, $pivot);
        }
    }
}