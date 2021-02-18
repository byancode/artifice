<?php

namespace Byancode\Artifice\Commands;

use Blueprint\Blueprint;
use Byancode\Artifice\Modificators\ModelModifier;
use Byancode\Artifice\Modificators\PivotModifier;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class BuildCommand extends Command
{
    public $data = [];
    public $blueprint;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'artifice:build
    {--name=artifice : Artifice yaml file }
    {--draft=draft : The path to the draft file }
    {--default-route=api : routers available: api, web}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public $authServicePoviderName;
    public $authServicePoviderFile;
    public $authServicePoviderContent;
    public function __construct()
    {
        parent::__construct();
        $this->blueprint = app(Blueprint::class);
        $this->authServicePoviderName = "ArtificeAuthServiceProvider";
        $this->authServicePoviderFile = app_path("Providers/{$this->authServicePoviderName}.php");
        $this->authServicePoviderContent = $this->getStub('service.auth.class');
    }
    public function stubPath(string $name)
    {
        return base_path("vendor/byancode/artifice/src/stubs/$name.stub");
    }
    public function getStub(string $name)
    {
        return file_get_contents($this->stubPath($name));
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->yamlFiles();
        $this->cleanerFiles();
        $this->generateDraft();
        $this->generatePivots();
        $this->generatePolicies();
        $this->createCompileFile();
    }
    public function cleanerFiles()
    {
        $compiled = $this->compiledArray();
        $currents = $this->compilingArray();
        $models = array_unique(array_merge(
            $compiled['models'] ?? [],
            $currents['models'] ?? [],
        ));
        $removeds = array_diff(
            $compiled['models'] ?? [],
            $currents['models'] ?? [],
        );
        foreach ($models as $model) {
            $this->remove_model($model);
        }
        foreach ($removeds as $model) {
            foreach ($this->getModificableModelFiles($model) as $file) {
                echo $file . PHP_EOL;
                file_exists($file) ? unlink($file) : null;
            }
        }
        foreach ($this->getAllPivotFiles($compiled) as $file) {
            echo $file . PHP_EOL;
            file_exists($file) ? unlink($file) : null;
        }
    }
    public function remove_controller(string $name)
    {
        $file = app_path("Http/Controllers/$name.php");
        file_exists($file) ? unlink($file) : null;
    }
    public function remove_model(string $name)
    {
        foreach ($this->getModelFiles($name) as $file) {
            file_exists($file) ? unlink($file) : null;
        }
    }
    public function remove_pivot(string $name)
    {
        foreach ($this->getPivotFiles($name) as $file) {
            file_exists($file) ? unlink($file) : null;
        }
    }
    public function remove_migrations(string $name)
    {
        foreach ($this->getMigrationsByModel($name) as $file) {
            file_exists($file) ? unlink($file) : null;
        }
    }
    public function getAllPivotFiles(array $compiled)
    {
        $items = [];
        $pivots = array_merge(
            $compiled['pivots'] ?? [],
            $this->pivotNames()
        );
        $pivots = array_unique($pivots);
        foreach ($pivots as $models) {
            $models = preg_split('/\W+/', $models);
            foreach ($models as $model) {
                foreach ($models as $relation) {
                    $name = $this->getPivotName($model, $relation);
                    $items[] = $this->getPivotFiles($name);
                }
            }
        }
        return call_user_func_array('array_merge', $items);
    }
    public function getPivotFiles(string $name)
    {
        $table = Str::kebab($name);
        $table = str_replace('-', '_', $table);
        return array_merge(
            [
                $this->getPathModel($name),
                app_path("Traits/{$name}Trait.php"),
                app_path("Observers/{$name}Observer.php"),
            ],
            glob(base_path("database/migrations/*_create_{$table}_table.php")),
        );
    }
    public function getPathModel(string $name)
    {
        $path = config('blueprint.models_namespace', 'Models');
        return app_path("$path/$name.php");
    }
    public function getModificableModelFiles(string $name)
    {
        return [
            app_path("Traits/{$name}Trait.php"),
            app_path("Http/Controllers/{$name}Controller.php"),
            app_path("Observers/{$name}Observer.php"),
            app_path("Policies/{$name}Policy.php"),
        ];
    }
    public function getModelFiles(string $name)
    {
        $plural = (string) Str::of($name)->plural()->lower();
        $singular = (string) Str::of($name)->singular()->lower();
        return array_merge(
            [
                $this->getPathModel($name),
                base_path("database/factories/{$name}Factory.php"),
            ],
            glob(base_path("database/migrations/*_create_{$plural}_table.php")),
            glob(base_path("database/migrations/*_create_{$plural}_indexes.php")),
            glob(base_path("database/migrations/*_create_{$singular}_*_table.php")),
            glob(base_path("database/migrations/*_create_*_{$singular}_table.php")),
            glob(base_path("database/migrations/*_create_*_{$singular}able_table.php")),
        );
    }
    public function createCompileFile()
    {
        file_put_contents(
            $this->getCompileFile(),
            Yaml::dump(
                $this->compilingArray(),
                5
            )
        );
    }
    public function getCompileFile()
    {
        return base_path('.artifice');
    }
    public function compiledArray()
    {
        $file = $this->getCompileFile();
        if (file_exists($file) === true) {
            return Yaml::parseFile($file, 5);
        } else {
            return [];
        }
    }

    public function compilingArray()
    {
        return [
            'models' => $this->modelNames(),
            'pivots' => $this->pivotNames(),
            'controllers' => $this->controllerNames(),
        ];
    }

    public function modelNames()
    {
        return array_keys($this->data['models'] ?? []);
    }
    public function pivotNames()
    {
        return array_keys($this->data['pivots'] ?? []);
    }
    public function controllerNames()
    {
        return array_keys($this->data['controllers'] ?? []);
    }
    public function generateDraft()
    {
        $this->generate($this->draftArray());
        $data = $this->data['models'] ?? [];
        foreach ($data as $model => $value) {
            if (data_get($value, '__build.policy') === true) {
                $this->addPolicy($model);
            }
            ModelModifier::create($key, $value);
        }
    }

    public function generate(array $data)
    {
        $data['cache'] = [];
        $registry = $this->blueprint->analyze($data);

        $this->blueprint->generate($registry, [], [], true);
    }
    public function getPivotRaw(string $model, string $relation)
    {
        $name = [$model, $relation];
        sort($name);
        return $name;
    }
    public function getPivotName(string $model, string $relation)
    {
        $name = [$model, $relation];
        sort($name);
        return join('', $name);
    }
    public function getMigrationTable(string $model, string $relation)
    {
        $name = [$model, $relation];
        sort($name);
        return strtolower(join('_', $name));
    }
    public function addPolicy(string $name)
    {
        $content = $this->getStub('service.array.key.value');
        $model = "App\\Models\\{$name}";
        $class = "App\\Policies\\{$name}Policy";
        $content = str_replace('{{ key }}', $model, $content);
        $content = str_replace('{{ value }}', $class, $content);
        $this->authServicePoviderContent = preg_replace(
            '/(protected \$policies = \[)/s', "$1\n$content", $this->authServicePoviderContent
        );
    }
    public function generatePolicies()
    {
        file_put_contents($this->authServicePoviderFile, $this->authServicePoviderContent);
    }
    public function generatePivots()
    {
        $items = [];
        $pivots = $this->data['pivots'] ?? [];
        foreach ($pivots as $models => $pivot) {
            $models = preg_split('/\W+/', $models);
            foreach ($models as $a => $model) {
                if (empty($model)) {
                    continue;
                }
                foreach ($models as $b => $relation) {
                    if ($a === $b) {
                        continue;
                    }
                    $temp = [];
                    $name = $this->getPivotName($model, $relation);
                    $key = strtolower("{$relation}_id");
                    $temp[$key] = 'id foreign';
                    $key = strtolower("{$model}_id");
                    $temp[$key] = 'id foreign';
                    $items[$name] = $pivot + $temp;
                }
            }
        }
        $this->generate([
            'models' => $items,
            'cache' => [],
        ]);
        foreach ($items as $new_model => $data) {
            $new_table = str_replace('-', '_', Str::kebab($new_model));
            $files = glob(base_path("database/migrations/*_create_{$new_table}_table.php"));
            foreach ($files as $file) {
                unlink($file);
            }
            if (data_get($data, '__build.policy') === true) {
                $this->addPolicy($new_model);
            }
            $old_table = Str::plural($new_table, 2);
            $old_model = Str::plural($new_model, 2);
            $files = glob(base_path("database/migrations/*_create_{$old_table}_table.php"));
            foreach ($files as $old_file) {
                $new_file = str_replace($old_table, $new_table, $old_file);
                $content = file_get_contents($old_file);
                $content = str_replace($old_table, $new_table, $content);
                $content = str_replace($old_model, $new_model, $content);
                file_put_contents($old_file, $content);
                rename(
                    $old_file,
                    $new_file
                );
                break;
            }
        }
        PivotModifier::createMany($items);
    }

    public function draftArray()
    {
        return [
            'controllers' => $this->data['controllers'] ?? [],
            'models' => $this->draftModels(),
        ];
    }
    public function draftModels()
    {
        return array_map(function ($value) {
            return Arr::except($value, ['__build', '__class', '__model', '__index']);
        }, $this->data['models'] ?? []);
    }
    public function yamlFiles()
    {
        $artifice = $this->option('name');
        $this->yamlSearch($artifice);
        $this->yamlSearch("$artifice/*");
    }
    public function yamlSearch(string $path)
    {
        $files = glob(base_path("$path.y*ml"));
        foreach ($files as $file) {
            $this->yamlParse($file);
        }
    }
    public function yamlParse(string $file)
    {
        $content = file_get_contents($file);
        $data = $this->blueprint->parse($content, false);

        foreach ([
            'controllers',
            'models',
            'pivots',
        ] as $key) {
            $this->data[$key] = array_merge($this->data[$key] ?? [], $data[$key] ?? []);
        }

        $values = array_values($data ?? []);
        $keys = array_keys($data);

        $matchs = preg_grep('/^routes/', $keys);
        $default = $this->option('default-route');

        foreach ($matchs as $key => $value) {
            [$name, $subName] = explode('.', $value) + [null, $default];
            $this->data[$name][$subName] = $values[$key];
        }
    }
}