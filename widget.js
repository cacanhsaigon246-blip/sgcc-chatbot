/**
 * widget.js — Sài Gòn Cá Cảnh Chatbot Widget
 * Nhúng file này vào saigoncacanh.com (WordPress footer)
 * Script sẽ tạo nút chat nổi góc phải, mở iframe đến chatbot.saigoncacanh.com
 */
(function () {
  'use strict';

  var CHAT_URL = 'https://chatbot.saigoncacanh.com';
  var BRAND_COLOR = '#446084';
  var ACCENT_COLOR = '#00c8ff';
  var isOpen = false;
  var hasShownBadge = false;

  // Tránh load 2 lần
  if (document.getElementById('sgcc-widget-btn')) return;

  // ── Inject CSS ──────────────────────────────────────────────
  var style = document.createElement('style');
  style.textContent = `
    #sgcc-widget-btn {
      position: fixed; bottom: 24px; right: 24px; z-index: 99999;
      width: 60px; height: 60px; border-radius: 50%;
      background: linear-gradient(135deg, ${BRAND_COLOR}, ${ACCENT_COLOR});
      border: none; cursor: pointer; outline: none;
      box-shadow: 0 4px 20px rgba(0,200,255,0.4), 0 8px 32px rgba(0,0,0,0.3);
      display: flex; align-items: center; justify-content: center;
      font-size: 26px; transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
      animation: sgcc-pulse 3s ease infinite;
    }
    #sgcc-widget-btn:hover { transform: scale(1.1); }
    #sgcc-widget-btn.open  { transform: scale(0.95); animation: none; }
    @keyframes sgcc-pulse {
      0%,100%{ box-shadow: 0 4px 20px rgba(0,200,255,0.4), 0 8px 32px rgba(0,0,0,0.3); }
      50%    { box-shadow: 0 4px 28px rgba(0,200,255,0.7), 0 8px 40px rgba(0,0,0,0.4); }
    }
    #sgcc-widget-badge {
      position: absolute; top: -4px; right: -4px;
      width: 20px; height: 20px; border-radius: 50%;
      background: #ff4444; color: white;
      font-size: 11px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      border: 2px solid white; animation: sgcc-bounce 1s ease infinite;
      font-family: Arial, sans-serif;
    }
    @keyframes sgcc-bounce {
      0%,100%{ transform: scale(1); }
      50%    { transform: scale(1.2); }
    }
    #sgcc-chat-frame-wrap {
      position: fixed; bottom: 96px; right: 24px; z-index: 99998;
      width: 390px; height: 620px;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 40px rgba(0,200,255,0.1);
      overflow: hidden;
      transform-origin: bottom right;
      transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.25s ease;
      transform: scale(0); opacity: 0; pointer-events: none;
    }
    #sgcc-chat-frame-wrap.open {
      transform: scale(1); opacity: 1; pointer-events: all;
    }
    #sgcc-chat-frame {
      width: 100%; height: 100%; border: none;
    }
    #sgcc-tooltip {
      position: fixed; bottom: 96px; right: 96px; z-index: 99997;
      background: rgba(5,11,24,0.95);
      border: 1px solid rgba(0,200,255,0.3);
      border-radius: 12px; padding: 10px 16px;
      font-family: -apple-system, 'Inter', sans-serif;
      font-size: 13px; color: #eaf0fb;
      box-shadow: 0 8px 32px rgba(0,0,0,0.4);
      max-width: 220px; line-height: 1.5;
      animation: sgcc-fadein 0.4s ease;
      cursor: pointer;
    }
    #sgcc-tooltip::after {
      content: '';
      position: absolute; right: -8px; bottom: 18px;
      width: 0; height: 0;
      border-top: 8px solid transparent;
      border-bottom: 8px solid transparent;
      border-left: 8px solid rgba(0,200,255,0.3);
    }
    #sgcc-tooltip-close {
      float: right; margin-left: 8px; cursor: pointer;
      color: rgba(255,255,255,0.4); font-size: 14px; line-height: 1;
    }
    @keyframes sgcc-fadein { from{opacity:0;transform:translateX(10px)} to{opacity:1;transform:translateX(0)} }

    @media (max-width: 480px) {
      #sgcc-chat-frame-wrap {
        width: 100vw; height: 100vh;
        bottom: 0; right: 0;
        border-radius: 0;
      }
      #sgcc-widget-btn { bottom: 16px; right: 16px; }
      #sgcc-tooltip { right: 80px; bottom: 80px; }
    }
  `;
  document.head.appendChild(style);

  // ── Tooltip (hiện sau 3 giây) ──────────────────────────────
  setTimeout(function () {
    if (isOpen || hasShownBadge) return;
    var tip = document.createElement('div');
    tip.id = 'sgcc-tooltip';
    tip.innerHTML = `
      <span id="sgcc-tooltip-close" onclick="document.getElementById('sgcc-tooltip').remove()">✕</span>
      🐟 <strong>Hỏi AI về cá cảnh!</strong><br/>
      <span style="color:rgba(255,255,255,0.6);font-size:11px">Tư vấn miễn phí 24/7</span>
    `;
    tip.onclick = function (e) {
      if (e.target.id === 'sgcc-tooltip-close') return;
      tip.remove();
      openChat();
    };
    document.body.appendChild(tip);
    // Tự ẩn sau 8 giây
    setTimeout(function () { if (tip.parentNode) tip.remove(); }, 8000);
  }, 3000);

  // ── Badge ──────────────────────────────────────────────────
  var badge = document.createElement('div');
  badge.id = 'sgcc-widget-badge';
  badge.textContent = '1';

  // ── Toggle Button ──────────────────────────────────────────
  var btn = document.createElement('button');
  btn.id = 'sgcc-widget-btn';
  btn.title = 'Chat với Sài Gòn Cá Cảnh';
  btn.setAttribute('aria-label', 'Mở chatbot Sài Gòn Cá Cảnh');
  btn.innerHTML = '🐟';
  btn.appendChild(badge);

  // ── iFrame Wrapper ─────────────────────────────────────────
  var wrap = document.createElement('div');
  wrap.id = 'sgcc-chat-frame-wrap';

  var iframe = document.createElement('iframe');
  iframe.id = 'sgcc-chat-frame';
  iframe.title = 'Chatbot Sài Gòn Cá Cảnh';
  iframe.allow = 'camera; microphone; clipboard-write';

  wrap.appendChild(iframe);

  // ── Toggle Logic ───────────────────────────────────────────
  function openChat() {
    isOpen = true;
    hasShownBadge = true;
    badge.style.display = 'none';
    document.getElementById('sgcc-tooltip') && document.getElementById('sgcc-tooltip').remove();

    // Lazy load iframe (chống cache cứng bằng timestamp + truyền user từ WordPress)
    if (!iframe.src) {
      var userParam = '';
      try {
        var detectedName = '';
        var detectedEmail = '';

        // 1. Quét tên từ admin bar
        var adminBarName = document.querySelector('#wp-admin-bar-my-account .display-name');
        if (adminBarName) detectedName = adminBarName.textContent.trim();

        // 2. Quét tên từ Flatsome Account item
        if (!detectedName) {
          var selectors = [
            '.account-item strong',
            '.account-item span',
            '.account-link strong',
            '.account-link span',
            '.nav-top-link strong'
          ];
          for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el) {
              var text = el.textContent.trim().replace(/^Chào,\s*/i, '');
              if (text && text !== 'Đăng nhập' && text.toLowerCase() !== 'my account') {
                detectedName = text;
                break;
              }
            }
          }
        }

        // 3. Quét từ các thẻ a My Account hoặc Tài khoản
        if (!detectedName) {
          var accLinks = document.querySelectorAll('a[href*="/my-account/"], a[href*="/tai-khoan/"]');
          for (var j = 0; j < accLinks.length; j++) {
            var txt = accLinks[j].textContent.trim().replace(/^Chào,\s*/i, '');
            if (txt && txt !== 'Đăng nhập' && txt.toLowerCase() !== 'my account' && txt.length < 30) {
              detectedName = txt;
              break;
            }
          }
        }

        // 4. Nếu đang ở trang sửa thông tin tài khoản WooCommerce, quét trực tiếp từ ô nhập liệu
        var emailInput = document.getElementById('account_email') || document.querySelector('input[name="account_email"]');
        if (emailInput && emailInput.value) detectedEmail = emailInput.value.trim();

        var nameInput = document.getElementById('account_display_name') || document.querySelector('input[name="account_display_name"]');
        if (nameInput && nameInput.value) detectedName = nameInput.value.trim();

        // 5. Đồng bộ và lưu trữ vào localStorage để dùng cho các trang khác không hiển thị tên
        if (detectedName) {
          localStorage.setItem('sgcc_wp_user_name', detectedName);
        }
        if (detectedEmail) {
          localStorage.setItem('sgcc_wp_user_email', detectedEmail);
        }

        // Đọc lại từ cache localStorage
        if (!detectedName) {
          detectedName = localStorage.getItem('sgcc_wp_user_name') || '';
        }
        if (!detectedEmail) {
          detectedEmail = localStorage.getItem('sgcc_wp_user_email') || '';
        }

        // 6. Dự phòng: Nếu body có class "logged-in" mà vẫn trống tên, gán mặc định là Thành viên
        if (!detectedName && document.body.classList.contains('logged-in')) {
          detectedName = 'Thành viên';
        }

        // 7. Nếu đã đăng xuất (không còn class logged-in), xóa sạch cache
        if (!document.body.classList.contains('logged-in')) {
          detectedName = '';
          detectedEmail = '';
          localStorage.removeItem('sgcc_wp_user_name');
          localStorage.removeItem('sgcc_wp_user_email');
        }

        // Ghép tham số
        if (detectedName) {
          userParam += '&wp_user=' + encodeURIComponent(detectedName);
        }
        if (detectedEmail) {
          userParam += '&wp_email=' + encodeURIComponent(detectedEmail);
        }
      } catch (e) {
        console.warn('[SGCC] Khong the doc thong tin user WordPress:', e);
      }
      iframe.src = CHAT_URL + '?v=' + new Date().getTime() + userParam;
    }

    wrap.classList.add('open');
    btn.classList.add('open');
    btn.innerHTML = '✕';
    btn.setAttribute('aria-label', 'Đóng chatbot');
  }

  function closeChat() {
    isOpen = false;
    wrap.classList.remove('open');
    btn.classList.remove('open');
    btn.innerHTML = '🐟';
    btn.appendChild(badge);
    btn.setAttribute('aria-label', 'Mở chatbot Sài Gòn Cá Cảnh');
  }

  btn.onclick = function () {
    if (isOpen) closeChat(); else openChat();
  };

  // Đóng khi click ra ngoài (chỉ trên desktop)
  document.addEventListener('click', function (e) {
    if (isOpen && !wrap.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
      closeChat();
    }
  });

  // ── Mount ──────────────────────────────────────────────────
  document.body.appendChild(wrap);
  document.body.appendChild(btn);

})();
