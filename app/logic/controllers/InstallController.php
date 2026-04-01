<?php

namespace App\Controllers;

use App\Services\Installer\InstallerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstallController extends Controller
{
    public function __construct(
        private readonly InstallerService $installer,
    ) {}

    public function index(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return view('install.already-installed');
        }

        return redirect('/install/requirements');
    }

    // ── Step 1: Requirements ─────────────────────────────────

    public function requirements(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect('/install');
        }

        $checks = $this->installer->checkRequirements();

        return view('install.requirements', [
            'checks' => $checks,
            'passed' => $this->installer->requirementsPassed($checks),
        ]);
    }

    // ── Step 2: Database ─────────────────────────────────────

    public function database(): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect('/install');
        }

        return view('install.database');
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect('/install');
        }

        $validated = $request->validate([
            'db_host'     => 'required|string|max:255',
            'db_port'     => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string|max:255',
        ]);

        $credentials = [
            'host'     => $validated['db_host'],
            'port'     => (string) $validated['db_port'],
            'database' => $validated['db_database'],
            'username' => $validated['db_username'],
            'password' => $validated['db_password'] ?? '',
        ];

        $result = $this->installer->testDatabaseConnection($credentials);

        if (!$result['success']) {
            return back()
                ->withInput()
                ->withErrors(['database' => 'Could not connect: ' . $result['error']]);
        }

        $this->installer->saveDatabaseConfig($credentials);

        $request->session()->put('install_db_ok', true);

        return redirect('/install/admin');
    }

    // ── Step 3: Admin account ────────────────────────────────

    public function admin(Request $request): View|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect('/install');
        }

        if (!$request->session()->get('install_db_ok')) {
            return redirect('/install/database')
                ->withErrors(['database' => 'Please configure the database first.']);
        }

        return view('install.admin', [
            'appUrl' => request()->getSchemeAndHttpHost(),
        ]);
    }

    public function finalize(Request $request): RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect('/install');
        }

        $validated = $request->validate([
            'admin_name'     => 'required|string|max:255',
            'admin_email'    => 'required|email|max:255',
            'admin_password' => 'required|string|min:8|confirmed',
            'app_url'        => 'required|url|max:255',
        ]);

        try {
            $this->installer->setAppUrl($validated['app_url']);
            $this->installer->generateAppKey();
            $migrationOutput = $this->installer->runMigrations();
            $this->installer->createAdminUser(
                $validated['admin_name'],
                $validated['admin_email'],
                $validated['admin_password'],
            );
            $this->installer->seedCronTasks();
            $this->installer->markInstalled();

            $request->session()->forget('install_db_ok');

            return redirect('/install/complete');
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['install' => 'Installation failed: ' . $e->getMessage()]);
        }
    }

    // ── Step 4: Complete ─────────────────────────────────────

    public function complete(): View
    {
        return view('install.complete', [
            'appUrl' => config('app.url'),
        ]);
    }
}
