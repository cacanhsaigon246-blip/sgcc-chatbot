$content = Get-Content -Path "c:\Users\SAIGONCACANH\.gemini\antigravity\scratch\sgcc-chatbot\chatbot-api.php" -Raw -Encoding UTF8

$target = @"
    if (`$cleanQuery !== '') {
        `$titleClean = removeVietnameseTones(isset(`$item['title']) ? `$item['title'] : '');
        `$catClean = removeVietnameseTones(isset(`$item['categoryName']) ? `$item['categoryName'] : '');
        if (strpos(`$titleClean, `$cleanQuery) === false && strpos(`$catClean, `$cleanQuery) === false) {
            continue;
        }
    }
"@

$replace = @"
    if (`$cleanQuery !== '') {
        `$titleClean = removeVietnameseTones(isset(`$item['title']) ? `$item['title'] : '');
        `$catClean = removeVietnameseTones(isset(`$item['categoryName']) ? `$item['categoryName'] : '');
        
        `$words = array_filter(explode(' ', `$cleanQuery));
        `$match = true;
        foreach (`$words as `$word) {
            if (strpos(`$titleClean, `$word) === false && strpos(`$catClean, `$word) === false) {
                `$match = false;
                break;
            }
        }
        if (!`$match) {
            continue;
        }
    }
"@

$newContent = $content.Replace($target, $replace)
[System.IO.File]::WriteAllText("c:\Users\SAIGONCACANH\.gemini\antigravity\scratch\sgcc-chatbot\chatbot-api.php", $newContent, [System.Text.Encoding]::UTF8)
