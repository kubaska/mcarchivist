<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->get('/config', 'ConfigController@index');
$router->get('/settings', 'SettingsController@index');
$router->post('/settings', 'SettingsController@store');
$router->get('/directory-selector', 'SettingsController@directorySelector');

$router->group(['prefix' => 'projects'], function () use ($router) {
    $router->get('/', ['uses' => 'ModController@index', 'as' => 'project.index']);
    $router->get('/{id}', ['uses' => 'ModController@show', 'as' => 'project.show']);
    $router->post('/{id}/archive', ['uses' => 'ModController@archive', 'as' => 'project.archive']);
    $router->get('/{id}/authors', ['uses' => 'ModController@authors', 'as' => 'project.authors']);
    $router->get('/{id}/dependencies', ['uses' => 'ModController@dependencies', 'as' => 'project.dependencies']);
    $router->get('/{id}/dependants', ['uses' => 'ModController@dependants', 'as' => 'project.dependants']);
    $router->get('/{id}/versions', ['uses' => 'ModController@versions', 'as' => 'project.versions']);
    $router->get('/{id}/versions/{versionId}/dependencies', ['uses' => 'ModController@versionDependencies', 'as' => 'project.version.dependencies']);
    $router->get('/{id}/versions/{versionId}/dependants', ['uses' => 'ModController@versionDependants', 'as' => 'project.version.dependants']);
    $router->get('/{id}/versions/{versionId}/files', ['uses' => 'ModController@versionFiles', 'as' => 'project.version.files']);
    $router->delete('/{id}/versions/{versionId}', ['uses' => 'ModController@versionDelete', 'as' => 'project.version.delete']);
    $router->delete('/{id}/versions/{versionId}/files/{fileId}', ['uses' => 'ModController@fileDelete', 'as' => 'project.version.files.delete']);
    $router->post('/{id}/versions/{versionId}/archive', ['uses' => 'ModController@versionArchive', 'as' => 'project.version.archive']);
    $router->post('/{id}/versions/{versionId}/revalidate', ['uses' => 'ModController@versionRevalidate', 'as' => 'project.version.revalidate']);
    $router->post('/{id}/related', ['uses' => 'ModController@getRelatedProjects', 'as' => 'project.related']);
    $router->post('/merge', ['uses' => 'ModController@merge', 'as' => 'project.merge']);
    $router->post('/{id}/unmerge', ['uses' => 'ModController@unmerge', 'as' => 'project.unmerge']);
    $router->post('/{id}/default', ['uses' => 'ModController@setDefault', 'as' => 'project.set-default']);
});

$router->group(['prefix' => 'game-versions'], function () use ($router) {
    $router->get('/', 'GameVersionController@index');
    $router->post('/update-index', 'GameVersionController@updateIndex');
    $router->get('/{versionId}/files', 'GameVersionController@files');
    $router->post('/{versionId}/archive', 'GameVersionController@archive');
    $router->post('/{versionId}/revalidate', 'GameVersionController@revalidate');
    $router->delete('/{versionId}', 'GameVersionController@destroy');
    $router->delete('/{versionId}/files/{fileId}', 'GameVersionController@destroyFile');
});

$router->group(['prefix' => 'loaders'], function () use ($router) {
    $router->get('/', 'LoaderController@index');
    $router->get('/{id}', 'LoaderController@versions');
    $router->post('/{id}/update-index', 'LoaderController@updateIndex');
    $router->post('/{id}/archive', 'LoaderController@archive');
    $router->post('/{id}/versions/{versionId}/revalidate', 'LoaderController@revalidate');
    $router->delete('/{id}/versions/{versionId}', 'LoaderController@destroy');
    $router->get('/{id}/versions/{versionId}/files', 'LoaderController@files');
    $router->delete('/{id}/versions/{versionId}/files/{fileId}', 'LoaderController@destroyFile');
});

$router->group(['prefix' => 'rulesets'], function () use ($router) {
    $router->get('/', 'RulesetController@index');
    $router->post('/', 'RulesetController@store');
    $router->post('/{id}', 'RulesetController@update');
    $router->delete('/{id}', 'RulesetController@destroy');
});

$router->group(['prefix' => 'queue'], function () use ($router) {
    $router->get('/', 'QueueController@index');
    $router->post('/{id}/cancel', 'QueueController@cancel');
    $router->post('/{id}/retry', 'QueueController@retry');
});
