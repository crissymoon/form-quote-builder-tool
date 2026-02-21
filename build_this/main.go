// xcm-build-this
// XcaliburMoon Web Development Pricing - Build Tool
// Lists saved builder forms, lets the user pick one (or all),
// and compiles a deployable self-contained package into deploy/this_<formname>/.

package main

import (
	"encoding/json"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
	"time"
)

const (
	version    = "2.0.0"
	srcDir     = "../src"
	assetsDir  = "../assets"
	configDir  = "../config"
	formsDir   = "../data/forms"
	deployBase = "../deploy"
)

type formMeta struct {
	ID        string `json:"id"`
	Name      string `json:"name"`
	UpdatedAt int64  `json:"updated_at"`
	Path      string
}

func main() {
	fmt.Printf("xcm-build-this v%s\n", version)
	fmt.Printf("Build started: %s\n", time.Now().Format(time.RFC1123))
	fmt.Println("---")

	// 1. Discover saved forms
	forms, err := discoverForms()
	if err != nil {
		log.Fatalf("Error scanning forms directory: %v", err)
	}
	if len(forms) == 0 {
		fmt.Println("No saved forms found in data/forms/.")
		fmt.Println("Open the Form Builder and save at least one form before building.")
		os.Exit(1)
	}

	// 2. Display menu
	fmt.Printf("Found %d saved form(s):\n\n", len(forms))
	for i, f := range forms {
		ts := time.Unix(f.UpdatedAt, 0).Format("Jan 2, 2006 3:04 PM")
		fmt.Printf("  [%d] %s  (updated %s)\n", i+1, f.Name, ts)
	}
	if len(forms) > 1 {
		fmt.Printf("  [%d] Build ALL forms\n", len(forms)+1)
	}
	fmt.Println()

	// 3. Get user choice
	var choice int
	for {
		fmt.Printf("Select a form to build (1-%d): ", len(forms)+boolToInt(len(forms) > 1))
		_, err := fmt.Scanf("%d", &choice)
		if err != nil {
			fmt.Println("  Please enter a number.")
			continue
		}
		max := len(forms)
		if len(forms) > 1 {
			max = len(forms) + 1
		}
		if choice < 1 || choice > max {
			fmt.Printf("  Please enter a number between 1 and %d.\n", max)
			continue
		}
		break
	}

	// 4. Build selected form(s)
	var toBuild []formMeta
	if len(forms) > 1 && choice == len(forms)+1 {
		toBuild = forms
	} else {
		toBuild = []formMeta{forms[choice-1]}
	}

	for _, f := range toBuild {
		fmt.Printf("\n--- Building: %s ---\n", f.Name)
		if err := buildForm(f); err != nil {
			log.Fatalf("Build error for '%s': %v", f.Name, err)
		}
	}

	fmt.Println("\n--- All builds complete ---")
}

func boolToInt(b bool) int {
	if b {
		return 1
	}
	return 0
}

func discoverForms() ([]formMeta, error) {
	files, err := filepath.Glob(filepath.Join(formsDir, "*.json"))
	if err != nil {
		return nil, err
	}

	var forms []formMeta
	for _, f := range files {
		data, err := os.ReadFile(f)
		if err != nil {
			continue
		}
		var meta formMeta
		if err := json.Unmarshal(data, &meta); err != nil {
			continue
		}
		meta.Path = f
		if meta.Name == "" {
			meta.Name = "Untitled"
		}
		forms = append(forms, meta)
	}

	sort.Slice(forms, func(i, j int) bool {
		return forms[i].UpdatedAt > forms[j].UpdatedAt
	})
	return forms, nil
}

func slugify(name string) string {
	s := strings.ToLower(strings.TrimSpace(name))
	re := regexp.MustCompile(`[^a-z0-9]+`)
	s = re.ReplaceAllString(s, "_")
	s = strings.Trim(s, "_")
	if len(s) > 50 {
		s = s[:50]
	}
	if s == "" {
		s = "untitled"
	}
	return s
}

func buildForm(f formMeta) error {
	slug := slugify(f.Name)
	deployDir := filepath.Join(deployBase, "this_"+slug)

	steps := []struct {
		name string
		fn   func() error
	}{
		{"Prepare output directory", func() error { return prepareOutputDir(deployDir) }},
		{"Generate self-contained index.php", func() error { return generateIndex(deployDir, f) }},
		{"Copy preview renderer", func() error { return copyFile(filepath.Join(srcDir, "builder", "preview.php"), filepath.Join(deployDir, "src", "builder", "preview.php")) }},
		{"Copy assets", func() error { return copyAssets(deployDir) }},
		{"Generate .htaccess", func() error { return generateHtaccess(deployDir) }},
		{"Generate service worker", func() error { return generateServiceWorker(deployDir) }},
		{"Write deploy README", func() error { return writeDeployReadme(deployDir, f) }},
	}

	for _, step := range steps {
		fmt.Printf("  > %s... ", step.name)
		if err := step.fn(); err != nil {
			fmt.Println("FAILED")
			return fmt.Errorf("step '%s': %w", step.name, err)
		}
		fmt.Println("OK")
	}

	fmt.Printf("  Deploy from: %s\n", filepath.Clean(deployDir))
	return nil
}

func prepareOutputDir(deployDir string) error {
	// Remove old build if it exists
	os.RemoveAll(deployDir)
	dirs := []string{
		filepath.Join(deployDir, "assets", "css"),
		filepath.Join(deployDir, "assets", "js"),
		filepath.Join(deployDir, "src", "builder"),
	}
	for _, d := range dirs {
		if err := os.MkdirAll(d, 0755); err != nil {
			return err
		}
	}
	return nil
}

func generateIndex(deployDir string, f formMeta) error {
	// Read the form JSON
	formData, err := os.ReadFile(f.Path)
	if err != nil {
		return fmt.Errorf("reading form data: %w", err)
	}

	// Generate a self-contained index.php that loads the form and renders via preview.php
	content := fmt.Sprintf(`<?php
declare(strict_types=1);
/**
 * %s
 * Generated by xcm-build-this v%s on %s
 * Self-contained deployment -- do not edit.
 */

$form = json_decode('%s', true);
if (!$form) {
    http_response_code(500);
    die('Form data error.');
}
$isBuilderPreview = false;
require __DIR__ . '/src/builder/preview.php';
`, escPhp(f.Name), version, time.Now().Format(time.RFC1123), escPhpJson(string(formData)))
	return os.WriteFile(filepath.Join(deployDir, "index.php"), []byte(content), 0644)
}

func escPhp(s string) string {
	return strings.ReplaceAll(s, "'", "\\'")
}

func escPhpJson(s string) string {
	return strings.ReplaceAll(s, "'", "\\'")
}

func copyAssets(deployDir string) error {
	files := []struct{ src, dst string }{
		{filepath.Join(assetsDir, "css", "main.css"), filepath.Join(deployDir, "assets", "css", "main.css")},
		{filepath.Join(assetsDir, "js", "quote.js"), filepath.Join(deployDir, "assets", "js", "quote.js")},
		{filepath.Join(assetsDir, "favicon.png"), filepath.Join(deployDir, "assets", "favicon.png")},
	}
	for _, f := range files {
		if err := copyFile(f.src, f.dst); err != nil {
			// Non-fatal: file may not exist yet
			fmt.Printf("(skipped: %s) ", filepath.Base(f.src))
		}
	}
	return nil
}

func generateHtaccess(deployDir string) error {
	content := `# XcaliburMoon Web Development Pricing
# Generated by xcm-build-this at ` + time.Now().Format(time.RFC1123) + `
# Do not edit this file directly.

Options -Indexes

<FilesMatch "\.(json|log|sqlite|sh|bak)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresDefault "access plus 1 month"
    ExpiresByType text/html "access plus 0 seconds"
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [L,QSA]
</IfModule>
`
	return os.WriteFile(filepath.Join(deployDir, ".htaccess"), []byte(content), 0644)
}

func generateServiceWorker(deployDir string) error {
	buildHash := fmt.Sprintf("%x", time.Now().UnixNano())
	content := fmt.Sprintf(`// XcaliburMoon Service Worker
// Generated by xcm-build-this
// Build hash: %s

const CACHE_NAME = 'xcm-cache-%s';

const PRECACHE_URLS = [
    '/',
    '/assets/css/main.css',
    '/assets/favicon.png',
];

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.addAll(PRECACHE_URLS);
        }).then(function() {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.filter(function(name) {
                    return name !== CACHE_NAME;
                }).map(function(name) {
                    return caches.delete(name);
                })
            );
        }).then(function() {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function(event) {
    if (event.request.method !== 'GET') { return; }
    event.respondWith(
        caches.match(event.request).then(function(cached) {
            var networkFetch = fetch(event.request).then(function(response) {
                if (response && response.status === 200 && response.type === 'basic') {
                    var responseClone = response.clone();
                    caches.open(CACHE_NAME).then(function(cache) {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            });
            return cached || networkFetch;
        })
    );
});
`, buildHash, buildHash)
	return os.WriteFile(filepath.Join(deployDir, "sw.js"), []byte(content), 0644)
}

func writeDeployReadme(deployDir string, f formMeta) error {
	content := fmt.Sprintf(`# %s - Deploy

**Build version**: %s
**Build timestamp**: %s
**Form**: %s (ID: %s)

## Technologies Included

| Component         | Version / Notes                        |
|-------------------|----------------------------------------|
| PHP               | 8.3+ required on host                  |
| Service Worker    | Generated by xcm-build-this            |

## Deployment Instructions

Upload the contents of this directory to the target folder on your server.
No build tools are required on the server. PHP 8.3+ and Apache or NGINX with rewrite support is all that is needed.

The form data is embedded in index.php. No database or external data files are needed.

Store SMTP credentials and API keys outside the web root before going live.
`, f.Name, version, time.Now().Format(time.RFC1123), f.Name, f.ID)
	return os.WriteFile(filepath.Join(deployDir, "README.md"), []byte(content), 0644)
}

func copyFile(src, dst string) error {
	data, err := os.ReadFile(src)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(dst), 0755); err != nil {
		return err
	}
	return os.WriteFile(dst, data, 0644)
}
