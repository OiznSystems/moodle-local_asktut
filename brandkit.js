// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Ask Tut brand kit: the company avatar's name and picture follow through the plugin's pages.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* Ask Tut brand kit (Mo, 19 Nov 2026): when the company avatar changes, every page follows it.
   The company avatar's name replaces the word "Tut" in visible text (titles, headings, labels, buttons, tooltips), and its
   picture replaces the Tut icon. A small "Powered by Ask Tut" line stays only in the admin footer (Tut Admin + Consumption).
   Same file in every Ask Tut edition — copy it unchanged; edition code only calls TutBrand.company(policy) and TutBrand.apply(...).
   Not touched: what people typed (inputs, text areas), table cells (questions, names, records), chat messages, and anything
   inside [data-nobrand] (e.g. the avatar cards, which must keep their own names).
   Teams: TutBrand.teamsPackage() builds the Teams app zip with the avatar's name and picture; TutBrand.teamsPush() publishes
   it to the organisation's Teams catalog as the signed-in admin (Microsoft Graph, AppCatalog.ReadWrite.All). */
var TutBrand = (function () {
  "use strict";
  var NAMES = { tut: "Tut", tutlogo: "Tut", bot: "Bot", guide: "Guide", queen: "Queen", samurai: "Samurai", zeus: "Zeus", athena: "Athena", custom: "My avatar" };
  var SKIP_TAGS = /^(SCRIPT|STYLE|NOSCRIPT|TEXTAREA|INPUT|SELECT|OPTION|TD|CODE|PRE|SVG|VIDEO|CANVAS|IFRAME)$/;
  var ATTRS = ["title", "placeholder", "aria-label", "alt"];
  var WORD = /\bTut\b(?! \(logo\))/g;
  var cur = { id: "tut", name: "Tut", icon: "" }, opts = {}, origText = new WeakMap(), obs = null, busy = false, origTitle = null;

  function clean(n) { return String(n || "").replace(/[<>"'`]/g, "").replace(/\s+/g, " ").trim().slice(0, 30); }
  // The company avatar = the one the admin chose (policy.avatar.active). A person's own pick never changes admin pages or app icons.
  function company(policy) {
    var a = (policy && policy.avatar) || {}, id = String(a.active || a.character || "tut"); // Canvas / Moodle call it "character"
    if (!NAMES.hasOwnProperty(id) || a.enabled === false) id = "tut";
    if (id === "custom" && !(a.custom && typeof a.custom === "object" && Object.keys(a.custom).length)) id = "tut"; // editions store the picture differently ({v}, {png,gif}, …)
    var nm = clean(a.names && a.names[id]) || NAMES[id];
    return { id: id, name: nm, plain: nm === "Tut", v: (a.custom && a.custom.v) || "" };
  }
  function swap(s) { return cur.name === "Tut" ? s : s.replace(WORD, cur.name); }
  var FORM = /^(INPUT|TEXTAREA|SELECT)$/; // their typed values are never touched; their placeholder / tooltip are
  function skipped(el, attrsOnly) {
    for (var e = el; e && e.nodeType === 1; e = e.parentNode) {
      if (attrsOnly && e === el && FORM.test(e.tagName)) continue;
      if (SKIP_TAGS.test(e.tagName) || e.isContentEditable || (e.hasAttribute && e.hasAttribute("data-nobrand"))) return true;
      if (opts.skip && e.matches && e.matches(opts.skip)) return true;
    }
    return false;
  }
  function fixText(t) {
    var o = origText.get(t); if (o === undefined) { if (!/\bTut\b/.test(t.nodeValue)) return; o = t.nodeValue; origText.set(t, o); }
    var v = swap(o); if (t.nodeValue !== v) t.nodeValue = v;
  }
  function fixEl(el) {
    for (var i = 0; i < ATTRS.length; i++) { var a = ATTRS[i], k = "data-tb-" + a, o = el.getAttribute(k);
      if (o === null) { var cv = el.getAttribute(a); if (cv === null || !/\bTut\b/.test(cv)) continue; o = cv; el.setAttribute(k, o); }
      var v = swap(o); if (el.getAttribute(a) !== v) el.setAttribute(a, v); }
    if (el.tagName === "IMG" && opts.iconMatch) {
      var orig = el.getAttribute("data-tb-src"), src = orig || el.getAttribute("src") || "";
      if (orig || el.hasAttribute("data-brandicon") || opts.iconMatch.test(src)) {
        if (!orig) el.setAttribute("data-tb-src", src);
        var want = cur.icon || src; if (el.getAttribute("src") !== want) el.setAttribute("src", want);
        el.style.objectFit = cur.icon ? "contain" : "";
      }
    }
  }
  function walk(root) {
    if (!root) return;
    if (root.nodeType === 3) { if (!skipped(root.parentNode)) fixText(root); return; }
    if (root.nodeType !== 1 && root.nodeType !== 9 && root.nodeType !== 11) return;
    var start = root.nodeType === 9 ? root.body : root; if (!start || (start.nodeType === 1 && skipped(start))) return;
    var doc = start.ownerDocument || document, tw = doc.createTreeWalker(start, 5 /* elements + text */, {
      acceptNode: function (n) { if (n.nodeType === 1 && FORM.test(n.tagName)) { if (!n.hasAttribute("data-nobrand")) fixEl(n); return 2; }
        if (n.nodeType === 1) return (SKIP_TAGS.test(n.tagName) || n.isContentEditable || n.hasAttribute("data-nobrand") || (opts.skip && n.matches(opts.skip))) ? 2 : 1; return 1; } });
    if (start.nodeType === 1) fixEl(start);
    for (var n = tw.nextNode(); n; n = tw.nextNode()) { if (n.nodeType === 3) fixText(n); else fixEl(n); }
  }
  function footer(doc) {
    var f = doc.getElementById("tbPowered");
    if (!opts.footer) { if (f) f.remove(); return; }
    if (!f) { f = doc.createElement("div"); f.id = "tbPowered"; f.setAttribute("data-nobrand", "");
      f.style.cssText = "margin:18px 0 6px;text-align:center;font:11px Segoe UI,system-ui,sans-serif;color:#8a90a0";
      f.textContent = "Powered by Ask Tut"; (opts.footerParent || doc.body).appendChild(f); }
    f.hidden = cur.name === "Tut"; // nothing to add while the avatar is still Tut
  }
  /* apply({ name, icon, iconMatch: /regex for the Tut icon src/, footer: bool, footerParent, skip: "css selector", doc }) */
  function apply(b) {
    b = b || {}; opts = b; var doc = b.doc || document, root = b.root || doc.body; // root: only this element (e.g. a pane inside someone else's page)
    cur = { name: clean(b.name) || "Tut", icon: b.icon || "" };
    if (!b.root) { if (origTitle === null) origTitle = doc.title || ""; doc.title = swap(origTitle); }
    busy = true; try { walk(root); footer(doc); if (obs) obs.takeRecords(); } finally { busy = false; }
    if (!obs && typeof MutationObserver !== "undefined") {
      obs = new MutationObserver(function (list) { if (busy) return; busy = true;
        try { list.forEach(function (m) { if (m.type === "characterData") { var t = m.target, o = origText.get(t); if (o !== undefined && t.nodeValue === swap(o)) return; if (!skipped(t.parentNode)) { origText.delete(t); fixText(t); } }
          else if (m.type === "attributes") { if (m.target.nodeType === 1 && !skipped(m.target, true)) { if (m.attributeName === "src") m.target.removeAttribute("data-tb-src"); else m.target.removeAttribute("data-tb-" + m.attributeName); fixEl(m.target); } }
          else m.addedNodes.forEach(walk); }); obs.takeRecords(); } finally { busy = false; } });
      obs.observe(root, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATTRS.concat(["src"]) });
    }
    return cur;
  }

  // ---- Teams package (zip, stored) ----
  var CRC = (function () { var t = new Uint32Array(256); for (var n = 0; n < 256; n++) { var c = n; for (var k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } return t; })();
  function crc32(u8) { var c = 0xFFFFFFFF; for (var i = 0; i < u8.length; i++) c = CRC[(c ^ u8[i]) & 255] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; }
  function zip(files) { // [{ name, data: Uint8Array }] -> Uint8Array (no compression; Teams accepts it)
    var enc = new TextEncoder(), parts = [], central = [], off = 0;
    function u16(v) { return [v & 255, (v >>> 8) & 255]; } function u32(v) { return [v & 255, (v >>> 8) & 255, (v >>> 16) & 255, (v >>> 24) & 255]; }
    files.forEach(function (f) { var nm = enc.encode(f.name), d = f.data, c = crc32(d);
      var loc = [].concat(u32(0x04034b50), u16(20), u16(0), u16(0), u16(0), u16(0x21), u32(c), u32(d.length), u32(d.length), u16(nm.length), u16(0));
      parts.push(new Uint8Array(loc), nm, d);
      central.push(new Uint8Array([].concat(u32(0x02014b50), u16(20), u16(20), u16(0), u16(0), u16(0), u16(0x21), u32(c), u32(d.length), u32(d.length), u16(nm.length), u16(0), u16(0), u16(0), u16(0), u32(0), u32(off))), nm);
      off += loc.length + nm.length + d.length; });
    var cs = central.reduce(function (s, p) { return s + p.length; }, 0);
    var end = new Uint8Array([].concat(u32(0x06054b50), u16(0), u16(0), u16(files.length), u16(files.length), u32(cs), u32(off), u16(0)));
    var all = parts.concat(central, [end]), len = all.reduce(function (s, p) { return s + p.length; }, 0), out = new Uint8Array(len), o = 0;
    all.forEach(function (p) { out.set(p, o); o += p.length; }); return out;
  }
  function bumpVersion(v) { var p = String(v || "1.0.0").split(".").map(function (x) { return parseInt(x, 10) || 0; }); while (p.length < 3) p.push(0); p[2]++; return p.slice(0, 3).join("."); }
  function newer(a, b) { var x = String(a || "0").split(".").map(Number), y = String(b || "0").split(".").map(Number); for (var i = 0; i < 3; i++) { if ((x[i] || 0) !== (y[i] || 0)) return (x[i] || 0) > (y[i] || 0) ? a : b; } return a; }
  function renameDeep(o, name) { // every text value in the manifest; ids, URLs and file names untouched
    if (typeof o === "string") return /^(https?:|[0-9a-f-]{36}$|[\w.-]+\.(png|json)$)/i.test(o) ? o : o.replace(WORD, name);
    if (Array.isArray(o)) return o.map(function (x) { return renameDeep(x, name); });
    if (o && typeof o === "object") { var r = {}; Object.keys(o).forEach(function (k) { r[k] = /^(id|botId|\$schema|version|manifestVersion|webApplicationInfo|validDomains|accentColor)$/.test(k) ? o[k] : renameDeep(o[k], name); }); return r; }
    return o;
  }
  function loadImg(src) { return new Promise(function (ok, bad) { var i = new Image(); i.crossOrigin = "anonymous"; i.onload = function () { ok(i); }; i.onerror = function () { bad(new Error("Couldn't load the avatar picture")); }; i.src = src; }); }
  function canvasPng(c) { return new Promise(function (ok) { c.toBlob(function (b) { b.arrayBuffer().then(function (a) { ok(new Uint8Array(a)); }); }, "image/png"); }); }
  async function icons(picture) { // color 192×192 on white; outline 32×32 = white silhouette on transparent (Teams rules)
    var img = await loadImg(picture), c = document.createElement("canvas"); c.width = c.height = 192; var g = c.getContext("2d");
    g.fillStyle = "#ffffff"; g.fillRect(0, 0, 192, 192);
    var s = Math.min(168 / img.naturalWidth, 168 / img.naturalHeight), w = img.naturalWidth * s, h = img.naturalHeight * s;
    g.drawImage(img, (192 - w) / 2, (192 - h) / 2, w, h); var color = await canvasPng(c);
    var o = document.createElement("canvas"); o.width = o.height = 32; var go = o.getContext("2d"), s2 = Math.min(28 / img.naturalWidth, 28 / img.naturalHeight);
    go.drawImage(img, (32 - img.naturalWidth * s2) / 2, (32 - img.naturalHeight * s2) / 2, img.naturalWidth * s2, img.naturalHeight * s2);
    var d = go.getImageData(0, 0, 32, 32), px = d.data, opaque = 0;
    for (var i = 0; i < px.length; i += 4) if (px[i + 3] > 128) opaque++;
    var solid = opaque > 32 * 32 * 0.9; // a picture without a clear background: use its dark parts as the silhouette
    for (var j = 0; j < px.length; j += 4) { var on = solid ? (px[j] + px[j + 1] + px[j + 2]) / 3 < 140 : px[j + 3] > 128; px[j] = px[j + 1] = px[j + 2] = 255; px[j + 3] = on ? 255 : 0; }
    go.putImageData(d, 0, 0); return { color: color, outline: await canvasPng(o) };
  }
  /* teamsPackage({ manifest (object), name, picture (url or null = keep Tut icons), color, outline (Uint8Array, Tut icons), version }) */
  async function teamsPackage(p) {
    var nm = clean(p.name) || "Tut", m = renameDeep(JSON.parse(JSON.stringify(p.manifest)), nm);
    if (m.name && m.name.short && m.name.short.length > 30) m.name.short = m.name.short.slice(0, 30);
    if (m.name && m.name.full && m.name.full.length > 100) m.name.full = m.name.full.slice(0, 100);
    m.version = p.version || bumpVersion(m.version);
    var ic = p.picture ? await icons(p.picture) : { color: p.color, outline: p.outline };
    return zip([{ name: "manifest.json", data: new TextEncoder().encode(JSON.stringify(m, null, 2)) }, { name: "color.png", data: ic.color }, { name: "outline.png", data: ic.outline }]);
  }
  /* teamsPush({ token (Graph, AppCatalog.ReadWrite.All), externalId, build: async (version) => Uint8Array }) -> { ok, version, review } | { ok:false, reason } */
  async function teamsPush(p) {
    var G = "https://graph.microsoft.com/v1.0/appCatalogs/teamsApps", h = { Authorization: "Bearer " + p.token };
    var r = await fetch(G + "?$filter=" + encodeURIComponent("externalId eq '" + p.externalId + "'") + "&$expand=appDefinitions", { headers: h });
    if (!r.ok) return { ok: false, reason: "Teams catalog not readable (" + r.status + ")" };
    var app = ((await r.json()).value || []).filter(function (a) { return a.distributionMethod === "organization"; })[0];
    if (!app) return { ok: false, reason: "not-in-catalog" };
    var top = (app.appDefinitions || []).reduce(function (v, d) { return newer(d.version, v); }, p.baseVersion || "1.0.0");
    var version = bumpVersion(top), body = await p.build(version);
    var u = await fetch(G + "/" + app.id + "/appDefinitions", { method: "POST", headers: Object.assign({ "Content-Type": "application/zip" }, h), body: body });
    if (u.ok) return { ok: true, version: version };
    if (u.status === 403) { // not a Teams admin: submit it for review instead
      var u2 = await fetch(G + "/" + app.id + "/appDefinitions?requiresReview=true", { method: "POST", headers: Object.assign({ "Content-Type": "application/zip" }, h), body: body });
      if (u2.ok) return { ok: true, version: version, review: true };
    }
    var t = ""; try { t = (await u.json()).error.message; } catch (e) { }
    return { ok: false, reason: "Teams said " + u.status + (t ? ": " + t : "") };
  }
  function download(bytes, file) { var a = document.createElement("a"); a.href = URL.createObjectURL(new Blob([bytes], { type: "application/zip" })); a.download = file; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 4000); }
  /* adminPage(policy, { footerParent, skip, pic: fn(brand) -> url, iconMatch }): Tut Admin / Consumption pages in one call.
     The picture comes from the admin page's own avatar card for the active avatar (.avcard[data-id=…] img) unless pic() gives one. */
  function adminPage(policy, o) { o = o || {}; var b = company(policy), icon = "";
    if (!b.plain) { var im = document.querySelector('.avcard[data-id="' + b.id + '"] img'); icon = (o.pic && o.pic(b)) || (im && im.getAttribute("src")) || ""; }
    apply({ name: b.name, icon: icon, iconMatch: o.iconMatch || /(^|\/)icon\.svg(\?|$)|tutlogo[\w-]*\.(png|svg|webp)/i, footer: true, footerParent: o.footerParent, root: o.root,
            skip: "#avList, #avSym, .avcard, .avpick, [data-av]" + (o.skip ? ", " + o.skip : ""), doc: o.doc }); return b; }
  return { NAMES: NAMES, company: company, apply: apply, adminPage: adminPage, swap: function (s) { return swap(String(s)); }, current: function () { return cur; },
           zip: zip, teamsPackage: teamsPackage, teamsPush: teamsPush, download: download, bumpVersion: bumpVersion };
})();
