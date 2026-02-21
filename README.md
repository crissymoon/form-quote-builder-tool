# XcaliburMoon Web Development Pricing

**A PHP-based, multi-step quote estimation tool built as a portable framework for web development, web design, software, and AI-driven application services.**

Repository: [https://github.com/crissymoon/form-quote-builder-tool](https://github.com/crissymoon/form-quote-builder-tool)
Portfolio: [https://crissymoon.com](https://crissymoon.com)
Contact: [crissy@xcaliburmoon.net](mailto:crissy@xcaliburmoon.net)
Live: [https://xcaliburmoon.net/this_xcaliburmoon_web_development_pricing/](https://xcaliburmoon.net/this_xcaliburmoon_web_development_pricing/)

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

Xcalibur Moon Web Development Pricing is an open-source, multi-step quote estimation framework built in PHP. It guides a prospective client through a structured set of service-related questions covering web development, web design, software engineering, and AI-driven web and native application development, then applies logic-driven pricing rules to produce a tiered estimate.

XcaliburMoon creates AI-driven web and native apps with smart automations that address real business challenges and boost revenue. This tool reflects that philosophy by allowing visitors to self-qualify their project needs and receive a reference estimate before engaging in a formal discovery conversation.

A live instance is running at [https://xcaliburmoon.net/this_xcaliburmoon_web_development_pricing/](https://xcaliburmoon.net/this_xcaliburmoon_web_development_pricing/). Visitors can walk through the form, receive a tiered estimate with a cost breakdown, and submit their information to receive a formal quote by email.

The form is configured entirely through a local form builder dashboard. A developer opens the builder, sets up services, complexity tiers, add-ons, contact fields, and styling, saves the form, then runs the `xcm-build-this` Go build tool to compile a deployable, self-contained package that can be placed into any folder on a shared or dedicated hosting environment and function immediately with no framework dependencies, no build tools on the server, and no database.

---

## Features

- Form Builder dashboard (`/form-builder`) for configuring services, complexity options, add-ons, contact fields, theme colors, fonts, and all copy without editing any PHP
- Multi-step quote form with client-side navigation -- no full page reloads between steps
- Self-contained preview renderer: all CSS and JS are inlined into a single PHP file with no external stylesheet or script dependencies
- Tiered estimate output (Basic, Standard, Premium) with an itemized cost breakdown at form completion
- Live preview in the form builder so changes are visible before building
- Themed HTML emails sent on submission -- one to the business, one to the user as a confirmation
- Email delivery via PHPMailer supporting SSL (port 465), TLS/STARTTLS (port 587), plain SMTP, or server-level sendmail
- Browser console diagnostics surfaced on email delivery failures for debugging without server log access
- iOS-safe email input with `autocapitalize`, `autocorrect`, and `inputmode` attributes and a client-side sanitizer that handles iOS autofill and Contacts-format values
- JSON-based submission storage with no database dependency
- Configurable toggles to enable or disable submission saving and email delivery independently
- Demo mode at `?demo=1` that renders a pre-populated result view through the same renderer as the live form
- Two-layer quote validation: deterministic PHP rule check plus optional ML confidence scoring via a trained DecisionTreeClassifier
- Time-limited reference ID generated per quote submission, configurable in the settings file
- Expired quote data summarized and archived into a structured clean data file for future model training
- Kaggle notebook (`ml/quote_math_validator.ipynb`) trains and evaluates the pricing validation model and exports a portable pkl bundle
- Go-based build tool (`xcm-build-this`) that compiles the full deployable package from source
- Service worker integration for real-time updates without requiring users to clear cache
- Versioned asset output with cache-busting built into every build
- Drop-in deployment to any `/folder` on shared or dedicated hosting
- `.htaccess` generated at build time to block direct access to JSON files and enforce routing rules
- Compatible with NGINX and Apache; tested on Hostinger shared hosting

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
|-- src/                          # Application source
|   |-- builder/
|       |-- preview.php           # Self-contained quote form renderer (live form + form builder preview)
|       |-- index.php             # Form Builder dashboard entry point
|       |-- ui.php                # Form Builder interface
|   |-- lib/
|       |-- QuoteEngine.php       # Pricing formula implementation
|       |-- QuoteValidator.php    # Two-layer validation (rule + ML)
|       |-- FormSteps.php
|       |-- ReferenceID.php
|   |-- templates/                # Legacy server-side templates (kept for reference)
|   |-- index.php                 # Public entry point
|-- config/                       # Settings files and mailer configuration
|-- modules/                      # Optional compiled modules (Rust or C23)
|-- data/
|   |-- forms/                    # Saved form configurations from the form builder
|-- assets/                       # Stylesheets, JavaScript, and static resources
|-- ml/                           # Machine learning pricing validation
|   |-- gen_data.py               # Generates labelled training CSV (2907 rows)
|   |-- quote_math_validation.csv # Training and validation dataset
|   |-- quote_math_validator.ipynb# Kaggle notebook: train, evaluate, save model
|   |-- validate_quote.py         # CLI wrapper called by QuoteValidator.php
|   |-- quote_math_model.pkl      # Trained DecisionTreeClassifier bundle
|   |-- verify.py                 # Local dev smoke test for the pkl
|-- design_research/              # Research notes and design context files
|-- build_this/                   # xcm-build-this Go build tool source and compiled binary
|-- deploy/                       # Output directory created by xcm-build-this
|   |-- README.md                 # Auto-generated build version and dependency log
|   |-- this_<form-name>/         # Deployable package, upload this folder to your server
|       |-- .htaccess
|       |-- index.php
|       |-- assets/
|       |-- (all compiled and optimized files)
|-- project_mgr/                  # Repo management notes and contributor tracking
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

### Step 4: Open the Form Builder and Configure Your Form

Start the development server and navigate to `/form-builder`. Set up your services, pricing, styling, and all form copy. Save the form when ready.

### Step 5: Run the Build Tool

Navigate to the `build_this/` directory and run the compiled binary.

```bash
cd build_this
./xcm-build-this
```

Select your saved form from the menu. The build output will be placed in `deploy/this_<form-slug>/`.

### Step 6: Deploy

Upload the contents of `deploy/this_<form-slug>/` to the desired folder on your server. The application will be active immediately.

---

## Configuration

All configuration is managed through files in the `config/` directory. No sensitive values should be hardcoded into any PHP file inside the web root.

### Mailer Configuration

The mailer supports three delivery modes. Set the mode and provide the corresponding values in your config file.

| Setting | Description |
|---|---|
| `MAIL_MODE` | Set to `ssl`, `tls`, `smtp`, or `server` |
| `SMTP_HOST` | Your SMTP server hostname |
| `SMTP_PORT` | Port number (`465` for SSL, `587` for TLS/STARTTLS) |
| `SMTP_USERNAME` | SMTP account username |
| `SMTP_PASSWORD` | SMTP account password, stored outside the web root |
| `MAIL_FROM` | The sender email address |
| `MAIL_FROM_NAME` | The sender display name |
| `MAIL_TO` | The destination address that receives submitted quote requests |

`ssl` uses implicit SSL on port 465 and is the correct mode for Hostinger and most modern shared hosting providers. `tls` uses STARTTLS on port 587. `smtp` is an alias for `ssl`. `server` uses the server's native sendmail or PHP `mail()` function without SMTP credentials.

### Quote Reference ID Configuration

The reference ID expiration period is set in the main settings file.

| Setting | Description |
|---|---|
| `REFERENCE_EXPIRY_DAYS` | Number of days before a reference ID expires (example: 7 or 14) |

Technology pricing shifts frequently. The expiration window is intentional and keeps quote references within a period of reasonable accuracy.

### Credential Storage Policy

All values marked as sensitive, including SMTP passwords, API keys, and any future integration tokens, must be stored in a file located outside the web root and document root. Reference them using an absolute server path. This is a hard requirement and not optional.

---

## Form Builder

The form builder is a local dashboard used to configure every aspect of the quote form before building. It runs inside the development environment and is not accessible in the deployed output.

Open the form builder by navigating to `/form-builder` in your local dev server. From there you can:

- Set the form name, description, and optional intro video
- Add, edit, and reorder services with labels, base prices, and help text
- Configure complexity levels with multipliers and descriptions
- Add and remove add-on services
- Define contact form fields (text, email, select) and mark required fields
- Adjust theme colors, font, and font size
- Edit all copy: headings, step descriptions, button labels, disclaimer text, and currency symbol
- Configure pricing tiers (e.g. Basic, Standard, Premium) with per-tier multipliers
- Toggle the cost breakdown section on or off

All changes are previewed live in the browser before saving. When the form is saved, a JSON configuration file is written to `data/forms/`. The build tool reads this file when compiling the deploy package.

---

## Build Process

The build tool is `xcm-build-this`. The pre-compiled binary lives in `build_this/`. Running it opens an interactive menu listing all saved form configurations. Select a form to build, and the tool compiles a complete deployable package.

When executed, it performs the following operations.

1. Reads the saved form configuration from `data/forms/`
2. Copies and processes source files from `src/`, `assets/`, and `config/`
3. Copies PHPMailer source files from the vendor directory
4. Generates the `.htaccess` file for JSON access control and routing
5. Generates service worker files for real-time update delivery with versioned asset hashes
6. Outputs the complete package to `deploy/this_<form-slug>/`
7. Generates `deploy/README.md` with the build version, timestamp, and a full list of included technologies

The deployed output contains only what is necessary to run the application. No development files, source maps, or build tooling are included.

To rebuild the binary from source:

```bash
cd build_this
go build -o xcm-build-this main.go
```

---

## Deployment

After running the build tool, upload the contents of `deploy/this_<form-slug>/` to the desired folder on your server. The folder name matches the slugified form name set in the form builder and can be renamed before uploading.

The application requires no special server configuration beyond PHP and either NGINX or Apache with rewrite support enabled.

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