<?php

declare(strict_types=1);

use Indieinabox\Yaml;

it('loads YAML configuration from a string', function () {
    $yamlString = "title: Indie Site\nauthor: ~lumen\nbuildall: true";
    $yaml = new Yaml();
    
    $parsed = $yaml->loadString($yamlString);
    
    expect($parsed)->toBe([
        'title' => 'Indie Site',
        'author' => '~lumen',
        'buildall' => true
    ]);
});

it('dumps PHP array to YAML string representation', function () {
    $array = [
        'name' => 'Indieinabox',
        'version' => '1.0.0',
        'options' => [
            'minify' => true
        ]
    ];
    
    $yaml = new Yaml();
    $dumped = $yaml->dump($array);
    
    expect($dumped)->toContain('name: Indieinabox')
        ->and($dumped)->toContain('version: 1.0.0')
        ->and($dumped)->toContain('minify: true');
});
