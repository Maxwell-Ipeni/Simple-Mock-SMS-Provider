<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Simple Mock SMS Provider</title>
    <style>
      :root{--bg:#ffffff;--muted:#f3f4f6;--accent:#1e90ff;--accent-2:#7c6cff;--success:#10b981;--danger:#ef4444;--border:#e6e6e6;--radius:8px}
      html,body{height:100%;margin:0;font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;background:var(--bg);color:#111}
      #app{min-height:100vh;padding:20px;box-sizing:border-box}

      /* Layout */
      .wrap{display:flex;gap:24px;align-items:flex-start}
      .col{display:flex;flex-direction:column}
      .left{flex:1 1 auto;min-width:0}
      .right{flex:0 0 360px;display:flex;flex-direction:column;gap:12px}

      h1{font-size:24px;margin:0 0 12px}
      label{display:block;margin:6px 0;color:#222;font-weight:600}
      .small{font-size:13px;color:#666}

      /* Inputs */
      .endpoint,input[type=text],textarea{width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;background:#fafafa;font-family:monospace;box-sizing:border-box}
      .json-block{min-height:120px;white-space:pre-wrap}
      .muted-box{background:#f8fafc;border:1px solid var(--border);padding:8px;border-radius:6px}

      /* Buttons */
      .btn{padding:8px 12px;border-radius:6px;border:0;cursor:pointer}
      .btn-primary{background:var(--accent);color:#fff}
      .btn-alt{background:var(--accent-2);color:#fff}
      .btn-success{background:var(--success);color:#fff}
      .btn-ghost{background:transparent;border:1px dashed var(--border);color:#333}

      /* Messages */
      .messages-box{border:1px solid var(--border);background:#fff;padding:12px;border-radius:var(--radius);margin-top:10px;max-height:64vh;overflow:auto;display:flex;flex-direction:column;gap:10px}
      .bubble{display:block;background:#f1f5f9;padding:12px;border-radius:16px;margin:0;max-width:100%;font-size:15px}
      .bubble.outgoing{background:#eef2ff}
      .bubble .timestamp{display:block;font-size:12px;color:#666;margin-top:8px;text-align:right}
      .bubble .bubble-text{white-space:pre-wrap}

      /* Panels */
      .panel{background:#fff;border:1px solid var(--border);padding:12px;border-radius:8px}
      .panel h3{margin:0 0 8px;font-size:15px}
      .panel.sticky{position:sticky;top:20px}

  /* Validation state */
  .is-invalid{border-color:var(--danger) !important;box-shadow:0 0 0 3px rgba(239,68,68,0.06)}
  .validation-msg{color:var(--danger);font-size:12px;margin-top:6px}

  /* Inline reply box under a message */
  .inline-reply{margin-top:8px;display:flex;gap:8px;align-items:flex-start}
  .inline-reply textarea{flex:1;min-height:64px}

      /* Response area */
      .response-block{border:1px solid var(--border);background:#fafafa;padding:12px;border-radius:6px;font-family:monospace;min-height:160px;overflow:auto}

      /* Responsive */
      @media (max-width:900px){
        .wrap{flex-direction:column}
        .right{flex:1 1 auto}
        .panel.sticky{position:relative;top:auto}
      }
    </style>
  </head>
  <body>
    <div id="app">
      <h1>Simple Mock SMS Provider</h1>
      <div class="wrap">
        <div class="col left">
          <div class="panel">
            <div class="row" style="display:flex;gap:12px;align-items:flex-start">
              <div style="flex:1">
                <label>Receive/Poll Endpoint (GET)</label>
                <div class="row" style="display:flex;gap:6px;align-items:center">
                  <input type="text" id="pollEndpointInput" class="endpoint" value="http://127.0.0.1:8000/messages/poll" />
                  <button id="testPollBtn" class="btn btn-ghost" title="Test poll">Test</button>
                </div>
                
              </div>
              <div style="flex:1">
                <label>Reply/Send Endpoint (POST)</label>
                <div class="row" style="display:flex;gap:6px;align-items:center">
                  <input type="text" id="replyEndpointInput" class="endpoint" value="http://127.0.0.1:8000/messages/send" />
                  <button id="testSendBtn" class="btn btn-ghost" title="Test send">Test</button>
                </div>
              </div>
            </div>

            <div style="margin-top:12px">
              <div style="display:flex;justify-content:space-between;align-items:center">
                <div style="font-weight:700;font-size:18px">Conversation History</div>
                <div style="display:flex;align-items:left;gap:8px">
                  <div id="userBadge" class="small muted-box" style="padding:6px 10px;border-radius:999px;cursor:default">User: <span id="userBadgeText">06995442...</span></div>
                </div>
              </div>

              <div class="messages-box" id="messagesBox">
                <div class="small" id="emptyConversation">No messages yet. Messages will appear here after a successful reply to your API.</div>
              </div>

              <div class="row" style="display:flex;gap:8px;align-items:center;margin-top:6px">
                <input type="text" id="replyInput" placeholder="Type your reply message here..." style="flex:1;padding:10px;border:1px solid var(--border);border-radius:6px;font-size:14px" />
                <button id="replySendBtn" class="btn btn-alt">Send</button>
              </div>
            </div>
          </div>
        </div>

        <div class="col right">
          <div id="headersPanel" class="panel sticky">
            <h3>Request Headers</h3>
            <div class="small" style="margin-bottom:8px">Add a single header (e.g., Authorization) that will be forwarded with outbound requests.</div>
            <label class="small">Header Key</label>
            <input type="text" id="headerKeyInput" placeholder="Authorization" />
            <label class="small" style="margin-top:8px">Header Value</label>
            <input type="text" id="headerValueInput" placeholder="Bearer sk-xxxxxxxxxxxxxxxx" />
          </div>

          <div id="jsonTemplates" class="panel">
            <h3>JSON Templates</h3>
            <div class="small" style="color:#666;margin-bottom:10px">Use <code>@{{message}}</code> as a placeholder for the text content.</div>

            <div style="margin-bottom:10px">
              <div style="font-weight:600;margin-bottom:6px">Inbound (Receive) Template</div>
              <div style="display:flex;gap:8px;margin-bottom:6px">
                <button id="uploadInboundBtn" class="btn btn-ghost">Upload JSON</button>
                <input type="file" id="uploadInboundFile" accept="application/json" style="display:none" />
                <button id="saveInboundTemplate" class="btn btn-ghost">Save</button>
              </div>
              <textarea id="inboundTemplateTextarea" class="json-block" placeholder='{"id":123,"sender":"+25451234","text":"@{{message}}","timestamp":"2025-11-17"}'></textarea>
            </div>

            <div>
              <div style="font-weight:600;margin-bottom:6px">Outbound (Send) Template</div>
              <div style="display:flex;gap:8px;margin-bottom:6px">
                <button id="uploadOutboundBtn" class="btn btn-ghost">Upload JSON</button>
                <input type="file" id="uploadOutboundFile" accept="application/json" style="display:none" />
                <button id="saveOutboundTemplate" class="btn btn-ghost">Save</button>
              </div>
              <textarea id="outboundTemplateTextarea" class="json-block" placeholder='{"recipient":"+25451234","message":"@{{message}}","source":"token-app"}'></textarea>
            </div>
          </div>

          <div class="panel">
            <h3>Response</h3>
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
    </div>

  <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <script>
      (function(){
        // Configure axios defaults to avoid long-hanging requests
        if (window.axios) {
          axios.defaults.timeout = 6000; // 6s
          axios.defaults.headers.post['Content-Type'] = 'application/json';
        }
        // endpoint inputs and test buttons
        const pollEndpointInput = document.getElementById('pollEndpointInput');
        const replyEndpointInput = document.getElementById('replyEndpointInput');
        const testPollBtn = document.getElementById('testPollBtn');
        const testSendBtn = document.getElementById('testSendBtn');

  // main controls
  const replyInput = document.getElementById('replyInput');
  const replySendBtn = document.getElementById('replySendBtn');
  const messagesBox = document.getElementById('messagesBox');
  const responseBlock = document.getElementById('responseBlock');

  // header inputs
  const headerKeyInput = document.getElementById('headerKeyInput');
  const headerValueInput = document.getElementById('headerValueInput');
  // auto-poll controls removed per user request

  // template inputs (inbound/outbound)
  const inboundTemplateTextarea = document.getElementById('inboundTemplateTextarea');
  const outboundTemplateTextarea = document.getElementById('outboundTemplateTextarea');
  const uploadInboundBtn = document.getElementById('uploadInboundBtn');
  const uploadOutboundBtn = document.getElementById('uploadOutboundBtn');
  const uploadInboundFile = document.getElementById('uploadInboundFile');
  const uploadOutboundFile = document.getElementById('uploadOutboundFile');
  const saveInboundTemplate = document.getElementById('saveInboundTemplate');
  const saveOutboundTemplate = document.getElementById('saveOutboundTemplate');

  // template helpers
  function replacePlaceholder(tpl, message){
    if(!tpl) return null;
    try{ return tpl.replace(/\{\{\s*message\s*\}\}/g, message); }catch(e){ return tpl; }
  }

  async function loadTemplates(){
    try{
      const res = await axios.get('/api/templates');
      if(res && res.data){
        if(res.data.inbound) inboundTemplateTextarea.value = res.data.inbound;
        if(res.data.outbound) outboundTemplateTextarea.value = res.data.outbound;
      }
    }catch(e){ console.warn('Could not load templates', e); }
  }

  async function saveTemplate(type, tpl){
    try{
      // Validate JSON before saving
      if(tpl && !isValidJSON(tpl)){
        responseBlock.textContent = JSON.stringify({ error: 'Invalid JSON — fix template before saving.' }, null, 2);
        return;
      }
      const res = await axios.post('/api/templates/save', { type, template: tpl });
      responseBlock.textContent = JSON.stringify(res.data, null, 2);
    }catch(e){ responseBlock.textContent = JSON.stringify({error: e.message}, null, 2); }
  }

  // upload file wiring
  uploadInboundBtn.addEventListener('click', ()=> uploadInboundFile.click());
  uploadOutboundBtn.addEventListener('click', ()=> uploadOutboundFile.click());

  uploadInboundFile.addEventListener('change', (ev)=>{
    const f = ev.target.files && ev.target.files[0];
    if(!f) return;
    const reader = new FileReader();
    reader.onload = ()=>{ inboundTemplateTextarea.value = reader.result; };
    reader.readAsText(f);
  });

  uploadOutboundFile.addEventListener('change', (ev)=>{
    const f = ev.target.files && ev.target.files[0];
    if(!f) return;
    const reader = new FileReader();
    reader.onload = ()=>{ outboundTemplateTextarea.value = reader.result; };
    reader.readAsText(f);
  });

  saveInboundTemplate.addEventListener('click', ()=> saveTemplate('inbound', inboundTemplateTextarea.value));
  saveOutboundTemplate.addEventListener('click', ()=> saveTemplate('outbound', outboundTemplateTextarea.value));

  // Client-side JSON validation helpers and live UI feedback
  function isValidJSON(str){
    if(!str || !str.trim()) return true; // allow empty
    try{ JSON.parse(str); return true; }catch(e){ return false; }
  }

  function setValidationState(el, ok){
    if(!el) return;
    if(ok){ el.classList.remove('is-invalid'); const msg = el.nextElementSibling; if(msg && msg.classList && msg.classList.contains('validation-msg')) msg.remove(); }
    else { el.classList.add('is-invalid'); if(!(el.nextElementSibling && el.nextElementSibling.classList && el.nextElementSibling.classList.contains('validation-msg'))){ const m = document.createElement('div'); m.className='validation-msg'; m.textContent='Invalid JSON'; el.parentNode.insertBefore(m, el.nextSibling); } }
  }

  inboundTemplateTextarea.addEventListener('input', ()=> setValidationState(inboundTemplateTextarea, isValidJSON(inboundTemplateTextarea.value)));
  outboundTemplateTextarea.addEventListener('input', ()=> setValidationState(outboundTemplateTextarea, isValidJSON(outboundTemplateTextarea.value)));

  // load saved endpoints or keep default
        const savedPoll = localStorage.getItem('sms_poll_endpoint');
        const savedReply = localStorage.getItem('sms_reply_endpoint');
        if (savedPoll) pollEndpointInput.value = savedPoll;
        if (savedReply) replyEndpointInput.value = savedReply;

  // load saved headers
  const savedHeaderKey = localStorage.getItem('sms_header_key');
  const savedHeaderValue = localStorage.getItem('sms_header_value');
  if (savedHeaderKey) headerKeyInput.value = savedHeaderKey;
  if (savedHeaderValue) headerValueInput.value = savedHeaderValue;

        function saveEndpoint(key, value){
          try { localStorage.setItem(key, value); } catch(e){ console.warn('Could not persist endpoint', e); }
        }

        // Debounced server-side save for UI settings (persist endpoints, headers, user id)
        let settingsSaveTimer = null;
        function scheduleSaveSettings(){
          try{ clearTimeout(settingsSaveTimer); }catch(e){}
          settingsSaveTimer = setTimeout(()=>{
            try{
              const payload = {
                poll_endpoint: pollEndpointInput.value.trim(),
                reply_endpoint: replyEndpointInput.value.trim(),
                header_key: headerKeyInput.value.trim(),
                header_value: headerValueInput.value.trim(),
                user_id: (localStorage.getItem('sms_user_id') || (document.getElementById('userBadgeText')||{}).textContent || '').trim()
              };
              axios.post('/api/settings/save', payload).catch(()=>{});
            }catch(e){ /* ignore */ }
          }, 500);
        }

        // Auto-poll removed — Test Poll button still triggers a single poll when pressed

        let pollSaveTimer, replySaveTimer;
        pollEndpointInput.addEventListener('input', () => {
          clearTimeout(pollSaveTimer);
          pollSaveTimer = setTimeout(()=> saveEndpoint('sms_poll_endpoint', pollEndpointInput.value.trim()), 300);
        });
        replyEndpointInput.addEventListener('input', () => {
          clearTimeout(replySaveTimer);
          replySaveTimer = setTimeout(()=> saveEndpoint('sms_reply_endpoint', replyEndpointInput.value.trim()), 300);
        });

        // persist header inputs
        function saveHeader(key, value){ try { localStorage.setItem(key, value); } catch(e){} }

  // when local inputs change, also schedule server-side persistence
  pollEndpointInput.addEventListener('input', ()=> scheduleSaveSettings());
  replyEndpointInput.addEventListener('input', ()=> scheduleSaveSettings());
  headerKeyInput.addEventListener('input', ()=> scheduleSaveSettings());
  headerValueInput.addEventListener('input', ()=> scheduleSaveSettings());
        let headerSaveTimerA, headerSaveTimerB;
        headerKeyInput.addEventListener('input', ()=>{ clearTimeout(headerSaveTimerA); headerSaveTimerA = setTimeout(()=> saveHeader('sms_header_key', headerKeyInput.value.trim()), 300); });
        headerValueInput.addEventListener('input', ()=>{ clearTimeout(headerSaveTimerB); headerSaveTimerB = setTimeout(()=> saveHeader('sms_header_value', headerValueInput.value.trim()), 300); });

        function getHeaders(){
          const k = (headerKeyInput.value || '').trim();
          const v = (headerValueInput.value || '').trim();
          if(!k) return {};
          const obj = {};
          obj[k] = v;
          return obj;
        }

        function getPollEndpoint(){ return pollEndpointInput.value.trim() || 'http://127.0.0.1:8000/messages/poll'; }
        function getReplyEndpoint(){ return replyEndpointInput.value.trim() || 'http://127.0.0.1:8000/messages/send'; }

        // Format timestamps in Kenyan time (Africa/Nairobi)
        function formatKenyaTime(dateStr){
          try{
            if(!dateStr) return '';
            let s = dateStr;
            // If numeric (epoch ms) convert to Number
            if(typeof s === 'number' || /^[0-9]+$/.test(String(s))){
              const n = Number(s);
              if(!isNaN(n)) return new Date(n).toLocaleString('en-KE', { timeZone: 'Africa/Nairobi', year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
            }

            // If format like 'YYYY-MM-DD HH:MM:SS' or 'YYYY-MM-DDTHH:MM:SS' without timezone, treat as UTC by appending 'Z'
            if(/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/.test(s)){
              s = s.replace(' ', 'T') + 'Z';
            }

            const d = new Date(s);
            if(isNaN(d.getTime())) return dateStr || '';
            return d.toLocaleString('en-KE', { timeZone: 'Africa/Nairobi', year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
          }catch(e){ return dateStr || ''; }
        }

        // Extract best recipient value from a payload or entry
        function extractRecipient(payload){
          if(!payload) return '';
          if(typeof payload === 'string') return payload;
          return payload.to || payload.recipient || payload.to_number || payload.number || payload.phone || payload.user_id || '';
        }

        function renderMessages(items){
          messagesBox.innerHTML = '';
          if(!items || items.length === 0){
            messagesBox.innerHTML = '<div class="small">No messages yet.</div>';
            return;
          }

          const sorted = (items.slice()).sort((a, b) => {
            if (a && b && a.created_at && b.created_at) {
              return new Date(b.created_at) - new Date(a.created_at);
            }
            return 0;
          });

          sorted.forEach(m => {
            const div = document.createElement('div');
            div.className = 'bubble';

            const fromLine = document.createElement('div');
            fromLine.className = 'small';
            fromLine.style.marginBottom = '6px';
            // Display From for incoming messages, To for outgoing
            try{
              const contact = (function getContactLabel(ent){
                if(!ent) return { label: '', value: '' };
                // prefer explicit 'to' fields for outgoing
                if(ent.type === 'outgoing'){
                  const v = ent.to || (ent.payload && (ent.payload.recipient || ent.payload.to || ent.payload.number || ent.payload.phone || ent.payload.to_number)) || '';
                  return { label: 'To', value: v };
                }
                // incoming/default
                const v = ent.from || (ent.payload && (ent.payload.from || ent.payload.sender || ent.payload.phone)) || '';
                return { label: 'From', value: v };
              })(m);
              fromLine.textContent = contact.value ? (contact.label + ': ' + contact.value) : '';
            }catch(e){ fromLine.textContent = m.from ? ('From: ' + m.from) : (m.payload && m.payload.from ? ('From: ' + m.payload.from) : ''); }

            const text = document.createElement('div');
            text.className = 'bubble-text';
            // attempt to render using inbound template if available
            try{
              const tpl = (inboundTemplateTextarea && inboundTemplateTextarea.value || '').trim();
              let display = m.message || '';
              if(tpl && m.payload){
                const msg = (m.payload.message || m.payload.text || (typeof m.payload === 'string' ? m.payload : ''));
                const replaced = replacePlaceholder(tpl, msg || display || JSON.stringify(m.payload));
                // if replaced looks like JSON, try to parse and prefer a message/text field
                try{
                  const parsed = JSON.parse(replaced);
                  display = parsed.message || parsed.text || JSON.stringify(parsed);
                }catch(_){ display = replaced; }
              }
              if(!display) display = m.message || JSON.stringify(m.payload || m);
              text.textContent = display;
            }catch(e){ text.textContent = m.message || JSON.stringify(m.payload || m); }

            const ts = document.createElement('div');
            ts.className = 'timestamp small';
            ts.textContent = formatKenyaTime(m.created_at);

            // Reply button for each message -> show inline reply box and autofill user id
            const replyBtn = document.createElement('button');
            replyBtn.className = 'btn btn-ghost';
            replyBtn.style.marginTop = '8px';
            replyBtn.style.marginLeft = '6px';
            replyBtn.textContent = 'Reply';
            replyBtn.addEventListener('click', ()=>{
              try{
                // Determine a canonical user id from message (from, payload.from, payload.user_id)
                const userId = m.from || (m.payload && (m.payload.from || m.payload.user_id)) || '';
                if(userId){
                  // update badge and persist
                  (document.getElementById('userBadgeText')||{}).textContent = userId;
                  try{ localStorage.setItem('sms_user_id', userId); }catch(e){}
                }

                // If there's already an inline reply for this message remove it (toggle)
                const existing = div.querySelector('.inline-reply');
                if(existing){ existing.remove(); return; }

                // create inline reply UI under this message bubble
                const replyContainer = document.createElement('div');
                replyContainer.className = 'inline-reply';

                const ta = document.createElement('textarea');
                ta.placeholder = 'Type reply below this message...';
                ta.style.padding = '8px';
                ta.style.border = '1px solid var(--border)';
                ta.style.borderRadius = '6px';

                const sendBtn = document.createElement('button');
                sendBtn.className = 'btn btn-alt';
                sendBtn.textContent = 'Send';

                const cancelBtn = document.createElement('button');
                cancelBtn.className = 'btn btn-ghost';
                cancelBtn.textContent = 'Cancel';

                replyContainer.appendChild(ta);
                const controls = document.createElement('div');
                controls.style.display = 'flex';
                controls.style.flexDirection = 'column';
                controls.style.gap = '6px';
                controls.appendChild(sendBtn);
                controls.appendChild(cancelBtn);
                replyContainer.appendChild(controls);

                div.appendChild(replyContainer);
                ta.focus();

                cancelBtn.addEventListener('click', ()=>{ replyContainer.remove(); });

                sendBtn.addEventListener('click', async ()=>{
                  sendBtn.disabled = true; sendBtn.textContent = 'Sending...';
                  try{
                    let payloadText = ta.value || '';
                    let payload;
                    try{ payload = payloadText.trim() ? JSON.parse(payloadText) : { message: payloadText }; }
                    catch(e){ payload = { message: payloadText }; }

                    // apply outbound template if available
                    const outboundTpl = (outboundTemplateTextarea && outboundTemplateTextarea.value || '').trim();
                    if(outboundTpl && typeof payload === 'object' && Object.keys(payload).length === 1 && payload.message){
                      const built = replacePlaceholder(outboundTpl, payload.message);
                      try{ payload = JSON.parse(built); }catch(e){ /* keep payload */ }
                    }

                    // attach user id from message (if present) or badge
                    const uid = userId || (localStorage.getItem('sms_user_id') || (document.getElementById('userBadgeText')||{}).textContent || '').trim();
                    if(uid) payload.user_id = uid;

                    const headers = getHeaders();
                    const res = await axios.post('/api/send-reply', { url: getReplyEndpoint(), payload, headers });
                    console.log('Reply response1');
                    responseBlock.textContent = JSON.stringify(res.data["success"], null, 2);
                    if(res.data && res.data.success){
                      appendMessage({ type: 'outgoing', to: extractRecipient(payload), message: payload.message || JSON.stringify(payload), created_at: new Date().toISOString() });
                    }
                    // cleanup
                    replyContainer.remove();
                    setTimeout(loadCache, 200);
                  }catch(err){ responseBlock.textContent = JSON.stringify({error: err.message}, null, 2); }
                  finally{ sendBtn.disabled = false; sendBtn.textContent = 'Send'; }
                });
              }catch(e){ console.error(e); }
            });

            if(fromLine.textContent) div.appendChild(fromLine);
            div.appendChild(text);
            // place timestamp and reply button in a small footer area
            const footer = document.createElement('div');
            footer.style.display = 'flex';
            footer.style.justifyContent = 'space-between';
            footer.style.alignItems = 'center';

            const leftSide = document.createElement('div');
            leftSide.appendChild(ts);

            const rightSide = document.createElement('div');
            rightSide.appendChild(replyBtn);

            footer.appendChild(leftSide);
            footer.appendChild(rightSide);

            div.appendChild(footer);
            messagesBox.appendChild(div);
          });

          messagesBox.scrollTop = 0;
        }

        function appendMessage(entry){
          const div = document.createElement('div');
          div.className = 'bubble';

          const text = document.createElement('div');
          text.className = 'bubble-text';
          try{
            const tpl = (inboundTemplateTextarea && inboundTemplateTextarea.value || '').trim();
            let display = entry.message || '';
            if(tpl && entry.payload){
              const msg = (entry.payload.message || entry.payload.text || (typeof entry.payload === 'string' ? entry.payload : ''));
              const replaced = replacePlaceholder(tpl, msg || display || JSON.stringify(entry.payload));
              try{ const parsed = JSON.parse(replaced); display = parsed.message || parsed.text || JSON.stringify(parsed); }catch(_){ display = replaced; }
            }
            if(!display) display = entry.message || JSON.stringify(entry.payload || entry);
            text.textContent = display;
          }catch(e){ text.textContent = entry.message || JSON.stringify(entry.payload || entry); }

          const ts = document.createElement('div');
          ts.className = 'timestamp small';
          ts.textContent = formatKenyaTime(entry.created_at);

          // reply button for appended messages -> inline reply + autofill user id
          const replyBtn = document.createElement('button');
          replyBtn.className = 'btn btn-ghost';
          replyBtn.style.marginTop = '8px';
          replyBtn.style.marginLeft = '6px';
          replyBtn.textContent = 'Reply';
          replyBtn.addEventListener('click', ()=>{
            try{
              const userId = entry.from || (entry.payload && (entry.payload.from || entry.payload.user_id)) || '';
              if(userId){ (document.getElementById('userBadgeText')||{}).textContent = userId; try{ localStorage.setItem('sms_user_id', userId); }catch(e){} }

              const existing = div.querySelector('.inline-reply');
              if(existing){ existing.remove(); return; }
              const replyContainer = document.createElement('div'); replyContainer.className = 'inline-reply';
              const ta = document.createElement('textarea'); ta.placeholder = 'Type reply below this message...'; ta.style.padding='8px'; ta.style.border='1px solid var(--border)'; ta.style.borderRadius='6px';
              const sendBtn = document.createElement('button'); sendBtn.className='btn btn-alt'; sendBtn.textContent='Send';
              const cancelBtn = document.createElement('button'); cancelBtn.className='btn btn-ghost'; cancelBtn.textContent='Cancel';
              replyContainer.appendChild(ta);
              const controls = document.createElement('div'); controls.style.display='flex'; controls.style.flexDirection='column'; controls.style.gap='6px'; controls.appendChild(sendBtn); controls.appendChild(cancelBtn);
              replyContainer.appendChild(controls);
              div.appendChild(replyContainer); ta.focus();
              cancelBtn.addEventListener('click', ()=>{ replyContainer.remove(); });
              sendBtn.addEventListener('click', async ()=>{
                sendBtn.disabled = true; sendBtn.textContent = 'Sending...';
                try{
                  let payloadText = ta.value || '';
                  let payload; try{ payload = payloadText.trim() ? JSON.parse(payloadText) : { message: payloadText }; }catch(e){ payload = { message: payloadText }; }
                  const outboundTpl = (outboundTemplateTextarea && outboundTemplateTextarea.value || '').trim();
                  if(outboundTpl && typeof payload==='object' && Object.keys(payload).length===1 && payload.message){ const built = replacePlaceholder(outboundTpl, payload.message); try{ payload = JSON.parse(built); }catch(e){} }
                  const uid = userId || (localStorage.getItem('sms_user_id') || (document.getElementById('userBadgeText')||{}).textContent || '').trim(); if(uid) payload.user_id = uid;
                  const headers = getHeaders();
                  const res = await axios.post('/api/send-reply', { url: getReplyEndpoint(), payload, headers });
                  console.log('Reply response2');
                  responseBlock.textContent = JSON.stringify(res.data, null, 2);
                  if(res.data && res.data.success){ appendMessage({ type: 'outgoing', to: extractRecipient(payload), message: payload.message || JSON.stringify(payload), created_at: new Date().toISOString() }); }
                  replyContainer.remove(); setTimeout(loadCache, 200);
                }catch(err){ responseBlock.textContent = JSON.stringify({error: err.message}, null, 2); }
                finally{ sendBtn.disabled = false; sendBtn.textContent = 'Send'; }
              });
            }catch(e){ console.error(e); }
          });

          div.appendChild(text);
          // Contact line (From/To) for appended messages
          const contactLine = document.createElement('div');
          contactLine.className = 'small';
          contactLine.style.marginBottom = '6px';
          try{
            const contact = (function getContactLabel(ent){
              if(!ent) return { label: '', value: '' };
              if(ent.type === 'outgoing'){
                const v = ent.to || (ent.payload && (ent.payload.recipient || ent.payload.to || ent.payload.number || ent.payload.phone || ent.payload.to_number)) || '';
                return { label: 'To', value: v };
              }
              const v = ent.from || (ent.payload && (ent.payload.from || ent.payload.sender || ent.payload.phone)) || '';
              return { label: 'From', value: v };
            })(entry);
            contactLine.textContent = contact.value ? (contact.label + ': ' + contact.value) : '';
            if(contact.value){ div.insertBefore(contactLine, text); }
          }catch(e){ /* ignore */ }
          const footer = document.createElement('div');
          footer.style.display = 'flex';
          footer.style.justifyContent = 'space-between';
          footer.style.alignItems = 'center';
          const left = document.createElement('div'); left.appendChild(ts);
          const right = document.createElement('div'); right.appendChild(replyBtn);
          footer.appendChild(left);
          footer.appendChild(right);
          div.appendChild(footer);
          messagesBox.insertBefore(div, messagesBox.firstChild);
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

        // Load settings stored on server (cache) and merge with localStorage values
        async function loadSettingsFromServer(){
          try{
            const res = await axios.get('/api/settings');
            const s = res && res.data ? res.data : {};
            if(s.poll_endpoint && !localStorage.getItem('sms_poll_endpoint')){ pollEndpointInput.value = s.poll_endpoint; try{ localStorage.setItem('sms_poll_endpoint', s.poll_endpoint); }catch(e){} }
            if(s.reply_endpoint && !localStorage.getItem('sms_reply_endpoint')){ replyEndpointInput.value = s.reply_endpoint; try{ localStorage.setItem('sms_reply_endpoint', s.reply_endpoint); }catch(e){} }
            if(s.header_key && !localStorage.getItem('sms_header_key')){ headerKeyInput.value = s.header_key; try{ localStorage.setItem('sms_header_key', s.header_key); }catch(e){} }
            if(s.header_value && !localStorage.getItem('sms_header_value')){ headerValueInput.value = s.header_value; try{ localStorage.setItem('sms_header_value', s.header_value); }catch(e){} }
            if(s.user_id && !localStorage.getItem('sms_user_id')){ try{ localStorage.setItem('sms_user_id', s.user_id); }catch(e){}; (document.getElementById('userBadgeText')||{}).textContent = s.user_id; }
          }catch(e){ console.warn('Could not load settings from server', e); }
        }

        // Observe userBadgeText and persist changes to server when user id updates
        try{
          const userBadgeTextEl = document.getElementById('userBadgeText');
          if(window.MutationObserver && userBadgeTextEl){
            const mo = new MutationObserver(()=>{
              try{ const v = (userBadgeTextEl.textContent||'').trim(); if(v){ try{ localStorage.setItem('sms_user_id', v); }catch(e){} scheduleSaveSettings(); } }catch(e){}
            });
            mo.observe(userBadgeTextEl, { childList:true, characterData:true, subtree:true });
          }
        }catch(e){}

        // reply input file upload was removed; replyInput is a simple text input

        // Test poll button -> trigger a single poll-fetch and update UI
        testPollBtn.addEventListener('click', async ()=>{
          testPollBtn.disabled = true;
          try{
            responseBlock.textContent = 'Polling endpoint...';
            const res = await axios.post('/api/poll-fetch', { url: getPollEndpoint() });
            responseBlock.textContent = JSON.stringify(res.data, null, 2);
            if(res && res.data && Array.isArray(res.data.saved) && res.data.saved.length){
              await loadCache();
            }
          }catch(err){ responseBlock.textContent = JSON.stringify({error: err.message}, null, 2); }
          finally{ testPollBtn.disabled = false; }
        });

  // Test send button -> server-side POST (uses replyInput value) - uses outbound template if present
        testSendBtn.addEventListener('click', async ()=>{
          testSendBtn.disabled = true;
          responseBlock.textContent = 'Testing send endpoint...';
          let payloadText = replyInput.value || '';
          let payload;
          // prefer explicit JSON typed by user
          try{ payload = payloadText.trim() ? JSON.parse(payloadText) : { message: payloadText }; }
          catch(e){ payload = { message: payloadText }; }

          // apply outbound template if user typed plain text and template exists
          const outboundTpl = (outboundTemplateTextarea && outboundTemplateTextarea.value || '').trim();
          if(outboundTpl && typeof payload === 'object' && Object.keys(payload).length === 1 && payload.message){
            const built = replacePlaceholder(outboundTpl, payload.message);
            try{ payload = JSON.parse(built); }catch(e){ /* fallback keep payload */ }
          }

          // attach user id automatically
          const userId = (localStorage.getItem('sms_user_id') || (document.getElementById('userBadgeText')||{}).textContent || '').trim();
          if(userId){ try{ payload.user_id = userId; } catch(e){} }

          try{
            const headers = getHeaders();
            const res = await axios.post('/api/send-reply', { url: getReplyEndpoint(), payload, headers });
            console.log('Reply response3');
            responseBlock.textContent = JSON.stringify(res.data, null, 2);
            if(res.data && res.data.success){
              appendMessage({ type: 'outgoing', to: extractRecipient(payload), message: payload.message || JSON.stringify(payload), created_at: new Date().toISOString() });
            }
          }catch(err){ responseBlock.textContent = JSON.stringify({error: err.message}, null, 2); }
          finally{ testSendBtn.disabled = false; }
        });

        // Reply Send button: forward replyInput to configured reply endpoint via server (avoids CORS) - uses outbound template when appropriate
        replySendBtn.addEventListener('click', async ()=>{
          replySendBtn.disabled = true;
          let payloadText = replyInput.value || '';
          let payload;
          try{ payload = payloadText.trim() ? JSON.parse(payloadText) : { message: payloadText }; }
          catch(e){ payload = { message: payloadText }; }

          // apply outbound template if available
          const outboundTpl2 = (outboundTemplateTextarea && outboundTemplateTextarea.value || '').trim();
          if(outboundTpl2 && typeof payload === 'object' && Object.keys(payload).length === 1 && payload.message){
            const built2 = replacePlaceholder(outboundTpl2, payload.message);
            try{ payload = JSON.parse(built2); }catch(e){ /* keep payload */ }
          }

          // attach user id automatically
          const userId2 = (localStorage.getItem('sms_user_id') || (document.getElementById('userBadgeText')||{}).textContent || '').trim();
          if(userId2){ try{ payload.user_id = userId2; } catch(e){} }

          try{
            const headers2 = getHeaders();
            const res = await axios.post('/api/send-reply', { url: getReplyEndpoint(), payload, headers: headers2 });
            const returnedObject = {
              success: res.data["success"],
              message: res.data["message"],
              callback: res.data["data"].callback
            }
            console.log('Reply response4',returnedObject);
            responseBlock.textContent = JSON.stringify(returnedObject, null, 2);
            if(res.data && res.data.success){
              appendMessage({ type: 'outgoing', to: extractRecipient(payload), message: payload.message || JSON.stringify(payload), created_at: new Date().toISOString() });
            }
            // refresh cache to include any new inbound messages
            setTimeout(loadCache, 200);
          }catch(err){ responseBlock.textContent = JSON.stringify({error: err.message}, null, 2); }
          finally{ replySendBtn.disabled = false; }
        });

        // Simulate inbound removed — use Test Poll or real endpoints to add inbound messages

        // seedInitialMessages: ensure we load existing cache and optionally seed example messages
        async function seedInitialMessages(){
          try{
            // avoid re-seeding on every page load in the same browser
            const seededFlag = localStorage.getItem('sms_seeded_v1');
            const res = await axios.get('/api/cache-watch').catch(()=>({data:[]}));
            const items = (res && res.data) || [];
            // always render existing cache
            renderMessages(items);

            if(!seededFlag && (!items || items.length === 0)){
              const presets = [
                { message: 'Welcome to Mock SMS Provider', from: 'System', created_at: new Date().toISOString() },
                { message: 'This is a test text message', from: '+2547000032', created_at: new Date().toISOString() }
              ];
              // post presets once and mark seeded
              await Promise.all(presets.map(p=> axios.post('/api/send-message', { payload: p }).catch(()=>{})));
              localStorage.setItem('sms_seeded_v1', '1');
              // refresh cache once
              setTimeout(loadCache, 200);
            }
          }catch(e){ console.warn(e); }
        }

  // kick off
  loadSettingsFromServer();
  loadTemplates();
  seedInitialMessages();
      })();
    </script>
  </body>
</html>
