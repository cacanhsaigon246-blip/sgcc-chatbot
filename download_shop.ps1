$ftpHost = "ftp://187.127.126.46/domains/saigoncacanh.com/public_html/shop/"
$username = "u972437838"
$password = "Cannabis041188@"

# Download index.html
try {
    $ftp = [System.Net.FtpWebRequest]::Create($ftpHost + "index.html")
    $ftp.Credentials = New-Object System.Net.NetworkCredential($username, $password)
    $ftp.Method = [System.Net.WebRequestMethods+Ftp]::DownloadFile
    $response = $ftp.GetResponse()
    $stream = $response.GetResponseStream()
    $reader = New-Object System.IO.StreamReader($stream)
    $content = $reader.ReadToEnd()
    $reader.Close()
    $response.Close()
    Set-Content -Path "c:\Users\SAIGONCACANH\.gemini\antigravity\scratch\sgcc-chatbot\shop_index.html" -Value $content
    Write-Host "Downloaded index.html"
} catch {
    Write-Host "Error downloading index.html : $_"
}
