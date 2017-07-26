<?php namespace Waavi\Translation;

use Illuminate\Translation\FileLoader as LaravelFileLoader;
use Illuminate\Translation\TranslationServiceProvider as LaravelTranslationServiceProvider;
use Waavi\Translation\Cache\RepositoryFactory as CacheRepositoryFactory;
use Waavi\Translation\Commands\CacheFlushCommand;
use Waavi\Translation\Commands\FileLoaderCommand;
use Waavi\Translation\Loaders\CacheLoader;
use Waavi\Translation\Loaders\DatabaseLoader;
use Waavi\Translation\Loaders\FileLoader;
use Waavi\Translation\Loaders\MixedLoader;
use Waavi\Translation\Middleware\TranslationMiddleware;
use Waavi\Translation\Models\Translation;
use Waavi\Translation\Repositories\LanguageRepository;
use Waavi\Translation\Repositories\TranslationRepository;
use Waavi\Translation\Routes\ResourceRegistrar;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class TranslationServiceProvider extends LaravelTranslationServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/translator.php' => config_path('translator.php'),
        ]);
        $this->loadScheema();
        //  $this->loadMigrationsFrom(__DIR__ . '/../database/migrations/');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/translator.php', 'translator');

        parent::register();
        $this->registerCacheRepository();
        $this->registerFileLoader();
        $this->registerCacheFlusher();
        $this->app->singleton('translation.uri.localizer', UriLocalizer::class);
        $this->app[\Illuminate\Routing\Router::class]->aliasMiddleware('localize', TranslationMiddleware::class);
        // Fix issue with laravel prepending the locale to localize resource routes:
        $this->app->bind('Illuminate\Routing\ResourceRegistrar', ResourceRegistrar::class);
    }

    /**
     *  IOC alias provided by this Service Provider.
     *
     * @return array
     */
    public function provides()
    {
        return array_merge(parent::provides(), ['translation.cache.repository', 'translation.uri.localizer', 'translation.loader']);
    }

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader()
    {
        $app = $this->app;
        $this->app->singleton('translation.loader', function ($app) {
            $source = $app['config']->get('translator.source');
            $defaultLocale = $app['config']->get('app.locale');
            $loader = null;
            try {
                switch ($source) {
                    case 'mixed':
                        $laravelFileLoader = new LaravelFileLoader($app['files'], $app->basePath() . '/resources/lang');
                        $fileLoader = new FileLoader($defaultLocale, $laravelFileLoader);
                        $databaseLoader = new DatabaseLoader($defaultLocale, $app->make(TranslationRepository::class));
                        $loader = new MixedLoader($defaultLocale, $fileLoader, $databaseLoader);
                        break;
                    case 'mixed_db':
                        $laravelFileLoader = new LaravelFileLoader($app['files'], $app->basePath() . '/resources/lang');
                        $fileLoader = new FileLoader($defaultLocale, $laravelFileLoader);
                        $databaseLoader = new DatabaseLoader($defaultLocale, $app->make(TranslationRepository::class));
                        $loader = new MixedLoader($defaultLocale, $databaseLoader, $fileLoader);
                        break;
                    case 'database':
                        $loader = new DatabaseLoader($defaultLocale, $app->make(TranslationRepository::class));
                        break;
                    default:
                    case 'files':
                        $laravelFileLoader = new LaravelFileLoader($app['files'], $app->basePath() . '/resources/lang');
                        $loader = new FileLoader($defaultLocale, $laravelFileLoader);
                        break;
                }
                if ($app['config']->get('translator.cache.enabled')) {
                    //$cacheStore      = $app['cache']->getStore();
                    //$cacheRepository = CacheRepositoryFactory::make($cacheStore, $app['config']->get('translator.cache.suffix'));
                    $loader = new CacheLoader($defaultLocale, $app['translation.cache.repository'], $loader, $app['config']->get('translator.cache.timeout'));
                }
            } catch (\Exception $e) {
            }
            return $loader;
        });
    }

    /**
     *  Register the translation cache repository
     *
     * @return void
     */
    public function registerCacheRepository()
    {
        $this->app->singleton('translation.cache.repository', function ($app) {
            $cacheStore = $app['cache']->getStore();
            return CacheRepositoryFactory::make($cacheStore, $app['config']->get('translator.cache.suffix'));
        });
    }

    /**
     * Register the translator:load language file loader.
     *
     * @return void
     */
    protected function registerFileLoader()
    {
        $app = $this->app;
        $defaultLocale = $app['config']->get('app.locale');
        $languageRepository = $app->make(LanguageRepository::class);
        $translationRepository = $app->make(TranslationRepository::class);
        $translationsPath = $app->basePath() . '/resources/lang';
        $command = new FileLoaderCommand($languageRepository, $translationRepository, $app['files'], $translationsPath, $defaultLocale);

        $this->app['command.translator:load'] = $command;
        $this->commands('command.translator:load');
    }

    /**
     *  Flushes the translation cache
     *
     * @return void
     */
    public function registerCacheFlusher()
    {
        //$cacheStore      = $this->app['cache']->getStore();
        //$cacheRepository = CacheRepositoryFactory::make($cacheStore, $this->app['config']->get('translator.cache.suffix'));
        $command = new CacheFlushCommand($this->app['translation.cache.repository'], $this->app['config']->get('translator.cache.enabled'));

        $this->app['command.translator:flush'] = $command;
        $this->commands('command.translator:flush');
    }


    /**
     * Dirty fix laravel 5.4
     */
    public function loadScheema()
    {
        if (!Schema::hasTable('translator_languages')) {
            $this->createLanguageScheema();
        }

        if (!Schema::hasTable('translator_translations')) {
            $this->createTranslatorScheema();
        }
    }


    /**
     * Creates language table if doesn't exist.
     */
    private function createLanguageScheema()
    {
        Schema::create('translator_languages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('locale', 10)->unique();
            $table->string('name', 60)->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Creates translation table if it doesn't exist
     */
    private function createTranslatorScheema()
    {
        Schema::create('translator_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('locale', 10);
            $table->string('namespace', 150)->default('*');
            $table->string('group', 150);
            $table->string('item', 150);
            $table->text('text');
            $table->boolean('unstable')->default(false);
            $table->boolean('locked')->default(false);
            $table->timestamps();
            $table->foreign('locale')->references('locale')->on('translator_languages');
            $table->unique(['locale', 'namespace', 'group', 'item']);
        });
    }
}
