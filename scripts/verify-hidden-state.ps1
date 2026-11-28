<#
  Verifies the four hidden problems that appear when one Laravel container is
  replaced by several replicas (Scalability & Load Distribution):

    1. code/.env bind mounts (local state leaks into every replica)
    2. CORS on /instance (the header used to prove load distribution)
    3. backend healthcheck (so stopped replicas are detected)
    4. load-balancer max_fails / fail_timeout / timeouts (zero-downtime failover)

  Usage:
    powershell -ExecutionPolicy Bypass -File .\scripts\verify-hidden-state.ps1 -Phase before
    powershell -ExecutionPolicy Bypass -File .\scripts\verify-hidden-state.ps1 -Phase after -Failover

  Each run writes an evidence file: tests/results/hidden-state-<phase>.md
#>
param(
    [ValidateSet('before', 'after')]
    [string]$Phase = 'after',
    [string]$OutDir = 'tests/results',
    [switch]$Failover
)

Set-Location (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

$checks = New-Object System.Collections.ArrayList

function Add-Check {
    param([string]$Name, [bool]$Passed, [string]$Detail)
    [void]$checks.Add([pscustomobject]@{
        Check  = $Name
        Result = if ($Passed) { 'PASS' } else { 'FAIL' }
        Detail = $Detail
    })
}

function Get-Instance([string]$Origin) {
    try {
        $r = Invoke-WebRequest -Uri 'http://localhost:8080/instance' `
            -Headers @{ Origin = $Origin } -UseBasicParsing -TimeoutSec 5
        return [pscustomobject]@{
            Status  = [int]$r.StatusCode
            ACAO    = [string]$r.Headers['Access-Control-Allow-Origin']
            Expose  = [string]$r.Headers['Access-Control-Expose-Headers']
            Backend = [string]$r.Headers['X-Backend-Instance']
        }
    } catch {
        return [pscustomobject]@{ Status = 0; ACAO = ''; Expose = ''; Backend = '' }
    }
}

# --- 1. bind mounts -----------------------------------------------------------
$cfg = docker compose config --format json | ConvertFrom-Json
$scaledServices = @('backend', 'rabbitmq-worker', 'reverb', 'order-email-outbox-publisher', 'migrate')
$codeMounts = @()
$envMounts = @()
foreach ($service in $scaledServices) {
    foreach ($volume in $cfg.services.$service.volumes) {
        if ($volume.type -eq 'bind' -and $volume.target -eq '/var/www/html') {
            $codeMounts += "$service -> $($volume.source)"
        }
        if ($volume.type -eq 'bind' -and $volume.target -eq '/var/www/html/.env') {
            $envMounts += [pscustomobject]@{ Service = $service; ReadOnly = [bool]$volume.read_only }
        }
    }
}
Add-Check 'source code is baked, not bind-mounted' ($codeMounts.Count -eq 0) `
    $(if ($codeMounts.Count -eq 0) { 'no /var/www/html bind mount in any PHP service' } else { $codeMounts -join ' | ' })
Add-Check '.env is mounted read-only' ($envMounts.Count -gt 0 -and @($envMounts | Where-Object { -not $_.ReadOnly }).Count -eq 0) `
    $(if ($envMounts.Count -eq 0) { 'no .env mount' } else { ($envMounts | ForEach-Object { "$($_.Service)=$($_.ReadOnly)" }) -join ', ' })

$cids = @(docker compose ps -q backend)
$liveCode = @()
$liveEnv = @()
foreach ($cid in $cids) {
    foreach ($mount in (docker inspect --format '{{json .Mounts}}' $cid | ConvertFrom-Json)) {
        if ($mount.Destination -eq '/var/www/html') { $liveCode += "$cid $($mount.Mode)" }
        if ($mount.Destination -eq '/var/www/html/.env') { $liveEnv += "$cid $($mount.Mode)" }
    }
}
Add-Check 'running replicas have no writable source mount' ($liveCode.Count -eq 0) `
    $(if ($liveCode.Count -eq 0) { 'live inspect: 0 source mounts' } else { $liveCode -join ' | ' })

# --- 2. CORS -----------------------------------------------------------------
$allowed = Get-Instance 'http://localhost:5173'
$evil = Get-Instance 'http://evil.example.com'
$corsOk = ($allowed.Status -eq 200) -and
          ($allowed.ACAO -eq 'http://localhost:5173') -and
          ($allowed.Expose -match 'X-Backend-Instance') -and
          ($evil.ACAO -ne 'http://evil.example.com')
Add-Check 'CORS on /instance allows SPA, blocks other origins, exposes instance header' $corsOk `
    "allowed: ACAO=[$($allowed.ACAO)] expose=[$($allowed.Expose)]; evil: ACAO=[$($evil.ACAO)]"

# --- 3. healthcheck ----------------------------------------------------------
$hcTest = $cfg.services.backend.healthcheck.test
$healthStatuses = @()
foreach ($cid in $cids) {
    $healthStatuses += docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' $cid
}
$curlPath = (docker compose exec -T backend sh -c 'command -v curl || true' 2>$null | Out-String).Trim()
$hcOk = ($null -ne $hcTest) -and ($healthStatuses.Count -gt 0) -and
        @($healthStatuses | Where-Object { $_ -ne 'healthy' }).Count -eq 0 -and
        ($curlPath -match 'curl')
Add-Check 'backend healthcheck configured and healthy' $hcOk `
    "configured=$([bool]($null -ne $hcTest)); statuses=$($healthStatuses -join ','); curl=$curlPath"

# --- 4. load balancer --------------------------------------------------------
$lbConf = (docker compose exec -T loadbalancer cat /etc/nginx/conf.d/default.conf 2>$null | Out-String)
$lbOk = $lbConf -match 'least_conn' -and
        $lbConf -match 'max_fails=' -and
        $lbConf -match 'fail_timeout=' -and
        $lbConf -match 'proxy_connect_timeout' -and
        $lbConf -match 'proxy_read_timeout' -and
        $lbConf -match 'proxy_next_upstream_tries'
$nginxTest = (docker compose exec -T loadbalancer sh -c 'nginx -t 2>&1' | Out-String).Trim() -replace "`r?`n", ' '
Add-Check 'nginx least_conn + max_fails + timeouts' ($lbOk -and ($nginxTest -match 'successful')) `
    "directives=$lbOk; nginx -t: $nginxTest"

# --- optional: zero-downtime failover ----------------------------------------
$failoverDetail = 'not requested (use -Failover)'
if ($Failover -and $cids.Count -gt 0) {
    $target = $cids[0]
    $hostname = docker exec $target hostname
    docker stop $target | Out-Null
    $ok = 0; $fail = 0; $served = @{}
    try {
        $deadline = (Get-Date).AddSeconds(20)
        while ((Get-Date) -lt $deadline) {
            try {
                $r = Invoke-WebRequest -Uri 'http://localhost:8080/instance' -UseBasicParsing -TimeoutSec 3
                if ($r.StatusCode -eq 200) { $ok++ }
                $id = [string]$r.Headers['X-Backend-Instance']
                $served[$id] = 1 + [int]$served[$id]
            } catch { $fail++ }
            Start-Sleep -Milliseconds 100
        }
    } finally {
        docker start $target | Out-Null
    }
    Start-Sleep -Seconds 8
    $ok2 = 0; $fail2 = 0
    1..60 | ForEach-Object {
        try {
            $r = Invoke-WebRequest -Uri 'http://localhost:8080/instance' -UseBasicParsing -TimeoutSec 3
            if ($r.StatusCode -eq 200) { $ok2++ }
        } catch { $fail2++ }
        Start-Sleep -Milliseconds 80
    }
    $failoverDetail = "stopped ${hostname}: during ok=$ok fail=$fail (served by $($served.Keys -join ',')); after restore ok=$ok2 fail=$fail2"
    Add-Check 'zero-downtime failover with 3 replicas' (($fail -eq 0) -and ($fail2 -eq 0)) $failoverDetail
}

# --- report -------------------------------------------------------------------
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
$reportPath = Join-Path $OutDir "hidden-state-$Phase.md"
$expected = if ($Phase -eq 'before') { 'FAIL' } else { 'PASS' }
$lines = @()
$lines += "# Hidden state verification - $Phase"
$lines += ''
$lines += "- Time: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
$lines += "- Branch: $(git branch --show-current)"
$lines += "- Expected result for this phase: $expected"
$lines += "- Failover: $failoverDetail"
$lines += ''
$lines += '| Check | Result | Evidence |'
$lines += '|---|---|---|'
foreach ($check in $checks) {
    $lines += "| $($check.Check) | $($check.Result) | $(($check.Detail) -replace '\|', '\|') |"
}
$lines += ''
$lines += 'Reproduce with:'
$lines += ''
$lines += '```powershell'
$lines += "powershell -ExecutionPolicy Bypass -File .\scripts\verify-hidden-state.ps1 -Phase $Phase$(if ($Failover) { ' -Failover' })"
$lines += '```'
Set-Content -Path $reportPath -Value ($lines -join "`n") -Encoding UTF8

$checks | Format-Table -AutoSize
"REPORT: $reportPath"
"SUMMARY: $(@($checks | Where-Object Result -eq 'PASS').Count) PASS / $(@($checks | Where-Object Result -eq 'FAIL').Count) FAIL"
