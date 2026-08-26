# psalm-plugin-magento2

Stops Psalm's `UnusedClass` / `UnusedMethod` / `PossiblyUnusedMethod`
checks from false-positiving on Magento 2 classes that the framework only
ever instantiates reflectively — by class name in `routes.xml`, layout
XML, `crontab.xml` or `di.xml` — never via a direct `new X()` or a
type-hint Psalm's static analysis can trace back to a caller.

With Psalm's `findUnusedCode` on by default (Psalm ≥5), a class like this:

```php
class Index extends \Magento\Customer\Controller\AbstractAccount
{
    public function execute()
    {
        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
    }
}
```

gets flagged `UnusedClass: Class ... is never used` even though Magento's
front controller invokes it on every request to its route — Psalm just
has no way to see `routes.xml` wiring a URL to this class name.

## What it covers

Matched by contract, wherever Magento provides one (interface or common
base class) — works regardless of which module or folder the class lives
in:

| Category | Matched by |
|---|---|
| Controllers | extends/implements `Magento\Framework\App\ActionInterface`, `Action`, `AbstractAction`, or `\Magento\Customer\Controller\AbstractAccount` |
| Blocks | extends `Magento\Framework\View\Element\AbstractBlock` or `Template` |
| Observers | implements `Magento\Framework\Event\ObserverInterface` |
| Setup patches | implements `DataPatchInterface` / `SchemaPatchInterface` |
| Console commands | extends `Symfony\Component\Console\Command\Command` |

Cron jobs and Plugins (interceptors) have **no common Magento
interface/base class** — `crontab.xml`/`di.xml` reference a bare class
name + method by convention only. Those two fall back to a namespace
segment check (FQCN contains `\Cron\` or `\Plugin\`) — the one
convention-based (not contract-based) rule here.

## Known limitation

Only directly declared `extends`/parent class and directly implemented
interfaces are inspected, not multi-level-inherited ones. A
project-specific `AbstractController implements ActionInterface`, with
real controllers extending *that* instead of a Magento class directly,
won't be picked up. Open an issue/PR if this bites you.

## Install

```bash
composer require --dev webgriffe/psalm-plugin-magento2
```

Then wire it into `psalm.xml`:

```xml
<plugins>
    <pluginClass class="Webgriffe\PsalmPluginMagento2\PluginEntryPoint" />
</plugins>
```

## Why not just `findUnusedCode="false"`?

That silences dead-code detection for the *entire* codebase — including
genuinely dead Helper/Model/Service/Repository classes, which is exactly
the kind of thing Psalm's unused-code detection is useful for. This
plugin only exempts the specific categories Magento wires outside PHP's
own reference graph, so the rest of the codebase keeps real dead-code
detection.

## Compatibility note for phar-based Psalm distributions

Some Psalm distributions (e.g. tools that bundle Psalm as a single phar
with its dependencies scoped under a private namespace prefix, to avoid
colliding with the host project's own Composer dependencies) rewrite
`nikic/php-parser`'s classes under a prefix like `PsalmPhar\PhpParser\...`.
An `instanceof \PhpParser\Node\Stmt\Class_` check silently never matches
in that case, even though the visited node's shape is identical. This
plugin matches on the resolved `Psalm\Storage\ClassLikeStorage` data
(`parent_class`, `class_implements`) and a class-name suffix check
instead, which works under both plain Composer-installed Psalm and
phar-scoped distributions.

## License

MIT
