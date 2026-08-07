# Script Tu Dong Upload SGCC-Chatbot len Hostinger FTP
$ftpHost = "ftp://187.127.126.46/domains/saigoncacanh.com/public_html/chatbot/"
$username = "u972437838"
$password = "Cannabis041188@"
$localDir = "E:\Backup_Ghi_Nho_Antigravity\01_Du_An_Code\sgcc-chatbot"

Write-Host "🚀 Dang tai file Chatbot len Hostinger FTP ($ftpHost)..." -ForegroundColor Cyan

$files = Get-ChildItem -Path $localDir -File | Where-Object { $_.Name -ne "upload-chatbot.ps1" -and $_.Name -ne ".git" }

foreach ($file in $files) {
    $filePath = $file.FullName
    $fileName = $file.Name
    $targetUrl = "$ftpHost$fileName"
    
    Write-Host " -> Uploading: $fileName ..." -ForegroundColor Yellow
    
    try {
        $ftp = [System.Net.FtpWebRequest]::Create($targetUrl)
        $ftp.Credentials = New-Object System.Net.NetworkCredential($username, $password)
        $ftp.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $ftp.UseBinary = $true
        
        $content = [System.IO.File]::ReadAllBytes($filePath)
        $ftp.ContentLength = $content.Length
        
        $stream = $ftp.GetRequestStream()
        $stream.Write($content, 0, $content.Length)
        $stream.Close()
        $stream.Dispose()
        
        Write-Host "    [OK] Thanh cong: $fileName" -ForegroundColor Green
    }
    catch {
        Write-Host "    [ERROR] Loi upload $fileName" -ForegroundColor Red
    }
}

Write-Host "🎉 Hoan tat upload toan bo file Chatbot len chatbot.saigoncacanh.com!" -ForegroundColor Green
