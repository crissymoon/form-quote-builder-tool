# Optional Modules

This directory contains optional compiled modules that extend the core application.

All modules must be written in Rust or C23. All modules must include a Makefile as the build entry point and must build cleanly on Linux, macOS, and Windows without modification.

## Planned Modules

### data-cleaner

Reads expired quote records from the JSON data store, applies summarization and behavioral pattern analysis, and writes the output to the clean data archive in `data/clean_data.json`.

This module is planned. It will be implemented in Rust or C23.

## Building a Module

```
cd modules/module-name
make
```

The compiled binary is placed in the module directory. The PHP application invokes it as needed.
