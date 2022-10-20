<?php

namespace Mha\LaravelModelWatch\Commands;

use Illuminate\Database\Console\DatabaseInspectionCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mha\LaravelModelWatch\Collections\BaseWatchCollection;
use Mha\LaravelModelWatch\ModelWatcher;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class LaravelModelWatchCommand extends DatabaseInspectionCommand
{
    protected $signature = 'model:watch
        {modelOrCollection=default : the model class or the name of a collection in your config}
        {id? : the ID of the model to show. Required if model is specified.}
        {--field=* : specify which field(s) to show}
        {--interval=500 : How often (in milliseconds to poll the database for changes)}
    ';

    protected $description = 'Watch an Eloquent model for changes';

    protected int $intervalMilliseconds = 2500;

    public function handle(): int
    {
        $this->ensureDependenciesExist();
        $this->intervalMilliseconds = $this->option('interval');
        $models = $this->getModels();
        if ($models->isEmpty()) {
            $this->error('No models found');

            return self::INVALID;
        }

        $modelWatchers = $models->map(function(Model $model){
            return ModelWatcher::create(
                $model,
                $this->output->getOutput()->section()
            );
        });

        while (true) {
            $modelWatchers->each->refresh();
            usleep(($this->intervalMilliseconds) * 1000);
        }
    }

    public function getModels(): Collection
    {
        if ($this->argument('id')) {
            $class = $this->qualifyModel($this->argument('modelOrCollection'));

            /** @var class-string<Model> $class */
            return collect([
                $class::findOrFail(
                    $this->argument('id'),
                    $this->option('field')
                ),
            ]);
        }

        $collectionClass = config('model-watch.collections.default');
        /** @var BaseWatchCollection $collection */
        $collection = app()->make($collectionClass);
        return $collection->getModels();

        return $collection->getModels();
    }

    /**
     * Qualify the given model class base name.
     *
     * @param  string  $model
     * @return string
     *
     * @see \Illuminate\Console\GeneratorCommand
     */
    protected function qualifyModel(string $model): string
    {
        if (str_contains($model, '\\') && class_exists($model)) {
            return $model;
        }

        $model = ltrim($model, '\\/');

        $model = str_replace('/', '\\', $model);

        $rootNamespace = $this->laravel->getNamespace();

        if (Str::startsWith($model, $rootNamespace)) {
            return $model;
        }

        return is_dir(app_path('Models'))
            ? $rootNamespace.'Models\\'.$model
            : $rootNamespace.$model;
    }
}
