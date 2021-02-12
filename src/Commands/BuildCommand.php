<?php

namespace Byancode\Artifice\Commands;

use Blueprint\Blueprint;
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
    {--only= : Comma separated list of file classes to generate, skipping the rest }
    {--skip= : Comma separated list of file classes to skip, generating the rest }
    {--force= : Comma separated list of file classes to override}
    {--default-route=api : routers available: api, web}
    {--no-traits}';

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
        $this->generateDraft();
        $this->createCompileFile();
    }

    public function cleanerFiles()
    {
        $compiled = $this->compiledArray();
        $current = $this->compilingArray();
        foreach ($compiled as $key => $list) {
            foreach ($list as $name) {
                if (in_array($name, $current[$key]) === false) {
                    $type = substr($key, 0, -1);
                    call_user_func([$this, "remove_$type"], $name);
                }
            }
        }
    }
    public function remove_controller(string $name)
    {
        $file = app_path("Http/Controllers/$name.php");
        file_exists($file) ? unlink($file) : null;
    }
    public function remove_model(string $name)
    {
        foreach ($this->getModelFiles() as $file) {
            file_exists($file) ? unlink($file) : null;
        }
    }
    public function remove_pivot(string $name)
    {
        foreach ($this->getPivotFiles() as $file) {
            file_exists($file) ? unlink($file) : null;
        }
    }
    public function getPivotFiles(string $name)
    {
        $files = [
            app_path("Pivots/$name.php"),
            app_path("Traits/Pivot{$name}Trait.php"),
            app_path("Observers/Pivot{$name}Observer.php"),
        ];
        return $files;
    }
    public function getModelFiles(string $name)
    {
        $path = config('blueprint.models_namespace', 'Models');
        $files = [
            app_path("$path/$name.php"),
            app_path("Traits/{$name}Trait.php"),
            app_path("Observers/{$name}Observer.php"),
        ];
        return $files;
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
        $data = $this->draftArray();
        $data['cache'] = [];
        $registry = $this->blueprint->analyze($data);

        $only = array_filter(explode(',', $this->option('only')));
        $skip = array_filter(explode(',', $this->option('skip')));

        $this->blueprint->generate($registry, $only, $skip, true);
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