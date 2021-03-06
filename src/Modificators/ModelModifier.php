<?php
namespace Byancode\Artifice\Modificators;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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
    public $data = [
        '__model' => [
            'autoIncrement' => null,
        ],
        '__build' => [
            'observe' => true,
            'policy' => true,
            'trait' => true,
        ],
    ];

    public function setDefaultData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    public function setModel(string $name, array $data)
    {
        $this->name = $name;
        $this->data = array_merge_recursive($this->data, $data);
        return $this;
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
    public function getStub(string $name)
    {
        return file_get_contents($this->stubPath($name));
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
        $path = app_path('Policies');
        $file = app_path("Policies/{$this->name}Policy.php");

        if (file_exists($file)) {
            if ($this->bool('__build.policy') === false) {
                unlink($file);
            }
            return;
        }

        !is_dir($path) && mkdir($path, 0777);

        $name = strtolower($this->name);
        $name = $name === 'user' ? 'userModel' : $name;

        $content = file_get_contents($this->stubPath('policy.class'));
        $content = str_replace('{{ model }}', $this->name, $content);
        $content = str_replace('{{ name }}', $name, $content);

        file_put_contents($file, $content);
    }

    public function aditionalTraits()
    {
        if ($this->bool('__class.traits')) {
            $data = $this->get('__class.traits') ?? [];
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
        if ($this->bool('__model.casts')) {
            $data = $this->get('__model.casts') ?? [];
            $dir = config('blueprint.models_namespace');
            if (isset($dir)) {
                $class = "App\\{$dir}\\{$this->name}";
            } else {
                $class = "App\\{$this->name}";
            }
            $casts = $class::make()->getCasts();
            if (is_array($data)) {
                $values = [];
                foreach (array_merge($casts, $data) as $key => $value) {
                    $content = $this->getStub('model.array.key.value');
                    $value = var_export($value, true);
                    $content = str_replace('{{ key }}', $key, $content);
                    $content = str_replace('{{ value }}', trim($value), $content);
                    $values[] = $content;
                }

                if ($this->matchArray('casts') === false) {
                    $content = $this->getStub('model.casts');
                    $content = str_replace('{{ values }}', join("\n", $values), $content);
                    $this->insertAfterTrait($content);
                } else {
                    $this->setArray('casts', join("\n", $values));
                }
            }
        }
    }
    public function additionalDates()
    {
        if ($this->bool('__model.dates')) {
            $data = $this->get('__model.dates') ?? [];
            if (is_array($data)) {
                $values = [];
                foreach ($data as $key => $value) {
                    $content = $this->getStub('model.array.key.value');
                    $value = var_export($value, true);
                    $content = str_replace('{{ key }}', $key, $content);
                    $content = str_replace('{{ value }}', trim($value), $content);
                    $this->addInArray('dates', $content);
                    $values[] = $content;
                }

                if ($this->matchArray('dates') === false) {
                    $content = $this->getStub('model.dates');
                    $content = str_replace('{{ key }}', $key, $content);
                    $content = str_replace('{{ values }}', join("\n", $values), $content);
                    $this->insertAfterTrait($content);
                }
            }
        }
    }
    public function additionalHidden()
    {
        if ($this->bool('__model.hidden')) {
            $data = $this->get('__model.hidden') ?? '';
            $data = preg_split('/\W+/', strval($data));
            if (is_array($data)) {
                $values = [];
                foreach ($data as $key => $value) {
                    $content = $this->getStub('model.array.value');
                    $content = str_replace('{{ value }}', strval($value), $content);
                    $this->addInArray('hidden', $content);
                    $values[] = $content;
                }

                if ($this->matchArray('hidden') === false) {
                    $content = $this->getStub('model.hidden');
                    $content = str_replace('{{ values }}', join("\n", $values), $content);
                    $this->insertAfterTrait($content);
                }
            }
        }
    }
    public function additionalAppends()
    {
        if ($this->bool('__model.appends')) {
            $data = $this->get('__model.appends') ?? '';
            $data = preg_split('/\W+/', strval($data));
            if (is_array($data)) {
                $values = [];
                foreach ($data as $key => $value) {
                    $content = $this->getStub('model.array.value');
                    $content = str_replace('{{ value }}', strval($value), $content);
                    $this->addInArray('appends', $content);
                    $values[] = $content;
                }

                if ($this->matchArray('appends') === false) {
                    $content = $this->getStub('model.appends');
                    $content = str_replace('{{ values }}', join("\n", $values), $content);
                    $this->insertAfterTrait($content);
                }
            }
        }
    }
    public function defineAutoIncrement()
    {
        $value = $this->get('__model.autoIncrement');

        if (is_bool($value) === false) {
            return;
        }
        $content = $this->getStub('model.autoincrement');
        $content = str_replace('{{ value }}', $value ? 'true' : 'false', $content);
        $this->insertAfterTrait($content);
    }
    public function matchArray(string $name)
    {
        return $this->match("/protected \W$name = \[/s");
    }
    public function addInArray(string $name, string $content)
    {
        return $this->replace("/(protected \W$name = \[)/s", "$1\n$content");
    }
    public function setArray(string $name, string $content)
    {
        return $this->replace("/([ \r\t]+)(protected \W$name = \[)([^\]]+)?/s", "$1$2\n$content\n$1");
    }

    public function migrationIndexes()
    {
        if ($this->bool('__index')) {
            $data = $this->get('__index') ?? [];
            if (is_array($data) && Arr::isAssoc($data) === false) {
                $model = Str::plural($this->name, 2);
                $table = strtolower($model);
                $migration = $this->getStub('migration.class');
                $values = [];
                foreach ($data as $index) {
                    if (is_array($index) && Arr::isAssoc($index)) {
                        foreach ($index as $type => $attrs) {
                            $attrs = preg_split('/\W+/', strval($attrs));
                            $content = $this->getStub('migration.index');
                            $content = str_replace('{{ type }}', $type, $content);
                            $content = str_replace('{{ name }}', Str::random(10), $content);
                            $items = [];
                            foreach ($attrs as $value) {
                                $item = $this->getStub('migration.array.value');
                                $item = str_replace('{{ value }}', $value, $item);
                                $items[] = $item;
                            }
                            $content = str_replace('{{ values }}', join("\n", $items), $content);
                            $values[] = $content;
                        }
                    }
                }
                $migration = str_replace('{{ model }}', $model, $migration);
                $migration = str_replace('{{ table }}', $table, $migration);
                $migration = str_replace('{{ content }}', join("\n", $values), $migration);

                $filename = date('Y_m_d_u') . "_create_{$table}_indexes.php";
                $path = base_path('database/migrations');

                !is_dir($path) && mkdir($path, 0777, true);
                $file = base_path("database/migrations/$filename");

                file_put_contents($file, $migration);
            }
        }
    }

    public function save()
    {
        if ($this->isAuth()) {
            $this->setExtends('Illuminate\Foundation\Auth\User', 'Authenticatable');
            $this->addTrait('Illuminate\Notifications\Notifiable');
        } else if ($this->isPivot()) {
            $this->setExtends('Illuminate\Database\Eloquent\Relations\Pivot');
        }
        $this->migrationIndexes();
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