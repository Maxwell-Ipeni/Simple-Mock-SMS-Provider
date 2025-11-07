<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Simple Mock SMS Provider</title>
    <style>
      :root{--bg:#ffffff;--muted:#f3f4f6;--accent:#1e90ff;--border:#e6e6e6}
      body{font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; background:var(--bg); color:#111; margin:32px}
      .wrap{display:flex;gap:32px;align-items:flex-start}
      .col{background:transparent;flex:1}
      .left{flex:0 0 55%}
      .right{flex:0 0 40%}
      h1{font-size:28px;margin:4px 0 18px}
      label{display:block;margin:8px 0 6px;font-weight:600}
      input[type=text].endpoint{width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;background:#fafafa;font-family:monospace}
      .json-block{width:100%;min-height:110px;border:1px solid var(--border);background:#fafafa;padding:12px;border-radius:6px;font-family:monospace;white-space:pre-wrap}
      textarea.payload{width:100%;min-height:110px;border:1px solid var(--border);background:#fafafa;padding:12px;border-radius:6px;font-family:monospace;resize:vertical}
      button.send{background:var(--accent);color:#fff;padding:10px 16px;border-radius:6px;border:0;margin-top:12px;cursor:pointer}
  .messages-box{border:1px solid var(--border);background:#fff;padding:12px;border-radius:8px;margin-top:10px;max-height:480px;overflow-y:auto;display:flex;flex-direction:column;gap:8px}
  .bubble{display:block;background:#f1f5f9;padding:10px 14px;border-radius:18px;margin:0;max-width:100%;font-size:15px;position:relative}
  .bubble .timestamp{display:block;font-size:12px;color:#666;margin-top:8px;text-align:right}
  .bubble .bubble-text{white-space:pre-wrap}
      .response-block{border:1px solid var(--border);background:#fafafa;padding:12px;border-radius:6px;font-family:monospace;min-height:300px}
      .subheading{margin-top:18px;font-size:18px}
      .controls{display:flex;gap:8px;align-items:center;margin-top:8px}
      .upload{font-size:13px}
      .small{font-size:13px;color:#666}
      .row{display:flex;gap:12px}
      .muted-box{background:#f8fafc;border:1px solid var(--border);padding:10px;border-radius:6px}
      .edit-toggle{background:transparent;border:1px dashed var(--border);padding:6px;border-radius:6px;cursor:pointer}
    </style>
  </head>
  <body>
    <div id="app">
      <h1>Simple Mock SMS Provider</h1>
      <div class="wrap">
        <div class="col left">
          <label>API Endpoint</label>
          <div style="display:flex;gap:8px;align-items:center">
            <input type="text" class="endpoint" id="endpointInput" readonly value="http://127.0.0.1:8000/receive-sms" />
            <button id="unlockBtn" class="edit-toggle" title="Edit endpoint">Edit</button>
          </div>

          <label style="margin-top:12px">Text payload</label>
          <textarea id="payloadInput" class="payload">{
  "message": "Hello, this is a test message."
}</textarea>

          <div class="controls">
            <button id="sendBtn" class="send">Send message</button>
            <div class="upload small">
              <label for="jsonFile" style="cursor:pointer">Upload JSON</label>
              <input type="file" id="jsonFile" accept="application/json" style="display:none" />
            </div>
          </div>

          <div class="subheading">Messages</div>
          <div class="messages-box" id="messagesBox">
            <!-- messages will render here -->
          </div>
        </div>

        <div class="col right">
          <label>Response</label>
          <div class="response-block" id="responseBlock">{
  "success": true,
  "message": "SMS sent successfully.",
  "data": {
    "id": "1",
    "message": "Hello, this is a test message."
  }
}</div>
        </div>
      </div>
    </div>

    <!-- React + Babel (for simple JSX in browser) -->
    <script src="https://unpkg.com/react@17/umd/react.development.js"></script>
    <script src="https://unpkg.com/react-dom@17/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <script>
      (function(){
        const endpointInput = document.getElementById('endpointInput');
        const unlockBtn = document.getElementById('unlockBtn');
        const payloadInput = document.getElementById('payloadInput');
        const sendBtn = document.getElementById('sendBtn');
        const messagesBox = document.getElementById('messagesBox');
        const responseBlock = document.getElementById('responseBlock');
        const jsonFile = document.getElementById('jsonFile');

        let unlocked = false;

        function renderMessages(items){
          messagesBox.innerHTML = '';
          if(!items || items.length === 0){
            messagesBox.innerHTML = '<div class="small">No messages yet.</div>';
            return;
          }

          // Sort messages newest -> oldest by created_at if available, otherwise keep original order and reverse
          const sorted = (items.slice()).sort((a, b) => {
            if (a && b && a.created_at && b.created_at) {
              return new Date(b.created_at) - new Date(a.created_at);
            }
            return 0;
          });

          sorted.forEach(m => {
            const div = document.createElement('div');
            div.className = 'bubble';

            const text = document.createElement('div');
            text.className = 'bubble-text';
            text.textContent = m.message || JSON.stringify(m.payload || m);

            const ts = document.createElement('div');
            ts.className = 'timestamp small';
            ts.textContent = m.created_at ? new Date(m.created_at).toLocaleString() : '';

            div.appendChild(text);
            div.appendChild(ts);
            messagesBox.appendChild(div);
          });

          // Keep view pinned to newest message at top
          messagesBox.scrollTop = 0;
        }

        function appendMessage(entry){
          // insert newest at top
          const div = document.createElement('div');
          div.className = 'bubble';

          const text = document.createElement('div');
          text.className = 'bubble-text';
          text.textContent = entry.message || JSON.stringify(entry.payload || entry);

          const ts = document.createElement('div');
          ts.className = 'timestamp small';
          ts.textContent = entry.created_at ? new Date(entry.created_at).toLocaleString() : '';

          div.appendChild(text);
          div.appendChild(ts);
          messagesBox.insertBefore(div, messagesBox.firstChild);
          // show the new item
          messagesBox.scrollTop = 0;
        }

        async function loadCache(){
          try{
            const res = await axios.get('/api/cache-watch');
            renderMessages(res.data || []);
          }catch(e){
            console.error(e);
          }
        }

        unlockBtn.addEventListener('click', ()=>{
          unlocked = !unlocked;
          endpointInput.readOnly = !unlocked;
          unlockBtn.textContent = unlocked ? 'Lock' : 'Edit';
        });

        jsonFile.addEventListener('change', (ev)=>{
          const f = ev.target.files[0];
          if(!f) return;
          const reader = new FileReader();
          reader.onload = function(e){
            try{
              const text = e.target.result;
              // pretty print
              const j = JSON.parse(text);
              payloadInput.value = JSON.stringify(j, null, 2);
            }catch(err){
              alert('Invalid JSON file');
            }
          };
          reader.readAsText(f);
        });

        sendBtn.addEventListener('click', async ()=>{
          let payloadText = payloadInput.value || '';
          let payload;
          try{
            // try parse as JSON first
            payload = payloadText.trim() ? JSON.parse(payloadText) : {};
          }catch(e){
            // if not valid JSON, allow raw text and send as { message: "..." }
            payload = { message: payloadText };
            console.warn('Payload was not valid JSON, sending as plain message object');
          }

          const cb = endpointInput.value || 'http://127.0.0.1:8000/receive-sms';

          try{
            const res = await axios.post('/api/get-message', { callback: cb, payload });
            // show response (includes callback status/response now)
            responseBlock.textContent = JSON.stringify(res.data, null, 2);

            // immediately append the returned entry to the UI (optimistic)
            if(res.data && res.data.data){
              appendMessage(res.data.data);
            }

            // refresh cache to sync with server state
            setTimeout(loadCache, 200);
            // also try an immediate cache refresh
            loadCache();
          }catch(err){
            responseBlock.textContent = JSON.stringify({error: err.message}, null, 2);
          }
        });

        // initial messages (match specification)
        function seedInitialMessages(){
          // Only seed if cache is empty
          axios.get('/api/cache-watch').then(res=>{
            if(!res.data || res.data.length === 0){
              const presets = [
                {message: 'Hello, this is a test message.'},
                {message: 'Hi, how can I help you?'},
                {message: "I'm just testing the fake SMS provider."}
              ];
              // push them via sendMessage to cache
              presets.forEach(p=> axios.post('/api/send-message', {payload: p}).catch(()=>{}));
              setTimeout(loadCache, 200);
            } else {
              renderMessages(res.data);
            }
          }).catch(()=>{});
        }

        // kick off
        seedInitialMessages();
      })();
    </script>
  </body>
</html>
