<?php
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;

Router::plugin(
    'Blog',
    ['path' => '/blog'],
    function (RouteBuilder $routes) {

        $routes->connect('/', ['controller' => 'Posts', 'action'=>'index']);

        $routes->connect('/categorie/:slug', ['controller' => 'Categories', 'action' => 'view'],
            [
                '_name' => 'category',

            ])
            ->setPass(['slug'])
            ->setPatterns([
                'slug' => '[0-9a-z_-]+',
            ])
        ;

        $routes->connect('/article/:slug', ['controller' => 'Posts', 'action' => 'view'],
            [
                '_name' => 'post',
            ])
            ->setPass(['slug'])
            ->setPatterns([
                'slug' => '[0-9a-z_-]+',
            ])

        ;

        $routes->fallbacks(DashedRoute::class);
    }
);

Router::prefix('admin', function ($routes) {
    $routes->plugin('Blog', function ($routes) {
        $routes->connect('/:controller', ['action' => 'index'], ['routeClass' => 'DashedRoute']);
        $routes->connect('/:controller/:action/*', [], ['routeClass' => 'DashedRoute']);
    });
});
