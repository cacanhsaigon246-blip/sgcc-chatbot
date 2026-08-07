$ftpHost = "ftp://187.127.126.46/domains/saigoncacanh.com/public_html/shop/"
$username = "u972437838"
$password = "Cannabis041188@"
$localFile = "c:\Users\SAIGONCACANH\.gemini\antigravity\scratch\sgcc-chatbot\shop_index.html"

# Upload index.html
try {
    $ftp = [System.Net.FtpWebRequest]::Create($ftpHost + "index.html")
    $ftp.Credentials = New-Object System.Net.NetworkCredential($username, $password)
    $ftp.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $ftp.UseBinary = $true
    
    $content = [System.IO.File]::ReadAllBytes($localFile)
    $ftp.ContentLength = $content.Length
    
    $stream = $ftp.GetRequestStream()
    $stream.Write($content, 0, $content.Length)
    $stream.Close()
    $stream.Dispose()
    
    Write-Host "Uploaded index.html successfully!"
} catch {
    Write-Host "Error uploading index.html : $_"
}
