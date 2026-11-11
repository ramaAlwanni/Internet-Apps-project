<#
.SYNOPSIS
    Verifies Redis product caching and conditional HTTP ETag behavior.

.DESCRIPTION
    Signs in interactively unless a JWT is supplied, then checks the product
    endpoint's 200/ETag, repeat-request Redis CACHE HIT, and conditional 304.
    Run from any directory; the Laravel log is read from this repository.

.PARAMETER ApiBaseUrl
    Laravel API base URL. Defaults to the Docker Compose host URL.

.PARAMETER Token
    Optional JWT. If omitted, the script prompts for account credentials.

.PARAMETER PerPage
    Page size used to isolate the cache key for this test.

.EXAMPLE
    .\tests\scripts\Test-CacheAndETag.ps1

.EXAMPLE
    .\tests\scripts\Test-CacheAndETag.ps1 -Token $token -PerPage 9
#>

param(
    [string] $ApiBaseUrl = "http://localhost:8080",
    [string] $Token,
    [ValidateRange(1, 100)]
    [int] $PerPage = 7
)

$ErrorActionPreference = "Stop"

try {
    Add-Type -AssemblyName System.Net.Http -ErrorAction Stop
}
catch {
    throw "Unable to load the .NET HTTP library required by this script: $($_.Exception.Message)"
}

function Write-Check {
    param([string] $Message)
    Write-Host "[PASS] $Message" -ForegroundColor Green
}

function Assert-Equal {
    param(
        [object] $Actual,
        [object] $Expected,
        [string] $Message
    )

    if ($Actual -ne $Expected) {
        throw "$Message Expected '$Expected', received '$Actual'."
    }

    Write-Check $Message
}

function Get-ApiToken {
    param([System.Net.Http.HttpClient] $Client)

    $email = Read-Host "Account email"
    $securePassword = Read-Host "Account password" -AsSecureString
    $passwordPointer = [IntPtr]::Zero

    try {
        $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
        $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
        $loginJson = @{ email = $email; password = $password } | ConvertTo-Json -Compress
        $content = [System.Net.Http.StringContent]::new(
            $loginJson,
            [System.Text.Encoding]::UTF8,
            "application/json"
        )
        $response = $Client.PostAsync(
            "$ApiBaseUrl/api/v1/users/sign-in",
            $content
        ).GetAwaiter().GetResult()

        if (-not $response.IsSuccessStatusCode) {
            throw "Sign-in failed with HTTP $([int] $response.StatusCode). Check the API URL and account credentials."
        }

        $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult() | ConvertFrom-Json
        if ([string]::IsNullOrWhiteSpace($body.data.token)) {
            throw "Sign-in succeeded, but the response did not contain data.token."
        }

        return $body.data.token
    }
    finally {
        if ($passwordPointer -ne [IntPtr]::Zero) {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
        }
        $password = $null
        $loginJson = $null
        $securePassword.Dispose()
    }
}

function Invoke-ProductsRequest {
    param(
        [System.Net.Http.HttpClient] $Client,
        [string] $Url,
        [string] $BearerToken,
        [string] $IfNoneMatch
    )

    $request = [System.Net.Http.HttpRequestMessage]::new(
        [System.Net.Http.HttpMethod]::Get,
        $Url
    )
    $request.Headers.Authorization = [System.Net.Http.Headers.AuthenticationHeaderValue]::new(
        "Bearer",
        $BearerToken
    )
    [void] $request.Headers.Accept.Add(
        [System.Net.Http.Headers.MediaTypeWithQualityHeaderValue]::new("application/json")
    )

    if (-not [string]::IsNullOrWhiteSpace($IfNoneMatch)) {
        [void] $request.Headers.TryAddWithoutValidation("If-None-Match", $IfNoneMatch)
    }

    try {
        $response = $Client.SendAsync($request).GetAwaiter().GetResult()
        $body = ""
        if ($null -ne $response.Content) {
            $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        }
        $etag = $null
        if ($null -ne $response.Headers.ETag) {
            $etag = $response.Headers.ETag.ToString()
        }

        return [PSCustomObject] @{
            StatusCode = [int] $response.StatusCode
            ETag = $etag
            Body = $body
        }
    }
    finally {
        $request.Dispose()
    }
}

$ApiBaseUrl = $ApiBaseUrl.TrimEnd("/")
$productsUrl = "$ApiBaseUrl/api/v1/products?per_page=$PerPage"
$client = [System.Net.Http.HttpClient]::new()
$client.Timeout = [TimeSpan]::FromSeconds(15)

try {
    if ([string]::IsNullOrWhiteSpace($Token)) {
        $Token = Get-ApiToken -Client $client
    }

    $projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
    $logPath = Join-Path $projectRoot "storage\logs\laravel.log"
    $logOffset = 0L
    if (Test-Path -LiteralPath $logPath) {
        $logOffset = (Get-Item -LiteralPath $logPath).Length
    }

    Write-Host "Testing $productsUrl"
    $first = Invoke-ProductsRequest -Client $client -Url $productsUrl -BearerToken $Token
    Assert-Equal $first.StatusCode 200 "Initial product-list request returns HTTP 200."
    if ([string]::IsNullOrWhiteSpace($first.ETag)) {
        throw "The HTTP 200 response did not include an ETag header."
    }
    if ([string]::IsNullOrWhiteSpace($first.Body)) {
        throw "The HTTP 200 response had an empty body."
    }
    Write-Check "Initial response includes an ETag and a response body."

    $second = Invoke-ProductsRequest -Client $client -Url $productsUrl -BearerToken $Token
    Assert-Equal $second.StatusCode 200 "Repeated unconditional request returns HTTP 200."
    Assert-Equal $second.ETag $first.ETag "Unchanged representation keeps the same ETag."

    $conditional = Invoke-ProductsRequest `
        -Client $client `
        -Url $productsUrl `
        -BearerToken $Token `
        -IfNoneMatch $first.ETag
    Assert-Equal $conditional.StatusCode 304 "Matching If-None-Match returns HTTP 304."
    if (-not [string]::IsNullOrEmpty($conditional.Body)) {
        throw "The HTTP 304 response unexpectedly contained a body."
    }
    Write-Check "HTTP 304 response has no body."

    if (-not (Test-Path -LiteralPath $logPath)) {
        throw "Laravel log not found at '$logPath'; unable to verify Redis cache activity."
    }

    $logFile = [System.IO.File]::Open(
        $logPath,
        [System.IO.FileMode]::Open,
        [System.IO.FileAccess]::Read,
        [System.IO.FileShare]::ReadWrite
    )
    try {
        if ($logFile.Length -lt $logOffset) {
            throw "Laravel log was truncated during the test; unable to verify Redis cache activity."
        }

        [void] $logFile.Seek($logOffset, [System.IO.SeekOrigin]::Begin)
        $newLogBytes = [byte[]]::new([int] ($logFile.Length - $logOffset))
        $bytesRead = 0
        while ($bytesRead -lt $newLogBytes.Length) {
            $count = $logFile.Read($newLogBytes, $bytesRead, $newLogBytes.Length - $bytesRead)
            if ($count -eq 0) { break }
            $bytesRead += $count
        }
    }
    finally {
        $logFile.Dispose()
    }

    $newLog = [System.Text.Encoding]::UTF8.GetString($newLogBytes, 0, $bytesRead)
    $cacheHitPattern = "\[CACHE HIT\].*products:v\d+:cat_all:page_1:per_$PerPage(?:\s|$)"
    if ($newLog -notmatch $cacheHitPattern) {
        throw "No Redis CACHE HIT for this request was found in the Laravel log. Check that Compose uses Redis and the log is writable at '$logPath'."
    }
    Write-Check "Laravel logged a Redis CACHE HIT for this product-list query."

    Write-Host ""
    Write-Host "Cache and ETag checks all passed." -ForegroundColor Green
}
finally {
    $client.Dispose()
}
