// Critical-journey browser check: land, open a product, add to basket, check
// out, and place a COD order.
//
// The Pest suite proves each step in isolation; this proves they compose in a
// real browser. It is what found the checkout form rendering unstyled (a class
// name the stylesheet did not know), its fields having no labels or
// autocomplete, and COD orders losing every line item ten minutes after being
// placed.
//
// Needs a server and a headless Chrome with the debugger open:
//
//   php artisan serve --port=8123
//   "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
//     --headless=new --remote-debugging-port=9222 --user-data-dir=/tmp/e2e about:blank
//   OUT=/tmp node tests/e2e/critical-journey.mjs
//
// The shop needs a country, postage and COD rows, and one product in stock;
// cod_enabled must be on. Driven over CDP with Node's built-in WebSocket, so
// there is nothing to install.


import { writeFileSync } from 'node:fs';

const BASE = 'http://127.0.0.1:8123';
const OUT = process.env.OUT;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const list = await (await fetch('http://127.0.0.1:9222/json/list')).json();
const target = list.find((t) => t.type === 'page');
const ws = new WebSocket(target.webSocketDebuggerUrl);
let id = 0;
const pending = new Map();
const consoleErrors = [];
const failedRequests = [];

ws.onmessage = (m) => {
  const d = JSON.parse(m.data);
  if (d.id && pending.has(d.id)) {
    const p = pending.get(d.id);
    pending.delete(d.id);
    d.error ? p.j(new Error(JSON.stringify(d.error))) : p.r(d.result);
  } else if (d.method === 'Log.entryAdded' && d.params.entry.level === 'error') {
    consoleErrors.push(d.params.entry.text);
  } else if (d.method === 'Network.responseReceived' && d.params.response.status >= 400) {
    failedRequests.push(`${d.params.response.status} ${d.params.response.url}`);
  }
};
await new Promise((r) => { ws.onopen = r; });
const send = (method, params = {}) => new Promise((r, j) => {
  const i = ++id; pending.set(i, { r, j });
  ws.send(JSON.stringify({ id: i, method, params }));
});

await send('Page.enable'); await send('Runtime.enable');
await send('Log.enable'); await send('Network.enable');
await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 950, deviceScaleFactor: 2, mobile: false });

const go = async (u) => { await send('Page.navigate', { url: BASE + u }); await sleep(2200); };
const evaluate = async (e) => (await send('Runtime.evaluate', { expression: e, awaitPromise: true, returnByValue: true })).result?.value;
const shot = async (n) => {
  const { data } = await send('Page.captureScreenshot', { format: 'png' });
  writeFileSync(`${OUT}/e2e-${n}.png`, Buffer.from(data, 'base64'));
};

const steps = [];
const check = (name, ok, detail = '') => {
  steps.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  — ' + detail : ''}`);
};

const setField = (sel, val) => `(() => {
  const el = document.querySelector(${JSON.stringify(sel)});
  if (!el) return false;
  const proto = el.tagName === 'SELECT' ? window.HTMLSelectElement.prototype : (el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype);
  Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, ${JSON.stringify(val)});
  el.dispatchEvent(new Event('input', { bubbles: true }));
  el.dispatchEvent(new Event('change', { bubbles: true }));
  return true;
})()`;

// --- 1. Land on the shop --------------------------------------------------
await send('Network.clearBrowserCookies');
await go('/');
check('home renders', await evaluate(`!!document.querySelector('header')`));
await shot('1-home');

// --- 2. Open a product ----------------------------------------------------
// /shop is a search page with a resting state by design, so browse starts on
// the homepage rows.
const productHref = await evaluate(`(() => {
  const a = [...document.querySelectorAll('a')].find(a => /\\/product\\//.test(a.getAttribute('href') || ''));
  return a ? a.getAttribute('href') : null;
})()`);
check('homepage lists a product', !!productHref, productHref || 'no product link found');
if (!productHref) { console.log(JSON.stringify({ steps, consoleErrors, failedRequests })); ws.close(); process.exit(1); }

await go(productHref);
const title = await evaluate(`document.title`);
check('product page opens', !!title, title);
await shot('2-product');

// --- 3. Add to basket -----------------------------------------------------
const added = await evaluate(`(() => {
  const b = [...document.querySelectorAll('button, a')].find(e => /add to cart|add to basket/i.test(e.textContent));
  if (!b) return false;
  b.click();
  return true;
})()`);
await sleep(2200);
check('add to cart clicked', added);

await go('/cart');
const cartCount = await evaluate(`document.querySelectorAll('tbody tr').length`);
check('basket holds the item', cartCount > 0, `${cartCount} row(s)`);
await shot('3-cart');

// --- 4. Checkout ----------------------------------------------------------
await go('/checkout');
check('checkout opens', await evaluate(`!!document.querySelector('form')`));

const fields = {
  '#first_name': 'Nurul', '#last_name': 'Aisyah', '#email': 'e2e@example.test',
  '#phone': '0123456789', '#address_1': 'No 22, Jalan Setia', '#city': 'Shah Alam',
  '#postcode': '40170',
};
for (const [sel, val] of Object.entries(fields)) {
  await evaluate(setField(sel, val));
}
await sleep(400);
await shot('4-checkout');

// Every field must be programmatically labelled, or a screen reader announces
// an anonymous text box on the most important form in the shop.
const unlabelled = await evaluate(`(() => {
  return [...document.querySelectorAll('form.checkout__form input, form.checkout__form select')]
    .filter(el => el.type !== 'checkbox' && el.type !== 'radio' && !el.disabled)
    .filter(el => !(el.id && document.querySelector('label[for="' + el.id + '"]')) && !el.getAttribute('aria-label'))
    .map(el => el.name || el.type);
})()`);
check('every checkout field is labelled', unlabelled.length === 0, unlabelled.join(', '));

const noAutofill = await evaluate(`(() => {
  return [...document.querySelectorAll('form.checkout__form input, form.checkout__form select')]
    .filter(el => ['text','tel','email','select-one'].includes(el.type) && !el.disabled && el.id !== 'remark')
    .filter(el => !el.getAttribute('autocomplete') && el.id !== 'courier_service')
    .map(el => el.id);
})()`);
check('address fields carry autocomplete tokens', noAutofill.length === 0, noAutofill.join(', '));

const styled = await evaluate(`(() => {
  const el = document.querySelector('form.checkout__form input#first_name');
  const s = getComputedStyle(el);
  return JSON.stringify({ height: s.height, width: s.width });
})()`);
check('checkout inputs pick up the template styling', JSON.parse(styled).height === '50px', styled);

// --- 5. Address, then place a COD order ----------------------------------
for (const [sel, val] of Object.entries({ '#state': 'Selangor', '#courier_service': 'J&T Express' })) {
  await evaluate(setField(sel, val));
}
await sleep(300);
await evaluate(`document.querySelector('form.checkout__form').requestSubmit(); true`);
await sleep(2600);
await shot('5-address-saved');

const postage = await evaluate(`(() => {
  const t = document.body.innerText;
  const m = t.match(/Postage[\\s\\S]{0,40}/);
  return m ? m[0].replace(/\\s+/g, ' ').trim() : null;
})()`);
check('postage is priced once the address is known', !!postage && !/Enter your address/.test(postage), postage || '');

const paid = await evaluate(`(() => {
  const r = [...document.querySelectorAll('input[type=radio]')].find(i => /cod/i.test(i.value));
  if (!r) return 'no COD option';
  r.click();
  return 'selected';
})()`);
check('COD is offered', paid === 'selected', String(paid));

await sleep(500);
const placed = await evaluate(`(() => {
  const b = [...document.querySelectorAll('button')].find(e => /place order|pay|confirm/i.test(e.textContent));
  if (!b) return false;
  b.click();
  return true;
})()`);
await sleep(3200);
await shot('6-order-placed');

const landed = await evaluate('location.pathname');
check('order placed and confirmed', /thanks|order/.test(landed), landed);

console.log(JSON.stringify({ consoleErrors: consoleErrors.slice(0, 5), failedRequests: failedRequests.slice(0, 5) }));
ws.close();

const failed = steps.filter((s) => !s.ok);
console.log(`\n${steps.length - failed.length}/${steps.length} steps passed`);
process.exitCode = failed.length === 0 ? 0 : 1;
