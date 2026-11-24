# Changelog

## [0.3.0] - 2026-11-24

### Added
 * `TranslatedExpression::getQueryParameters()` and `$values` argument for `TranslatedExpression::getCondition()`.
 * An example for two-variable expression in [README.md](./README.md#expression-with-multiple-variables).

## [0.2.0] - 2026-11-20

### Added
 * `Parameter` attribute that maps to `ParameterTranslator`: can be applied to properties that do not map to
   database objects and can only be used for reference.
 * `Subselect` and `ScalarSubselect` attributes that map properties to subselects from tables having
   foreign keys relationships with the base table. These are backed by 
   `translators\properties\SubselectLookupTranslator` and either `translators\properties\SubselectReferenceTranslator`
   or `translators\properties\ParameterTranslator`.
 * Translation of `ends with` operator.

### Changed
 * Depend on stable `pg_gateway` package
 * Translators for `min()` / `max()` / `is_null()` will throw exceptions for non-scalar arguments
   rather than silently wrap them in `SubselectExpression`.


## [0.1.0] - 2026-11-12

Initial release on GitHub.

[0.1.0]: https://github.com/sad-spirit/el2sql/releases/tag/v0.1.0
[0.2.0]: https://github.com/sad-spirit/pg-gateway/compare/v0.1.0...v0.2.0
[0.3.0]: https://github.com/sad-spirit/pg-gateway/compare/v0.2.0...v0.3.0
