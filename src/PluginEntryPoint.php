<?php

declare(strict_types=1);

namespace Webgriffe\PsalmPluginMagento2;

use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;

/**
 * Composer/psalm.xml entry point. Registered via:
 *
 *   <plugins>
 *       <pluginClass class="Webgriffe\PsalmPluginMagento2\PluginEntryPoint" />
 *   </plugins>
 *
 * The actual detection logic lives in {@see MagentoUnusedCodePlugin}, which
 * implements Psalm's `AfterClassLikeVisitInterface` hook directly.
 *
 * @psalm-suppress UnusedClass — loaded via composer.json's `extra.psalm.pluginClass`,
 * not referenced anywhere in PHP. Deliberately not handled by this package's
 * own matching logic, which targets Magento conventions, not Psalm's own.
 */
final class PluginEntryPoint implements PluginEntryPointInterface
{
    #[\Override]
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        // registerHooksFromClass() requires the class to already be loaded
        // (it checks class_exists($handler, false) — no autoload trigger).
        // Composer's autoloader may not have resolved it yet at this point,
        // so require it explicitly rather than relying on autoload timing.
        require_once __DIR__ . '/MagentoUnusedCodePlugin.php';

        $registration->registerHooksFromClass(MagentoUnusedCodePlugin::class);
    }
}
