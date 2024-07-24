<?php

namespace BookStack\Api;

use BookStack\Http\ApiController;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class ApiDocsGenerator
{
    protected array $reflectionClasses = [];
    protected array $controllerClasses = [];

    /**
     * Load the docs form the cache if existing
     * otherwise generate and store in the cache.
     */
    public static function generateConsideringCache(): Collection
    {
        $appVersion = trim(file_get_contents(base_path('version')));
        $cacheKey = 'api-docs::' . $appVersion;
        $isProduction = config('app.env') === 'production';
        $cacheVal = $isProduction ? Cache::get($cacheKey) : null;

        if (!is_null($cacheVal)) {
            return $cacheVal;
        }

        $docs = (new ApiDocsGenerator())->generate();
        Cache::put($cacheKey, $docs, 60 * 24);

        return $docs;
    }

    /**
     * Generate API documentation.
     */
    protected function generate(): Collection
    {
        $apiRoutes = $this->getFlatApiRoutes();
        $apiRoutes = $this->loadDetailsFromControllers($apiRoutes);
        $apiRoutes = $this->loadDetailsFromFiles($apiRoutes);
        $apiRoutes = $apiRoutes->groupBy('base_model');

        return $apiRoutes;
    }

    /**
     * Load any API details stored in static files.
     */
    protected function loadDetailsFromFiles(Collection $routes): Collection
    {
        return $routes->map(function (array $route) {
            $exampleTypes = ['request', 'response'];
            $fileTypes = ['json', 'http'];
            foreach ($exampleTypes as $exampleType) {
                foreach ($fileTypes as $fileType) {
                    $exampleFile = base_path("dev/api/{$exampleType}s/{$route['name']}." . $fileType);
                    if (file_exists($exampleFile)) {
                        $route["example_{$exampleType}"] = file_get_contents($exampleFile);
                        continue 2;
                    }
                }
                $route["example_{$exampleType}"] = null;
            }

            return $route;
        });
    }

    /**
     * Load any details we can fetch from the controller and its methods.
     */
    protected function loadDetailsFromControllers(Collection $routes): Collection
    {
        return $routes->map(function (array $route) {
            $method = $this->getReflectionMethod($route['controller'], $route['controller_method']);
            $comment = $method->getDocComment();
            $route['description'] = $comment ? $this->parseDescriptionFromMethodComment($comment) : null;
            $route['body_params'] = $this->getBodyParamsFromClass($route['controller'], $route['controller_method']);

            return $route;
        });
    }

    /**
     * Load body params and their rules by inspecting the given class and method name.
     *
     * @throws BindingResolutionException
     */
    protected function getBodyParamsFromClass(string $className, string $methodName): ?array
    {
        /** @var ApiController $class */
        $class = $this->controllerClasses[$className] ?? null;
        if ($class === null) {
            $class = app()->make($className);
            $this->controllerClasses[$className] = $class;
        }

        $rules = collect($class->getValidationRules()[$methodName] ?? [])->map(function ($validations) {
            return array_map(function ($validation) {
                return $this->getValidationAsString($validation);
            }, $validations);
        })->toArray();

        return empty($rules) ? null : $rules;
    }

    /**
     * Convert the given validation message to a readable string.
     */
    protected function getValidationAsString($validation): string
    {
        if (is_string($validation)) {
            return $validation;
        }

        if (is_object($validation) && method_exists($validation, '__toString')) {
            return strval($validation);
        }

        if ($validation instanceof Password) {
            return 'min:8';
        }

        $class = get_class($validation);

        throw new Exception("Cannot provide string representation of rule for class: {$class}");
    }

    /**
     * Parse out the description text from a class method comment.
     */
    protected function parseDescriptionFromMethodComment(string $comment): string
    {
        $matches = [];
        preg_match_all('/^\s*?\*\s?($|((?![\/@\s]).*?))$/m', $comment, $matches);

        $text = implode(' ', $matches[1] ?? []);
        return str_replace('  ', "\n", $text);
    }

    /**
     * Get a reflection method from the given class name and method name.
     *
     * @throws ReflectionException
     */
    protected function getReflectionMethod(string $className, string $methodName): ReflectionMethod
    {
        $class = $this->reflectionClasses[$className] ?? null;
        if ($class === null) {
            $class = new ReflectionClass($className);
            $this->reflectionClasses[$className] = $class;
        }

        return $class->getMethod($methodName);
    }

    /**
     * Get the system API routes, formatted into a flat collection.
     */
    protected function getFlatApiRoutes(): Collection
    {
        return collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return strpos($route->uri, 'api/') === 0;
        })->map(function ($route) {
            [$controller, $controllerMethod] = explode('@', $route->action['uses']);
            $baseModelName = explode('.', explode('/', $route->uri)[1])[0];
            $shortName = $baseModelName . '-' . $controllerMethod;

            return [
                'name'                    => $shortName,
                'uri'                     => $route->uri,
                'method'                  => $route->methods[0],
                'controller'              => $controller,
                'controller_method'       => $controllerMethod,
                'controller_method_kebab' => Str::kebab($controllerMethod),
                'base_model'              => $baseModelName,
            ];
        });
    }
}
