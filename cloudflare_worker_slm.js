/**
 * cloudflare_worker_slm.js — Cloudflare Worker Micro SLM for Sài Gòn Cá Cảnh
 * Triển khai miễn phí trên Cloudflare Workers AI (Free Tier 10,000 neuron units/ngày)
 * Mô hình: @cf/qwen/qwen1.5-0.5b-chat hoặc @cf/meta/llama-3.2-1b-instruct
 */

export default {
  async fetch(request, env) {
    // CORS headers
    const corsHeaders = {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization',
      'Content-Type': 'application/json; charset=utf-8',
    };

    if (request.method === 'OPTIONS') {
      return new Response(null, { headers: corsHeaders });
    }

    if (request.method !== 'POST') {
      return new Response(JSON.stringify({ error: 'Only POST supported' }), {
        status: 405,
        headers: corsHeaders,
      });
    }

    try {
      const body = await request.json();
      const userMessage = body.question || body.message || '';

      if (!userMessage) {
        return new Response(JSON.stringify({ error: 'Missing question parameter' }), {
          status: 400,
          headers: corsHeaders,
        });
      }

      const systemPrompt = `Bạn là trợ lý AI thân thiện, nhiệt tình của cửa hàng tiệm cá cảnh 'Sài Gòn Cá Cảnh' (địa chỉ 246 Hồ Văn Huê, Phú Nhuận, TP.HCM).
Xưng hô: Luôn gọi khách hàng là "anh" (hoặc "chị" tùy ngữ cảnh) và tự xưng là "em".
Văn phong: Tự nhiên, gần gũi, nhiệt tình đúng chất anh em chơi cá Sài Gòn.
Nhiệm vụ: Trả lời ngắn gọn, tư vấn cá cảnh, thức ăn, thuốc trị bệnh cá, thiết bị hồ cá và hướng dẫn khách mua tại [Siêu Thị Sài Gòn Cá Cảnh](https://shop.saigoncacanh.com).`;

      // Phản hồi từ mô hình Cloudflare Workers AI
      const aiResponse = await env.AI.run('@cf/qwen/qwen1.5-0.5b-chat', {
        messages: [
          { role: 'system', content: systemPrompt },
          { role: 'user', content: userMessage }
        ],
        max_tokens: 350,
      });

      const replyText = aiResponse.response || 'Dạ em chào anh, anh ghé gian hàng [Siêu Thị Sài Gòn Cá Cảnh](https://shop.saigoncacanh.com) xem sản phẩm giúp em nha!';

      return new Response(JSON.stringify({
        status: 'success',
        source: 'Cloudflare_Worker_SLM',
        reply: replyText,
      }), {
        headers: corsHeaders,
      });
    } catch (err) {
      return new Response(JSON.stringify({
        error: err.message || 'Worker AI Error',
      }), {
        status: 500,
        headers: corsHeaders,
      });
    }
  },
};
