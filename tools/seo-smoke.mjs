// Read-only public-page checks in a fresh headless Chrome profile. No admin credentials needed.
import { spawn } from 'node:child_process';
import { mkdtemp, mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { once } from 'node:events';

const base = process.env.CMS_TEST_URL || 'http://localhost/laser-symfony/public/';
const temp = join(process.env.LOCALAPPDATA, 'Temp', 'opencode');
const profile = await mkdtemp(join(temp, 'laser-seo-'));
const chrome = spawn(process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
  '--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
  '--disable-extensions', '--no-proxy-server', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank',
], { stdio: ['ignore', 'ignore', 'pipe'] });
const output = new URL('../var/seo-checks/', import.meta.url);
await mkdir(output, { recursive: true });
let websocket;
try {
  const endpoint = await new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error('Chrome startup timed out')), 20000);
    let text = '';
    chrome.stderr.on('data', chunk => {
      text += chunk;
      const match = text.match(/DevTools listening on (ws:\/\/[^\s]+)/);
      if (match) { clearTimeout(timeout); resolve(match[1]); }
    });
    chrome.once('error', error => { clearTimeout(timeout); reject(error); });
  });
  const tabs = await (await fetch(`${new URL(endpoint).origin.replace('ws:', 'http:')}/json/list`)).json();
  websocket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
  await once(websocket, 'open');
  let sequence = 0;
  const pending = new Map();
  const events = new Map();
  const errors = [];
  websocket.addEventListener('message', ({ data }) => {
    const message = JSON.parse(data);
    if (message.id) {
      const request = pending.get(message.id);
      if (request) {
        pending.delete(message.id); clearTimeout(request.timeout);
        message.error ? request.reject(new Error(JSON.stringify(message.error))) : request.resolve(message.result);
      }
    } else {
      if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
      const listeners = events.get(message.method) || [];
      events.delete(message.method);
      listeners.forEach(listener => listener(message.params));
    }
  });
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++sequence;
    const timeout = setTimeout(() => reject(new Error(`Timeout: ${method}`)), 30000);
    pending.set(id, { resolve, reject, timeout });
    websocket.send(JSON.stringify({ id, method, params }));
  });
  const event = name => new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error(`Timeout: ${name}`)), 30000);
    events.set(name, [...(events.get(name) || []), value => { clearTimeout(timeout); resolve(value); }]);
  });
  const evaluate = async expression => {
    const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
  };
  const assert = (condition, message) => { if (!condition) throw new Error(message); console.log(`OK: ${message}`); };
  const navigate = async () => {
    const loaded = event('Page.domContentEventFired');
    await send('Page.navigate', { url: base });
    await loaded;
    await evaluate(`(async () => {
      document.querySelectorAll('img[src]').forEach(img => img.loading='eager');
      await Promise.all(Array.from(document.querySelectorAll('img[src]')).map(img=>img.decode().catch(()=>{})));
    })()`);
  };
  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  await send('Network.setCacheDisabled', { cacheDisabled: true });
  for (const [name, width, height] of [['desktop', 1365, 900], ['mobile', 390, 844]]) {
    await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: name === 'mobile' });
    await navigate();
    const result = await evaluate(`({
      h1: document.querySelectorAll('h1').length,
      visibleH1: document.querySelector('h1').getBoundingClientRect().height > 10,
      canonical: document.querySelector('link[rel=canonical]')?.href,
      broken: Array.from(document.querySelectorAll('img[src]')).filter(img=>!img.naturalWidth).map(img=>img.src),
      hero: document.querySelector('.slide.is-active .slide-photo').currentSrc,
      schema: JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent)['@graph'].length,
      overflow: document.documentElement.scrollWidth > innerWidth + 1,
      distorted: Array.from(document.querySelectorAll('img.rounded')).filter(img=>{
        const expected=Number(img.getAttribute('width'))/Number(img.getAttribute('height'));
        return Math.abs(img.clientWidth/img.clientHeight-expected)/expected > 0.015;
      }).map(img=>img.src)
    })`);
    assert(result.h1 === 1 && result.visibleH1, `${name}: one visible H1`);
    assert(result.broken.length === 0, `${name}: all images decoded (${JSON.stringify(result.broken)})`);
    assert(result.hero.endsWith('.webp'), `${name}: browser selected responsive WebP`);
    assert(result.schema >= 3 && !!result.canonical, `${name}: canonical and JSON-LD present`);
    assert(!result.overflow, `${name}: no horizontal overflow`);
    assert(result.distorted.length===0, `${name}: natural photo proportions (${result.distorted.join(', ')})`);
    const undersized = await evaluate(`(async()=>{
      const results=await Promise.all(Array.from(document.querySelectorAll('.gallery img')).map(async img=>{
        const bitmap=await createImageBitmap(await (await fetch(img.currentSrc)).blob());
        const small=bitmap.width+1<img.clientWidth*devicePixelRatio;
        bitmap.close();
        return small?img.currentSrc:null;
      }));
      return results.filter(Boolean);
    })()`);
    assert(undersized.length===0, `${name}: gallery uses sharp, sufficiently large variants (${undersized.join(', ')})`);
    await evaluate(`Promise.race([
      Promise.all(Array.from(document.querySelectorAll('.slide.is-active h2, .slide.is-active .cta-row')).flatMap(element=>element.getAnimations().map(animation=>animation.finished.catch(()=>{})))),
      new Promise(resolve=>setTimeout(resolve,2500))
    ])`);
    const screenshot = await send('Page.captureScreenshot', { format: 'png' });
    await writeFile(new URL(`${name}.png`, output), Buffer.from(screenshot.data, 'base64'));
  }
  await send('Network.setBlockedURLs', { urls: ['*.webp'] });
  await navigate();
  assert(await evaluate(`document.querySelector('.slide.is-active .slide-photo').naturalWidth > 0 && document.querySelector('.slide.is-active .slide-photo').currentSrc.endsWith('.jpg')`), 'missing WebP falls back to original JPEG');
  assert(errors.length === 0, `no JavaScript exceptions (${errors.join(', ')})`);
} finally {
  websocket?.close();
  chrome.kill();
}
