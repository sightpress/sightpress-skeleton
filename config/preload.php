<?php

declare(strict_types=1);

// OPcache preloading (opcache.preload) for the production container.
if (is_file(dirname(__DIR__).'/var/cache/prod/App_KernelProdContainer.preload.php')) {
    require dirname(__DIR__).'/var/cache/prod/App_KernelProdContainer.preload.php';
}
