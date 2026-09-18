# AGENTS.md

Instructions for AI coding agents working on this repository.

## What this is

A Pimcore bundle that renders the label of related elements in relation fields
(Many-to-Many, Many-to-One, Advanced Many-to-Many, …) from a YAML pattern instead of
requiring a hand-written `PathFormatterInterface` class per use case.

There is exactly one runtime entry point:
`Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter::formatPath()`.
It picks a pattern and caches the result; `Formatter\PatternRenderer` turns a pattern into a
string.

## Hard constraints

- **The service ID is stored in class definitions.** Editors type
  `@Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter` into the
  *Formatter* field of a relation field, and Pimcore persists that string in the class
  definition PHP/JSON. Renaming or moving the class silently breaks every project that
  uses it. The `DependencyInjection` namespace is historical and wrong — leave it.
- **Never return `null` entries from `formatPath()`.** Pimcore Studio's `FormatedPath`
  DTO types the path as `int|string`; a `null` becomes a `TypeError` in
  `/pimcore-studio/api/data-objects/format-path`. Every target must get a string, worst
  case its own path.
- **`Concrete::setGetInheritedValues()` is global request state.** It must be restored in
  a `finally`, otherwise a throwing getter leaves inheritance enabled for the rest of the
  request — including saves.
- **Resolved property values are HTML-escaped, patterns are not.** Values come from
  editors and land in the classic admin grid as HTML. The `<img>` preview the bundle
  builds itself is the only markup that stays raw.
- Formatted paths are cached in the Pimcore cache, tagged with every element touched
  while resolving a pattern. If you add a new way to reach data, add its cache tag too or
  labels go stale. `{{ … }}` expressions are the known exception — they cannot report what
  they traversed, which is why the README tells people to use `{…}` for other elements.
- **Rendered values never go through a second pass.** Every resolved value is parked in a
  `RenderState` slot, because a product named `{name}` must not be read as a placeholder.
- Pattern syntax is `{property.path}`, `{@translation.key}`, `{{ expression }}` and
  `[[ optional part ]]`. Single brackets are literal — patterns like `"[{countryIso}] {name}"`
  are common in the wild and must keep working.

## How Pimcore calls the formatter

Three call sites, with different `$params`:

| Caller | `$targets` | `$params['context']` |
|---|---|---|
| `AdminBundle\Controller\Admin\ElementController::getNicePathAction` (classic UI) | all targets of one field, keyed by element id | present, has `fieldname` |
| `StudioBackendBundle\DataObject\Service\PathFormatterService::convert` | all targets of one field, keyed by `object_<id>` | present, has `fieldname` |
| `ClassDefinition\Data\Relations\AbstractRelations::getNicePath` (object grids) | **one** target, key `0` | **may be absent** |

Never assume `$params['context']` exists, and never assume `$targets` keys are numeric.

## Layout

- `DependencyInjection/BasilicomPathFormatter` — picks a pattern, caches, owns the inheritance flag
- `Formatter/PatternRenderer` — turns a pattern plus an element into a string
- `Formatter/ExpressionFunctions` — the helpers available inside `{{ … }}`
- `Command/DebugPathFormatterCommand` — `basilicom:path-formatter:debug`

## Commands

```bash
make test               # phpunit in the pimcore docker image
make lint-fix           # php-cs-fixer, also adds the license header to new files
make lint               # php-cs-fixer --dry-run + phpstan
```

Locally, `php vendor/bin/phpunit --configuration ./tests/phpunit.xml` works too — the
test bootstrap boots Pimcore from `vendor/`, no database needed.

## Conventions

- `src/` is PSR-12 plus aligned `=>`/`=` (see `.php-cs-fixer.dist.php`), PHPStan level 6.
- Tests use `// prepare` / `// test` / `// verify` blocks and PHPUnit attributes.
- DataObject test fixtures live in `tests/fixtures` and are autoloaded into the
  `Pimcore\Model\DataObject\` namespace via `autoload-dev`.
- Comments explain *why*, never *what*. One or two lines.
- Mockery `overload:` mocks alias a class for the whole PHP process, so the same class can
  only be overloaded once across the entire suite. `Asset\Image` belongs to
  `PatternRendererTest`; other tests must not redefine it.
- `PimcoreAdapter` is the seam for Pimcore's static API (`DataObject::getById`, `Cache::load`).
  New static calls go through it so they stay mockable.
