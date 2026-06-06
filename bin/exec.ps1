$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RunPy = Join-Path $ScriptDir "exec.py"

if ($IsWindows -or $env:OS -eq "Windows_NT") {
    $PyWrapper = Join-Path $ScriptDir "py.cmd"
} else {
    $PyWrapper = Join-Path $ScriptDir "py"
}

if (-not (Test-Path $PyWrapper)) {
    Write-Error "Python wrapper not found: $PyWrapper"
    exit 1
}

if (-not (Test-Path $RunPy)) {
    Write-Error "Runner script not found: $RunPy"
    exit 1
}

& $PyWrapper $RunPy @args
exit $LASTEXITCODE
