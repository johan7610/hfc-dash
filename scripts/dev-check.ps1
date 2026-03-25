# dev-check.ps1 - Targeted check for changed files only
# Usage:
#   scripts/dev-check.ps1          -> lint + cache clear (changed files only)
#   scripts/dev-check.ps1 -Full    -> lint + cache clear + full test suite
#
# Run from repo root (PowerShell)

param(
    [switch]$Full
)

$ErrorActionPreference = 'Stop'
$failed = $false

Write-Host '=== DEV CHECK ===' -ForegroundColor Cyan

# 0) PHP present
php -v | Out-Null

# -- Collect changed files --
$changedPhp = @()
$changedBlade = @()

if (Get-Command git -ErrorAction SilentlyContinue) {
    # Staged + unstaged + untracked changes vs HEAD
    $allChanged = @()
    $allChanged += git diff --name-only --diff-filter=ACMRT HEAD 2>$null
    $allChanged += git diff --name-only --cached 2>$null
    $allChanged += git ls-files --others --exclude-standard 2>$null
    $allChanged = $allChanged | Sort-Object -Unique | Where-Object { $_ }

    $changedPhp   = $allChanged | Where-Object { $_ -match '\.php$' }
    $changedBlade = $allChanged | Where-Object { $_ -match '\.blade\.php$' }

    if ($allChanged.Count -eq 0) {
        Write-Host ''
        Write-Host 'No changed files detected.' -ForegroundColor Green
    } else {
        Write-Host ''
        Write-Host "Changed files: $($allChanged.Count)" -ForegroundColor DarkGray
    }
} else {
    Write-Host 'git not found; skipping changed-file detection.' -ForegroundColor DarkYellow
}

# -- 1. Lint changed PHP files --
Write-Host ''
Write-Host '1. Lint PHP files' -ForegroundColor Yellow
$lintCount = 0
foreach ($f in $changedPhp) {
    if (Test-Path $f) {
        $result = php -l $f 2>&1
        if ($LASTEXITCODE -ne 0) {
            Write-Host "   FAIL: $f" -ForegroundColor Red
            Write-Host "   $result" -ForegroundColor Red
            $failed = $true
        } else {
            $lintCount++
        }
    }
}
if ($lintCount -gt 0) {
    Write-Host "   $lintCount file(s) lint OK" -ForegroundColor Green
} elseif ($changedPhp.Count -eq 0) {
    Write-Host '   No PHP files changed' -ForegroundColor DarkGray
}

# -- 2. Clear caches --
Write-Host ''
Write-Host '2. Clear caches' -ForegroundColor Yellow
php artisan optimize:clear 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host '   artisan optimize:clear failed' -ForegroundColor Red
    $failed = $true
} else {
    Write-Host '   Caches cleared' -ForegroundColor Green
}

# -- 3. Route check (only if routes or controllers changed) --
$routeFiles = $changedPhp | Where-Object { $_ -match 'routes[/\\]' -or $_ -match 'Controllers[/\\]' }
if ($routeFiles.Count -gt 0) {
    Write-Host ''
    Write-Host '3. Route check' -ForegroundColor Yellow
    $routeResult = php artisan route:clear 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host '   Route compilation failed!' -ForegroundColor Red
        Write-Host "   $routeResult" -ForegroundColor Red
        $failed = $true
    } else {
        Write-Host '   Routes compile OK' -ForegroundColor Green
    }
} else {
    Write-Host ''
    Write-Host '3. Route check -- skipped (no route/controller changes)' -ForegroundColor DarkGray
}

# -- 4. View check (only if blade files changed) --
if ($changedBlade.Count -gt 0) {
    Write-Host ''
    Write-Host '4. View compilation check' -ForegroundColor Yellow
    $viewResult = php artisan view:cache 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host '   View compilation failed!' -ForegroundColor Red
        Write-Host "   $viewResult" -ForegroundColor Red
        $failed = $true
    } else {
        Write-Host '   Views compile OK' -ForegroundColor Green
    }
    # Clean up compiled views
    php artisan view:clear 2>&1 | Out-Null
} else {
    Write-Host ''
    Write-Host '4. View check -- skipped (no blade changes)' -ForegroundColor DarkGray
}

# -- 5. Tests --
if ($Full) {
    Write-Host ''
    Write-Host '5. Full test suite' -ForegroundColor Yellow
    php artisan test
    if ($LASTEXITCODE -ne 0) {
        $failed = $true
    }
} else {
    Write-Host ''
    Write-Host '5. Tests -- skipped (use -Full to run all 894 tests)' -ForegroundColor DarkGray
}

# -- Result --
Write-Host ''
if ($failed) {
    Write-Host '=== DEV CHECK FAILED ===' -ForegroundColor Red
    exit 1
} else {
    Write-Host '=== DEV CHECK OK ===' -ForegroundColor Green
}
