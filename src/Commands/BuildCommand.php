<?php

namespace Byancode\Artifice\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;

class BuildCommand extends Command
{
    public $data = [];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'artifice:build {--name=artifice} {--no-traits} {--force}';

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
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->files();
        print_r($this->draft());
    }

    public function draft()
    {
        return Arr::only($this->data, ['controllers']) + [
            'models' => $this->draftModels(),
        ];
    }
    public function draftModels()
    {
        return collect($this->data['models'] ?? [])->map(function ($value, $key) {
            return collect($value)->except(['__build', '__class', '__index']);
        })->all();
    }
    public function files()
    {
        $this->finder('artifice');
        foreach ([
            'controllers',
            'models',
            'routes',
            'pivots',
        ] as $name) {
            $this->finder("artifice/$name", $name);
        }
    }
    public function finder(string $path, string $name = null)
    {
        $files = glob(base_path("$path.y*ml"));
        foreach ($files as $file) {
            $this->parser($file, $name);
        }
    }
    public function parser(string $file, string $name = null)
    {
        $data = Yaml::parseFile($file);

        if (isset($name)) {
            $this->data[$name] = array_merge($this->data[$name] ?? [], $data);
        } else {
            $this->data = array_merge($this->data, $data);
        }
    }
}