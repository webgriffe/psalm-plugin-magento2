<?php

declare(strict_types=1);

namespace Webgriffe\PsalmPluginMagento2;

use Psalm\Plugin\EventHandler\AfterClassLikeVisitInterface;
use Psalm\Plugin\EventHandler\Event\AfterClassLikeVisitEvent;
use Psalm\Storage\ClassLikeStorage;

/**
 * Marks Magento 2 framework-instantiated classes as Psalm's "public API" so
 * they (and their public methods) are never flagged as UnusedClass /
 * UnusedMethod / PossiblyUnusedMethod.
 *
 * Magento wires these classes reflectively — by class name in routes.xml,
 * layout XML, crontab.xml or di.xml — never via a direct `new X()` or a
 * type-hint Psalm's static analysis can trace back to a caller. From
 * Psalm's point of view they have zero references and look dead, even
 * though the framework calls them on every request/cron run.
 *
 * Matched by contract where Magento provides one (interface or common base
 * class — works regardless of which module/folder the class lives in).
 * Cron jobs and Plugins (interceptors) have no such contract in Magento —
 * crontab.xml/di.xml reference a bare class name + method by convention
 * only — so those two fall back to a namespace-segment check
 * (`\Cron\` / `\Plugin\`). That is the one convention-based (not
 * contract-based) rule here; everything else matches by inheritance.
 *
 * Only directly declared `extends`/`implements` on the visited class are
 * inspected (not multi-level-inherited ones). If a project introduces its
 * own intermediate base class (e.g. `AbstractController implements
 * ActionInterface`, with real controllers extending that instead of a
 * Magento class directly), this plugin won't see it. No known Webgriffe
 * module needs that yet.
 *
 * @see https://psalm.dev/docs/running_psalm/plugins/authoring_plugins/
 */
final class MagentoUnusedCodePlugin implements AfterClassLikeVisitInterface
{
    private const BASE_CLASSES = [
        'magento\framework\app\action\action',
        'magento\framework\app\action\abstractaction',
        'magento\customer\controller\abstractaccount',
        'magento\framework\view\element\abstractblock',
        'magento\framework\view\element\template',
        'symfony\component\console\command\command',
    ];

    private const INTERFACES = [
        'magento\framework\app\actioninterface',
        'magento\framework\event\observerinterface',
        'magento\framework\setup\patch\datapatchinterface',
        'magento\framework\setup\patch\schemapatchinterface',
    ];

    /** FQCN namespace segments with no common Magento interface/base class to key off. */
    private const CONVENTION_NAMESPACE_SEGMENTS = [
        '\\cron\\',
        '\\plugin\\',
    ];

    #[\Override]
    public static function afterClassLikeVisit(AfterClassLikeVisitEvent $event): void
    {
        $stmt = $event->getStmt();

        // Duck-typed, not `instanceof PhpParser\Node\Stmt\Class_`: some Psalm
        // distributions (e.g. Webgriffe's own phar-scoped `*-psalm-dist`
        // packages) rewrite nikic/php-parser's classes under a private
        // namespace prefix (`PsalmPhar\PhpParser\...`) to avoid collisions
        // with the host project's own dependencies. `instanceof` against the
        // unprefixed class silently never matches there, even though the
        // object's shape (and its `extends`/`implements`/`name` properties)
        // is identical. Matching on the class-name suffix works regardless
        // of prefixing.
        if (!str_ends_with($stmt::class, '\\Stmt\\Class_')) {
            return;
        }

        $storage = $event->getStorage();
        $fqcnLower = strtolower($storage->name);

        if (self::matchesBaseClass($storage) || self::matchesInterface($storage) || self::matchesConvention($fqcnLower)) {
            $storage->public_api = true;
        }
    }

    private static function matchesBaseClass(ClassLikeStorage $storage): bool
    {
        if ($storage->parent_class === null) {
            return false;
        }

        return in_array(strtolower($storage->parent_class), self::BASE_CLASSES, true);
    }

    private static function matchesInterface(ClassLikeStorage $storage): bool
    {
        foreach ($storage->class_implements as $interface) {
            if (in_array(strtolower($interface), self::INTERFACES, true)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesConvention(string $fqcnLower): bool
    {
        foreach (self::CONVENTION_NAMESPACE_SEGMENTS as $segment) {
            if (str_contains('\\' . $fqcnLower . '\\', $segment)) {
                return true;
            }
        }

        return false;
    }
}
