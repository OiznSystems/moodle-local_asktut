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
 * "Where the AI runs" chooser used on the Tut Admin page.
 *
 * @package    local_asktut
 * @copyright  2026 Oizn Systems Inc. {@link https://asktut.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* Ask Tut - "Where the AI runs" widget: other AI models (Azure/Microsoft Foundry, Databricks, ChatGPT, Gemini, other / own model).
 * Copied unchanged from the Dynamics 365 edition's Tut Admin (webresources/admin.js, OAI block) on 18 Nov 2026 so every
 * edition shows the same screen (Mo: "people need to feel that we are a single system run individually").
 * Settings only: keys live in Azure Key Vault. API: TutOAI.mount(el, opts), show(name, processIn), set(provider), get(),
 * validate(name, processIn), label(name), result(statusJson), has(name), options(). */
  // ---------- Other AI models (Mo, 8 Nov 2026; redesign 11 Nov 2026): Azure OpenAI, Databricks, ChatGPT, Gemini, other / own model ----------
  // Same block in every edition's Tut Admin. Settings only: keys live in Key Vault (or the customer's encrypted key store).
  // 11 Nov 2026 evening (Mo): provider list = Direct (Claude, OpenAI, Gemini) · Through a platform (Microsoft Foundry,
  // Databricks: pick the model family next) · Other. Foundry + Claude family = the edition's own "foundry" path; Foundry +
  // any other family = id "azureopenai" (hidden option, shown as "Microsoft Foundry"). The widget switches between the two.
  window.TutOAI = (function () {
    // bill = who bills you, sign = how Tut signs in, credit = whether Azure credit pays (plain words, shown for every choice)
    var INFO = {
      claude: { bill: "Anthropic bills you directly", sign: "Claude API key", credit: "No" },
      foundry: { bill: "On your Azure invoice through Azure Marketplace (sold by Anthropic)", sign: "Managed identity or Foundry key", credit: "No (Marketplace item). Counts toward an Azure commitment (MACC)" },
      azureopenai: { bill: "Microsoft, on your Azure invoice", sign: "Managed identity (no key) or Foundry key", credit: "Yes" },
      foundry_other: { bill: "On your Azure invoice: models sold directly by Azure are billed by Microsoft; partner models come through Azure Marketplace", sign: "Managed identity (no key) or Foundry key", credit: "Yes for models sold directly by Azure; no for Marketplace (partner) models. The model's card in Foundry says which" },
      databricks: { bill: "On your Azure Databricks bill, in DBUs", sign: "Databricks token, or managed identity", credit: "Not confirmed: Azure Databricks is billed by Microsoft; check with your Microsoft contact" },
      openai: { bill: "OpenAI bills you directly", sign: "OpenAI API key", credit: "No" },
      gemini: { bill: "Google bills you directly", sign: "Gemini API key", credit: "No" },
      custom: { bill: "Whoever runs the model", sign: "That server's key (or none on a private network)", credit: "No" }
    };
    var PROV = {
      azureopenai: { label: "Microsoft Foundry", key: "FOUNDRY_API_KEY", eg: ["gpt-6-luna", "gpt-6.1-sol", "gpt-6-astra"], role: "Cognitive Services OpenAI User",
        note: "Your organization's own Microsoft Foundry resource (an Azure OpenAI resource works too). Model name = the deployment name you chose in Foundry.",
        prices: { date: "11 Nov 2026", src: "OpenAI list prices; check your Azure price sheet", rows: [["gpt-6-luna", 0.10, 0.50], ["gpt-6.1-sol", 2, 10], ["gpt-6-astra", 10, 50]] } },
      databricks: { label: "Databricks", key: "DATABRICKS_TOKEN", eg: ["databricks-claude-haiku-4-5", "databricks-claude-sonnet-4-6", "databricks-claude-opus-4-6"],
        note: "Your organization's own Databricks workspace (pay-per-token Foundation Model APIs). Model name = the serving endpoint name (Serving page, or List models).",
        prices: { date: "11 Nov 2026", src: "Databricks DBU rate card x your $ per DBU", dbu: true, rows: [["databricks-claude-haiku-4-5", 14.286, 71.429], ["databricks-claude-sonnet-4-6", 42.857, 214.286], ["databricks-claude-opus-4-6", 71.429, 357.143]] } },
      openai: { label: "OpenAI (ChatGPT)", key: "OPENAI_API_KEY", eg: ["gpt-6-luna", "gpt-6.1-sol", "gpt-6-astra"], note: "Your organization's own OpenAI API account (platform.openai.com).",
        prices: { date: "11 Nov 2026", src: "OpenAI API pricing page", rows: [["gpt-6-luna", 0.10, 0.50], ["gpt-6.1-sol", 2, 10], ["gpt-6-astra", 10, 50]] } },
      gemini: { label: "Google Gemini", key: "GEMINI_API_KEY", eg: ["gemini-3.1-flash-lite", "gemini-3.8-flash", "gemini-4-argon"], note: "Your organization's own Gemini API key (Google AI Studio or Google Cloud). Gemini can't read PDFs through this connection; Tut tells the model to ask for the text instead.",
        prices: { date: "11 Nov 2026", src: "Google Gemini API pricing (3.8 Flash introductory to 31 Dec 2026; 4 Argon list price from 1 Dec 2026)", rows: [["gemini-3.1-flash-lite", 0.25, 1.50], ["gemini-3.8-flash", 0.75, 3.75], ["gemini-4-argon", 4, 20]] } },
      custom: { label: "Other / own model (self-hosted, or other providers)", key: "CUSTOM_AI_API_KEY", eg: ["", "", ""], note: "Any server that speaks the OpenAI Chat Completions format with tool calling: a model you host yourself (vLLM, Ollama, LM Studio, TGI) published over https, or another provider's API." }
    };
    var PRESETS = [["", "Choose a preset (optional)", "", ""], ["mistral", "Mistral", "https://api.mistral.ai/v1", "bearer"],
      ["xai", "xAI (Grok)", "https://api.x.ai/v1", "bearer"], ["openrouter", "OpenRouter", "https://openrouter.ai/api/v1", "bearer"], ["own", "Own model server over https (vLLM, Ollama, LM Studio, TGI)", "https://<your-server>/v1", "bearer"]];
    var TIERS = [["small", "Fast", "simple lookups (Auto) — Tut's Haiku slot"], ["standard", "Standard", "most answers, writes and drafts — Tut's Sonnet slot"], ["large", "Most capable", "deep analysis, only if allowed — Tut's Opus slot"]];
    var GROUPS = [["Direct (your own account with the AI company)", [["claude", "Claude (Anthropic API)"], ["openai", "OpenAI (ChatGPT)"], ["gemini", "Google Gemini"]]],
      ["Through a platform (pick the model family next)", [["foundry", "Microsoft Foundry"], ["azureopenai", "Microsoft Foundry", 1], ["databricks", "Databricks"]]],
      ["Other", [["custom", "Other / own model (self-hosted, or other providers)"]]]];
    // model families per platform: [id, label, example deployment names, typical prices (rows) or null]
    var FAM = {
      foundry: [["claude", "Claude (Anthropic)"], ["openai", "OpenAI (GPT)"], ["deepseek", "DeepSeek"], ["llama", "Meta Llama"], ["mistral", "Mistral"], ["grok", "xAI Grok"], ["other", "Other Foundry model"]],
      databricks: [["claude", "Claude (Anthropic)"], ["openai", "OpenAI (GPT)"], ["gemini", "Google Gemini"], ["llama", "Meta Llama"], ["other", "Other Databricks model"]] };
    var o = {}, root = null, own = false, famF = "claude", famD = "claude", azFam = "openai", sel = null, famRow = null;
    function e(s) { return String(s === null || s === undefined ? "" : s).replace(/[&<>"]/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]; }); }
    function q(id) { return root.querySelector("#" + id); }
    function infoKey(n) { return n === "azureopenai" ? (famF === "openai" ? "azureopenai" : famF === "claude" ? "foundry" : "foundry_other") : n; }
    function famLabel(g, f) { var x = (FAM[g] || []).filter(function (a) { return a[0] === f; })[0]; return x ? x[1] : ""; }
    function infoHtml(n) { var i = INFO[infoKey(n)]; if (!i) return ""; return '<p class="hint" style="margin:6px 0 10px"><b>Who bills you:</b> ' + e(i.bill) + ' &nbsp;·&nbsp; <b>Sign-in:</b> ' + e(i.sign) + ' &nbsp;·&nbsp; <b>Paid by Azure credit:</b> ' + e(i.credit) + '</p>'; }
    function signSel(n, opts) { return '<div><label for="oai_' + n + '_auth">Sign-in</label><select id="oai_' + n + '_auth">' + opts.map(function (x) { return '<option value="' + x[0] + '">' + e(x[1]) + '</option>'; }).join("") + '</select></div>'; }
    function block(n) { var d = PROV[n], h = '<div data-oai="' + n + '" hidden><p class="hint">' + e(d.note) + ' <span data-oai-kv>The key goes in Azure Key Vault as app setting <b>' + d.key + '</b> (never on this page).</span>' +
        (d.role || n === "databricks" ? ' <span data-oai-mi hidden>No key needed: ' + (n === "databricks" ? "add the Ask Tut app's managed identity to the Databricks workspace as a service principal and give it <b>Can query</b> on the serving endpoints." : "give the Ask Tut app's managed identity the <b data-oai-role>" + e(d.role) + "</b> role on the Foundry resource.") + '</span>' : '') + '</p>' +
        // customer orgs on a shared service paste their own key here (encrypted, never shown again)
        '<div data-oai-key hidden style="margin:6px 0 10px"><label for="oai_' + n + '_key">Your ' + e(d.label) + (n === "databricks" ? " token" : " API key") + ' <span class="hint" id="oai_' + n + '_keyState" style="margin:0"></span></label>' +
        '<div style="display:flex;gap:6px"><input type="password" id="oai_' + n + '_key" autocomplete="off" data-oai-nodirty style="flex:1" placeholder="Paste the key"><button class="add" type="button" data-oai-keysave="' + n + '">Save key</button><button class="add" type="button" data-oai-keydel="' + n + '">Remove</button></div>' +
        '<span class="hint" id="oai_' + n + '_keyMsg" style="margin:0"></span></div>';
      if (n === "custom") h += '<div class="grid2"><div><label for="oai_custom_preset">Preset</label><select id="oai_custom_preset">' + PRESETS.map(function (p) { return '<option value="' + p[0] + '">' + e(p[1]) + '</option>'; }).join("") + '</select></div>' +
        '<div><label for="oai_custom_endpoint">Endpoint (base URL)</label><input id="oai_custom_endpoint" type="url" placeholder="https://host/v1" style="width:100%"></div></div>' +
        '<div class="grid2" style="margin-top:8px"><div><label for="oai_custom_auth">Key header</label><select id="oai_custom_auth"><option value="bearer">Authorization: Bearer (most servers)</option><option value="api-key">api-key</option><option value="none">No key (private network endpoint)</option></select></div>' +
        '<div><label for="oai_custom_maxParam">Length setting</label><select id="oai_custom_maxParam"><option value="max_tokens">max_tokens (most servers)</option><option value="max_completion_tokens">max_completion_tokens (newer OpenAI-style)</option></select></div></div>' +
        '<p style="margin-top:8px"><label><input type="checkbox" id="oai_custom_pdf"> The model can read PDFs (file parts)</label> &nbsp; <label><input type="checkbox" id="oai_custom_images" checked> The model accepts images</label></p>';
      if (n === "azureopenai") h += '<div class="grid2"><div><label for="oai_azureopenai_endpoint">Foundry endpoint</label><input id="oai_azureopenai_endpoint" type="url" placeholder="https://your-resource.services.ai.azure.com" style="width:100%"></div>' +
        signSel(n, [["entra", "Managed identity (recommended, no key)"], ["api-key", "API key from Key Vault"]]) + '</div>' +
        '<p style="margin-top:8px" data-oai-pdfrow><label><input type="checkbox" id="oai_azureopenai_pdf"> The deployed models can read PDFs (file parts)</label></p>';
      if (n === "databricks") h += '<div class="grid2"><div><label for="oai_databricks_endpoint">Workspace URL</label><input id="oai_databricks_endpoint" type="url" placeholder="https://adb-1234567890123456.7.azuredatabricks.net" style="width:100%"></div>' +
        signSel(n, [["bearer", "Databricks token from Key Vault"], ["entra", "Managed identity (no token)"]]) + '</div>' +
        '<div class="grid2" style="margin-top:8px"><div><label for="oai_databricks_dbu">Your price per DBU (US$)</label><input id="oai_databricks_dbu" type="number" min="0" step="0.001" style="width:110px" placeholder="0.07"></div>' +
        '<div><p class="hint" style="margin:18px 0 0">Databricks bills in DBUs, not dollars. Tut needs $ per 1M tokens: <b>DBUs per 1M tokens × your $ per DBU</b>. The DBU rates per model are on Databricks\' pricing page; 0.07 is the Azure Premium list price for AI serving. "Fill typical prices" does the sum for you.</p></div></div>';
      h += '<table style="margin-top:8px"><thead><tr><th>Tut model slot</th><th>Model name</th><th>Input $ / 1M tokens</th><th>Output $ / 1M tokens</th></tr></thead><tbody>' + TIERS.map(function (t, i) {
        return '<tr><td><b>' + t[1] + '</b><br><span class="hint" style="margin:0">' + e(t[2]) + '</span></td><td><input id="oai_' + n + '_' + t[0] + '_model" list="oai_' + n + '_list" placeholder="' + e(d.eg[i] ? "e.g. " + d.eg[i] : "model name") + '" aria-label="' + t[1] + ' model name"></td>' +
          '<td><input id="oai_' + n + '_' + t[0] + '_in" type="number" min="0" step="0.01" style="width:90px" aria-label="' + t[1] + ' input price"></td><td><input id="oai_' + n + '_' + t[0] + '_out" type="number" min="0" step="0.01" style="width:90px" aria-label="' + t[1] + ' output price"></td></tr>'; }).join("") +
        '</tbody></table><datalist id="oai_' + n + '_list"></datalist>' +
        (n === "azureopenai" || n === "databricks" ? '<p class="hint" data-oai-noprices="' + n + '" hidden style="margin-top:6px">Prices: copy them from the model\'s card (' + (n === "databricks" ? "Databricks pricing, in DBUs × your $ per DBU" : "Foundry model catalog") + '), in US$ per 1M tokens.</p>' : '') +
        (d.prices ? '<p style="margin-top:6px" data-oai-pricerow="' + n + '"><button class="add" type="button" data-oai-prices="' + n + '">Fill typical prices</button> <span class="hint" style="margin:0">Fills the three slots with common models and list prices (checked ' + e(d.prices.date) + '; ' + e(d.prices.src) + '). Change anything you need; prices change, so check them now and then.</span></p>' : '') +
        '<div class="grid2" style="margin-top:8px"><div><label for="oai_' + n + '_reasoning">Reasoning effort</label><select id="oai_' + n + '_reasoning"><option value="">Model default</option><option value="low">Low (faster, cheaper)</option><option value="medium">Medium</option><option value="high">High (slower, more tokens)</option></select></div>' +
        '<div><label>&nbsp;</label><button class="add" type="button" data-oai-list="' + n + '">List models</button> <span class="hint" id="oai_' + n + '_listStatus" style="margin:0">Fills the model name lists from your ' + (n === "databricks" ? "workspace" : "key") + ' (save first).</span></div></div>' +
        '<p data-oai-us style="margin-top:8px" hidden><label><input type="checkbox" id="oai_' + n + '_us"> This account / endpoint keeps processing in the US only (needed for "Process in: US only"; tick only if your contract or deployment guarantees it — otherwise Tut refuses rather than send it)</label></p>' +
        '<p class="hint">If a slot is empty Tut uses the nearest one you filled in. Prices turn tokens into money on the Consumption dashboard and limits; use your list prices in USD.</p></div>';
      return h; }
    function mount(el, opts) { o = opts || {}; root = el; root.innerHTML = "<style>[data-oai][hidden],[data-oai-us][hidden],[data-oai-key][hidden],[data-oai-kv][hidden],[data-oai-mi][hidden],[data-oai-noprices][hidden],[data-oai-pricerow][hidden],[data-oai-pdfrow][hidden]{display:none!important}</style><div data-oai-info></div>" + Object.keys(PROV).map(block).join("");
      root.querySelectorAll("input:not([data-oai-nodirty]),select").forEach(function (i) { i.addEventListener("input", function () { if (o.dirty) o.dirty(); }); i.addEventListener("change", function () { if (o.dirty) o.dirty(); }); });
      q("oai_custom_preset").addEventListener("change", function () { var p = PRESETS.filter(function (x) { return x[0] === q("oai_custom_preset").value; })[0]; if (p && p[2]) { q("oai_custom_endpoint").value = p[2]; q("oai_custom_auth").value = p[3]; } });
      ["azureopenai", "databricks"].forEach(function (n) { q("oai_" + n + "_auth").addEventListener("change", function () { signShow(n); }); });
      root.querySelectorAll("[data-oai-list]").forEach(function (b) { b.addEventListener("click", function () { listModels(b.getAttribute("data-oai-list")); }); });
      root.querySelectorAll("[data-oai-prices]").forEach(function (b) { b.addEventListener("click", function () { fillPrices(b.getAttribute("data-oai-prices")); }); });
      root.querySelectorAll("[data-oai-keysave]").forEach(function (b) { b.addEventListener("click", function () { var n = b.getAttribute("data-oai-keysave"), v = q("oai_" + n + "_key").value.trim(); if (v) saveKey(n, v); }); });
      root.querySelectorAll("[data-oai-keydel]").forEach(function (b) { b.addEventListener("click", function () { saveKey(b.getAttribute("data-oai-keydel"), null); }); }); }
    // managed identity choice: hide the key notes, show the role note; customer orgs on a shared service can only use keys
    function signShow(n) { var d = root.querySelector('[data-oai="' + n + '"]'); if (!d) return; var mi = q("oai_" + n + "_auth").value === "entra";
      d.querySelectorAll("[data-oai-mi]").forEach(function (x) { x.hidden = !mi; }); d.querySelectorAll("[data-oai-kv]").forEach(function (x) { x.hidden = mi || own; }); d.querySelectorAll("[data-oai-key]").forEach(function (x) { x.hidden = mi || !own; }); }
    function presetOK(n) { return n === "azureopenai" ? famF === "openai" : n === "databricks" ? famD === "claude" : !!PROV[n].prices; }
    function fillPrices(n) { var pr = PROV[n].prices; if (!pr || !presetOK(n)) return; var rate = pr.dbu ? (Number(q("oai_databricks_dbu").value) || 0.07) : 1;
      TIERS.forEach(function (t, i) { var r = pr.rows[i]; if (!r) return; var mi = q("oai_" + n + "_" + t[0] + "_model"); if (!mi.value.trim()) mi.value = r[0];
        if (mi.value.trim() === r[0]) { q("oai_" + n + "_" + t[0] + "_in").value = Math.round(r[1] * rate * 10000) / 10000; q("oai_" + n + "_" + t[0] + "_out").value = Math.round(r[2] * rate * 10000) / 10000; } });
      if (o.dirty) o.dirty(); }
    function saveKey(n, v) { var m = q("oai_" + n + "_keyMsg"); if (!o.saveKey) return; m.textContent = v ? "Saving…" : "Removing…";
      Promise.resolve().then(function () { return o.saveKey(n, v); }).then(function (st) { keyState(n, st); q("oai_" + n + "_key").value = ""; m.textContent = v ? "Saved. The key is encrypted and never shown again." : "Removed."; },
        function (err) { m.textContent = "Couldn't save: " + err.message; }); }
    function keyState(n, st) { var x = q("oai_" + n + "_keyState"); if (x) x.textContent = st && st.set ? "(set, ends with …" + (st.last4 || "") + ")" : "(not set)"; }
    // keys(true, {openai:{set,last4},...}) shows the per-customer key boxes instead of the Key Vault note
    function keys(on, states) { if (!root) return; own = !!on; root.querySelectorAll("[data-oai-key]").forEach(function (d) { d.hidden = !on; }); root.querySelectorAll("[data-oai-kv]").forEach(function (d) { d.hidden = !!on; });
      ["azureopenai", "databricks"].forEach(function (n) { var s = q("oai_" + n + "_auth"); s.querySelector('option[value="entra"]').disabled = own; if (own && s.value === "entra") s.value = n === "databricks" ? "bearer" : "api-key"; signShow(n); });
      if (on) Object.keys(PROV).forEach(function (n) { keyState(n, (states || {})[n]); }); }
    function listModels(n) { var s = q("oai_" + n + "_listStatus"); if (!o.listModels) return; if (o.isDirty && o.isDirty()) { s.textContent = "Save first: the list uses the saved settings and key."; return; }
      s.textContent = "Asking " + PROV[n].label + "…"; Promise.resolve().then(function () { return o.listModels(n); }).then(function (m) { q("oai_" + n + "_list").innerHTML = m.map(function (x) { return '<option value="' + e(x) + '">'; }).join(""); s.textContent = m.length + " models available — start typing in a model name box."; },
        function (err) { s.textContent = "Couldn't list models: " + err.message; }); }
    // ---- platform + model family (Foundry, Databricks) ----
    function bind() { if (sel && document.contains(sel)) return;
      var opt = root && root.ownerDocument.querySelector('select option[value="azureopenai"]'); sel = opt ? opt.parentNode : null; while (sel && sel.tagName !== "SELECT") sel = sel.parentNode;
      if (!sel) return;
      if (!sel.querySelector("optgroup")) { var v = sel.value; sel.innerHTML = options(); sel.value = v; } // group the edition's list (Direct / Through a platform / Other)
      famRow = root.ownerDocument.createElement("div"); famRow.setAttribute("data-oai-famrow", ""); famRow.style.display = "none";
      famRow.innerHTML = '<label for="oai_family">Model family</label><select id="oai_family"></select>';
      sel.parentNode.insertAdjacentElement("afterend", famRow); // after the Provider field (its <div> or <label>)
      famRow.querySelector("select").addEventListener("change", famChange); }
    function famSel() { return famRow ? famRow.querySelector("select") : null; }
    function fire() { var ev; try { ev = new Event("change", { bubbles: true }); } catch (x) { ev = document.createEvent("Event"); ev.initEvent("change", true, true); } sel.dispatchEvent(ev); }
    function switchTo(v) { if (!sel || sel.value === v) return false; sel.value = v; fire(); return true; }
    // a family change re-runs the page's own provider handler (its hint line names the family too)
    function famChange() { var f = famSel().value, n = sel ? sel.value : "";
      if (n === "databricks") famD = f;
      else { famF = f; if (f !== "claude") azFam = f; var t = f === "claude" && hasFoundry() ? "foundry" : "azureopenai"; if (sel.value !== t) sel.value = t; }
      fire(); if (o.dirty) o.dirty(); }
    function hasFoundry() { return !!(sel && sel.querySelector('option[value="foundry"]')); }
    // per-family details: role, example names, typical prices, PDF option
    function famApply(n) { var d = root.querySelector('[data-oai="' + n + '"]'); if (!d) return; var f = n === "databricks" ? famD : famF, ok = presetOK(n), pr = PROV[n].prices;
      var eg = ok && pr ? pr.rows.map(function (r) { return r[0]; }) : n === "azureopenai" && f === "openai" ? PROV[n].eg : ["", "", ""];
      TIERS.forEach(function (t, i) { q("oai_" + n + "_" + t[0] + "_model").placeholder = eg[i] ? "e.g. " + eg[i] : n === "databricks" ? "serving endpoint name" : "deployment name"; });
      d.querySelectorAll("[data-oai-pricerow]").forEach(function (x) { x.hidden = !ok; }); d.querySelectorAll("[data-oai-noprices]").forEach(function (x) { x.hidden = ok; });
      d.querySelectorAll("[data-oai-role]").forEach(function (x) { x.textContent = f === "openai" ? "Cognitive Services OpenAI User" : "Cognitive Services User"; });
      d.querySelectorAll("[data-oai-pdfrow]").forEach(function (x) { x.hidden = f === "claude"; }); }
    // show(name) - the who-bills line for every choice (Claude and Foundry included), the settings block for the ones handled here
    function show(name, processIn) { bind();
      if (name === "foundry" && famF !== "claude" && switchTo("azureopenai")) return; // "Microsoft Foundry" picked again: back to the family last used
      if (name === "azureopenai" && famF === "claude" && hasFoundry() && switchTo("foundry")) return;
      var g = name === "databricks" ? "databricks" : name === "foundry" || name === "azureopenai" ? "foundry" : "";
      if (famRow) { famRow.style.display = g ? "" : "none"; var fs = famSel();
        if (g && fs.getAttribute("data-g") !== g) { fs.innerHTML = FAM[g].map(function (x) { return '<option value="' + x[0] + '">' + e(x[1]) + '</option>'; }).join(""); fs.setAttribute("data-g", g); }
        if (g) fs.value = g === "databricks" ? famD : name === "foundry" ? "claude" : famF; }
      if (g === "foundry" && name === "azureopenai") famApply("azureopenai"); if (g === "databricks") famApply("databricks"); root.querySelectorAll("[data-oai]").forEach(function (d) { d.hidden = d.getAttribute("data-oai") !== name; });
      root.querySelectorAll("[data-oai-us]").forEach(function (d) { d.hidden = processIn !== "us"; }); root.querySelector("[data-oai-info]").innerHTML = infoHtml(name);
      root.hidden = !INFO[name]; root.style.display = INFO[name] ? "" : "none"; if (PROV[name] && q("oai_" + name + "_auth") && (name === "azureopenai" || name === "databricks")) signShow(name); }
    function set(prov) { prov = prov || {}; var az = prov.azureopenai || {}; azFam = az.family && az.family !== "claude" ? az.family : "openai";
      famF = prov.name === "foundry" ? "claude" : prov.name === "azureopenai" ? (az.family || "openai") : az.family || "claude"; famD = (prov.databricks || {}).family || "claude";
      Object.keys(PROV).forEach(function (n) { var s = prov[n] || {}, m = s.models || {};
      TIERS.forEach(function (t) { var x = m[t[0]] || {}; q("oai_" + n + "_" + t[0] + "_model").value = typeof x === "string" ? x : (x.model || ""); q("oai_" + n + "_" + t[0] + "_in").value = x.in || x.in === 0 ? x.in : ""; q("oai_" + n + "_" + t[0] + "_out").value = x.out || x.out === 0 ? x.out : ""; });
      q("oai_" + n + "_reasoning").value = s.reasoning || ""; q("oai_" + n + "_us").checked = !!s.usConfirmed;
      if (n === "custom") { q("oai_custom_preset").value = s.preset || ""; q("oai_custom_endpoint").value = s.endpoint || ""; q("oai_custom_auth").value = s.auth || "bearer"; q("oai_custom_maxParam").value = s.maxParam || "max_tokens"; q("oai_custom_pdf").checked = !!s.pdf; q("oai_custom_images").checked = s.images !== false; }
      if (n === "azureopenai") { q("oai_azureopenai_endpoint").value = s.endpoint || ""; q("oai_azureopenai_auth").value = s.auth === "api-key" ? "api-key" : "entra"; q("oai_azureopenai_pdf").checked = !!s.pdf; signShow(n); }
      if (n === "databricks") { q("oai_databricks_endpoint").value = s.endpoint || ""; q("oai_databricks_auth").value = s.auth === "entra" ? "entra" : "bearer"; q("oai_databricks_dbu").value = s.dbu || ""; signShow(n); } }); }
    function get() { var out = {}; Object.keys(PROV).forEach(function (n) { var models = {}, any = false;
      TIERS.forEach(function (t) { var mv = q("oai_" + n + "_" + t[0] + "_model").value.trim(); if (mv) any = true; models[t[0]] = { model: mv, in: Number(q("oai_" + n + "_" + t[0] + "_in").value) || 0, out: Number(q("oai_" + n + "_" + t[0] + "_out").value) || 0 }; });
      var s = { models: models, reasoning: q("oai_" + n + "_reasoning").value, usConfirmed: q("oai_" + n + "_us").checked };
      if (n === "custom") { s.preset = q("oai_custom_preset").value; s.endpoint = q("oai_custom_endpoint").value.trim().replace(/\/+$/, "").replace(/\/chat\/completions$/i, ""); s.auth = q("oai_custom_auth").value; s.maxParam = q("oai_custom_maxParam").value; s.pdf = q("oai_custom_pdf").checked; s.images = q("oai_custom_images").checked; if (s.endpoint) any = true; }
      if (n === "azureopenai") { s.endpoint = q("oai_azureopenai_endpoint").value.trim().replace(/\/+$/, ""); s.auth = q("oai_azureopenai_auth").value; s.pdf = q("oai_azureopenai_pdf").checked; if (s.endpoint) any = true;
        s.family = famF === "claude" && hasFoundry() ? azFam : famF; }
      if (n === "databricks") { s.endpoint = q("oai_databricks_endpoint").value.trim().replace(/\/+$/, ""); s.auth = q("oai_databricks_auth").value; s.dbu = Number(q("oai_databricks_dbu").value) || 0; s.family = famD; if (s.endpoint) any = true; }
      if (any) out[n] = s; }); return out; }
    function validate(name, processIn) { if (!PROV[name]) return ""; var s = get()[name]; if (!s) return PROV[name].label + ": enter at least one model name.";
      var names = TIERS.map(function (t) { return s.models[t[0]].model; }).filter(Boolean); if (!names.length) return PROV[name].label + ": enter at least one model name.";
      for (var i = 0; i < names.length; i++) if (!/^[A-Za-z0-9._:\/@+-]{1,120}$/.test(names[i])) return "Not a valid model name: " + names[i];
      if (name === "custom" && (!/^https:\/\/[A-Za-z0-9.-]+(:\d{2,5})?(\/[A-Za-z0-9._~\/-]*)?$/.test(s.endpoint))) return "Endpoint must be an https address like https://host/v1 (replace the <placeholder> parts of a preset).";
      if (name === "azureopenai" && !/^https:\/\/[A-Za-z0-9-]+\.(openai\.azure\.com|services\.ai\.azure\.com|cognitiveservices\.azure\.com)(\/[A-Za-z0-9._~\/-]*)?$/i.test(s.endpoint)) return "Microsoft Foundry endpoint must look like https://your-resource.services.ai.azure.com";
      if (name === "databricks" && !/^https:\/\/[A-Za-z0-9.-]+\.(azuredatabricks\.net|cloud\.databricks\.com|gcp\.databricks\.com)(\/[A-Za-z0-9._~\/-]*)?$/i.test(s.endpoint)) return "Databricks workspace URL must look like https://adb-1234567890123456.7.azuredatabricks.net";
      if (own && s.auth === "entra") return PROV[name].label + ": managed identity works only when Ask Tut runs in your own Azure. Choose the key option.";
      if (processIn === "us" && !s.usConfirmed) return PROV[name].label + " + US only: tick the US-only confirmation, or set Process in to Global.";
      return ""; }
    function label(n) { return n === "azureopenai" ? "Microsoft Foundry · " + famLabel("foundry", famF) : n === "databricks" ? "Databricks · " + famLabel("databricks", famD) : PROV[n] ? PROV[n].label
      : n === "foundry" ? "Microsoft Foundry · Claude (Anthropic)" : n === "claude" ? "Claude (Anthropic API)" : ""; }
    function options() { return GROUPS.map(function (g) { return '<optgroup label="' + e(g[0]) + '">' + g[1].map(function (x) { return '<option value="' + x[0] + '"' + (x[2] ? " hidden disabled" : "") + '>' + e(x[1]) + '</option>'; }).join("") + '</optgroup>'; }).join(""); }
    // rows for the "Check connection" result table
    function result(j, esc) { var t = j.test || {}, m = j.models || {}; esc = esc || e; var mi = j.signIn === "managed identity";
      return "<tr><td>" + (mi ? "Sign-in" : "API key" + (j.ownAccount ? "" : " (" + esc(j.keyName) + ")")) + "</td><td>" + (mi ? (j.keyConfigured ? "managed identity" : "managed identity is not turned on for the Ask Tut app") : j.keyConfigured ? "yes" : j.ownAccount ? "NO — paste it above" : "NO — add it in Key Vault") + "</td></tr>" + (j.family ? "<tr><td>Model family</td><td>" + esc(famLabel(j.provider === "databricks" ? "databricks" : "foundry", j.family) || j.family) + "</td></tr>" : "") + (j.endpoint ? "<tr><td>Endpoint</td><td>" + esc(j.endpoint) + "</td></tr>" : "") +
        "<tr><td>Models</td><td>" + TIERS.map(function (x) { return x[1] + ": " + esc(m[x[0]] || "—"); }).join(" · ") + "</td></tr>"; }
    return { mount: mount, show: show, set: set, get: get, validate: validate, label: label, result: result, keys: keys, options: options, info: function (n) { return INFO[infoKey(n)] || null; }, has: function (n) { return !!PROV[n]; },
      family: function (n) { return n === "databricks" ? famD : n === "foundry" ? "claude" : famF; } };
  })();
