<?php
namespace Byancode\Artifice\Modificators;

class ModelModifier extends ClassModifier
{
    public static function getPath(string $model)
    {
        $path = config('blueprint.models_namespace', 'Models');
        return app_path("$path/$model.php");
    }
    public static function create(string $name, array $data)
    {
        $file = self::getPath($name);
        $modifier = new static($file);
        $modifier->setModel($name, $data);
        $modifier->save();
    }
    public static function createMany(array $data)
    {
        foreach ($data as $key => $value) {
            static::create($key, $value, $pivot);
        }
    }

    public $name;
    public $data = [];

    public function setModel(string $name, array $data)
    {
        $this->name = $name;
        $this->data = $data;
    }

    public function bool(string $keys)
    {
        return boolval(data_get($this->data, $keys, false));
    }
    public function has(string $keys)
    {
        return data_get($this->data, $keys) !== null;
    }
    public function get(string $keys)
    {
        return data_get($this->data, $keys);
    }
    public function set(string $keys, $value)
    {
        return data_set($this->data, $keys, $value);
    }
    public function stubPath(string $name)
    {
        return base_path("vendor/byancode/artifice/src/stubs/$name.stub");
    }

    public function isPivot()
    {
        return $this->bool('__model.pivot');
    }
    public function isAuth()
    {
        return $this->bool('__model.auth');
    }

    public function createObserver()
    {
        $path = app_path('Observers');
        $file = app_path("Observers/{$this->name}Observer.php");

        if (file_exists($file)) {
            if ($this->bool('__build.observe') === false) {
                unlink($file);
            }
            return;
        }

        !is_dir($path) && mkdir($path, 0777);

        $content = file_get_contents($this->stubPath('observer'));
        $content = str_replace('{{ model }}', $this->name, $content);
        $content = str_replace('{{ variable }}', strtolower($this->name), $content);

        if ($this->bool('__build.observe')) {
            file_put_contents($file, $content);
        }
    }

    public function createTrait()
    {
        $path = app_path('Traits');
        $file = app_path("Traits/{$this->name}Trait.php");

        if (file_exists($file)) {
            if ($this->bool('__build.trait') === false) {
                unlink($file);
            }
            return;
        }

        !is_dir($path) && mkdir($path, 0777);

        if ($this->bool('__build.observe')) {
            $stubFile = $this->stubPath('trait.observer');
        } else {
            $stubFile = $this->stubPath('trait');
        }

        $content = file_get_contents($stubFile);
        $content = str_replace('{{ model }}', $this->name, $content);

        if ($this->bool('__build.trait')) {
            file_put_contents($file, $content);
        }
    }

    public function createPolicy()
    {
        if ($this->bool('__model.auth') === false) {
            return;
        }

        $path = app_path('Policies');
        $file = app_path("Policies/{$this->name}Policy.php");

        if (file_exists($file)) {
            if ($this->bool('__build.policy') === false) {
                unlink($file);
            }
            return;
        }

        !is_dir($path) && mkdir($path, 0777);

        $content = file_get_contents($this->stubPath('policy'));
        $content = str_replace('{{ model }}', $this->name, $content);

        if ($this->bool('__build.policy')) {
            file_put_contents($file, $content);
        }
    }

    public function save()
    {
        if ($this->isAuth()) {
            $this->setExtends('Illuminate\Foundation\Auth\User', 'Authenticatable');
        } else if ($this->isPivot()) {
            $this->setExtends('Illuminate\Database\Eloquent\Relations\Pivot');
        }

        $this->createObserver();
        $this->createPolicy();
        $this->createTrait();

        parent::save();
    }
}