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
        $this->defaultServiceProvider();
        $this->generateDraft();
        $this->generatePivots();
        $this->generateRoutes();
        $this->generatePolicies();
        $this->createCompileFile();
    }
    public function stubPath(string $name)
    {
        return base_path("vendor/byancode/artifice/src/stubs/$name.stub");
    }
    public function getStub(string $name)
    {
        return file_get_contents($this->stubPath($name));
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
    public function defaultServiceProvider()
    {

        $this->authServicePoviderName = "ArtificeAuthServiceProvider";
        $this->authServicePoviderFile = app_path("Providers/{$this->authServicePoviderName}.php");
        $this->authServicePoviderContent = $this->getStub('service.auth.class');
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
        $artifice = $this->option('name');
        return base_path("$artifice.lock");
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
            if (data_get($value, '__build.policy', true) === true) {
                $this->addPolicy($model);
            }
            ModelModifier::create($model, $value);
        }
    }

    public function generateRoutes()
    {
        $pattern = '/#start::artifice.*#end::artifice|#::artifice/s';
        $allRoutes = $this->data['routes'] ?? [];
        foreach ($allRoutes as $channel => $routes) {
            $text = $this->createRoutes($routes);
            $file = base_path("routes/$channel.php");
            $content = file_get_contents($file);
            $text = "#start::artifice\n$text\n#end::artifice";
            $content = preg_replace($pattern, $text, $content);
            file_put_contents($file, $content);
        }
    }

    public function createRoutes(array $routes, int $tab = 0)
    {
        $groups = [];
        foreach ($routes as $key => $array) {
            if (is_array($array) === false || Arr::isAssoc($array) === false) {
                continue;
            }
            $attrs = [];
            if (preg_match('/^\//', $key) && preg_match('/ \+ /', $key)) {
                [$attrs['prefix'], $attrs['middleware']] = preg_split('/\s+\+\s+/s', $key);
            } elseif (preg_match('/^\+/', $key)) {
                $attrs['middleware'] = preg_replace('/^[\+\s]+/', '', $key);
            } elseif (preg_match('/^\//', $key)) {
                $attrs['prefix'] = $key;
            } else {
                continue;
            }
            $group = $this->getStub('routes.group');
            $contents = [];
            foreach ($attrs as $key => $value) {
                $content = $this->getStub('routes.array.key.value');
                $content = str_replace('{{ key }}', $key, $content);
                $value = var_export($value, true);
                $content = str_replace('{{ value }}', $value, $content);
                $contents[] = trim($content);
            }
            $content = join("\n", $contents);
            $content = $this->addTabs($content, 1);
            $group = str_replace('{{ attrs }}', $content, $group);

            $contents = [];
            $methods = Arr::only($array, ['get', 'post', 'put', 'patch', 'delete']);
            foreach ($methods as $method => $controllerAndFunction) {
                [$controller, $function] = explode('@', $controllerAndFunction);
                $this->generateController($method, $controller, $function);
                if (isset($array['where'])) {
                    $content = $this->getStub('routes.method.where');
                    $content = str_replace('{{ method }}', $method, $content);
                    $content = str_replace('{{ controller }}', $controllerAndFunction, $content);
                    $wheres = [];
                    foreach ($array['where'] as $key => $regexp) {
                        $where = $this->getStub('routes.array.key.value');
                        $where = str_replace('{{ key }}', $key, $where);
                        $regexp = var_export($regexp, true);
                        $where = str_replace('{{ value }}', $regexp, $where);
                        $wheres[] = trim($where);
                    }
                    $where = join("\n", $wheres);
                    $where = $this->addTabs($where, 1);
                    $content = str_replace('{{ attrs }}', $where, $content);
                } else {
                    $content = $this->getStub('routes.method');
                    $content = str_replace('{{ method }}', $method, $content);
                    $content = str_replace('{{ controller }}', $controllerAndFunction, $content);
                }
                $contents[] = $content;
            }
            $content = join("\n", $contents);
            $content = $this->addTabs($content, 1);
            $content .= "\n" . $this->createRoutes($array, 1);
            $group = str_replace('{{ content }}', $content, $group);
            $groups[] = $group;
        }
        $content = join("\n", $groups);
        $content = $this->addTabs($content, $tab);
        return $content;
    }
    public function addTabs(string $content, int $tab = 0)
    {
        $tabs = str_repeat(' ', $tab * 4);
        return preg_replace('/^/m', $tabs, $content);
    }
    public function generateController(string $method, string $controller, string $function)
    {
        $file = app_path("Http/Controllers/$controller.php");

        if (file_exists($file)) {
            $content = file_get_contents($file);
        } else {
            $content = $this->getStub('controller.class');
        }

        $content = str_replace('{{ name }}', $controller, $content);
        $functionName = ucfirst($function);

        $requestName = preg_replace('/Controller$/s', "Request$functionName", $controller);
        $requestFile = app_path("Http/Requests/$requestName.php");
        $requestClass = "App\\Requests\\$requestName";

        if (preg_match("/public function $function\(/s", $content) !== 1) {
            $stub = $this->getStub([
                'get' => 'controller.none',
                'put' => 'controller.validate',
                'post' => 'controller.validate',
                'patch' => 'controller.by.id',
                'delete' => 'controller.by.id',
            ][$method]);
            $stub = str_replace('{{ name }}', $function, $stub);
            $stub = str_replace('{{ request }}', $requestName, $stub);
            $content = preg_replace("/\vuse $requestClass;(?:[ \r]+)?/s", '', $content, 1);
            $content = preg_replace('/(\vuse )/s', "$1$requestClass;$1", $content, 1);
            $content = preg_replace('/\}(?:\s+)?$/s', "\n$stub\n}", $content);
        }
        if (file_exists($requestFile) === false && in_array($method, ['put', 'post'])) {
            $requestContent = $this->getStub('request.class');
            $requestContent = str_replace('{{ name }}', $requestName, $requestContent);
            file_put_contents($requestFile, $requestContent);
        }
        file_put_contents($file, $content);
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
        $content = str_replace('{{ key }}', "App\\Models\\{$name}", $content);
        $content = str_replace('{{ value }}', "App\\Policies\\{$name}Policy", $content);
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
            if (data_get($data, '__build.policy', false) === true) {
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
        $this->yamlSearch(".$artifice/*");
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