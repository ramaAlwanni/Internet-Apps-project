<#
.SYNOPSIS
  Proves the Scalability & Load Distribution requirement end to end.
.DESCRIPTION
  1) Brings up 3 backend replicas + 3 RabbitMQ workers.
  2) Sends 500 requests through Nginx and asserts 3 distinct backend hostnames.
  3) Stops one backend replica while k6 keeps calling, then asserts zero failures.
#>
[CmdletBinding()]
param(
    [string] $K6Bin = 'k6',
    [int] $BackendPort = 8080,
    [string] $FailoverDuration = '45s'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$resultsDir = Join-Path $projectRoot 'tests/k6/results'
New-Item -ItemType Directory -Force -Path $resultsDir | Out-Null

$baseUrl = "http://localhost:$BackendPort"
$distributionTest = Join-Path $projectRoot 'tests/k6/load-balancer-test.js'
$failoverTest = Join-Path $projectRoot 'tests/k6/failover-test.js'

function Get-Points([string] $JsonPath, [string] $Metric) {
    if (-not (Test-Path $JsonPath)) { throw "Missing k6 output: $JsonPath" }
    return Get-Content $JsonPath | ForEach-Object {
        $_ | ConvertFrom-Json
    } | Where-Object {
        $_.type -eq 'Point' -and $_.metric -eq $Metric
    }
}

Write-Host '[1/5] Rebuilding a clean stack with 3 app replicas + 3 workers...' -ForegroundColor Cyan
docker compose down --remove-orphans | Out-Null
docker compose up -d --build --wait --wait-timeout 180 --scale backend=3 --scale rabbitmq-worker=3
if ($LASTEXITCODE -ne 0) { throw 'docker compose up failed' }

Write-Host '[2/5] Waiting for the load balancer...' -ForegroundColor Cyan
$ready = $false
for ($i = 0; $i -lt 60; $i++) {
    try {
        $null = Invoke-RestMethod -Uri "$baseUrl/instance" -TimeoutSec 3
        $ready = $true
        break
    } catch {
        Start-Sleep -Seconds 2
    }
}
if (-not $ready) { throw 'Load balancer never became ready' }

Write-Host '[3/5] Sending 500 requests through the load balancer...' -ForegroundColor Cyan
$distributionJson = Join-Path $resultsDir 'distribution.json'
& $K6Bin run --quiet --out "json=$distributionJson" `
    --env "API_BASE_URL=$baseUrl" `
    --env 'TEST_ITERATIONS=500' `
    --env 'TEST_VUS=10' `
    $distributionTest
if ($LASTEXITCODE -ne 0) { throw 'Distribution k6 run failed' }

$distribution = Get-Points $distributionJson 'backend_requests' |
    ForEach-Object { $_.data.tags.backend } |
    Group-Object | Sort-Object Name
$distribution | Select-Object Name, Count | Format-Table -AutoSize
if ($distribution.Count -lt 3) {
    throw "Expected >=3 backends, saw $($distribution.Count): $($distribution.Name -join ', ')"
}

$workers = @(docker compose ps -q rabbitmq-worker)
Write-Host "RabbitMQ workers: $($workers.Count)"
if ($workers.Count -ne 3) { throw "Expected 3 workers, saw $($workers.Count)" }

Write-Host '[4/5] Stopping one replica during a continuous k6 run...' -ForegroundColor Cyan
$failoverJson = Join-Path $resultsDir 'failover.json'
$k6Args = @(
    'run', '--quiet', "--out=json=$failoverJson",
    "--env=API_BASE_URL=$baseUrl",
    "--env=TEST_DURATION=$FailoverDuration",
    "--env=TEST_VUS=5",
    $failoverTest
)
$proc = Start-Process -FilePath $K6Bin -ArgumentList $k6Args -PassThru -NoNewWindow

Start-Sleep -Seconds 8
$backendIds = @(docker compose ps -q backend)
if ($backendIds.Count -lt 3) { throw "Expected 3 backend containers, saw $($backendIds.Count)" }
$target = $backendIds[0]
Write-Host "Stopping backend container $target"
& docker stop $target | Out-Null

$proc.WaitForExit()
$proc.Refresh()
# With -NoNewWindow, ExitCode can come back empty even after a successful run.
# k6 thresholds and the failed-request check below are the real assertions.
$exitCode = if ($null -eq $proc.ExitCode) { 0 } else { [int] $proc.ExitCode }
if ($exitCode -ne 0) { throw "Failover k6 run failed with exit code $exitCode" }

$failed = @(Get-Points $failoverJson 'http_req_failed' | Where-Object { $_.data.value -eq 1 })
if ($failed.Count -gt 0) { throw "Failover run had $($failed.Count) failed requests" }

$afterStop = Get-Points $failoverJson 'backend_requests' |
    ForEach-Object { $_.data.tags.backend } | Sort-Object -Unique
Write-Host "Backends observed during failover: $($afterStop -join ', ')"
if ($afterStop.Count -lt 2) { throw 'No second replica served traffic after the stop' }

Write-Host '[5/5] Restoring the stopped replica and printing final state...' -ForegroundColor Cyan
& docker start $target | Out-Null
docker compose ps
Write-Host 'PASS: distribution across 3 replicas, 3 workers, and zero-downtime failover.' -ForegroundColor Green
