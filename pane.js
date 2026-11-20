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
 * The Ask Tut side pane (chat, cards, avatars).
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

/* Ask Tut for Moodle - the side pane (pane.php). Plain script, no build step.
 * Talks to the Ask Tut service (Azure Function in the site's own Azure) with the signed-in user's token for the
 * "Ask Tut" web service, so Moodle's own roles and capabilities apply to everything Tut reads and changes.
 */
(function () {
  var CFG = JSON.parse(document.getElementById('tut_cfg').textContent);
  var FR = /^fr/i.test(CFG.lang || ''), AR = /^ar/i.test(CFG.lang || '');
  var L = FR ? { ask: 'Posez une question…', send: 'Envoyer', approve: 'Approuver', deny: 'Refuser', change: 'Tut veut modifier Moodle', note: 'Rien n’est modifié avant votre approbation.', approved: 'Approuvé', denied: 'Refusé', newchat: 'Nouvelle conversation', thinking: 'Tut réfléchit…', down: 'Le service Tut n’est pas joignable pour l’instant.', helpful: 'Utile', notHelpful: 'Pas utile', thanks: 'Merci !', expired: 'Votre session Tut a expiré. Rechargez la page.' }
    : { ask: 'Ask a question…', send: 'Send', approve: 'Approve', deny: 'Deny', change: 'Tut wants to change Moodle', note: 'Nothing is changed until you approve.', approved: 'Approved', denied: 'Denied', newchat: 'New chat', thinking: 'Tut is thinking…', down: 'The Tut service is not reachable right now.', helpful: 'Helpful', notHelpful: 'Not helpful', thanks: 'Thanks!', expired: 'Your Tut session expired. Reload the page.' };
  if (AR) document.documentElement.dir = 'rtl';
  var $ = function (id) { return document.getElementById(id); };
  var HIST = 'asktut.chat.' + (CFG.uid || 'u') + '.' + ((CFG.ctx && (CFG.ctx.cmid || CFG.ctx.courseid)) || 'site'); // per person (Log in as shares the browser)
  var messages = []; try { messages = JSON.parse(sessionStorage.getItem(HIST) || '[]'); } catch (e) { messages = []; }

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function md(s) { // tiny, safe markdown: escape first, then links (this Moodle site only), bold, italics, bullets, tables
    var lines = esc(s).split('\n'), out = [], inList = false, tbl = [];
    function flushT() { if (!tbl.length) return; var rows = tbl.filter(function (r) { return !/^\|?\s*:?-{3,}/.test(r); }).map(function (r) { return r.replace(/^\||\|$/g, '').split('|'); });
      out.push('<table class="tut-t">' + rows.map(function (c, i) { return '<tr>' + c.map(function (x) { return (i ? '<td>' : '<th>') + x.trim() + (i ? '</td>' : '</th>'); }).join('') + '</tr>'; }).join('') + '</table>'); tbl = []; }
    lines.forEach(function (l) {
      if (/^\s*\|.*\|\s*$/.test(l)) { tbl.push(l.trim()); return; } flushT();
      var b = l.match(/^\s*[-*•]\s+(.*)$/); if (b) { if (!inList) { out.push('<ul>'); inList = true; } out.push('<li>' + b[1] + '</li>'); return; }
      if (inList) { out.push('</ul>'); inList = false; } out.push(l.trim() ? '<p>' + l + '</p>' : '');
    }); flushT(); if (inList) out.push('</ul>');
    return out.join('').replace(/\[([^\]]+)\]\((https:\/\/[^)\s]+)\)/g, function (m, t, u) { u = u.replace(/&amp;/g, '&'); return u.indexOf(location.origin + '/') === 0 ? '<a href="' + esc(u) + '" target="_top">' + t + '</a>' : t; })
      .replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>').replace(/(^|[^*])\*([^*]+)\*/g, '$1<i>$2</i>');
  }
  // ---- avatar + welcome (18 Nov 2026: parity with the other editions) ----
  var AVN = { tutlogo: 'Tut', samurai: 'Samurai', zeus: 'Zeus', athena: 'Athena', tut: 'Tut', bot: 'Bot', guide: 'Guide', queen: 'Queen', custom: 'Tut' };
  var AVS = ['tutlogo', 'samurai', 'zeus', 'athena', 'tut', 'bot', 'guide', 'queen', 'custom'];
  var PICK = 'asktut.mdl.avatar.' + (CFG.uid || 'u');
  function hasCustom() { return !!(CFG.custom && CFG.custom.v); }
  function curAv() { var k = CFG.avatar; if (CFG.pick) { try { var m = localStorage.getItem(PICK); if (m && AVS.indexOf(m) >= 0 && (m !== 'custom' || hasCustom())) k = m; } catch (e) { /* storage blocked */ } }
    return AVS.indexOf(k) >= 0 && (k !== 'custom' || hasCustom()) ? k : 'tutlogo'; }
  function avName(k) { k = k || curAv(); var n = (CFG.names && CFG.names[k]) || (k === CFG.avatar && CFG.name) || AVN[k] || 'Tut'; return n; }
  function avSrc(k, anim) { k = k || curAv(); if (k === 'custom' && hasCustom()) return customUrl(anim && CFG.custom.gif); return CFG.harness + '/api/asset/' + k + '.png'; }
  function suggestions() {
    var c = CFG.ctx || {}, pg = c.page, S;
    var T = FR ? { a1: 'Qui n’a pas encore remis ce travail ?', a2: 'Résume les remises jusqu’ici', a3: 'Que demande ce travail, en 3 points ?', p1: 'Résume cette page en 3 points', p2: 'Écris 3 questions de quiz sur cette page',
        c1: 'Qui n’a pas remis le dernier travail ?', c2: 'Qu’est-ce qui est dû dans ce cours dans les deux prochaines semaines ?', c3: 'Quels étudiants prennent du retard ?', c4: 'Ajoute un événement au cours vendredi à 10 h : heures de bureau',
        s1: 'Qu’est-ce que je dois remettre cette semaine ?', s2: 'Quelles sont mes notes dans ce cours ?', n1: 'Qu’est-ce qui est dû dans mes cours cette semaine ?', n2: 'Quels cours ont des travaux à corriger ?', n3: 'Qu’y a-t-il à mon calendrier la semaine prochaine ?', x1: 'Qui a terminé cette activité ?', x2: 'Résume cette activité' }
      : { a1: 'Who hasn\'t submitted this assignment yet?', a2: 'Summarize the submissions so far', a3: 'What does this assignment ask for, in 3 bullets?', p1: 'Summarize this page in 3 bullets', p2: 'Write 3 quiz questions from this page',
        c1: 'Who hasn\'t submitted the latest assignment?', c2: 'What\'s due in this course in the next two weeks?', c3: 'Which students are falling behind?', c4: 'Add a course event Friday at 10 am: office hour',
        s1: 'What\'s due for me this week?', s2: 'What are my grades in this course?', n1: 'What\'s due in my courses this week?', n2: 'Which of my courses have work waiting to be graded?', n3: 'What\'s on my calendar next week?', x1: 'Who has completed this activity?', x2: 'Summarize this activity' };
    if (CFG.learner) S = c.courseid ? [T.s1, T.s2, T.c2] : [T.s1, T.n1];
    else if ((pg && pg.type === 'assignment') || c.modname === 'assign') S = [T.a1, T.a2, T.a3, T.c2];
    else if (pg) S = [T.p1, T.p2, T.c2];
    else if (c.cmid) S = [T.x1, T.x2, T.c2];
    else if (c.courseid) S = [T.c1, T.c2, T.c3, T.c4];
    else S = [T.n1, T.n2, T.n3];
    return S;
  }
  function welcome() {
    var w = document.createElement('div'); w.className = 'tut-wel';
    var hero = CFG.show === false ? '' : '<img class="tut-hero' + (curAv() === 'tutlogo' || curAv() === 'custom' ? '' : ' tut-flat') + '" src="' + esc(avSrc(null, true)) + '" alt="">';
    w.innerHTML = hero + '<div class="tut-hi">' + esc((FR ? 'Bonjour, je suis ' : 'Hi, I\'m ') + avName()) + '</div><div class="tut-sub">' + esc(FR ? 'Je travaille avec vos données ' + 'Moodle' + ', en votre nom. Je demande avant de modifier quoi que ce soit.' : 'I work with your ' + 'Moodle' + ' data, as you. I ask before I change anything.') + '</div>';
    var chips = document.createElement('div'); chips.className = 'tut-chips';
    suggestions().forEach(function (q) { var b = document.createElement('button'); b.type = 'button'; b.textContent = q; b.onclick = function () { ask(q); }; chips.appendChild(b); });
    w.appendChild(chips); $('tut_log').appendChild(w);
  }
  function setHead() { var img = $('tut_avatar'); if (CFG.show === false) { img.style.display = 'none'; } else { img.onerror = function () { img.onerror = null; img.src = CFG.harness + '/api/asset/tutlogo.png'; }; img.src = avSrc(null, false); }
    $('tut_title').textContent = avName() === 'Tut' ? 'Ask Tut' : 'Ask ' + avName();
    var tbr = document.getElementById('tut_root'); if (tbr) try { TutBrand.apply({ name: avName(), root: tbr, skip: '.tut-msg, .tut-card, .tut-pickrow' }); } catch (e) { } } // Mo, 19 Nov: every "Tut" in the pane follows the avatar
  function picker(e) { e.preventDefault(); var old = document.getElementById('tut_pickrow'); if (old) { old.remove(); return; }
    var row = document.createElement('div'); row.id = 'tut_pickrow'; row.className = 'tut-pickrow';
    AVS.filter(function (k) { return k !== 'custom' || hasCustom(); }).forEach(function (k) { var b = document.createElement('button'); b.type = 'button'; b.title = avName(k); b.className = k === curAv() ? 'on' : '';
      b.innerHTML = '<img src="' + esc(avSrc(k, false)) + '" alt=""><span>' + esc(avName(k)) + '</span>'; b.onclick = function () { try { localStorage.setItem(PICK, k); } catch (x) { /* blocked */ } row.remove(); setHead(); if (!messages.length) render(); }; row.appendChild(b); });
    $('tut_chat').insertBefore(row, $('tut_log')); }
  function customUrl(gif) { return gif && CFG.custom.gifUrl ? CFG.custom.gifUrl : CFG.custom.png; }
  function bubble(role, html, extra) { var d = document.createElement('div'); d.className = 'tut-msg tut-' + role; d.innerHTML = html; if (extra) d.appendChild(extra); $('tut_log').appendChild(d); $('tut_log').scrollTop = 1e9; return d; }
  function render() {
    $('tut_log').innerHTML = ''; if (!messages.length) { welcome(); return; }
    messages.forEach(function (m) { if (m.role === 'user' && typeof m.content === 'string') bubble('user', esc(m.content)); else if (m.role === 'assistant' && Array.isArray(m.content)) { var tx = m.content.filter(function (b) { return b.type === 'text'; }).map(function (b) { return b.text; }).join('\n'); if (tx) bubble('tut', md(tx)); } });
  }
  function post(path, body) {
    return fetch(CFG.harness + path, { method: 'POST', headers: { Authorization: 'Bearer ' + CFG.token, 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (r.status === 401) throw new Error(L.expired); if (!r.ok) throw new Error(j.error || ('HTTP ' + r.status)); return j; }); });
  }
  function feedbackBar(usageId) {
    var bar = document.createElement('div'); bar.className = 'tut-fb'; if (!usageId) return bar;
    [[1, '👍', L.helpful], [-1, '👎', L.notHelpful]].forEach(function (x) { var b = document.createElement('button'); b.textContent = x[1]; b.title = x[2]; b.onclick = function () {
      post('/api/feedback', { usageId: usageId, rating: x[0] }).then(function () { bar.textContent = L.thanks; }).catch(function () { bar.textContent = ''; }); }; bar.appendChild(b); });
    return bar;
  }
  // Approval card: what will change, in plain words (old -> new where there is an old value). Never raw JSON.
  // 19 Nov 2026 (D365 parity): documents to download, drafts the user sends themselves
  function fileCard(f) {
    var c = document.createElement('div'); c.className = 'tut-card tut-file';
    var kind = /\.xlsx$/.test(f.name) ? 'Excel' : /\.pdf$/.test(f.name) ? 'PDF' : 'Word';
    c.innerHTML = '<div class="tut-card-h">' + esc(f.name) + '</div><div class="tut-card-n">' + esc(kind) + ' · ' + Math.max(1, Math.round((f.bytes || 0) / 1024)) + ' KB</div>';
    var row = document.createElement('div'); row.className = 'tut-card-b';
    var a = document.createElement('a'); a.className = 'tut-ok tut-dl'; a.textContent = FR ? 'Télécharger' : 'Download'; a.href = '#';
    a.onclick = function (e) { e.preventDefault(); try { var bin = atob(f.base64), u8 = new Uint8Array(bin.length); for (var i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
      var url = URL.createObjectURL(new Blob([u8], { type: f.mime })), x = document.createElement('a'); x.href = url; x.download = f.name; document.body.appendChild(x); x.click(); x.remove(); setTimeout(function () { URL.revokeObjectURL(url); }, 60000); }
      catch (err) { if (f.url) window.open(f.url, '_blank'); } };
    row.appendChild(a);
    if (f.url) { var o = document.createElement('a'); o.href = f.url; o.target = '_blank'; o.rel = 'noopener'; o.className = 'tut-alt'; o.textContent = FR ? 'Ouvrir dans un nouvel onglet' : 'Open in a new tab'; row.appendChild(o); }
    c.appendChild(row); return c;
  }
  function draftCard(d) {
    var K = FR ? { message: 'Brouillon de message', announcement: 'Brouillon d’annonce', reply: 'Brouillon de réponse' } : { message: 'Draft message', announcement: 'Draft announcement', reply: 'Draft reply' };
    var c = document.createElement('div'); c.className = 'tut-card tut-draft';
    c.innerHTML = '<div class="tut-card-h">' + esc(K[d.kind] || K.message) + '</div>' + (d.to || d.subject ? '<table>' + (d.to ? '<tr><td>' + (FR ? 'À' : 'To') + '</td><td>' + esc(d.to) + '</td></tr>' : '') + (d.subject ? '<tr><td>' + (FR ? 'Objet' : 'Subject') + '</td><td>' + esc(d.subject) + '</td></tr>' : '') + '</table>' : '') +
      '<div class="tut-draft-body">' + esc(d.body).replace(/\n/g, '<br>') + '</div><div class="tut-card-n">' + esc(FR ? 'Tut ne l’envoie pas : copiez-le et envoyez-le vous-même.' : 'Tut does not send it: copy it and send it yourself.') + '</div>';
    var row = document.createElement('div'); row.className = 'tut-card-b';
    var cp = document.createElement('button'); cp.type = 'button'; cp.className = 'tut-ok'; cp.textContent = FR ? 'Copier' : 'Copy';
    cp.onclick = function () { var t = (d.subject ? d.subject + '\n\n' : '') + d.body; var done = function () { cp.textContent = FR ? 'Copié' : 'Copied'; };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(done, function () { fallback(t); done(); }); else { fallback(t); done(); } };
    row.appendChild(cp);
    if (d.url) { var o = document.createElement('a'); o.href = d.url; o.target = '_blank'; o.rel = 'noopener'; o.className = 'tut-alt'; o.textContent = FR ? 'Ouvrir' : (d.kind === 'announcement' ? 'Open the forum' : d.kind === 'reply' ? 'Open the discussion' : 'Open Moodle messages'); row.appendChild(o); }
    c.appendChild(row); return c;
  }
  function fallback(t) { var ta = document.createElement('textarea'); ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (e) { /* ignore */ } ta.remove(); }
  function approvalCard(p) {
    var s = p.summary || {}, c = document.createElement('div'); c.className = 'tut-card';
    var rows = (s.rows || []).map(function (r) { return '<tr><td>' + esc(r.label) + '</td><td>' + (r.old !== undefined && r.old !== null && r.old !== '' ? '<span class="tut-old">' + esc(r.old) + '</span> → ' : '') + (r.url ? '<a href="' + esc(r.url) + '" target="_top">' + esc(r.value) + '</a>' : esc(r.value)) + '</td></tr>'; }).join('');
    c.innerHTML = '<div class="tut-card-h">' + esc(s.headline || L.change) + '</div>' + (rows ? '<table>' + rows + '</table>' : '') + (s.warning ? '<div class="tut-card-n"><b>' + esc(s.warning) + '</b></div>' : '') + '<div class="tut-card-n">' + esc(L.note) + '</div>';
    var row = document.createElement('div'); row.className = 'tut-card-b';
    [[true, L.approve, 'ok'], [false, L.deny, 'no']].forEach(function (x) { var b = document.createElement('button'); b.textContent = x[1]; b.className = 'tut-' + x[2];
      b.onclick = function () { row.innerHTML = '<i>' + esc(x[0] ? L.approved : L.denied) + '</i>'; ask(null, { id: p.id, sig: p.sig, at: p.at, approved: x[0] }); }; row.appendChild(b); });
    c.appendChild(row); return c;
  }
  var TSYM = {
    hiero: [[0x13153, 0x132F9, 0x13193, 0x131A3, 0x132BD, 0x1313F, 0x131CB, 0x13143, 0x13184, 0x13080, 0x13000, 0x1302D].map(function (c) { return String.fromCodePoint(c); }), "'Segoe UI Historic','Noto Sans Egyptian Hieroglyphs',serif", ["#8a5a0f", "#173a6e"], "Hieroglyphs (Egyptian)"],
    japanese: [["\u4F8D","\u6B66","\u9053","\u5FC3","\u548C","\u685C","\u6708","\u5C71","\u98A8","\u529B"], "'Yu Gothic UI','Yu Gothic','Meiryo','Noto Sans JP',sans-serif", ["#173a6e", "#a3261f"], "Japanese characters"],
    greek: [["\u0391","\u0394","\u0398","\u039B","\u03A3","\u03A6","\u03A8","\u03A9","\u03DF","\u039E"], "'Segoe UI','Times New Roman',serif", ["#173a6e", "#8a5a0f", "#173a6e", "#1f7fb8"], "Greek letters"],
    circuit: [["0","1","\u2699","1","0","\u25C8","\u2B21","\u23FB","\u2263","\u25A3"], "Consolas,'Segoe UI Symbol',monospace", ["#0b6f86", "#173a6e"], "Circuit symbols"],
    nature: [["\u2726","\u263E","\u2248","\u2600","\u274B","\u25B2","\u2727","\u2741"], "'Segoe UI Symbol','Noto Sans Symbols 2',sans-serif", ["#0f7d78", "#a3361f", "#0f7d78", "#8a5a0f"], "Nature symbols"],
    ideas: [["?","!","\u270E","\u2731","\u263C","\u2042","\u2726","\u2192"], "'Segoe UI Symbol','Segoe UI',sans-serif", ["#173a6e", "#b8902e"], "Ideas"],
    science: [["\u269B\uFE0E","\u2697\uFE0E","\u232C","\u2609","\u2641","\u223F","\u03BB","\u0394"], "'Segoe UI Symbol','Segoe UI',sans-serif", ["#173a6e", "#1f7fb8"], "Science"],
    math: [["\u03C0","\u2211","\u221A","\u221E","\u222B","\u00B1","\u00F7","\u2248","="], "'Cambria Math','Segoe UI Symbol',serif", ["#173a6e", "#1f7fb8"], "Math"],
    music: [["\u266A","\u266B","\u2669","\u266C","\u266D","\u266F"], "'Segoe UI Symbol','Segoe UI',sans-serif", ["#173a6e", "#1f7fb8"], "Music notes"],
    space: [["\u2605","\u2606","\u263E","\u2604\uFE0E","\u2727","\u2644","\u2726"], "'Segoe UI Symbol','Segoe UI',sans-serif", ["#173a6e", "#b8902e"], "Stars and space"],
    chess: [["\u2654","\u2655","\u2656","\u2657","\u2658","\u2659"], "'Segoe UI Symbol','Segoe UI',sans-serif", ["#173a6e", "#b8902e"], "Chess"],
    office: [["\u2713","\u270E","\u2709\uFE0E","\u2318","\u2399"], "'Segoe UI Symbol','Segoe UI',sans-serif", ["#173a6e", "#b8902e"], "Office"],
    money: [["$","\u20AC","\u00A3","\u00A5","%","#"], "'Segoe UI',sans-serif", ["#173a6e", "#b8902e"], "Money"],
    arrows: [["\u2192","\u21BB","\u21C4","\u2197","\u2934"], "'Segoe UI Symbol','Segoe UI',sans-serif", ["#173a6e", "#b8902e"], "Arrows"],
    braille: [["\u2801","\u2803","\u2809","\u2819","\u2811","\u280B"], "'Segoe UI Symbol','Segoe UI',sans-serif", ["#173a6e", "#b8902e"], "Braille"],
    runes: [["\u16A0","\u16A2","\u16A6","\u16A8","\u16B1","\u16B2","\u16B7"], "'Segoe UI Historic','Noto Sans Runic',serif", ["#173a6e", "#b8902e"], "Norse runes"],
    dots: [["\u25CF"], "'Segoe UI',sans-serif", ["#1F3A68"], "Simple dots"] };
  function tsymRow(k) { if (k === 'off') return ''; var d = TSYM[k] || TSYM.hiero, h = '<span class="tsym" aria-hidden="true" style="font-family:' + d[1] + (k === 'dots' ? ';font-size:12px' : '') + '">';
    for (var i = 0; i < 5; i++) h += '<i style="color:' + d[2][i % d[2].length] + '">' + d[0][(i * 3) % d[0].length] + '</i>'; return h + '</span>'; }
  function ask(text, approval) {
    if (text) { messages.push({ role: 'user', content: text }); bubble('user', esc(text)); }
    var w0 = $('tut_log').querySelector('.tut-wel'); if (w0) w0.remove();
    var wait = bubble('tut', '<i>' + esc(FR ? avName() + ' réfléchit…' : avName() + ' is thinking…') + '</i>' + tsymRow(CFG.sym || 'hiero')); $('tut_send').disabled = true;
    post('/api/chat', { messages: messages, approval: approval || undefined, context: Object.assign({}, CFG.ctx, { assistantName: avName() }) })
      .then(function (j) {
        wait.remove(); messages = j.messages || messages; try { sessionStorage.setItem(HIST, JSON.stringify(messages.slice(-40))); } catch (e) { /* full */ }
        if (j.reply) { var d = bubble('tut', md(j.reply)); var meta = document.createElement('div'); meta.className = 'tut-meta'; meta.textContent = (j.tools && j.tools.length ? (FR ? 'Consulté : ' : 'Looked up: ') + j.tools.join(', ') + ' · ' : '') + (j.modelName || j.model || '') + (j.cost !== undefined ? ' · $' + Number(j.cost).toFixed(4) : '') + (j.readOnly ? ' · read only' : ''); d.appendChild(meta); d.appendChild(feedbackBar(j.usageId)); }
        (j.drafts || []).forEach(function (d) { bubble('tut', '', draftCard(d)); });
        (j.files || []).forEach(function (f) { bubble('tut', '', fileCard(f)); });
        if (j.pending) bubble('tut', '', approvalCard(j.pending));
      })
      .catch(function (e) { wait.remove(); bubble('err', esc(/Failed to fetch|NetworkError/i.test(e.message) ? L.down : e.message)); })
      .then(function () { $('tut_send').disabled = false; $('tut_in').focus(); });
  }

  $('tut_in').placeholder = L.ask; $('tut_send').textContent = L.send; $('tut_new').textContent = L.newchat;
  var c = CFG.ctx || {}; if (c.activity || c.course) { $('tut_ctx').textContent = c.activity || c.course; $('tut_ctx').title = [c.course, c.activity].filter(Boolean).join(' › '); }
  $('tut_new').onclick = function (e) { e.preventDefault(); messages = []; sessionStorage.removeItem(HIST); render(); };
  $('tut_form').onsubmit = function (e) { e.preventDefault(); var v = $('tut_in').value.trim(); if (!v) return; $('tut_in').value = ''; ask(v); };
  setHead(); if (CFG.pick) { $('tut_pick').style.display = 'inline'; $('tut_pick').textContent = 'Avatar'; $('tut_pick').onclick = picker; }
  if (CFG.admin) { $('tut_admin').href = CFG.admin.tut; $('tut_admin').style.display = 'inline'; $('tut_cons').href = CFG.admin.consumption; $('tut_cons').textContent = FR ? 'Consommation' : 'Consumption'; $('tut_cons').style.display = 'inline'; }
  render(); $('tut_in').focus();
})();
