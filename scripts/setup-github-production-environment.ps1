param(
    [string] $Repository = "Mabonax/pro_erp",
    [string] $Environment = "production",
    [string] $DefaultDeployUser = "prograg9g3o8",
    [string] $DefaultDeployPort = "22",
    [string] $ErpPath = "/home/prograg9g3o8/apps/erp.programofaction.org",
    [string] $WebsitePath = "/home/prograg9g3o8/apps/website",
    [string] $KeyPath = "$env:USERPROFILE\.ssh\poa_github_actions_ed25519",
    [switch] $AllowLegacySsh
)

$ErrorActionPreference = "Stop"

if (-not $AllowLegacySsh) {
    throw "This helper belongs to the superseded SSH-push deployment path. Use scripts/deployment/pull-release-config.example.env and docs/deployment/cpanel-pull-release-runbook.md for the current pull-based release system."
}

function Require-Command($Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "$Name is required but was not found in PATH."
    }
}

function Read-Required($Prompt, $Default = "") {
    if ($Default -ne "") {
        $value = Read-Host "$Prompt [$Default]"
        if ([string]::IsNullOrWhiteSpace($value)) {
            return $Default
        }

        return $value.Trim()
    }

    do {
        $value = Read-Host $Prompt
    } while ([string]::IsNullOrWhiteSpace($value))

    return $value.Trim()
}

Require-Command gh
Require-Command ssh-keygen
Require-Command ssh-keyscan

Write-Host "Checking GitHub authentication..."
gh auth status
if ($LASTEXITCODE -ne 0) {
    throw "GitHub CLI is not authenticated. Run 'gh auth login' first, then rerun this script."
}

$deployHost = Read-Required "Afrihost SSH host or IP"
$deployHost = $deployHost.Trim()
if ($deployHost -match "://") {
    $deployHost = ([Uri] $deployHost).Host
}
if ($deployHost.Contains("@")) {
    $deployHost = ($deployHost -split "@")[-1]
}
$deployHost = $deployHost.TrimEnd("/")
$deployPort = Read-Required "Afrihost SSH port" $DefaultDeployPort
$deployUser = Read-Required "Afrihost SSH user" $DefaultDeployUser

$keyDirectory = Split-Path -Parent $KeyPath
if (-not (Test-Path $keyDirectory)) {
    New-Item -ItemType Directory -Force -Path $keyDirectory | Out-Null
}

if (-not (Test-Path $KeyPath)) {
    Write-Host "Generating dedicated deploy key at $KeyPath..."
    $escapedKeyPath = $KeyPath.Replace('"', '\"')
    $sshKeygenCommand = "ssh-keygen -t ed25519 -C `"github-actions-poa-production`" -f `"$escapedKeyPath`" -N `"`""
    cmd.exe /d /c $sshKeygenCommand
    if ($LASTEXITCODE -ne 0) {
        throw "ssh-keygen failed to create the deploy key."
    }
} else {
    Write-Host "Using existing deploy key at $KeyPath"
}

$publicKey = Get-Content "$KeyPath.pub" -Raw
Write-Host ""
Write-Host "Install this PUBLIC key in Afrihost/cPanel authorized SSH keys for user ${deployUser}:"
Write-Host "----- PUBLIC KEY START -----"
Write-Host $publicKey
Write-Host "----- PUBLIC KEY END -----"
Write-Host ""
Read-Host "Press Enter only after the public key is installed and authorized in Afrihost/cPanel"

Write-Host "Collecting SSH known_hosts entry..."
$knownHosts = cmd.exe /d /c "ssh-keyscan -T 15 -p $deployPort $deployHost 2>NUL"
if ([string]::IsNullOrWhiteSpace($knownHosts)) {
    Write-Host ""
    Write-Host "ssh-keyscan returned no host keys for ${deployHost}:${deployPort}."
    Write-Host "Checking TCP connectivity from this machine..."
    if (Get-Command Test-NetConnection -ErrorAction SilentlyContinue) {
        Test-NetConnection -ComputerName $deployHost -Port ([int] $deployPort) | Format-List ComputerName,RemoteAddress,RemotePort,TcpTestSucceeded
    }

    Write-Host ""
    Write-Host "If Afrihost/cPanel shows a different SSH hostname or port, stop this script and rerun it with those values."
    Write-Host "If Afrihost provides a verified known_hosts line, paste it below. Otherwise press Enter to stop."
    $manualKnownHosts = Read-Host "Verified known_hosts line"
    if ([string]::IsNullOrWhiteSpace($manualKnownHosts)) {
        throw "Stopped because no SSH host key could be collected. Check Afrihost SSH host, SSH port, and whether SSH access is enabled."
    }

    $knownHosts = $manualKnownHosts.Trim()
}

Write-Host ""
Write-Host "Server fingerprint(s):"
$knownHosts | ssh-keygen -lf -
Write-Host ""
$confirmFingerprint = Read-Host "Have you verified this fingerprint with Afrihost/cPanel/support? Type YES to continue"
if ($confirmFingerprint -ne "YES") {
    throw "Stopped before writing secrets because the SSH host fingerprint was not confirmed."
}

$intakeTokenBytes = New-Object byte[] 48
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
try {
    $rng.GetBytes($intakeTokenBytes)
} finally {
    $rng.Dispose()
}
$intakeToken = [Convert]::ToBase64String($intakeTokenBytes)
$privateKey = Get-Content $KeyPath -Raw

Write-Host "Creating/updating GitHub environment..."
gh api --method PUT "repos/$Repository/environments/$Environment" | Out-Null

Write-Host "Setting environment variables..."
gh variable set POA_ERP_PATH --repo $Repository --env $Environment --body $ErpPath
gh variable set POA_WEBSITE_PATH --repo $Repository --env $Environment --body $WebsitePath

Write-Host "Setting environment secrets..."
gh secret set POA_DEPLOY_HOST --repo $Repository --env $Environment --body $deployHost
gh secret set POA_DEPLOY_PORT --repo $Repository --env $Environment --body $deployPort
gh secret set POA_DEPLOY_USER --repo $Repository --env $Environment --body $deployUser
gh secret set POA_DEPLOY_SSH_KEY --repo $Repository --env $Environment --body $privateKey
gh secret set POA_DEPLOY_KNOWN_HOSTS --repo $Repository --env $Environment --body $knownHosts
gh secret set POA_ERP_PUBLIC_INTAKE_TOKEN --repo $Repository --env $Environment --body $intakeToken

$envOut = Join-Path $PSScriptRoot "production-env-values-to-set-on-server.txt"
@"
Set these on the Afrihost production .env files. Do not commit this file.

ERP .env:
CITIZEN_ACCESS_PUBLIC_INTAKE_TOKEN=$intakeToken

Website .env:
POA_ERP_BASE_URL=https://erp.programofaction.org
POA_ERP_PUBLIC_INTAKE_TOKEN=$intakeToken
"@ | Set-Content -Path $envOut -Encoding UTF8

Write-Host ""
Write-Host "GitHub production environment variables and secrets are configured for $Repository."
Write-Host "Next required manual step: set the generated token values in the server .env files."
Write-Host "I wrote the server-only values to:"
Write-Host $envOut
Write-Host "Delete that file after updating Afrihost .env files."
