//! xcm-dev
//!
//! Cross-platform development launcher for PHP projects.
//! - Finds the first free TCP port in a configurable range
//! - Runs an optional setup script (setup.sh / setup.bat)
//! - Starts the PHP built-in server with a router file
//! - Opens a set of URLs in the default browser
//! - Streams PHP server output to stdout
//!
//! Reusable: all paths are resolved relative to the directory
//! that contains the binary, or can be overridden via CLI flags.
//!
//! Usage:
//!   xcm-dev [--root <path>] [--host <host>] [--port-start <n>]
//!           [--port-end <n>] [--router <file>] [--open <url>]...
//!           [--no-setup] [--no-open]

use std::env;
use std::io::{self, BufRead, BufReader};
use std::net::TcpListener;
use std::path::{Path, PathBuf};
use std::process::{Child, Command, Stdio};
use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::Arc;
use std::thread;
use std::time::{Duration, Instant};

// ── constants ────────────────────────────────────────────────────────────────

const DEFAULT_HOST: &str = "127.0.0.1";
const DEFAULT_PORT_START: u16 = 8080;
const DEFAULT_PORT_END: u16 = 8200;
const DEFAULT_ROUTER: &str = "router.php";
const READY_TIMEOUT_SECS: u64 = 10;
const READY_POLL_MS: u64 = 100;
const BROWSER_OPEN_DELAY_MS: u64 = 300;

// ── entry point ──────────────────────────────────────────────────────────────

fn main() {
    let cfg = Config::from_args();

    print_banner(&cfg);

    // Setup step
    if cfg.run_setup {
        run_setup(&cfg);
    }

    // Find a free port
    let port = find_free_port(&cfg.host, cfg.port_start, cfg.port_end).unwrap_or_else(|| {
        eprintln!("  ERROR: No free port found in range {}-{}", cfg.port_start, cfg.port_end);
        std::process::exit(1);
    });

    // Build the URLs
    let base = format!("http://{}:{}", cfg.host, port);
    let urls: Vec<String> = if cfg.open_paths.is_empty() {
        vec![
            format!("{}/dashboard", base),
            format!("{}/project-mgr", base),
            format!("{}/", base),
            format!("{}/?demo=1", base),
        ]
    } else {
        cfg.open_paths.iter().map(|p| format!("{}{}", base, p)).collect()
    };

    println!();
    println!("  Port:            {}", port);
    println!("  Dashboard:       {}/dashboard", base);
    println!("  Project Manager: {}/project-mgr", base);
    println!("  Form:            {}/", base);
    println!("  Form Example:    {}/?demo=1", base);
    println!();
    println!("  Starting PHP server... (Ctrl+C to stop)");
    println!();

    // Start the PHP server
    let mut php = start_php_server(&cfg, port);

    // Forward PHP server output on a background thread
    let running = Arc::new(AtomicBool::new(true));
    let running_clone = Arc::clone(&running);

    if let Some(stdout) = php.stdout.take() {
        thread::spawn(move || {
            let reader = BufReader::new(stdout);
            for line in reader.lines() {
                if !running_clone.load(Ordering::Relaxed) {
                    break;
                }
                match line {
                    Ok(l) => println!("  [php] {}", l),
                    Err(_) => break,
                }
            }
        });
    }

    if let Some(stderr) = php.stderr.take() {
        let running_clone2 = Arc::clone(&running);
        thread::spawn(move || {
            let reader = BufReader::new(stderr);
            for line in reader.lines() {
                if !running_clone2.load(Ordering::Relaxed) {
                    break;
                }
                match line {
                    Ok(l) => eprintln!("  [php] {}", l),
                    Err(_) => break,
                }
            }
        });
    }

    // Wait until the server is accepting connections
    let ready = wait_for_server(&cfg.host, port);
    if !ready {
        eprintln!("  WARNING: Server did not respond within {}s", READY_TIMEOUT_SECS);
    }

    // Open URLs in the default browser
    if cfg.open_browser {
        for url in &urls {
            open_browser(url);
            thread::sleep(Duration::from_millis(BROWSER_OPEN_DELAY_MS));
        }
    }

    // Block until the PHP process exits (user presses Ctrl+C)
    let exit_status = php.wait();
    running.store(false, Ordering::Relaxed);

    match exit_status {
        Ok(s) if s.success() => {}
        Ok(s) => eprintln!("  PHP server exited with status: {}", s),
        Err(e) => eprintln!("  PHP server error: {}", e),
    }
}

// ── config ───────────────────────────────────────────────────────────────────

struct Config {
    root: PathBuf,
    host: String,
    port_start: u16,
    port_end: u16,
    router: String,
    open_paths: Vec<String>,
    run_setup: bool,
    open_browser: bool,
    php_bin: String,
}

impl Config {
    fn from_args() -> Self {
        let args: Vec<String> = env::args().collect();

        // Default root: parent of the directory containing this binary.
        // When built as devtool/target/release/xcm-dev the parent of the
        // binary resolves to the project root. For cargo run from devtool/
        // we also check the parent of CWD.
        let default_root = Self::detect_project_root();

        let mut root = default_root;
        let mut host = DEFAULT_HOST.to_string();
        let mut port_start = DEFAULT_PORT_START;
        let mut port_end = DEFAULT_PORT_END;
        let mut router = DEFAULT_ROUTER.to_string();
        let mut open_paths: Vec<String> = Vec::new();
        let mut run_setup = true;
        let mut open_browser = true;
        let mut php_bin = "php".to_string();

        let mut i = 1;
        while i < args.len() {
            match args[i].as_str() {
                "--root"       => { i += 1; root = PathBuf::from(&args[i]); }
                "--host"       => { i += 1; host = args[i].clone(); }
                "--port-start" => { i += 1; port_start = args[i].parse().unwrap_or(DEFAULT_PORT_START); }
                "--port-end"   => { i += 1; port_end = args[i].parse().unwrap_or(DEFAULT_PORT_END); }
                "--router"     => { i += 1; router = args[i].clone(); }
                "--open"       => { i += 1; open_paths.push(args[i].clone()); }
                "--php"        => { i += 1; php_bin = args[i].clone(); }
                "--no-setup"   => { run_setup = false; }
                "--no-open"    => { open_browser = false; }
                "--help" | "-h" => {
                    print_help();
                    std::process::exit(0);
                }
                _ => {}
            }
            i += 1;
        }

        Config { root, host, port_start, port_end, router, open_paths, run_setup, open_browser, php_bin }
    }

    fn detect_project_root() -> PathBuf {
        // 1. Walk up from the binary path looking for composer.json or package.json
        if let Ok(exe) = env::current_exe() {
            if let Some(dir) = exe.parent() {
                if let Some(found) = find_project_root(dir) {
                    return found;
                }
            }
        }
        // 2. Walk up from CWD
        if let Ok(cwd) = env::current_dir() {
            if let Some(found) = find_project_root(&cwd) {
                return found;
            }
        }
        // 3. Fall back to CWD
        env::current_dir().unwrap_or_else(|_| PathBuf::from("."))
    }
}

/// Walk upward from `start`, return the first directory that looks like a
/// project root (contains composer.json, package.json, or router.php).
fn find_project_root(start: &Path) -> Option<PathBuf> {
    let markers = ["composer.json", "package.json", "router.php"];
    let mut dir = start.to_path_buf();
    loop {
        for marker in &markers {
            if dir.join(marker).exists() {
                return Some(dir.clone());
            }
        }
        if !dir.pop() {
            break;
        }
    }
    None
}

// ── port discovery ───────────────────────────────────────────────────────────

fn find_free_port(host: &str, start: u16, end: u16) -> Option<u16> {
    for port in start..=end {
        let addr = format!("{}:{}", host, port);
        if TcpListener::bind(&addr).is_ok() {
            return Some(port);
        }
    }
    None
}

// ── setup ────────────────────────────────────────────────────────────────────

fn run_setup(cfg: &Config) {
    #[cfg(target_os = "windows")]
    let script = cfg.root.join("setup.bat");
    #[cfg(not(target_os = "windows"))]
    let script = cfg.root.join("setup.sh");

    if !script.exists() {
        return;
    }

    println!("  Running setup...");

    #[cfg(target_os = "windows")]
    let mut cmd = Command::new("cmd");
    #[cfg(target_os = "windows")]
    cmd.args(["/C", script.to_str().unwrap_or("setup.bat")]);

    #[cfg(not(target_os = "windows"))]
    let mut cmd = Command::new("bash");
    #[cfg(not(target_os = "windows"))]
    cmd.arg(script.to_str().unwrap_or("setup.sh"));

    cmd.current_dir(&cfg.root);

    match cmd.status() {
        Ok(s) if s.success() => println!("  Setup complete."),
        Ok(s) => eprintln!("  Setup exited with status: {}", s),
        Err(e) => eprintln!("  Setup error: {}", e),
    }
    println!();
}

// ── PHP server ───────────────────────────────────────────────────────────────

fn start_php_server(cfg: &Config, port: u16) -> Child {
    let router_path = cfg.root.join(&cfg.router);
    let bind_addr = format!("{}:{}", cfg.host, port);

    Command::new(&cfg.php_bin)
        .args(["-S", &bind_addr, router_path.to_str().unwrap_or("router.php")])
        .current_dir(&cfg.root)
        .stdout(Stdio::piped())
        .stderr(Stdio::piped())
        .spawn()
        .unwrap_or_else(|e| {
            eprintln!("  ERROR: Could not start PHP server: {}", e);
            eprintln!("  Make sure '{}' is on your PATH.", cfg.php_bin);
            std::process::exit(1);
        })
}

// ── readiness poll ───────────────────────────────────────────────────────────

fn wait_for_server(host: &str, port: u16) -> bool {
    let addr = format!("{}:{}", host, port);
    let deadline = Instant::now() + Duration::from_secs(READY_TIMEOUT_SECS);

    while Instant::now() < deadline {
        if TcpListener::bind(&addr).is_err() {
            // Port is no longer bindable — server is listening
            return true;
        }
        thread::sleep(Duration::from_millis(READY_POLL_MS));
    }
    false
}

// ── browser ──────────────────────────────────────────────────────────────────

fn open_browser(url: &str) {
    #[cfg(target_os = "macos")]
    let result = Command::new("open").arg(url).status();

    #[cfg(target_os = "windows")]
    let result = Command::new("cmd").args(["/C", "start", url]).status();

    #[cfg(target_os = "linux")]
    let result = Command::new("xdg-open").arg(url).status();

    #[cfg(not(any(target_os = "macos", target_os = "windows", target_os = "linux")))]
    let result: Result<_, io::Error> = Err(io::Error::new(io::ErrorKind::Other, "unsupported platform"));

    if let Err(e) = result {
        eprintln!("  Could not open browser: {}", e);
    }
    // Suppress unused import warning on some platforms
    let _ = io::stdout();
}

// ── banner / help ─────────────────────────────────────────────────────────────

fn print_banner(cfg: &Config) {
    println!();
    println!("  xcm-dev v{}", env!("CARGO_PKG_VERSION"));
    println!("  Project root: {}", cfg.root.display());
    println!("  Port range:   {}-{}", cfg.port_start, cfg.port_end);
    println!("  Router:       {}", cfg.router);
    println!();
}

fn print_help() {
    println!("xcm-dev - Cross-platform PHP development launcher");
    println!();
    println!("USAGE:");
    println!("  xcm-dev [OPTIONS]");
    println!();
    println!("OPTIONS:");
    println!("  --root <path>        Project root directory (default: auto-detect)");
    println!("  --host <host>        Bind host (default: 127.0.0.1)");
    println!("  --port-start <n>     First port to try (default: 8080)");
    println!("  --port-end <n>       Last port to try (default: 8200)");
    println!("  --router <file>      PHP router filename (default: router.php)");
    println!("  --open <path>        URL path to open, repeatable (default: /dashboard /project-mgr / /?demo=1)");
    println!("  --php <bin>          PHP binary name or path (default: php)");
    println!("  --no-setup           Skip setup.sh / setup.bat");
    println!("  --no-open            Do not open the browser");
    println!("  --help               Show this help");
}
