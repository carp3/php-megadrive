<?php

use PhpMegaDriveEmulator\Hardware\IO;
use PhpMegaDriveEmulator\Hardware\MegaDrive;
use PhpMegaDriveEmulator\Rom\Loader;

define('DEBUG', 0);

if (!function_exists('m68k_init')) {
    exit("You need to install M68K extension first, get it from: https://github.com/carp3/php-m68k\n");
}

spl_autoload_register(function ($class) {
    include __DIR__ . str_replace('\\', '/', str_replace('PhpMegaDriveEmulator', '/src', $class)) . '.php';
});


$arg = 'sonic.md';
$loader = new Loader($argv[1] ?? $arg);
$rom = $loader->load();
echo sprintf("%s\n%s", $rom->getConsole(), $rom->getGlobalGameName());

SDL_Init(SDL_INIT_VIDEO);
$window = SDL_CreateWindow($rom->getGlobalGameName(), SDL_WINDOWPOS_UNDEFINED, SDL_WINDOWPOS_UNDEFINED, 320, 240, SDL_WINDOW_SHOWN);
$renderer = SDL_CreateRenderer($window, 0, SDL_RENDERER_ACCELERATED);

SDL_UpdateWindowSurface($window);

$md = new MegaDrive($rom, $renderer);
$time = microtime(true);
$fps = 0;
$event = new SDL_Event;
$fc = 0;
for (; ;) {
    if ($fc++ % 100 === 0) {
        echo "FPS: " . (($fc - $fps)) / (microtime(true) - $time) . PHP_EOL;
    }

    $md->frame();

    SDL_RenderPresent($renderer);
    SDL_PollEvent($event);

    switch ($event->type) {
        case SDL_QUIT:
            break 2;
        case SDL_KEYDOWN:
            switch ($event->key->keysym->sym) {
                case SDLK_UP:
                    $md->pressKey(IO::PAD_UP);
                    break;
                case SDLK_DOWN:
                    $md->pressKey(IO::PAD_DOWN);
                    break;
                case SDLK_RIGHT:
                    $md->pressKey(IO::PAD_RIGHT);
                    break;
                case SDLK_LEFT:
                    $md->pressKey(IO::PAD_LEFT);
                    break;
                case SDLK_RETURN:
                case SDLK_RETURN2:
                    $md->pressKey(IO::PAD_S);
                    break;
                case SDLK_a:
                    $md->pressKey(IO::PAD_A);
                    break;
                case SDLK_s:
                    $md->pressKey(IO::PAD_B);
                    break;
                case SDLK_d:
                    $md->pressKey(IO::PAD_C);
                    break;
            }

            break;

        case SDL_KEYUP:
            switch ($event->key->keysym->sym) {
                case SDLK_UP:
                    $md->releaseKey(IO::PAD_UP);
                    break;
                case SDLK_DOWN:
                    $md->releaseKey(IO::PAD_DOWN);
                    break;
                case SDLK_RIGHT:
                    $md->releaseKey(IO::PAD_RIGHT);
                    break;
                case SDLK_LEFT:
                    $md->releaseKey(IO::PAD_LEFT);
                    break;
                case SDLK_RETURN:
                case SDLK_RETURN2:
                    $md->releaseKey(IO::PAD_S);
                    break;
                case SDLK_a:
                    $md->releaseKey(IO::PAD_A);
                    break;
                case SDLK_s:
                    $md->releaseKey(IO::PAD_B);
                    break;
                case SDLK_d:
                    $md->releaseKey(IO::PAD_C);
                    break;
            }
            break;
    }
}
echo sprintf('Total execution time: %s seconds', microtime(true) - $time);
SDL_DestroyRenderer($renderer);
SDL_DestroyWindow($window);
SDL_Quit();