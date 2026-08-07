$ftpHost = "ftp://187.127.126.46/domains/shop.saigoncacanh.com/public_html/"
$username = "u972437838"
$password = "Cannabis041188@"

try {
    $ftp = [System.Net.FtpWebRequest]::Create($ftpHost)
    $ftp.Credentials = New-Object System.Net.NetworkCredential($username, $password)
    $ftp.Method = [System.Net.WebRequestMethods+Ftp]::ListDirectory
    $response = $ftp.GetResponse()
    $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
    $files = $reader.ReadToEnd()
    $reader.Close()
    $response.Close()
    Write-Host "Contents of $ftpHost :"
    Write-Host $files
} catch {
    Write-Host "Error listing $ftpHost : $_"
}

$ftpHost2 = "ftp://187.127.126.46/domains/saigoncacanh.com/public_html/"
try {
    $ftp = [System.Net.FtpWebRequest]::Create($ftpHost2)
    $ftp.Credentials = New-Object System.Net.NetworkCredential($username, $password)
    $ftp.Method = [System.Net.WebRequestMethods+Ftp]::ListDirectory
    $response = $ftp.GetResponse()
    $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
    $files = $reader.ReadToEnd()
    $reader.Close()
    $response.Close()
    Write-Host "Contents of $ftpHost2 :"
    Write-Host $files
} catch {
    Write-Host "Error listing $ftpHost2 : $_"
}
