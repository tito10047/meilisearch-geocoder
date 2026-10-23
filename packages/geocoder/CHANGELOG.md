# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- First version of the geocoder.
- `Import\File\ExportAwareConverter`: one Importer indexes the directories of exports and the
  dumps alike.
- `PdoNameCatalog::reset()` forgets the remembered translations (for long-running workers).
