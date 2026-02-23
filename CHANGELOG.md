# Changelog

All notable changes to `laravel-flare` will be documented in this file

## 2.7.1 - 2026-02-23

**Full Changelog**: https://github.com/spatie/laravel-flare/compare/2.7.0...2.7.1

## 2.7.0 - 2026-01-14

### What's Changed

* Add support for Livewire v4 by @joshhanley in https://github.com/spatie/laravel-flare/pull/31

**Full Changelog**: https://github.com/spatie/laravel-flare/compare/2.6.2...2.7.0

## 2.6.2 - 2026-01-08

- Add support for censoring cookies and session

## 2.6.1 - 2025-12-19

- Fix issue where test command would early return before checking all report callbacks

## 2.6.0 - 2025-12-19

- Allow configuring request & console attribute providers

## 2.5.1 - 2025-11-13

### What's Changed

* Guard custom flare.collects entries to prevent configuration crashes by @imhayatunnabi in https://github.com/spatie/laravel-flare/pull/24

**Full Changelog**: https://github.com/spatie/laravel-flare/compare/2.5.0...2.5.1

## 2.5.0 - 2025-11-07

- Add better context support in traces

## 2.4.2 - 2025-11-07

- Fix issues with the Livewire collector not working when app_debug was false

## 2.4.1 - 2025-11-06

- Allow using the legacy git integration

## 2.4.0 - 2025-11-05

- Add support for livewire tracing
- Add support for better telemetry versions

## 2.3.1 - 2025-10-13

- Fix issue where merging extra config did not work with non array options

## 2.3.0 - 2025-10-08

- Refactor the recorders

## 2.2.4 - 2025-10-06

- Fix an issue where jobs on vapor were sampled but not sent to Flare

## 2.2.3 - 2025-10-06

Fixed: streamed responses in external HTTP calls are no longer read to determine response size. This solves compatibility issues with PrismPHP and other libraries that use Laravel's HTTP client to stream responses.

**Full Changelog**: https://github.com/spatie/laravel-flare/compare/2.2.2...2.2.3

## 2.2.2 - 2025-10-01

**Full Changelog**: https://github.com/spatie/laravel-flare/compare/2.2.1...2.2.2

## 2.2.1 - 2025-10-01

### What's Changed

* Update issue template by @AlexVanderbist in https://github.com/spatie/laravel-flare/pull/16

### New Contributors

* @AlexVanderbist made their first contribution in https://github.com/spatie/laravel-flare/pull/16

**Full Changelog**: https://github.com/spatie/laravel-flare/compare/2.2.0...2.2.1

## 2.2.0 - 2025-09-11

- Don't reset Flare context when using sync queues

**Full Changelog**: https://github.com/spatie/laravel-flare/compare/2.1.2...2.2.0

## 2.1.1 - 2025-08-27

### What's Changed

* Move registerShareButton call in FlareServiceProvider by @marventhieme in https://github.com/spatie/laravel-flare/pull/14

**Full Changelog**: https://github.com/spatie/laravel-flare/compare/2.1.0...2.1.1

## 2.1.0 - 2025-08-27

- Fix vapor issues
- Allow logs to have stack traces

## 2.0.7 - 2025-07-17

- Fix issues with performance monitoring on Octane

## 2.0.6 - 2025-06-24

- Fix an issue where bodies of requests were missing when an error was reported
- Add support for completely disabling Flare when no key is set

## 2.0.5 - 2025-06-16

### What's Changed

* Allow enabling trace by .env variable by @mbardelmeijer in https://github.com/spatie/laravel-flare/pull/9

**Full Changelog**: https://github.com/spatie/laravel-flare/compare/2.0.4...2.0.5

## 2.0.4 - 2025-06-10

- Support old Laravel skeletons

## 2.0.3 - 2025-05-23

- Fix an error with a route not being ended well

## 2.0.2 - 2025-05-21

- Use correct base url

## 2.0.1 - 2025-05-20

- Use correct dependency

## 2.0.0 - 2025-05-20

A complete new release!

- Please read the upgrade guide and use the new config file instead of the old one
- We've also updated our docs with all the new features

## 1.1.2 - 2025-03-01

### What's Changed

* Laravel 12 support by @dbfx in https://github.com/spatie/laravel-flare/pull/5

### New Contributors

* @dbfx made their first contribution in https://github.com/spatie/laravel-flare/pull/5

**Full Changelog**: https://github.com/spatie/laravel-flare/compare/1.1.1...1.1.2

## 1.1.1 - 2025-01-20

### What's Changed

* Added null option to fix PHP 8.4 deprecation notices by @chrispage1 in https://github.com/spatie/laravel-flare/pull/3

### New Contributors

* @chrispage1 made their first contribution in https://github.com/spatie/laravel-flare/pull/3

**Full Changelog**: https://github.com/spatie/laravel-flare/compare/1.1.0...1.1.1

## 1.1.0 - 2024-12-02

- Add support for overriding grouping

## 1.0.0 - 2024-06-??
