$content = Get-Content -Path "shop_index.html" -Raw -Encoding UTF8
$script = @"
  <!-- Chatbot Sai Gon Ca Canh -->
  <script>
  (function(){
    var s = document.createElement('script');
    s.src = 'https://chatbot.saigoncacanh.com/widget.js?v=' + Date.now();
    s.async = true;
    document.head.appendChild(s);
  })();
  </script>
</body>
"@
$newContent = $content -replace '</body>', $script
[System.IO.File]::WriteAllText("c:\Users\SAIGONCACANH\.gemini\antigravity\scratch\sgcc-chatbot\shop_index.html", $newContent, [System.Text.Encoding]::UTF8)
