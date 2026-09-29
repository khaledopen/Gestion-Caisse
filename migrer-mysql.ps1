$ErrorActionPreference = 'Stop'

Write-Host '======================================================'
Write-Host '  Connexion de Gestion Caisse à MySQL'
Write-Host '======================================================'
Write-Host ''

$adminUser = Read-Host "Utilisateur administrateur MySQL [root]"
if ([string]::IsNullOrWhiteSpace($adminUser)) {
    $adminUser = 'root'
}

$securePassword = Read-Host 'Mot de passe MySQL (la saisie reste masquée)' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)

try {
    $env:MYSQL_ADMIN_USER = $adminUser
    $env:MYSQL_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    & "$PSScriptRoot\php.cmd" "$PSScriptRoot\setup-mysql.php"
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    Remove-Item Env:MYSQL_ADMIN_USER -ErrorAction SilentlyContinue
    Remove-Item Env:MYSQL_ADMIN_PASSWORD -ErrorAction SilentlyContinue
}
