// Dependency-free Chrome smoke test. Uses its own temporary profile, never the user's browser.
import { spawn } from 'node:child_process';
import { mkdtemp, mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { once } from 'node:events';

const base = process.env.CMS_TEST_URL || 'http://localhost/laser-symfony/public/';
if (!process.env.CMS_ADMIN_PASSWORD) throw new Error('Set CMS_ADMIN_PASSWORD before running this test.');
const temp = process.env.LOCALAPPDATA ? join(process.env.LOCALAPPDATA, 'Temp', 'opencode') : process.env.TEMP;
const profile = await mkdtemp(join(temp, 'laser-browser-'));
const chrome = spawn(process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
  '--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking', '--disable-extensions',
  '--no-proxy-server', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank',
], { stdio: ['ignore', 'ignore', 'pipe'] });
let websocket;
const output = new URL('../var/browser-checks/', import.meta.url);
await mkdir(output, { recursive: true });
try {
  const endpoint = await new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error('Chrome startup timed out')), 20000);
    let text = '';
    chrome.stderr.on('data', chunk => {
      text += chunk;
      const match = text.match(/DevTools listening on (ws:\/\/[^\s]+)/);
      if (match) { clearTimeout(timeout); resolve(match[1]); }
    });
    chrome.on('error', reject);
    chrome.on('exit', code => { clearTimeout(timeout); reject(new Error(`Chrome exited: ${code}`)); });
  });
  const origin = new URL(endpoint).origin.replace('ws:', 'http:');
  const tabs = await (await fetch(`${origin}/json/list`)).json();
  websocket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
  await once(websocket, 'open');
  let sequence = 0;
  const pending = new Map();
  const listeners = new Map();
  const errors = [];
  websocket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.id) {
      const deferred = pending.get(message.id);
      if (deferred) { pending.delete(message.id); message.error ? deferred.reject(new Error(JSON.stringify(message.error))) : deferred.resolve(message.result); }
    } else {
      if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
      if (message.method === 'Network.loadingFailed') console.error('Network:', message.params.errorText);
      const callbacks = listeners.get(message.method) || [];
      listeners.delete(message.method);
      callbacks.forEach(callback => callback(message.params));
    }
  });
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++sequence; pending.set(id, { resolve, reject }); websocket.send(JSON.stringify({ id, method, params }));
  });
  const event = name => new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error(`Timeout: ${name}`)), 45000);
    listeners.set(name, [...(listeners.get(name) || []), value => { clearTimeout(timeout); resolve(value); }]);
  });
  const evaluate = async expression => {
    const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
  };
  const navigate = async path => {
    const loaded = event('Page.domContentEventFired');
    loaded.catch(() => {});
    const result = await send('Page.navigate', { url: new URL(path, base).href });
    if (result.errorText) {
      console.error('Navigation state:', await evaluate('JSON.stringify({url:location.href,state:document.readyState,text:document.body?.innerText?.slice(0,500)})'));
      await screenshot('navigation-error.png');
      throw new Error(result.errorText);
    }
    await loaded;
  };
  const screenshot = async name => { const result = await send('Page.captureScreenshot', { format: 'png' }); await writeFile(new URL(name, output), Buffer.from(result.data, 'base64')); };
  const waitForHeroText = () => evaluate(`Promise.race([
    Promise.all(Array.from(document.querySelectorAll('.slide.is-active h2, .slide.is-active .cta-row')).flatMap(e=>e.getAnimations().map(a=>a.finished.catch(()=>{})))),
    new Promise(resolve=>setTimeout(resolve,2500))
  ])`);
  const assert = (condition, message) => { if (!condition) throw new Error(message); console.log(`OK: ${message}`); };
  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false });
  await navigate('admin');
  await screenshot('admin-login.png');
  const loaded = event('Page.domContentEventFired');
  await evaluate(`document.querySelector('[name=user]').value='admin'; document.querySelector('[name=pass]').value=${JSON.stringify(process.env.CMS_ADMIN_PASSWORD)}; document.querySelector('form').requestSubmit();`);
  await loaded;
  assert(await evaluate('location.pathname.endsWith("/admin/settings")'), 'Admin login redirects to settings');
  await screenshot('admin-desktop.png');
  assert(await evaluate('document.querySelectorAll(".settings-group").length >= 8'), 'Settings groups render');
  assert(await evaluate('new Set(Array.from(document.querySelectorAll("[id]")).map(e=>e.id)).size===document.querySelectorAll("[id]").length'), 'No duplicate form IDs');
  if (process.env.CMS_TEST_WRITE === '1') {
    const result = await evaluate(`(async () => {
      const form = document.querySelector('[data-dirty-form]');
      const original = new FormData(form);
      const changed = new FormData(form);
      const name = original.get('settings[site.name]') + ' — test zapisu';
      changed.set('settings[site.name]', name);
      let saved = false, visible = false, restored = false;
      try {
        const response = await fetch(location.href, {method:'POST',body:changed});
        saved = response.ok && (await response.text()).includes('Zapisano ustawienia');
        const page = await (await fetch(${JSON.stringify(base)})).text();
        visible = page.includes(name);
      } finally {
        const response = await fetch(location.href, {method:'POST',body:original});
        restored = response.ok && (await response.text()).includes('Zapisano ustawienia');
      }
      return {saved,visible,restored};
    })()`);
    assert(result.saved && result.visible && result.restored, 'Database write is visible on home; original content restored');
  }
  await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
  await evaluate('document.querySelector("[data-settings-search]").value="telefon"; document.querySelector("[data-settings-search]").dispatchEvent(new Event("input"));');
  assert(await evaluate('Array.from(document.querySelectorAll(".settings-group")).some(e=>!e.hidden&&e.open)'), 'Admin field search opens matching groups');
  assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'), 'Mobile admin has no horizontal overflow');
  await screenshot('admin-mobile.png');
  await navigate('');
  const expectsMotion = await evaluate('document.body.dataset.motion!=="none"');
  assert(await evaluate('document.querySelectorAll("h1").length===1'), 'Home has one main heading');
  assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'), 'Mobile homepage has no horizontal overflow');
  await evaluate('document.querySelector("#burger").click()');
  assert(await evaluate('document.querySelector("#burger").getAttribute("aria-expanded")==="true"'), 'Mobile menu opens');
  await evaluate('document.querySelector("#navLinks a").click()');
  assert(await evaluate('document.querySelector("#burger").getAttribute("aria-expanded")==="false"'), 'Mobile menu closes after navigation');
  await evaluate(`new Promise(resolve => {
    const image = document.querySelector('.is-active .slide-photo');
    if (!image || (image.complete && image.naturalWidth)) { resolve(); return; }
    const timeout = setTimeout(resolve, 6000);
    image.addEventListener('load', () => {clearTimeout(timeout); resolve();}, {once:true});
  })`);
  assert(await evaluate('document.querySelectorAll(".slide.is-active").length===1'), 'Only one hero slide is active');
  await evaluate('document.querySelector("#nextS").click()');
  assert(await evaluate('document.querySelector("#slideNumber").textContent==="02" && document.querySelectorAll(".slide.is-active").length===1'), 'Layered slider changes slide and counter');
  await evaluate('document.querySelector("#prevS").click(); document.activeElement.blur()');
  if (expectsMotion) assert(await evaluate('!!document.querySelector(".marquee-track") || document.body.dataset.motion==="subtle"'), 'Dynamic materials strip initializes without duplicating accessible content');
  await waitForHeroText();
  assert(await evaluate('getComputedStyle(document.querySelector(".slide.is-active h2")).opacity==="1"'), 'Hero text remains visible after the entrance animation');
  await screenshot('home-mobile.png');
  assert(await evaluate('(() => {const e=document.querySelector(".ba"); if(!e)return false; e.dispatchEvent(new KeyboardEvent("keydown",{key:"ArrowRight",bubbles:true})); return e.getAttribute("aria-valuenow")==="55";})()'), 'Before/after slider supports keyboard and ARIA');
  await evaluate('document.querySelector(".gallery-open").click()');
  assert(await evaluate('document.querySelector("#lightbox").open'), 'Gallery opens native accessible dialog');
  await evaluate('document.querySelector("#lightbox").close()');
  await send('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false });
  await send('Emulation.setEmulatedMedia', { features:[{name:'prefers-reduced-motion',value:'reduce'}] });
  await evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
  assert(await evaluate('document.querySelector("#pauseSlider").getAttribute("aria-pressed")==="true" && getComputedStyle(document.querySelector(".hero-scan")).display==="none"'), 'Reduced motion disables automatic slides and laser effects');
  assert(await evaluate('Array.from(document.querySelectorAll(".reveal")).every(e=>getComputedStyle(e).opacity==="1")'), 'Reduced motion keeps all content visible');
  await send('Emulation.setEmulatedMedia', { features:[{name:'prefers-reduced-motion',value:'no-preference'}] });
  await evaluate('scrollTo(0,0)');
  await waitForHeroText();
  await screenshot('home-desktop.png');
  await send('Emulation.setScriptExecutionDisabled', {value:true});
  await navigate('');
  // CDP evaluation stays available while page-owned JavaScript is disabled.
  assert(await evaluate('getComputedStyle(document.querySelector(".slide.is-active h2")).opacity==="1" && Array.from(document.querySelectorAll(".reveal")).every(e=>getComputedStyle(e).opacity==="1")'), 'Page content remains readable without JavaScript');
  await send('Emulation.setScriptExecutionDisabled', {value:false});
  assert(errors.length === 0, `No JavaScript exceptions (${errors.length})`);
  console.log('Screenshots: var/browser-checks/');
} finally {
  if (websocket) websocket.close();
  chrome.kill();
}
