<?php

namespace KusikusiCMS\Models\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kusikusicms:make:model';

    protected $aliases = ['k:make:model'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new KusikusiCMS entity model class';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = Str::studly(trim((string) $this->ask('What should the model be named?')));

        if ($name === '') {
            $this->components->error('The model name is required.');

            return self::FAILURE;
        }

        $path = app_path('Models/'.$name.'.php');

        if (File::exists($path)) {
            $this->components->error('Model already exists: '.$name);

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->template($name));

        $this->components->info('Model created successfully: '.$path);

        return self::SUCCESS;
    }

    /**
     * Build the contents of the model class.
     */
    protected function template(string $name): string
    {
        return <<<PHP
<?php

namespace App\Models;

use KusikusiCMS\Models\Entity;

class {$name} extends Entity
{

}

PHP;
    }
}
