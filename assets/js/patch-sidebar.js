const fs = require("fs");
const path = require("path");
const dir = path.join(__dirname, "..");
const files = fs.readdirSync(dir).filter((f) => f.endsWith(".html"));

const brandOldCr = `        <div class="app-sidebar__brand">\r\n          <a href="index.html" aria-label="PostAI home">`;
const brandNewCr = `        <div class="app-sidebar__brand">\r\n          <button type="button" class="sidebar-collapse-btn" data-app-sidebar-collapse aria-expanded="true" aria-label="Collapse navigation" title="Collapse sidebar">\r\n            <i class="fa-solid fa-angles-left" aria-hidden="true"></i>\r\n          </button>\r\n          <a href="index.html" aria-label="PostAI home">`;
const brandOldLf = `        <div class="app-sidebar__brand">\n          <a href="index.html" aria-label="PostAI home">`;
const brandNewLf = `        <div class="app-sidebar__brand">\n          <button type="button" class="sidebar-collapse-btn" data-app-sidebar-collapse aria-expanded="true" aria-label="Collapse navigation" title="Collapse sidebar">\n            <i class="fa-solid fa-angles-left" aria-hidden="true"></i>\n          </button>\n          <a href="index.html" aria-label="PostAI home">`;

const reps = [
  ['<a class="nav-link nav-link--sub" href="products.html">Products</a>', '<a class="nav-link nav-link--sub" href="products.html"><i class="fa-solid fa-cube fa-fw" aria-hidden="true"></i>Products</a>'],
  ['<a class="nav-link nav-link--sub nav-link--active" href="products.html">Products</a>', '<a class="nav-link nav-link--sub nav-link--active" href="products.html"><i class="fa-solid fa-cube fa-fw" aria-hidden="true"></i>Products</a>'],
  ['<a class="nav-link nav-link--sub" href="discounts.html">Discounts</a>', '<a class="nav-link nav-link--sub" href="discounts.html"><i class="fa-solid fa-tag fa-fw" aria-hidden="true"></i>Discounts</a>'],
  ['<a class="nav-link nav-link--sub nav-link--active" href="discounts.html">Discounts</a>', '<a class="nav-link nav-link--sub nav-link--active" href="discounts.html"><i class="fa-solid fa-tag fa-fw" aria-hidden="true"></i>Discounts</a>'],
  ['<a class="nav-link nav-link--sub" href="licenses.html">Licenses</a>', '<a class="nav-link nav-link--sub" href="licenses.html"><i class="fa-solid fa-key fa-fw" aria-hidden="true"></i>Licenses</a>'],
  ['<a class="nav-link nav-link--sub nav-link--active" href="licenses.html">Licenses</a>', '<a class="nav-link nav-link--sub nav-link--active" href="licenses.html"><i class="fa-solid fa-key fa-fw" aria-hidden="true"></i>Licenses</a>'],
  ['<a class="nav-link nav-link--sub" href="payments.html">Payments</a>', '<a class="nav-link nav-link--sub" href="payments.html"><i class="fa-solid fa-credit-card fa-fw" aria-hidden="true"></i>Payments</a>'],
  ['<a class="nav-link nav-link--sub nav-link--active" href="payments.html">Payments</a>', '<a class="nav-link nav-link--sub nav-link--active" href="payments.html"><i class="fa-solid fa-credit-card fa-fw" aria-hidden="true"></i>Payments</a>'],
  ['<a class="nav-link nav-link--sub" href="subscriptions.html">Subscriptions</a>', '<a class="nav-link nav-link--sub" href="subscriptions.html"><i class="fa-solid fa-rotate fa-fw" aria-hidden="true"></i>Subscriptions</a>'],
  ['<a class="nav-link nav-link--sub nav-link--active" href="subscriptions.html">Subscriptions</a>', '<a class="nav-link nav-link--sub nav-link--active" href="subscriptions.html"><i class="fa-solid fa-rotate fa-fw" aria-hidden="true"></i>Subscriptions</a>'],
  ['<a class="nav-link nav-link--sub" href="balances.html">Balance</a>', '<a class="nav-link nav-link--sub" href="balances.html"><i class="fa-solid fa-wallet fa-fw" aria-hidden="true"></i>Balance</a>'],
  ['<a class="nav-link nav-link--sub nav-link--active" href="balances.html">Balance</a>', '<a class="nav-link nav-link--sub nav-link--active" href="balances.html"><i class="fa-solid fa-wallet fa-fw" aria-hidden="true"></i>Balance</a>'],
  ['<a class="nav-link nav-link--sub" href="api-webhooks.html">API &amp; Webhooks</a>', '<a class="nav-link nav-link--sub" href="api-webhooks.html"><i class="fa-solid fa-plug fa-fw" aria-hidden="true"></i>API &amp; Webhooks</a>'],
  ['<a class="nav-link nav-link--sub nav-link--active" href="api-webhooks.html">API &amp; Webhooks</a>', '<a class="nav-link nav-link--sub nav-link--active" href="api-webhooks.html"><i class="fa-solid fa-plug fa-fw" aria-hidden="true"></i>API &amp; Webhooks</a>'],
  ['<a class="nav-link nav-link--sub nav-link--external" href="documentation.html">Documentation <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>', '<a class="nav-link nav-link--sub nav-link--external" href="documentation.html"><i class="fa-solid fa-book fa-fw" aria-hidden="true"></i>Documentation <i class="fa-solid fa-arrow-up-right-from-square fa-xs" aria-hidden="true"></i></a>'],
  ['<a class="nav-link nav-link--sub nav-link--external nav-link--active" href="documentation.html">Documentation <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>', '<a class="nav-link nav-link--sub nav-link--external nav-link--active" href="documentation.html"><i class="fa-solid fa-book fa-fw" aria-hidden="true"></i>Documentation <i class="fa-solid fa-arrow-up-right-from-square fa-xs" aria-hidden="true"></i></a>'],
];

for (const f of files) {
  if (f === "index.html" || f === "dashboard.html") continue;
  const p = path.join(dir, f);
  let c = fs.readFileSync(p, "utf8");
  const o = c;
  if (c.includes(brandOldCr)) c = c.split(brandOldCr).join(brandNewCr);
  else if (c.includes(brandOldLf)) c = c.split(brandOldLf).join(brandNewLf);
  for (const [a, b] of reps) c = c.split(a).join(b);
  if (c !== o) {
    fs.writeFileSync(p, c);
    console.log("updated", f);
  }
}

const partial = path.join(dir, "partials", "app-sidebar.html");
if (fs.existsSync(partial)) {
  let c = fs.readFileSync(partial, "utf8");
  const o = c;
  if (c.includes(`<div class="app-sidebar__brand">\n    <a href="index.html"`)) {
    c = c.split(`<div class="app-sidebar__brand">\n    <a href="index.html"`).join(
      `<div class="app-sidebar__brand">\n    <button type="button" class="sidebar-collapse-btn" data-app-sidebar-collapse aria-expanded="true" aria-label="Collapse navigation" title="Collapse sidebar">\n      <i class="fa-solid fa-angles-left" aria-hidden="true"></i>\n    </button>\n    <a href="index.html"`
    );
  }
  for (const [a, b] of reps) c = c.split(a).join(b);
  if (c !== o) {
    fs.writeFileSync(partial, c);
    console.log("updated partials/app-sidebar.html");
  }
}
