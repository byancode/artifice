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
            static::create($key, $value);
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

    public function insertOberver()
    {
        $stub = $this->stubPath('function.observer');
        $content = file_get_contents($stub);
        $content = str_replace('{{ model }}', $this->name, $content);
        $this->append($content);
    }

    public function createObserver()
    {
        $path = app_path('Observers');
        $file = app_path("Observers/{$this->name}Observer.php");
        $fnpath = $this->stubPath('function.observer');

        if (file_exists($file)) {
            if ($this->bool('__build.observe') === false) {
                unlink($file);
            } else {
                $this->insertOberver();
            }
            return;
        }

        !is_dir($path) && mkdir($path, 0777);

        $content = file_get_contents($this->stubPath('observer'));
        $content = str_replace('{{ model }}', $this->name, $content);
        $content = str_replace('{{ variable }}', strtolower($this->name), $content);

        if ($this->bool('__build.observe')) {
            file_put_contents($file, $content);
            $this->insertOberver();
        }
    }

    public function createTrait()
    {
        $path = app_path('Traits');
        $file = app_path("Traits/{$this->name}Trait.php");

        if (file_exists($file)) {
            if ($this->bool('__build.trait') === false) {
                unlink($file);
            } else {
                $this->addTrait("App\\Traits\\{$this->name}Trait");
            }
            return;
        }

        !is_dir($path) && mkdir($path, 0777);

        $content = file_get_contents($this->stubPath('trait'));
        $content = str_replace('{{ model }}', $this->name, $content);

        if ($this->bool('__build.trait')) {
            $this->addTrait("App\\Traits\\{$this->name}Trait");
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

        file_put_contents($file, $content);
    }

    public function aditionalTraits()
    {
        if ($this->bool('__build.traits')) {
            $data = $this->get('__build.traits') ?? [];
            if (is_array($data)) {
                foreach ($data as $value) {
                    if (is_string($value)) {
                        $this->addTrait($value);
                    } elseif (is_array($value)) {
                        foreach ($value as $as => $class) {
                            $this->addTrait($class, $as);
                        }
                    }
                }
            }
        }
    }
    public function additionalCasts()
    {
        if ($this->bool('__build.casts')) {
            $data = $this->get('__build.casts') ?? [];
            if (is_array($data)) {
                $values = [];
                foreach ($data as $key => $value) {
                    $file = $this->stubPath('model.array.key.value');
                    $content = file_get_contents($file);
                    $value = var_export($value, true);
                    $content = str_replace('{{ value }}', trim($value), $content);
                    $values[] = $content;
                }

                $file = $this->stubPath('model.casts');
                $content = file_get_contents($file);
                $content = str_replace('{{ values }}', join("\n", $values), $content);
                $this->insertAfterTrait($content);
            }
        }
    }
    public function additionalDates()
    {
        if ($this->bool('__build.dates')) {
            $data = $this->get('__build.dates') ?? [];
            if (is_array($data)) {
                $values = [];
                foreach ($data as $key => $value) {
                    $file = $this->stubPath('model.array.key.value');
                    $content = file_get_contents($file);
                    $value = var_export($value, true);
                    $content = str_replace('{{ value }}', trim($value), $content);
                    $values[] = $content;
                }

                $file = $this->stubPath('model.dates');
                $content = file_get_contents($file);
                $content = str_replace('{{ values }}', join("\n", $values), $content);
                $this->insertAfterTrait($content);
            }
        }
    }
    public function additionalHidden()
    {
        if ($this->bool('__build.hidden')) {
            $data = $this->get('__build.hidden') ?? '';
            $data = preg_split('/\W+/', strval($data));
            if (is_array($data)) {
                $values = [];
                foreach ($data as $key => $value) {
                    $file = $this->stubPath('model.array.value');
                    $content = file_get_contents($file);
                    $content = str_replace('{{ value }}', strval($value), $content);
                    $values[] = $content;
                }

                $file = $this->stubPath('model.dates');
                $content = file_get_contents($file);
                $content = str_replace('{{ values }}', join("\n", $values), $content);
                $this->insertAfterTrait($content);
            }
        }
    }
    public function additionalAppends()
    {
        if ($this->bool('__build.appends')) {
            $data = $this->get('__build.appends') ?? '';
            $data = preg_split('/\W+/', strval($data));
            if (is_array($data)) {
                $values = [];
                foreach ($data as $key => $value) {
                    $file = $this->stubPath('model.array.value');
                    $content = file_get_contents($file);
                    $content = str_replace('{{ value }}', strval($value), $content);
                    $values[] = $content;
                }

                $file = $this->stubPath('model.appends');
                $content = file_get_contents($file);
                $content = str_replace('{{ values }}', join("\n", $values), $content);
                $this->insertAfterTrait($content);
            }
        }
    }
    public function defineAutoIncrement()
    {
        $file = $this->stubPath('model.autoincrement');
        $content = file_get_contents($file);
        $value = $this->bool('__model.autoIncrement') ? 'true' : 'false';
        $content = str_replace('{{ value }}', $value, $content);
        $this->insertAfterTrait($content);
    }

    public function save()
    {
        if ($this->isAuth()) {
            $this->setExtends('Illuminate\Foundation\Auth\User', 'Authenticatable');
            $this->addTrait('Illuminate\Notifications\Notifiable');
        } else if ($this->isPivot()) {
            $this->setExtends('Illuminate\Database\Eloquent\Relations\Pivot');
        }
        $this->additionalHidden();
        $this->additionalAppends();
        $this->additionalDates();
        $this->additionalCasts();
        $this->defineAutoIncrement();
        $this->aditionalTraits();
        $this->createObserver();
        $this->createPolicy();
        $this->createTrait();

        parent::save();
    }
}