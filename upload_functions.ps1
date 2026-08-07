$ftpHost = "ftp://187.127.126.46/domains/saigoncacanh.com/public_html/wp-content/themes/saigoncacanh_theme/functions.php"
$username = "u972437838"
$password = "Cannabis041188@"
$localFile = "E:\Backup_Ghi_Nho_Antigravity\01_Du_An_Code\saigoncacanh_theme\functions.php"

try {
    $ftp = [System.Net.FtpWebRequest]::Create($ftpHost)
    $ftp.Credentials = New-Object System.Net.NetworkCredential($username, $password)
    $ftp.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $ftp.UseBinary = $true
    
    $content = [System.IO.File]::ReadAllBytes($localFile)
    $ftp.ContentLength = $content.Length
    
    $stream = $ftp.GetRequestStream()
    $stream.Write($content, 0, $content.Length)
    $stream.Close()
    $stream.Dispose()
    
    Write-Host "Uploaded functions.php to Theme successfully!" -ForegroundColor Green
} catch {
    Write-Host "Error uploading functions.php: $_" -ForegroundColor Red
}
