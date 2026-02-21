# XcaliburMoon Web Development Pricing

**A PHP-based, multi-step quote estimation tool built as a portable framework for web development, web design, software, and AI-driven application services.**

Repository: [https://github.com/crissymoon/form-quote-builder-tool](https://github.com/crissymoon/form-quote-builder-tool)
Portfolio: [https://crissymoon.com](https://crissymoon.com)
Contact: [crissy@xcaliburmoon.net](mailto:crissy@xcaliburmoon.net)

---

<!-- xcm:recent-updates:start -->

## Recent Updates

**Full-Stack Dev**
2026-02-21 | by Crissy
Several Updates - Checking Dev Dashboard

**QuoteValidator and ML model integration**
2026-02-20 | by CrissyMoon
Integrated the trained model into the PHP app. Added src/lib/QuoteValidator.php which runs a two-layer check: (1) deterministic rule check re-implementing th...

**project_mgr note sync**
2026-02-20 | by CrissyMoon
sync_readme.php reads the last four dated notes and writes a Recent Updates block at the top of README.md. add_note.php calls sync automatically after every...

**ML pricing validation model**
2026-02-20 | by CrissyMoon
Added ml/ directory with gen_data.py (generates 2907 labelled rows), quote_math_validation.csv, and quote_math_validator.ipynb (Kaggle notebook). Notebook tr...

<!-- xcm:recent-updates:end -->


## Table of Contents

- [Description](#description)
- [Features](#features)
- [Architecture Overview](#architecture-overview)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Build Process](#build-process)
- [Deployment](#deployment)
- [Security](#security)
- [Optional Modules](#optional-modules)
- [Quote Reference IDs and Data Lifecycle](#quote-reference-ids-and-data-lifecycle)
- [Real-Time Updates and Service Workers](#real-time-updates-and-service-workers)
- [Contributing](#contributing)
- [License](#license)

---

## Description

Xcalibur Moon Web Development Pricing is an open-source, multi-step quote estimation framework built in PHP 8.3+. It guides a prospective client through a structured set of service-related questions covering web development, web design, software engineering, and AI-driven web and native application development, then applies logic-driven pricing rules to produce an estimated price range.

XcaliburMoon creates AI-driven web and native apps with smart automations that address real business challenges and boost revenue. This tool reflects that philosophy by allowing visitors to self-qualify their project needs and receive a reference estimate before engaging in a formal discovery conversation.

At the end of the form, the user receives an estimated price range, a time-limited reference ID, and the option to submit the quote request by email for a formal review and finalized quote.

This project is built as a portable, drop-in framework. A developer configures the form locally using a structured source layout, then runs the `xcm-build-this` Go build tool to compile a deployable, self-contained package that can be placed into any folder on a shared or dedicated hosting environment and function immediately.

---

## Features

- Multi-step quote form with pre-built questions, dropdowns, selects, and option groups
- Logic-driven pricing engine using PHP 8.3+ and Math.js for client-side calculations
- Estimated price output at form completion based on user-selected service options
- Two-layer quote validation: deterministic PHP rule check plus optional ML confidence scoring via a trained DecisionTreeClassifier
- Validation result displayed on the result page showing rule status, model confidence percentage, and any incorrect fields
- Time-limited reference ID generated per quote submission, configurable in the settings file
- Email submission via PHPMailer supporting TLS, SMTP with credentials, or server-level no-reply delivery
- JSON-based data storage with no database dependency, allowing full compatibility with the Xcalibur Agent
- Expired quote data is summarized and archived into a structured clean data file for future model training
- Kaggle notebook (`ml/quote_math_validator.ipynb`) trains and evaluates the pricing validation model and exports a portable pkl bundle
- Go-based build tool (`xcm-build-this`) that compiles the full deployable package from source
- Service worker integration for real-time, instant updates without requiring users to clear cache or history
- Versioned asset output with cache-busting built into every build
- Load animation included in the deployed output for asset-heavy states
- Drop-in deployment to any `/folder` on shared or dedicated hosting
- `.htaccess` generated at build time to block direct access to JSON files and enforce routing rules
- All sensitive credentials must be stored outside the web root and document root
- Compatible with NGINX and Apache server configurations
- Compatible with all popular hosting providers
- Framework approach allowing full local customization of form steps, questions, and pricing logic before building

---

## Architecture Overview

The project separates three distinct layers.

**Local Development Layer**
The developer works inside the source directory, editing form configuration, pricing logic, service options, and settings. No build artifacts exist here. Everything is authored in a structured, readable format.

**Build Layer**
The `xcm-build-this` tool, located in `build_this/`, reads the source directory and compiles a fully optimized, self-contained deployment package. It generates PHP files, JavaScript, CSS, service worker files, versioned asset hashes, and the `.htaccess` file. It also produces a `deploy/README.md` documenting the version and all technologies included in that build.

**Deployment Layer**
The contents of `deploy/this/` are uploaded or copied to the target server folder. The application runs immediately on any server meeting the PHP 8.3+ requirement. No build tools, compilers, or additional software are required on the host server.

---

## Project Structure

The following structure represents the local development environment before a build is run.

```
/
|-- src/                    # Form steps, pricing logic, PHP templates
|   |-- lib/
|       |-- QuoteEngine.php       # Pricing formula implementation
|       |-- QuoteValidator.php    # Two-layer validation (rule + ML)
|       |-- FormSteps.php
|       |-- ReferenceID.php
|-- config/                 # Settings files, mailer configuration
|-- modules/                # Optional compiled modules (Rust or C23)
|-- data/                   # JSON quote storage and clean data archive
|-- assets/                 # Stylesheets, JavaScript, and static resources
|-- ml/                     # Machine learning pricing validation
|   |-- gen_data.py               # Generates labelled training CSV (2907 rows)
|   |-- quote_math_validation.csv # Training and validation dataset
|   |-- quote_math_validator.ipynb# Kaggle notebook: train, evaluate, save model
|   |-- validate_quote.py         # CLI wrapper called by QuoteValidator.php
|   |-- quote_math_model.pkl      # Trained DecisionTreeClassifier bundle
|   |-- verify.py                 # Local dev smoke test for the pkl
|-- design_research/        # Research notes and design context files
|-- build_this/             # xcm-build-this Go build tool
|-- deploy/                 # Output directory created by xcm-build-this
|   |-- README.md           # Auto-generated build version and dependency log
|   |-- this/               # Deployable package, upload this folder to your server
|       |-- .htaccess
|       |-- index.php
|       |-- assets/
|       |-- (all compiled and optimized files)
|-- project_mgr/            # Repo management notes and contributor tracking
|-- LICENSE
|-- README.md
```

Sensitive credentials, SMTP passwords, and API keys must never be stored inside any folder within the web root or document root. Store them in a location above the public directory and reference them by absolute path inside the configuration files.

---

## Requirements

**Server Requirements**

- PHP 8.3 or higher
- NGINX or Apache web server
- The server must allow `.htaccess` overrides (Apache) or equivalent rewrite rules (NGINX)
- Direct file access to JSON data files must be blocked at the server level

**Build Requirements (local only)**

- Go (for running `xcm-build-this`)
- A Makefile-compatible build environment (for optional compiled modules)

**Frontend Dependencies (resolved at build time)**

- Tailwind CSS
- Math.js
- Vanilla JavaScript

**PHP Dependencies**

- PHPMailer

---

## Installation

### Step 1: Clone the Repository

```bash
git clone https://github.com/crissymoon/form-quote-builder-tool.git
cd form-quote-builder-tool
```

### Step 2: Install PHP Dependencies

Install PHPMailer via Composer inside the `src/` directory or at the project root, depending on your setup.

```bash
composer require phpmailer/phpmailer
```

### Step 3: Configure the Application

See the [Configuration](#configuration) section before proceeding to the build step.

### Step 4: Run the Build Tool

Navigate to the `build_this/` directory and run `xcm-build-this` to compile the deployment package.

```bash
cd build_this
go run xcm-build-this
```

The build output will be placed in `deploy/this/`.

### Step 5: Deploy

Upload the contents of `deploy/this/` to the desired folder on your server. The application will be active immediately.

---

## Configuration

All configuration is managed through files in the `config/` directory. No sensitive values should be hardcoded into any PHP file inside the web root.

### Mailer Configuration

The mailer supports three delivery modes. Set the mode and provide the corresponding values in your config file.

| Setting | Description |
|---|---|
| `MAIL_MODE` | Set to `smtp`, `tls`, or `server` |
| `SMTP_HOST` | Your SMTP server hostname |
| `SMTP_PORT` | Port number (typically 587 for TLS, 465 for SSL) |
| `SMTP_USERNAME` | SMTP account username |
| `SMTP_PASSWORD` | SMTP account password, stored outside the web root |
| `MAIL_FROM` | The no-reply or sender email address |
| `MAIL_TO` | The destination address that receives submitted quote requests |

When using the `server` mode, PHPMailer will use the server's native mail function without SMTP credentials. This is suitable for environments where a no-reply address is configured at the server level.

### Quote Reference ID Configuration

The reference ID expiration period is set in the main settings file.

| Setting | Description |
|---|---|
| `REFERENCE_EXPIRY_DAYS` | Number of days before a reference ID expires (example: 7 or 14) |

Technology pricing shifts frequently. The expiration window is intentional and keeps quote references within a period of reasonable accuracy.

### Credential Storage Policy

All values marked as sensitive, including SMTP passwords, API keys, and any future integration tokens, must be stored in a file located outside the web root and document root. Reference them using an absolute server path. This is a hard requirement and not optional.

---

## Build Process

The build tool is `xcm-build-this` and it lives in the `build_this/` folder.

When executed, it performs the following operations.

1. Reads source files from `src/`, `assets/`, and `config/`
2. Compiles and minifies CSS using Tailwind CSS directives
3. Bundles and versions all JavaScript assets with cache-busting hashes
4. Generates service worker files for real-time update delivery
5. Compiles all PHP templates into a clean, portable output
6. Generates the `.htaccess` file for JSON access control and routing
7. Outputs the complete package to `deploy/this/`
8. Generates `deploy/README.md` with the build version, timestamp, and a full list of technologies and versions included in that build

The deployed output in `deploy/this/` contains only what is necessary to run the application. No development files, source maps beyond what is useful, or build tooling is included in the output.

A load animation is included in the deployed output and activates when the application detects that heavier assets require time to initialize.

---

## Deployment

Upload the contents of `deploy/this/` to any folder on your server. The application requires no special server configuration beyond PHP 8.3+ and either NGINX or Apache with rewrite support enabled.

The generated `.htaccess` file handles the following at the application level.

- Blocking direct HTTP access to JSON data files
- Enforcing routing rules required by the application

The application is designed to be compatible with all popular shared and dedicated hosting providers. If you encounter a provider-specific issue, open an issue in the repository and document the hosting environment.

---

## Security

Security in this project is distributed across two layers: the PHP application layer and the server configuration layer.

### PHP Layer Responsibilities

- Input validation and sanitization on all form submissions
- Reference ID generation and expiration enforcement
- Access control for all data read and write operations
- Restricting email submission to validated, complete quote payloads
- Enforcing credential storage outside the web root

### Server and `.htaccess` Layer Responsibilities

- Blocking direct browser access to all `.json` files in the `data/` directory
- Enforcing directory index rules
- Preventing access to sensitive configuration paths

### Contributor Security Requirement

When contributing, do not commit any credentials, SMTP passwords, API keys, or local path references into the repository. The `.gitignore` file should be respected at all times. If you discover a security vulnerability, contact the maintainer directly at [crissy@xcaliburmoon.net](mailto:crissy@xcaliburmoon.net) before opening a public issue.

---

## Optional Modules

Optional modules extend the core application with compiled, cross-platform functionality. All optional modules must be written in a language that compiles to a native binary and runs consistently across server environments. The supported languages for optional modules are Rust and C23.

All optional modules use a Makefile as the standard build entry point.

```bash
cd modules/module-name
make
```

### Quote Math Validation Model (Active)

A trained `DecisionTreeClassifier` validates every quote the engine produces. The model and its supporting tooling live in the `ml/` directory and are active in the current codebase.

**How it works**

The `ml/` directory contains:

- `gen_data.py` — generates a labelled CSV of 2907 rows covering every service, complexity, and addon combination, with approximately 15% intentionally miscalculated rows.
- `quote_math_validation.csv` — the training and validation dataset, also published as a Kaggle dataset at `crissymoon/quote-math-validation`.
- `quote_math_validator.ipynb` — a Kaggle notebook that loads the CSV, engineers derived features (`expected_subtotal`, `low_delta`, `high_delta`), trains the model, evaluates it, exposes `predict_quote_detail()` with confidence scoring, and saves the trained bundle to `quote_math_model.pkl`.
- `validate_quote.py` — a CLI script that loads the pkl and accepts quote parameters as arguments, returning a single JSON line to stdout.
- `quote_math_model.pkl` — the serialised bundle containing the trained model, label encoders, feature list, and all pricing constants.

**Integration with the PHP app**

`src/lib/QuoteValidator.php` runs two checks on every submitted estimate:

1. **Rule check** — re-implements the QuoteEngine formula in PHP and verifies claimed subtotal and range values exactly.
2. **ML check** — calls `validate_quote.py` via `shell_exec()` and reads the JSON confidence score. Falls back gracefully if Python or the pkl is unavailable on the server.

The combined result is stored with the quote record and displayed on the result page as a Math Verified or Calculation Error Detected badge.

### Data Cleaning and Summarization Module (Planned)

When a reference ID expires, the associated quote data is not discarded. It is moved through a summarization process and written to a structured clean data file in the `data/` directory.

The data cleaning and summarization pipeline will be implemented as a compiled module written in Rust or C23. It operates independently from the PHP application, reads expired quote records from the JSON store, applies summarization and behavioral pattern analysis, and writes the output to the clean data archive.

All optional modules must be portable and build cleanly on Linux, macOS, and Windows server environments using the provided Makefile.

---

## Quote Reference IDs and Data Lifecycle

When a user completes the quote form and submits their request, the system generates a unique reference ID. This ID is tied to the quote record stored in the JSON data file and is valid for a period defined in the settings configuration.

**Active State**
The reference ID is valid and the quote record is accessible. The user can reference this ID when following up by email.

**Expired State**
Once the configured expiration period passes, the reference ID is no longer valid for user lookup. The quote record is flagged for archival processing. The data cleaning module reads the expired record, produces a summarized entry capturing relevant behavioral and preference data, and writes it to the clean data archive. The original record is then marked as processed.

**Archived State**
The summarized record lives in the clean data file. It is structured for future use in training a pricing model. No personally identifying information that is not required for model training should be retained in the archive.

The expiration period is intentionally configurable because technology service pricing changes frequently. A quote from three weeks ago may not reflect current rates, and the reference ID system enforces that boundary automatically.

---

## Real-Time Updates and Service Workers

The deployed application uses service workers to deliver real-time updates to the end user without requiring any manual cache clearing, browser refresh, or history management. This behavior is a core requirement of the deployment output.

Service worker files are generated automatically by `xcm-build-this` during the build process. They are not manually authored and should not be edited directly in the `deploy/this/` directory, as they will be overwritten on the next build.

All JavaScript and CSS assets are output with versioned filenames containing build-time hashes. This ensures the service worker references the correct asset versions on every update and that users receive the latest build the moment it is deployed.

End users interacting with the finished application will never need to manually clear their browser cache or history to see updates.

---

## Contributing

Contributions are welcome. This project follows an open suggestion model. If you have found a newer technology, a more efficient approach, or a pattern that integrates cleanly with the existing architecture, you are encouraged to propose it.

### How to Contribute

1. Fork the repository at [https://github.com/crissymoon/form-quote-builder-tool](https://github.com/crissymoon/form-quote-builder-tool)
2. Create a new branch for your change
3. Make your changes and test them thoroughly
4. Open a pull request with a clear description of what was changed and why

### Project Management Notes

The [project_mgr/](project_mgr/) folder contains repository management notes and contributor tracking. If you are not the repository owner, all updates inside that folder must be submitted by pull request so the additions can be reviewed before they are used. When a note is added, the contributor must also be recorded in the contributor list located in the same folder.

### Contributor Testing Requirement

Every pull request must include a brief description of the environment the contributor tested on. Include the following where applicable.

- Operating system and version
- Server software and version (Apache, NGINX, or shared hosting provider name)
- PHP version
- Hosting type (shared, VPS, dedicated, local dev)

This requirement exists because the build output is expected to work across all popular hosting providers and environments. Documented testing helps maintain that compatibility.

### Suggesting Alternatives

If a newer library, tool, or approach exists that would replace a current dependency and integrate seamlessly with the project, open an issue or pull request with the proposal clearly documented. No suggestion is out of scope if it improves the project without creating unnecessary complexity or breaking existing behavior.

### Optional Module Contributions

If you are contributing an optional compiled module, it must be written in Rust or C23, include a Makefile as the build entry point, and build cleanly on Linux, macOS, and Windows without modification.

---

## License

This project is licensed under the MIT License.

Copyright 2025 XcaliburMoon Web Development

The MIT License file is included in the root of this repository. All contributors must retain this license in any derivative works or forks.