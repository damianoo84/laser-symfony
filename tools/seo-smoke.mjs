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
      await Promise.race([document.fonts.ready,new Promise(resolve=>setTimeout(resolve,4000))]);
      window.scrollTo({top:0,behavior:'instant'});
    })()`);
  };
  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  await send('Emulation.setFocusEmulationEnabled', { enabled: true });
  await send('Network.setCacheDisabled', { cacheDisabled: true });
  const settle = () => evaluate('new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))');
  const screenshot = async name => {
    const image = await send('Page.captureScreenshot', { format: 'png' });
    await writeFile(new URL(`${name}.png`, output), Buffer.from(image.data, 'base64'));
  };
  for (const [name, width, height] of [['desktop',1365,900],['mobile-320',320,568],['mobile-360',360,800],['mobile',390,844],['mobile-412',412,915],['landscape',560,320],['tablet',768,1024]]) {
    await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: name !== 'desktop' });
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
    await screenshot(name);
    const imported = await evaluate('document.querySelectorAll(".content-articles details").length');
    if (imported && (name === 'desktop' || name === 'mobile')) {
      assert(imported >= 15, `${name}: recovered descriptions are present`);
      const gallery = await evaluate(`({total:document.querySelectorAll('.gallery .g-item').length,visible:document.querySelectorAll('.gallery .g-item:not([hidden])').length})`);
      assert(gallery.total>=69&&gallery.visible===6, `${name}: all recovered photos exist but only six show initially`);
      await evaluate('document.querySelector("[data-gallery-more]").click()');
      assert(await evaluate('document.querySelectorAll(".gallery .g-item:not([hidden])").length===12'), `${name}: gallery reveals the next six images`);
      await evaluate(`(()=>{const button=document.querySelector('[data-gallery-more]');while(!button.hidden)button.click();})()`);
      assert(await evaluate('document.querySelectorAll(".gallery .g-item:not([hidden])").length===document.querySelectorAll(".gallery .g-item").length'), `${name}: every recovered photo can be revealed`);
      await evaluate(`document.querySelector('a[href="#wp-drewno"]').click()`);
      await settle();
      assert(await evaluate('document.querySelector("#wp-drewno").open'), `${name}: offer link opens the full description`);
      await evaluate('document.querySelector("#wp-drewno").scrollIntoView({block:"start",behavior:"instant"})');
      await settle();
      await screenshot(name+'-recovered-description');
      await evaluate(`document.querySelector('#wp-drewno').open=false;document.querySelector('a[href="#wp-drewno"]').click()`);
      assert(await evaluate('document.querySelector("#wp-drewno").open'), `${name}: repeated description link still opens a manually closed panel`);
      const movies = await evaluate(`Promise.all(Array.from(document.querySelectorAll('.archive-media video')).map(video=>new Promise((resolve,reject)=>{
        const timer=setTimeout(()=>reject(new Error('Video metadata timeout: '+video.querySelector('source').src)),15000);
        video.addEventListener('loadedmetadata',()=>{clearTimeout(timer);resolve({width:video.videoWidth,height:video.videoHeight,duration:video.duration,controls:video.controls,autoplay:video.autoplay});},{once:true});
        video.addEventListener('error',()=>{clearTimeout(timer);reject(new Error('Video decoding failed: '+video.querySelector('source').src));},{once:true});
        video.preload='metadata';video.load();
      })))`);
      assert(movies.length===4&&movies.every(video=>video.width>0&&video.height>0&&video.duration>0&&video.controls&&!video.autoplay), `${name}: all four local videos decode and have manual controls`);
      await evaluate('document.querySelectorAll(".content-articles details").forEach(details=>details.open=false);document.activeElement.blur();window.scrollTo({top:0,behavior:"instant"})');
      await settle();
    }
    await settle();
    assert(await evaluate('document.querySelector(".mobile-cta").hidden'), `${name}: contact bar does not overlap the hero`);
    if (width <= 560 && height >= 420) {
      await evaluate(`document.querySelector('.slide.is-active .cta-row').scrollIntoView({block:'center',behavior:'instant'})`);
      await settle();
      assert(await evaluate(`(()=>{const button=document.querySelector('.slide.is-active .cta-row a'),r=button.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return document.querySelector('.mobile-cta').hidden&&(hit===button||button.contains(hit));})()`), `${name}: hero action is visible and not covered by floating buttons`);
      await screenshot(name+'-hero-actions');
      await evaluate(`window.scrollTo({top:scrollY+document.querySelector('#heroSlider').getBoundingClientRect().bottom-document.querySelector('#siteHeader').getBoundingClientRect().bottom+24,behavior:'instant'})`);
      await settle();
      const bar=await evaluate(`(()=>{const bar=document.querySelector('.mobile-cta');return{visible:!bar.hidden,height:bar.getBoundingClientRect().height,buttons:Array.from(bar.querySelectorAll('a')).map(a=>({width:a.getBoundingClientRect().width,height:a.getBoundingClientRect().height,fit:a.scrollWidth<=a.clientWidth+1}))};})()`);
      assert(bar.visible&&bar.height<=80&&bar.buttons.every(button=>button.height>=48&&button.fit), `${name}: compact contact bar appears after the hero with readable 48px tap targets`);
      await screenshot(name+'-contact-bar');
      await evaluate('document.querySelector("#burger").click()');
      assert(await evaluate('document.querySelector(".mobile-cta").hidden'), `${name}: menu hides the contact bar`);
      await evaluate('document.querySelector("#burger").click()');
      assert(await evaluate('!document.querySelector(".mobile-cta").hidden'), `${name}: closing the menu restores the contact bar`);
      await evaluate('document.querySelector(".gallery-open").click()');
      assert(await evaluate('document.querySelector(".mobile-cta").hidden'), `${name}: gallery modal hides the contact bar`);
      await evaluate(`new Promise(resolve=>{const dialog=document.querySelector('#lightbox');dialog.addEventListener('close',resolve,{once:true});dialog.querySelector('button').click();})`);
      await settle();
      assert(await evaluate('!document.querySelector(".mobile-cta").hidden'), `${name}: closing the gallery restores the contact bar`);
      await evaluate('document.querySelector("#f-name").focus()');
      await settle();
      const editingState=await evaluate(`({hidden:document.querySelector('.mobile-cta').hidden,active:document.activeElement?.outerHTML,hero:document.querySelector('#heroSlider').getBoundingClientRect().bottom,viewport:window.visualViewport?.height})`);
      assert(editingState.hidden, `${name}: editing the form hides the contact bar ${editingState.hidden?'':JSON.stringify(editingState)}`);
      await evaluate('document.activeElement.blur();document.querySelector("#kontakt").scrollIntoView({block:"start",behavior:"instant"})');
      await settle();
      assert(await evaluate('document.querySelector(".mobile-cta").hidden'), `${name}: contact section has no floating overlay`);
    } else {
      await evaluate('document.querySelector("#intro").scrollIntoView({block:"start",behavior:"instant"})');
      await settle();
      assert(await evaluate('document.querySelector(".mobile-cta").hidden'), `${name}: no contact bar on wide or very short screens`);
    }
  }
  await send('Network.setBlockedURLs', { urls: ['*.webp'] });
  await navigate();
  assert(await evaluate(`document.querySelector('.slide.is-active .slide-photo').naturalWidth > 0 && document.querySelector('.slide.is-active .slide-photo').currentSrc.endsWith('.jpg')`), 'missing WebP falls back to original JPEG');
  assert(errors.length === 0, `no JavaScript exceptions (${errors.join(', ')})`);
} finally {
  websocket?.close();
  chrome.kill();
}
