<template id="aiChatTemplate">
    <div class="ai-chat-widget">
        <button class="ai-chat-trigger" onclick="toggleChat()">
            <i data-lucide="bot" class="icon-bot"></i>
        </button>

        <div class="ai-chat-window" id="aiChatWindow">
            <div class="ai-chat-header">
                <div class="ai-profile">
                    <div>
                        <h4 class="ai-title">ผู้ช่วยประชุม</h4>
                        <span class="ai-subtitle">พร้อมให้บริการตลอด 24 ชม.</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button onclick="clearChat()"
                        style="background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; transition: color 0.2s;"
                        title="ล้างประวัติการสนทนา">
                        <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                    </button>
                    <button class="btn-close-chat" onclick="toggleChat()">&times;</button>
                </div>
            </div>

            <div class="ai-chat-body" id="chatBody">
                <div class="message ai-msg">สวัสดีครับ มีอะไรให้ผมช่วยเหลือเกี่ยวกับวาระการประชุมในวันนี้ไหมครับ?</div>
            </div>

            <div class="ai-chat-footer">
                <div class="input-container">
                    <input type="text" id="chatInput" placeholder="พิมพ์ข้อความถาม AI..."
                        onkeypress="handleKeyPress(event)" autocomplete="off">
                    <button class="btn-send-chat" onclick="sendMessage()">
                        <i data-lucide="send" style="width: 15px; height: 15px;"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.ai-chat-widget {
    position: fixed;
    bottom: 32px;
    right: 32px;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.ai-chat-trigger {
    width: 60px;
    height: 60px;
    background: #0f172a;
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.ai-chat-trigger:hover {
    transform: translateY(-3px) scale(1.02);
    background: #1e293b;
    box-shadow: 0 20px 32px -8px rgba(15, 23, 42, 0.35);
}

.ai-chat-trigger .icon-bot {
    width: 26px;
    height: 26px;
}

.ai-chat-window {
    display: none;
    width: 370px;
    height: 520px;
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 0 24px 48px -12px rgba(15, 23, 42, 0.12);
    position: absolute;
    bottom: 76px;
    right: 0;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #f1f5f9;
    animation: fadeIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(16px) scale(0.96);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.ai-chat-header {
    background: #ffffff;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #0f172a;
    border-bottom: 1px solid #f1f5f9;
}

.ai-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ai-avatar {
    width: 34px;
    height: 34px;
    background: #f1f5f9;
    color: #0f172a;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ai-title {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
}

.ai-subtitle {
    font-size: 11px;
    color: #64748b;
    display: block;
    margin-top: 1px;
}

.btn-close-chat {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
    padding-bottom: 4px;
    transition: color 0.2s;
}

.btn-close-chat:hover {
    color: #475569;
}

.ai-chat-body {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: #f8fafc;
    scrollbar-width: thin;
}

.ai-chat-body::-webkit-scrollbar {
    width: 4px;
}

.ai-chat-body::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}

.message {
    max-width: 82%;
    padding: 11px 15px;
    border-radius: 16px;
    font-size: 13.5px;
    line-height: 1.5;
}

.ai-msg {
    background: #ffffff;
    color: #334155;
    align-self: flex-start;
    border-bottom-left-radius: 4px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.01);
}

.user-msg {
    background: #0f172a;
    color: white;
    align-self: flex-end;
    border-bottom-right-radius: 4px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.ai-chat-footer {
    padding: 14px 20px 18px 20px;
    border-top: 1px solid #f1f5f9;
    background: white;
}

.input-container {
    display: flex;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 4px 4px 4px 14px;
    align-items: center;
    transition: all 0.2s;
}

.input-container:focus-within {
    border-color: #cbd5e1;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.03);
}

.input-container input {
    flex: 1;
    border: none;
    background: transparent;
    font-size: 13.5px;
    outline: none;
    color: #0f172a;
    padding: 6px 0;
}

.input-container input::placeholder {
    color: #94a3b8;
}

.btn-send-chat {
    background: #0f172a;
    color: white;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s, transform 0.1s;
}

.btn-send-chat:hover {
    background: #1e293b;
    transform: scale(1.03);
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const template = document.getElementById('aiChatTemplate');
    if (template) {
        const clone = template.content.cloneNode(true);
        document.body.appendChild(clone);
        if (window.lucide) {
            lucide.createIcons();
        }
    }
});

function toggleChat() {
    const chatWindow = document.getElementById('aiChatWindow');
    if (chatWindow) {
        chatWindow.style.display = (chatWindow.style.display === 'none' || chatWindow.style.display === '') ? 'flex' :
            'none';
        if (window.lucide) {
            lucide.createIcons();
        }
    }
}

function handleKeyPress(e) {
    if (e.key === 'Enter') sendMessage();
}

function clearChat() {
    if (confirm('คุณต้องการล้างประวัติการสนทนานี้ใช่หรือไม่?')) {
        const chatBody = document.getElementById('chatBody');
        const clearUrl = window.location.origin + '/Meeting_msu/app/controllers/ChatAiController.php?clear=1';
        fetch(clearUrl)
            .then(res => res.json())
            .then(data => {
                console.log('AI Memory:', data.message);
            })
            .catch(err => console.error('Clear memory error:', err));

        if (chatBody) {
            chatBody.innerHTML =
                `<div class="message ai-msg">สวัสดีครับ มีอะไรให้ผมช่วยเหลือเกี่ยวกับวาระการประชุมในวันนี้ไหมครับ?</div>`;
        }
    }
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    if (!input) return;
    const msgText = input.value.trim();
    if (!msgText) return;

    const chatBody = document.getElementById('chatBody');
    if (!chatBody) return;

    const userMessage = document.createElement('div');
    userMessage.className = 'message user-msg';
    userMessage.textContent = msgText;
    chatBody.appendChild(userMessage);
    input.value = '';
    chatBody.scrollTop = chatBody.scrollHeight;

    const targetUrl = window.location.origin + '/Meeting_msu/app/controllers/ChatAiController.php';

    fetch(targetUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'message=' + encodeURIComponent(msgText)
        })
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            const aiMessage = document.createElement('div');
            aiMessage.className = 'message ai-msg';
            aiMessage.textContent = data.reply || '';
            chatBody.appendChild(aiMessage);
            chatBody.scrollTop = chatBody.scrollHeight;
        })
        .catch(err => {
            console.error('Chat Error:', err);
            chatBody.innerHTML +=
                `<div class="message ai-msg" style="color:#ef4444; border-color:#fee2e2; background:#fef2f2;">ขออภัย ระบบเชื่อมต่อ AI ขัดข้องในขณะนี้</div>`;
            chatBody.scrollTop = chatBody.scrollHeight;
        });
}
</script>