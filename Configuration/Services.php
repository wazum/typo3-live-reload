<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Adminpanel\ModuleApi\AbstractModule;
use Wazum\LiveReload\AdminPanel\BroadcastsInformation;
use Wazum\LiveReload\AdminPanel\CacheTagsInformation;
use Wazum\LiveReload\AdminPanel\LiveReloadModule;
use Wazum\LiveReload\AdminPanel\StatusInformation;

return static function (ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void {
    if (!class_exists(AbstractModule::class)) {
        return;
    }

    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->set(LiveReloadModule::class);
    $services->set(StatusInformation::class);
    $services->set(CacheTagsInformation::class);
    $services->set(BroadcastsInformation::class);
};
