/* ============================================================
   CHATBOT.JS — Sài Gòn Cá Cảnh
   Frontend kết nối Backend V4.2 (Node.js Express)
   SSE Streaming | Google Auth | 4 Cấp Phân Quyền
   ============================================================ */

// ─── CONFIG ───────────────────────────────────────────────────
const CONFIG = {
  API_BASE_URL: 'https://chatbot.saigoncacanh.com',
  GOOGLE_CLIENT_ID: 'YOUR_GOOGLE_CLIENT_ID', // Anh điền vào sau
  GUEST_MESSAGE_LIMIT: 5,
  BOT_NAME: 'Sài Gòn Cá Cảnh',
  SHOP_URL: 'https://saigoncacanh.com',
  ZALO_URL: 'https://zalo.me/0938604144',
  MAX_HISTORY: 15, // Giữ 15 tin nhắn gần nhất gửi lên server
};

// ─── STATE ────────────────────────────────────────────────────
let state = {
  user: JSON.parse(localStorage.getItem('sgcc_user') || 'null'),
  guestMsgCount: parseInt(localStorage.getItem('sgcc_guest_count') || '0'),
  chatHistory: JSON.parse(localStorage.getItem('sgcc_chat_history') || '[]'),
  messages: [],              // UI messages
  pendingImage: null,        // base64 image to send
  isTyping: false,
  products: [],              // From CSV
};

// ─── INIT ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Không xóa sgcc_guest_count để duy trì giới hạn tin nhắn khách vãng lai
  createOceanAnimations();
  
  // Phát hiện người dùng từ WordPress
  const urlParams = new URLSearchParams(window.location.search);
  const wpUser = urlParams.get('wp_user') || urlParams.get('user_name');
  const wpEmail = urlParams.get('wp_email') || urlParams.get('user_email');
  if (wpUser) {
    state.user = {
      name: wpUser,
      email: wpEmail || '',
      picture: ''
    };
    localStorage.setItem('sgcc_user', JSON.stringify(state.user));
    registerMemberOnServer(wpUser);

    // LẬP TỨC XÓA SẠCH BỘ ĐẾM TIN NHẮN KHÁCH VÃNG LAI
    state.guestMsgCount = 0;
    localStorage.setItem('sgcc_guest_count', '0');
  } else {
    // Chỉ đăng xuất/xóa session đồng bộ WordPress nếu WordPress báo đã đăng xuất (wp_user bị unset trên trang có class logged-in bị mất)
    // Nếu là phiên đăng nhập trực tiếp Google Auth (có credential) thì giữ nguyên.
    const isWpSyncedUser = state.user && !state.user.credential;
    if (isWpSyncedUser) {
      state.user = null;
      localStorage.removeItem('sgcc_user');
    }
  }

  initGoogleSignIn();
  loadProductsFromStorage();
  showApp();
  checkServerHealth();
});

async function registerMemberOnServer(name) {
  try {
    await fetch('register_member.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: name })
    });
  } catch (e) {
    console.error('[SGCC] Loi dang ky thanh vien:', e);
  }
}

function showApp() {
  const setupEl = document.getElementById('setup-overlay');
  if (setupEl) setupEl.style.display = 'none';
  document.getElementById('app-wrapper').style.display = 'flex';
  renderQuickReplies();
  showWelcomeMessage();
  updateUserBar();
}

// ─── SERVER HEALTH CHECK ──────────────────────────────────────
async function checkServerHealth() {
  try {
    const res = await fetch(`${CONFIG.API_BASE_URL}/api/health`);
    if (res.ok) {
      console.log('[SGCC] ✅ Server Backend V4.2 đang hoạt động');
    }
  } catch (e) {
    console.warn('[SGCC] ⚠️ Không kết nối được Backend. Kiểm tra server.');
  }
}

// ─── GOOGLE AUTH ──────────────────────────────────────────────
async function initGoogleSignIn() {
  if (typeof google === 'undefined') return;
  try {
    const res = await fetch(`${CONFIG.API_BASE_URL}/get_google_config.php`);
    if (res.ok) {
      const data = await res.json();
      if (data.client_id) {
        CONFIG.GOOGLE_CLIENT_ID = data.client_id;
      }
    }
  } catch (e) {
    console.warn('[SGCC] Không nạp được Google Client ID từ server, dùng cấu hình mặc định.');
  }

  if (!CONFIG.GOOGLE_CLIENT_ID || CONFIG.GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID') {
    console.log('[SGCC] Chưa cấu hình Google Client ID hợp lệ.');
    return;
  }

  try {
    google.accounts.id.initialize({
      client_id: CONFIG.GOOGLE_CLIENT_ID,
      callback: handleGoogleCredential,
      auto_select: false,
    });
  } catch (err) {
    console.warn('[SGCC] Lỗi khởi tạo Google Sign-in:', err);
  }
}

function renderGoogleButton() {
  if (typeof google === 'undefined') return;
  const container = document.getElementById('google-signin-btn');
  if (!container) return;
  
  if (!CONFIG.GOOGLE_CLIENT_ID || CONFIG.GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID') {
    container.innerHTML = '<p style="font-size:12px; color:#888; text-align:center;">Vui lòng đăng nhập qua WordPress hoặc Zalo</p>';
    return;
  }

  container.innerHTML = '';
  try {
    google.accounts.id.renderButton(container, {
      theme: 'filled_blue',
      size: 'large',
      text: 'signin_with',
      locale: 'vi',
      width: 280,
    });
  } catch (err) {
    console.warn('[SGCC] Lỗi render Google Button:', err);
  }
}

function handleGoogleCredential(response) {
  const payload = parseJwt(response.credential);
  state.user = {
    name: payload.name,
    email: payload.email,
    picture: payload.picture,
    credential: response.credential,
  };
  localStorage.setItem('sgcc_user', JSON.stringify(state.user));
  closeLoginModal();
  updateUserBar();
  addBotMessage(`Xin chào **${payload.name.split(' ').pop()}** ạ! 🎉 Anh đã đăng nhập thành công rồi. Em là trợ lý của Sài Gòn Cá Cảnh, anh cứ hỏi thoải mái nhé, em hỗ trợ hết ạ! 🐟`);
  showToast(`✅ Đăng nhập thành công! Chào ${payload.name.split(' ').pop()} ạ`);
}

function parseJwt(token) {
  const base64 = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
  return JSON.parse(decodeURIComponent(atob(base64).split('').map(c =>
    '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)).join('')));
}

function signOut() {
  state.user = null;
  localStorage.removeItem('sgcc_user');
  updateUserBar();
  showToast('👋 Anh đã đăng xuất rồi ạ');
}

function updateUserBar() {
  const bar = document.getElementById('user-bar');
  if (state.user) {
    bar.style.display = 'flex';
    document.getElementById('user-avatar').src = state.user.picture || '';
    document.getElementById('user-name-bar').textContent = state.user.name || 'Thành viên';
  } else {
    bar.style.display = 'none';
  }
}

// ─── MESSAGE LIMIT (Đã mở khóa hoàn toàn 100%) ────────────────
function checkMessageLimit() {
  return true; // Luôn luôn cho phép chat không giới hạn
}

function incrementGuestCount() {
  if (!state.user) {
    state.guestMsgCount++;
    localStorage.setItem('sgcc_guest_count', state.guestMsgCount);
    updateGuestCounter();
  }
}

function updateGuestCounter() {
  if (!state.user) {
    const remaining = CONFIG.GUEST_MESSAGE_LIMIT - state.guestMsgCount;
    if (remaining <= 2 && remaining > 0) {
      showToast(`💡 Anh còn ${remaining} tin nhắn miễn phí. Đăng nhập Google để chat không giới hạn ạ!`);
    }
  }
}

// ─── LOGIN MODAL (Đã tắt hoàn toàn) ──────────────────────────
function showLoginModal() {
  return; // Đã mở khóa 100%, không hiện popup
}
function closeLoginModal() {
  const modal = document.getElementById('login-modal');
  if (modal) modal.style.display = 'none';
}

// ─── WELCOME MESSAGE ──────────────────────────────────────────
function showWelcomeMessage() {
  const greeting = getGreeting();
  const userName = state.user ? state.user.name.split(' ').pop() : 'anh';
  addBotMessage(
    `${greeting} **${userName}** ạ! 🐟\n\nEm là trợ lý AI của **Sài Gòn Cá Cảnh** — chuyên cá cảnh, thức ăn, phụ kiện, thuốc và vật liệu lọc tại TP.HCM.\n\nAnh cần hỏi gì em hỗ trợ ngay ạ! Hoặc anh bấm vào gợi ý bên dưới nhé 👇`,
    false
  );
}

function getGreeting() {
  const h = new Date().getHours();
  if (h < 11) return 'Chào buổi sáng';
  if (h < 13) return 'Chào buổi trưa';
  if (h < 18) return 'Chào buổi chiều';
  return 'Chào buổi tối';
}

// ─── SEND MESSAGE (V4.2 — SSE STREAMING) ─────────────────────
async function sendMessage() {
  const input = document.getElementById('user-input');
  const text = input.value.trim();
  const hasImage = !!state.pendingImage;

  if (!text && !hasImage) return;

  state.isTyping = true;
  const sendBtn = document.getElementById('send-btn');
  if (sendBtn) sendBtn.disabled = true;

  try {
    // Add user message to UI
    const displayText = text || '📷 [Ảnh cá bệnh]';
    addUserMessage(displayText, state.pendingImage);

    // Prepare message text
    let messageText = text;
    if (!text && hasImage) messageText = 'Đây là ảnh cá của em, anh xem giúp em cá bị bệnh gì không ạ?';

    // Clear input
    input.value = '';
    autoResize(input);
    hideQuickReplies();
    removeImage();
    showTyping();

    // Send to Backend Proxy
    try {
      const reply = await callBackendSSE(messageText);
      hideTyping();
      addBotMessage(reply);
      findAndShowArticle(reply, messageText);
      saveChatLog(state.user ? state.user.name : 'Khách vãng lai', messageText, reply, false);
    } catch (err) {
      hideTyping();
      const errorMsg = handleError(err);
      addBotMessage(errorMsg);
      saveChatLog(state.user ? state.user.name : 'Khách vãng lai', messageText, errorMsg, true);
    }
  } catch (e) {
    console.error('Send message error:', e);
    hideTyping();
    addBotMessage(handleError(e));
  } finally {
    state.isTyping = false;
    if (sendBtn) sendBtn.disabled = false;
  }
}

// ─── LƯU LOG CHAT BÁO CÁO ADMIN ─────────────────────────────
function saveChatLog(user, question, answer, isFallback = false) {
  // 1. Lưu cục bộ trình duyệt (để truy xuất nhanh tại chỗ nếu cần)
  const logs = JSON.parse(localStorage.getItem('sgcc_chat_logs') || '[]');
  const now = new Date();
  const timeStr = now.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) + ' ' + now.toLocaleDateString('vi-VN');
  logs.unshift({ time: timeStr, user: user, question: question, answer: answer.slice(0, 150) + (answer.length > 150 ? '...' : '') });
  if (logs.length > 100) logs.pop();
  localStorage.setItem('sgcc_chat_logs', JSON.stringify(logs));

  // 2. Đồng bộ đẩy trực tiếp lên server Hostinger để Admin xem ở thiết bị khác
  fetch('save_chat_log.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      user: user,
      question: question,
      answer: answer,
      isFallback: isFallback
    })
  }).catch(e => console.error('[SGCC] Loi luu log chat len server:', e));
}

// ─── SMART FALLBACK TỪ KHO KIẾN THỨC ────
function searchSmartFallback(query) {
  return `Dạ anh ơi! Hệ thống AI đang cập nhật thông tin dữ liệu mới nhất một chút ạ. 🐟\n\nAnh có thể ghé trực tiếp gian hàng online bên em để xem đầy đủ hình ảnh, giá cả và đặt mua nhanh nhất tại: [Siêu Thị Sài Gòn Cá Cảnh](https://shop.saigoncacanh.com) nha anh!`;
}

function sendQuickReply(text) {
  document.getElementById('user-input').value = text;
  sendMessage();
}

function insertQuestion(text) {
  document.getElementById('user-input').value = text;
  document.getElementById('user-input').focus();
}

// ─── BACKEND CALL VIA PROXY.PHP ───────────────────────────────
async function callBackendSSE(message) {
  const headers = { 'Content-Type': 'application/json' };

  const systemInstruction = `Bạn là trợ lý AI thân thiện, nhiệt tình của cửa hàng tiệm cá cảnh 'Sài Gòn Cá Cảnh' (địa chỉ 246 Hồ Văn Huê, Phú Nhuận, TP.HCM).
Xưng hô: Luôn gọi khách hàng là "anh" (hoặc "chị" tùy ngữ cảnh) và tự xưng là "em". 
Văn phong: Nói chuyện tự nhiên, gần gũi, mộc mạc, nhiệt tình đúng chất anh em chơi cá Sài Gòn. Tuyệt đối KHÔNG trả lời mang tính văn mẫu rập khuôn kiểu: "Dạ về câu hỏi của anh... em xin tư vấn giải pháp nhanh cho anh ạ". Trả lời trực tiếp, hào hứng, tự nhiên như 2 người bạn đam mê cá đang trao đổi với nhau.

Nhiệm vụ trọng tâm:
1. TRAO ĐỔI VỚI KHÁCH: Trả lời ngắn gọn, đúng trọng tâm vấn đề bệnh cá, phụ kiện, thức ăn, chăm sóc nước.
2. GỢI Ý SẢN PHẨM KHÉO LÉO: Đưa ra thông tin sản phẩm cụ thể từ danh sách POS (có tên sản phẩm, giá bán, tình trạng còn hàng). 
3. LINK GIAN HÀNG SẢN PHẨM: Đưa ra link xem và mua sản phẩm trực tiếp từ hệ thống gian hàng chính thức: https://shop.saigoncacanh.com bằng định dạng [Tên sản phẩm](https://shop.saigoncacanh.com).
4. KHÔNG nhắc tới nút bấm hoặc liên hệ Zalo nữa.`;

  // Chuẩn bị payload kèm lịch sử trò chuyện
  const historyParts = state.chatHistory.map(h => ({
    role: h.role === 'model' ? 'model' : 'user',
    parts: [{ text: h.content }]
  }));

  historyParts.push({
    role: 'user',
    parts: [{ text: message }]
  });

  const res = await fetch(`${CONFIG.API_BASE_URL}/proxy.php`, {
    method: 'POST',
    headers: headers,
    body: JSON.stringify({ contents: historyParts })
  });

  if (!res.ok) {
    let errText = `HTTP ${res.status}`;
    try {
      const errJson = await res.json();
      if (errJson.error) errText = typeof errJson.error === 'string' ? errJson.error : JSON.stringify(errJson.error);
    } catch(e) {}
    console.error('[SGCC] API Error:', errText);
    throw new Error(errText);
  }

  const data = await res.json();
  let fullReply = '';
  if (data.candidates && data.candidates[0] && data.candidates[0].content && data.candidates[0].content.parts[0]) {
    fullReply = data.candidates[0].content.parts[0].text;
  } else if (data.error) {
    const errMsg = typeof data.error === 'string' ? data.error : (data.error.message || 'Lỗi API');
    console.error('[SGCC] API Error Data:', errMsg);
    throw new Error(errMsg);
  }

  if (!fullReply) {
    throw new Error('Empty reply');
  }

  // Lưu vào history
  state.chatHistory.push({ role: 'user', content: message });
  state.chatHistory.push({ role: 'model', content: fullReply });
  saveHistoryToStorage();

  return fullReply;
}

function saveHistoryToStorage() {
  // Giữ tối đa 15 tin nhắn trong localStorage
  const trimmed = state.chatHistory.slice(-(CONFIG.MAX_HISTORY));
  localStorage.setItem('sgcc_chat_history', JSON.stringify(trimmed));
}

// ─── STREAMING UI ─────────────────────────────────────────────
function createStreamingBubble(msgId) {
  const time = formatTime(new Date());
  const html = `
    <div class="msg-row bot" id="${msgId}">
      <div class="msg-avatar">🐟</div>
      <div>
        <div class="msg-bubble" id="${msgId}-content"><span class="streaming-cursor">▌</span></div>
        <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
          <div class="msg-time">${time}</div>
          <button class="msg-action-btn" onclick="copyText('${msgId}')" title="Copy">📋</button>
          <button class="msg-action-btn" onclick="rateMsg('${msgId}','up')" title="Hữu ích">👍</button>
          <button class="msg-action-btn" onclick="rateMsg('${msgId}','down')" title="Chưa tốt">👎</button>
        </div>
      </div>
    </div>`;
  appendMessage(html);
}

function updateStreamingBubble(msgId, text) {
  const el = document.getElementById(`${msgId}-content`);
  if (!el) return;
  el.innerHTML = formatBotText(text) + '<span class="streaming-cursor">▌</span>';
  const area = document.getElementById('messages-area');
  area.scrollTo({ top: area.scrollHeight, behavior: 'smooth' });
}

function formatPrice(p) {
  if (!p) return '?';
  return parseInt(p).toLocaleString('vi-VN') + 'đ';
}

function handleError(err) {
  const msg = err.message || '';
  if (msg.includes('429') || msg.includes('quá nhanh')) return '⏳ Anh ơi, hệ thống đang bận ạ. Anh thử lại sau vài giây nhé! Hoặc liên hệ Zalo Shop ạ.';
  if (msg.includes('Failed to fetch') || msg.includes('network')) return '🌐 Mất kết nối mạng rồi anh ơi! Anh kiểm tra wifi/4G rồi thử lại nhé ạ.';
  if (msg.includes('500')) return '🔧 Server đang bảo trì anh ạ. Anh thử lại sau vài phút nhé!';
  console.error('Backend error:', err);
  return `❌ Hệ thống bận một chút ạ! Anh ghé gian hàng online [Siêu Thị Sài Gòn Cá Cảnh](https://shop.saigoncacanh.com) xem sản phẩm nhé ạ!`;
}

// ─── UI — MESSAGES ────────────────────────────────────────────
function addUserMessage(text, image) {
  const id = 'msg-' + Date.now();
  const time = formatTime(new Date());
  const html = `
    <div class="msg-row user" id="${id}">
      <div>
        <div class="msg-bubble">
          ${image ? `<img src="data:${image.mimeType};base64,${image.data}" class="msg-image" alt="Ảnh gửi"/>` : ''}
          ${text !== '📷 [Ảnh cá bệnh]' ? escapeHtml(text) : ''}
        </div>
        <div class="msg-time">${time}</div>
      </div>
      <div class="msg-avatar">
        ${state.user && state.user.picture
          ? `<img src="${state.user.picture}" style="width:100%;height:100%;border-radius:50%;object-fit:cover" />`
          : '👤'}
      </div>
    </div>`;
  appendMessage(html);
}

function addBotMessage(text, animate = true) {
  const id = 'msg-' + Date.now();
  const time = formatTime(new Date());
  const formatted = formatBotText(text);
  const html = `
    <div class="msg-row bot${animate ? '' : ''}" id="${id}">
      <div class="msg-avatar">🐟</div>
      <div>
        <div class="msg-bubble">${formatted}</div>
        <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
          <div class="msg-time">${time}</div>
          <button class="msg-action-btn" onclick="copyText('${id}')" title="Copy">📋</button>
          <button class="msg-action-btn" onclick="rateMsg('${id}','up')" title="Hữu ích">👍</button>
          <button class="msg-action-btn" onclick="rateMsg('${id}','down')" title="Chưa tốt">👎</button>
        </div>
      </div>
    </div>`;
  appendMessage(html);
}

function appendMessage(html) {
  const area = document.getElementById('messages-area');
  if (!area) return;
  area.insertAdjacentHTML('beforeend', html);
  area.scrollTo({ top: area.scrollHeight, behavior: 'smooth' });
}

function formatBotText(text) {
  return text
    .replace(/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/g, '<a href="$2" target="_blank">$1</a>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/`(.+?)`/g, '<code>$1</code>')
    .replace(/\n\n/g, '</p><p>')
    .replace(/\n/g, '<br/>')
    .replace(/^/, '<p>').replace(/$/, '</p>')
    .replace(/###\s(.+)/g, '<strong style="color:var(--accent);font-size:14px">$1</strong>')
    .replace(/##\s(.+)/g, '<strong style="color:var(--accent);font-size:15px">$1</strong>');
}

function escapeHtml(text) {
  return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
             .replace(/\n/g,'<br>');
}

function copyText(msgId) {
  const el = document.querySelector(`#${msgId} .msg-bubble`);
  if (!el) return;
  navigator.clipboard.writeText(el.innerText).then(() => showToast('📋 Đã copy rồi ạ!'));
}

function rateMsg(msgId, type) {
  showToast(type === 'up' ? '👍 Cảm ơn anh đã đánh giá ạ!' : '👎 Em ghi nhận để cải thiện ạ!');
}

// ─── TYPING INDICATOR ─────────────────────────────────────────
function showTyping() {
  const html = `
    <div class="msg-row bot typing-indicator" id="typing-indicator">
      <div class="msg-avatar">🐟</div>
      <div class="msg-bubble">
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
      </div>
    </div>`;
  appendMessage(html);
}
function hideTyping() {
  document.getElementById('typing-indicator')?.remove();
}

// ─── ARTICLE FINDER ───────────────────────────────────────────
function findAndShowArticle(reply, userText) {
  const combined = (reply + ' ' + userText).toLowerCase();
  for (const [key, article] of Object.entries(RELATED_ARTICLES)) {
    if (combined.includes(key)) {
      const html = `
        <div class="msg-row bot" style="animation-delay:0.2s">
          <div class="msg-avatar" style="opacity:0">.</div>
          <div>
            <div class="article-card">
              <span class="read-icon">📖</span>
              <a href="${article.url}" target="_blank">${article.title}</a>
              <span style="font-size:10px;color:var(--text-muted);flex-shrink:0">→</span>
            </div>
          </div>
        </div>`;
      document.getElementById('messages-area').insertAdjacentHTML('beforeend', html);
      document.getElementById('messages-area').scrollTo({ top: 99999, behavior: 'smooth' });
      break;
    }
  }
}

// ─── QUICK REPLIES ────────────────────────────────────────────
function renderQuickReplies() {
  const bar = document.getElementById('quick-replies-bar');
  bar.innerHTML = QUICK_SUGGESTIONS.map(s =>
    `<button class="qr-chip" onclick="sendQuickReply('${s.text}')">
      <span>${s.icon}</span> ${s.text}
    </button>`
  ).join('');
}
function hideQuickReplies() {
  const bar = document.getElementById('quick-replies-bar');
  bar.style.opacity = '0';
  bar.style.height = '0';
  bar.style.overflow = 'hidden';
  bar.style.padding = '0';
}

// ─── IMAGE UPLOAD ─────────────────────────────────────────────
function handleImageUpload(event) {
  const file = event.target.files[0];
  if (!file) return;
  if (!file.type.startsWith('image/')) { showToast('❌ Chỉ hỗ trợ file ảnh ạ'); return; }
  if (file.size > 5 * 1024 * 1024) { showToast('❌ Ảnh quá lớn, tối đa 5MB ạ'); return; }

  const reader = new FileReader();
  reader.onload = (e) => {
    const base64 = e.target.result.split(',')[1];
    state.pendingImage = { data: base64, mimeType: file.type };
    document.getElementById('img-preview').src = e.target.result;
    document.getElementById('img-preview-wrap').style.display = 'flex';
    document.getElementById('user-input').placeholder = 'Thêm mô tả (tùy chọn)...';
    showToast('📷 Ảnh sẵn sàng! Anh nhấn gửi để AI chẩn đoán ạ');
  };
  reader.readAsDataURL(file);
  event.target.value = '';
}

function removeImage() {
  state.pendingImage = null;
  document.getElementById('img-preview-wrap').style.display = 'none';
  document.getElementById('img-preview').src = '';
  document.getElementById('user-input').placeholder = 'Hỏi về cá cảnh, bệnh, hồ lọc...';
}

// ─── CSV PRODUCT LOADING ──────────────────────────────────────
function loadProductsFromStorage() {
  const raw = localStorage.getItem('sgcc_products');
  if (raw) {
    try { state.products = JSON.parse(raw); } catch(e) {}
  }
}

function loadProductsFromCSV(csvText) {
  const lines = csvText.trim().split('\n');
  if (lines.length < 2) return 0;
  const headers = lines[0].split(',').map(h => h.trim().toLowerCase()
    .replace('tên sản phẩm', 'name').replace('tên', 'name')
    .replace('danh mục', 'category').replace('size', 'size')
    .replace('số lượng', 'qty').replace('sl', 'qty')
    .replace('giá nhập', 'importPrice').replace('giá bán', 'sellPrice')
    .replace('đơn vị', 'unit').replace('mã vạch', 'barcode')
  );
  const products = [];
  for (let i = 1; i < lines.length; i++) {
    if (!lines[i].trim()) continue;
    const vals = parseCSVLine(lines[i]);
    const obj = {};
    headers.forEach((h, idx) => { obj[h] = (vals[idx] || '').trim(); });
    if (obj.name) products.push(obj);
  }
  state.products = products;
  localStorage.setItem('sgcc_products', JSON.stringify(products));
  return products.length;
}

function parseCSVLine(line) {
  const result = []; let current = ''; let inQuotes = false;
  for (const ch of line) {
    if (ch === '"') { inQuotes = !inQuotes; }
    else if (ch === ',' && !inQuotes) { result.push(current); current = ''; }
    else { current += ch; }
  }
  result.push(current);
  return result;
}

// ─── OCEAN ANIMATIONS ─────────────────────────────────────────
function createOceanAnimations() {
  // Bubbles
  const bubbleContainer = document.getElementById('bubbles');
  for (let i = 0; i < 18; i++) {
    const b = document.createElement('div');
    b.className = 'bubble';
    const size = Math.random() * 20 + 6;
    b.style.cssText = `
      width:${size}px; height:${size}px;
      left:${Math.random() * 100}%;
      animation-duration:${Math.random() * 8 + 6}s;
      animation-delay:${Math.random() * 8}s;
    `;
    bubbleContainer.appendChild(b);
  }

  // Fish
  const fishContainer = document.getElementById('fish-container');
  const fishEmojis = ['🐠', '🐡', '🐟', '🐟', '🐠'];
  for (let i = 0; i < 5; i++) {
    const f = document.createElement('div');
    f.className = 'fish';
    f.textContent = fishEmojis[i % fishEmojis.length];
    const topPct = 10 + Math.random() * 80;
    const duration = Math.random() * 20 + 18;
    const delay = Math.random() * 15;
    f.style.cssText = `
      top:${topPct}%; font-size:${16 + Math.random()*12}px;
      animation-duration:${duration}s;
      animation-delay:${delay}s;
    `;
    fishContainer.appendChild(f);
  }
}

// ─── INPUT HELPERS ────────────────────────────────────────────
function handleKeyDown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function clearChat() {
  if (!confirm('Xóa toàn bộ lịch sử chat không anh?')) return;
  document.getElementById('messages-area').innerHTML = '';
  state.chatHistory = [];
  state.messages = [];
  localStorage.removeItem('sgcc_chat_history');
  renderQuickReplies();
  const qr = document.getElementById('quick-replies-bar');
  qr.style.opacity = '1'; qr.style.height = '';
  qr.style.overflow = ''; qr.style.padding = '';
  showWelcomeMessage();
  showToast('🗑️ Đã xóa lịch sử chat ạ');
}

// ─── UTILITIES ────────────────────────────────────────────────
function formatTime(date) {
  return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}

function showToast(msg) {
  const existing = document.querySelector('.toast');
  if (existing) existing.remove();
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = msg;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}
