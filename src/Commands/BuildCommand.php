<?php

namespace Byancode\Artifice\Commands;

use Blueprint\Blueprint;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

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
    {--m|overwrite-migrations : Update existing migration files, if found }
    {--default-route=api}
    {--no-traits}
    {--force}';

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
        $this->generateDraft();
    }

    public function generateDraft()
    {
        $data = $this->draftArray();
        $data['cache'] = [];
        $registry = $this->blueprint->analyze($data);

        $only = array_filter(explode(',', $this->option('only')));
        $skip = array_filter(explode(',', $this->option('skipe')));
        $overwriteMigrations = $this->option('overwrite-migrations');

        $this->blueprint->generate($registry, $only, $skip, $overwriteMigrations);
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
        $this->yamalSearch($artifice);
        $this->yamalSearch("$artifice/*");
    }
    public function yamalSearch(string $path)
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