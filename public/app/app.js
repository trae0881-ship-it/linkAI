'use strict';
/* Link2Social AI dashboard. Security note: all dynamic text is rendered via
   textContent (see h()); innerHTML is never used, because website- and
   AI-derived content is untrusted. */

const API = '/api/v1';
const S = { user: null, ws: null, workspaces: [], brands: [], brand: null, route: 'analyze', ctx: {} };

// ---------- helpers ----------
function h(tag, attrs, ...kids) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs || {})) {
    if (k === 'class') el.className = v;
    else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
    else if (v !== false && v != null) el.setAttribute(k, v === true ? '' : v);
  }
  for (const kid of kids.flat()) {
    if (kid == null || kid === false) continue;
    el.append(kid.nodeType ? kid : document.createTextNode(String(kid)));
  }
  return el;
}
const cookie = n => decodeURIComponent((document.cookie.split('; ').find(c => c.startsWith(n + '=')) || '').split('=').slice(1).join('='));
async function api(method, path, body) {
  const opts = { method, credentials: 'include', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
  if (method !== 'GET') { opts.headers['X-XSRF-TOKEN'] = cookie('XSRF-TOKEN'); }
  if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  const res = await fetch(API + path, opts);
  if (res.status === 204) return null;
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const msg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || `Request failed (${res.status})`);
    const e = new Error(msg); e.status = res.status; throw e;
  }
  return data;
}
const csrf = () => fetch('/sanctum/csrf-cookie', { credentials: 'include' });
function toast(msg) { const t = h('div', { class: 'toast' }, msg); document.body.append(t); setTimeout(() => t.remove(), 3500); }
const wsPath = p => `/workspaces/${S.ws.id}${p}`;
const brandPath = p => wsPath(`/brands/${S.brand.id}${p}`);
const statusBadge = s => h('span', { class: 'badge ' + ({ analyzed: 'ok', ready: 'ok', generated: 'ok', approved: 'ok', published: 'ok', selected: 'ok', failed: 'bad', rejected: 'bad', VERIFIED_FROM_SOURCE: 'ok', REQUIRES_USER_CONFIRMATION: 'warn', UNVERIFIED: 'bad', SUPPORTED_BY_PROVIDED_DATA: 'ok', pending_review: 'warn' }[s] || '') }, s);
const list = items => h('ul', {}, (items || []).map(i => h('li', {}, i)));
async function guard(fn, box) {
  try { return await fn(); } catch (e) { if (box) box.replaceChildren(h('div', { class: 'err' }, e.message)); else toast(e.message); }
}

// ---------- auth ----------
function authView() {
  let mode = 'login';
  const box = h('div', { class: 'card auth' });
  const render = () => {
    const err = h('div', { class: 'err' });
    const f = {};
    const field = (k, label, type = 'text') => { f[k] = h('input', { type, autocomplete: k === 'password' ? 'current-password' : 'off' }); return [h('label', {}, label), f[k]]; };
    box.replaceChildren(
      h('h2', {}, 'Link2Social AI'), h('p', { class: 'sub' }, mode === 'login' ? 'Sign in' : 'Create account'),
      mode === 'register' ? field('name', 'Name') : null, field('email', 'Email', 'email'), field('password', 'Password', 'password'),
      mode === 'register' ? field('password_confirmation', 'Confirm password', 'password') : null, err,
      h('div', { class: 'row', style: 'margin-top:12px' },
        h('button', { onclick: async () => {
          const body = Object.fromEntries(Object.entries(f).map(([k, el]) => [k, el.value]));
          await guard(async () => {
            await csrf();
            if (mode === 'register') { await api('POST', '/register', body); }
            S.user = (await api('POST', '/login', { email: body.email, password: body.password })).data;
            await boot();
          }, err);
        } }, mode === 'login' ? 'Sign in' : 'Register'),
        h('button', { class: 'ghost', onclick: () => { mode = mode === 'login' ? 'register' : 'login'; render(); } }, mode === 'login' ? 'Need an account?' : 'Have an account?')));
  };
  render();
  return box;
}

// ---------- shell ----------
const NAV = [['Workflow'], ['analyze', 'URL analysis'], ['intel', 'Brand intelligence'], ['concepts', 'Trends & concepts'], ['create', 'Media generation'], ['content', 'Editor & approval'],
  ['Distribute'], ['accounts', 'Social accounts'], ['calendar', 'Publishing status'], ['analytics', 'Analytics'],
  ['Workspace'], ['settings', 'Settings & members'], ['billing', 'Billing (Razorpay)'], ['audit', 'Audit & security']];
const ADMIN_NAV = [['Site admin'], ['admin_pages', 'Website pages'], ['admin_settings', 'Site details'], ['admin_messages', 'Contact messages'],
  ['admin_plans', 'Plans & pricing'], ['admin_gateway', 'Payment gateway'], ['admin_payments', 'All payments']];
function shell() {
  const view = h('div', { id: 'view' });
  const nav = h('nav', {}, h('h1', {}, 'Link2Social AI'), [...NAV, ...(S.user.is_platform_admin ? ADMIN_NAV : [])].map(n => n.length === 1 ? h('div', { class: 'sec' }, n[0]) :
    h('a', { class: S.route === n[0] ? 'active' : '', onclick: () => { S.route = n[0]; render(); } }, n[1])));
  const wsSel = h('select', { onchange: async e => { S.ws = S.workspaces.find(w => w.id === e.target.value); S.brand = null; await loadBrands(); render(); } },
    S.workspaces.map(w => h('option', { value: w.id, selected: S.ws && w.id === S.ws.id }, `${w.name} (${w.role})`)));
  const brSel = h('select', { onchange: e => { S.brand = S.brands.find(b => b.id === e.target.value); S.ctx = {}; render(); } },
    h('option', { value: '' }, S.brands.length ? 'Select brand…' : 'No brands yet'), S.brands.map(b => h('option', { value: b.id, selected: S.brand && b.id === S.brand.id }, b.name)));
  const top = h('div', { class: 'top' }, wsSel, brSel,
    h('button', { class: 'ghost', onclick: newBrand }, '+ Brand'), h('button', { class: 'ghost', onclick: newWorkspace }, '+ Workspace'),
    h('span', { class: 'grow' }), h('span', { class: 'muted' }, S.user.name),
    h('button', { class: 'ghost', onclick: async () => { await guard(() => api('POST', '/logout')); S.user = null; render(); } }, 'Sign out'));
  return { root: h('div', { class: 'shell' }, nav, h('main', {}, top, view)), view };
}
async function newWorkspace() { const name = prompt('Workspace name'); if (!name) return; await guard(async () => { await api('POST', '/workspaces', { name }); await boot(); }); }
async function newBrand() {
  const name = prompt('Brand name'); if (!name) return;
  const website_url = prompt('Website URL (optional)') || '';
  await guard(async () => { const r = await api('POST', wsPath('/brands'), { name, website_url: website_url || undefined }); await loadBrands(); S.brand = S.brands.find(b => b.id === r.data.id); render(); });
}
async function loadBrands() { S.brands = S.ws ? (await api('GET', wsPath('/brands'))).data : []; if (S.brand) S.brand = S.brands.find(b => b.id === S.brand.id) || null; }
async function boot() {
  S.workspaces = (await api('GET', '/workspaces')).data;
  S.ws = S.workspaces.find(w => S.ws && w.id === S.ws.id) || S.workspaces[0] || null;
  await loadBrands(); render();
}
const needs = (view, what) => { view.replaceChildren(h('div', { class: 'card muted' }, what)); };
async function render() {
  const app = document.getElementById('app');
  if (!S.user) { app.replaceChildren(authView()); return; }
  const { root, view } = shell(); app.replaceChildren(root);
  if (!S.ws) return needs(view, 'Create a workspace to begin.');
  const oauth = new URLSearchParams(location.search);
  if (oauth.get('code') && oauth.get('state')) return accountsCallback(view, oauth);
  if (!S.brand && !['settings', 'audit', 'accounts', 'billing'].includes(S.route) && !S.route.startsWith('admin_')) return needs(view, 'Create or select a brand to begin.');
  await (SCREENS[S.route] || SCREENS.analyze)(view);
}
const head = (t, s) => [h('h2', {}, t), h('p', { class: 'sub' }, s)];

// ---------- screens ----------
const SCREENS = {};
const IN_PROGRESS = ['pending', 'validating', 'fetching', 'sanitizing', 'analyzing'];
let pollTimer = null;
const poll = fn => { clearTimeout(pollTimer); pollTimer = setTimeout(fn, 3500); };

SCREENS.analyze = async view => {
  const err = h('div', { class: 'err' }), url = h('input', { type: 'url', placeholder: 'https://example.com', class: 'grow' });
  const listBox = h('div');
  const load = async () => {
    const rows = (await api('GET', brandPath('/sources'))).data;
    listBox.replaceChildren(h('table', {}, h('tr', {}, ['URL', 'Status', 'Note'].map(x => h('th', {}, x))),
      rows.map(s => h('tr', {}, h('td', {}, s.submitted_url), h('td', {}, statusBadge(s.status)), h('td', { class: 'muted' }, s.rejection_reason || '')))));
    if (rows.some(s => IN_PROGRESS.includes(s.status)) && S.route === 'analyze') poll(load);
  };
  view.replaceChildren(...head('URL analysis', 'Submit a website. It is validated (SSRF-safe), fetched, sanitized and analyzed in the background.'),
    h('div', { class: 'card' }, h('div', { class: 'row' }, url, h('button', { onclick: () => guard(async () => {
      await api('POST', wsPath('/sources'), { brand_id: S.brand.id, url: url.value }); url.value = ''; toast('Analysis started'); await load();
    }, err) }, 'Analyze')), err), h('div', { class: 'card' }, listBox));
  await load();
};

async function analyzedSources() { return (await api('GET', brandPath('/sources'))).data.filter(s => s.status === 'analyzed' && s.business_profile && s.business_profile.id); }

SCREENS.intel = async view => {
  const srcs = await analyzedSources();
  if (!srcs.length) return view.replaceChildren(...head('Brand intelligence', ''), h('div', { class: 'card muted' }, 'No analyzed source yet. Run a URL analysis first.'));
  const p = srcs[0].business_profile;
  const sect = (t, body) => h('div', { class: 'card' }, h('h3', {}, t), body);
  view.replaceChildren(...head('Brand intelligence', srcs[0].normalized_url || srcs[0].submitted_url),
    h('div', { class: 'grid' },
      sect('Brand', h('div', {}, h('b', {}, p.brand?.name), h('p', {}, p.brand?.description), h('div', { class: 'muted' }, 'Tone'), list(p.brand?.tone))),
      sect('Offer', h('div', {}, h('p', {}, p.offer?.value_proposition), h('div', { class: 'muted' }, 'Products / services'), list(p.offer?.products_or_services))),
      sect('Audience', h('div', {}, h('div', { class: 'muted' }, 'Segments'), list(p.audience?.segments), h('div', { class: 'muted' }, 'Pain points'), list(p.audience?.pain_points))),
      sect('SEO', h('div', {}, h('div', { class: 'muted' }, 'Keywords'), list(p.seo?.primary_keywords)))),
    sect('Claims & provenance', h('table', {}, (p.claims || []).map(c => h('tr', {}, h('td', {}, c.claim), h('td', {}, statusBadge(c.status)), h('td', { class: 'muted' }, c.evidence))))));
};

SCREENS.concepts = async view => {
  const srcs = await analyzedSources();
  if (!srcs.length) return view.replaceChildren(...head('Trends & concepts', ''), h('div', { class: 'card muted' }, 'Analyze a URL first.'));
  const src = srcs[0], box = h('div', { class: 'grid' });
  const load = async () => {
    const cs = (await api('GET', brandPath(`/business-profiles/${src.business_profile.id}/concepts`))).data;
    if (!cs.length) return box.replaceChildren(h('div', { class: 'card muted' }, 'No concepts yet — generate them above (takes a moment; refresh).'));
    box.replaceChildren(...cs.map(c => h('div', { class: 'card' }, h('div', { class: 'row' }, h('b', { class: 'grow' }, c.title), statusBadge(c.status)),
      h('p', {}, c.reasoning),
      h('div', { class: 'muted' }, 'Trends used'), c.trend_references?.length ? list(c.trend_references) : h('div', { class: 'muted' }, 'None (no trends provider configured or no match)'),
      h('div', { class: 'muted' }, 'Pros'), list(c.pros), h('div', { class: 'muted' }, 'Cons'), list(c.cons), h('div', { class: 'muted' }, 'Risks'), list(c.risks),
      h('div', { class: 'muted' }, 'Platform suitability'), h('table', {}, Object.entries(c.platform_suitability || {}).map(([k, v]) => h('tr', {}, h('td', {}, k), h('td', {}, `${v.score}/10`), h('td', { class: 'muted' }, v.note)))),
      h('div', { style: 'margin-top:10px' }, h('button', { disabled: c.status === 'selected', onclick: () => guard(async () => { await api('POST', wsPath(`/brands/${S.brand.id}/concepts/${c.id}/select`)); toast('Concept selected'); load(); }) }, c.status === 'selected' ? 'Selected' : 'Select this concept')))));
  };
  view.replaceChildren(...head('Trends & concepts', 'Concepts combine your brand profile with current trend signals. Trend data appears per concept.'),
    h('div', { class: 'card row' }, h('button', { onclick: () => guard(async () => { await api('POST', wsPath(`/brands/${S.brand.id}/sources/${src.id}/generate-concepts`)); toast('Generating…'); setTimeout(load, 6000); }) }, 'Generate concepts'),
      h('button', { class: 'ghost', onclick: load }, 'Refresh')), box);
  await load();
};

SCREENS.create = async view => {
  const srcs = await analyzedSources(); const err = h('div', { class: 'err' });
  if (!srcs.length) return view.replaceChildren(...head('Media generation', ''), h('div', { class: 'card muted' }, 'Analyze a URL and select a concept first.'));
  const cs = (await api('GET', brandPath(`/business-profiles/${srcs[0].business_profile.id}/concepts`))).data.filter(c => c.status === 'selected');
  if (!cs.length) return view.replaceChildren(...head('Media generation', ''), h('div', { class: 'card muted' }, 'Select a concept first.'));
  const c = cs[0], qty = h('input', { type: 'number', min: 1, max: 10, value: 3 }), fmt = h('select', {}, ['image', 'video', 'carousel'].map(x => h('option', { value: x }, x)));
  view.replaceChildren(...head('Media generation', `Selected concept: ${c.title}`), h('div', { class: 'card' },
    h('label', {}, 'How many?'), qty, h('label', {}, 'Format'), fmt, err,
    h('p', { class: 'muted' }, 'Video requires a configured video provider; images use the configured image provider.'),
    h('button', { onclick: () => guard(async () => { await api('POST', wsPath(`/brands/${S.brand.id}/concepts/${c.id}/content`), { quantity: +qty.value, media_format: fmt.value }); toast('Prompts built; generating media'); S.route = 'content'; render(); }, err) }, 'Generate prompts & media')));
};

const PLATFORMS = ['instagram', 'linkedin', 'pinterest', 'x', 'tiktok'];
SCREENS.content = async view => {
  const box = h('div');
  const load = async () => {
    const items = (await api('GET', brandPath('/content'))).data;
    box.replaceChildren(...(items.length ? items.map(editor) : [h('div', { class: 'card muted' }, 'No content yet.')]));
    if (items.some(i => i.status === 'generating') && S.route === 'content') poll(load);
  };
  view.replaceChildren(...head('Editor & approval', 'Review and edit everything. Any edit after approval clears the approval; publishing is blocked unless the approved hash still matches.'), box);
  await load();
  function editor(c) {
    const prompts = h('textarea', {}, (c.generation_prompts || []).join('\n---\n'));
    const copy = c.platform_copy || {}, fields = {};
    const copyUi = PLATFORMS.map(p => { const v = copy[p]; if (!v) return null;
      fields[p] = { caption: h('textarea', {}, v.caption), hashtags: h('input', { value: (v.hashtags || []).join(', ') }), cta: h('input', { value: v.cta }) };
      return h('div', { class: 'card' }, h('b', {}, p), h('label', {}, 'Caption'), fields[p].caption, h('label', {}, 'Hashtags (comma separated)'), fields[p].hashtags, h('label', {}, 'CTA'), fields[p].cta,
        v.tracking_url ? h('div', { class: 'muted' }, 'Tracking: ' + v.tracking_url) : null); });
    const save = async () => {
      const platform_copy = {};
      for (const p of Object.keys(fields)) platform_copy[p] = { ...copy[p], caption: fields[p].caption.value, cta: fields[p].cta.value, hashtags: fields[p].hashtags.value.split(',').map(x => x.trim().replace(/^#/, '')).filter(Boolean) };
      await api('PATCH', brandPath(`/content/${c.id}`), { generation_prompts: prompts.value.split(/\n---\n/).map(x => x.trim()).filter(Boolean), platform_copy: Object.keys(platform_copy).length ? platform_copy : undefined });
    };
    const act = (label, fn, cls) => h('button', { class: cls || '', onclick: () => guard(async () => { await fn(); await load(); }) }, label);
    return h('div', { class: 'card' },
      h('div', { class: 'row' }, h('b', { class: 'grow' }, `${c.media_format} × ${c.requested_quantity}`), statusBadge(c.status)),
      h('div', { class: 'grid' }, (c.media_assets || []).map(a => h('div', {}, statusBadge(a.status), a.url && a.type === 'image' ? h('img', { class: 'thumb', src: a.url, alt: 'Generated media' }) : null))),
      h('label', {}, 'Generation prompts (separate with a line containing ---)'), prompts, h('div', { class: 'grid', style: 'margin-top:10px' }, copyUi),
      h('div', { class: 'row', style: 'margin-top:10px' },
        act('Save edits', async () => { await save(); toast('Saved'); }, 'ghost'),
        act('Generate captions', async () => { await api('POST', brandPath(`/content/${c.id}/captions`)); toast('Captions generated'); }, 'ghost'),
        act('Approve', async () => { await api('POST', brandPath(`/content/${c.id}/approve`)); toast('Approved'); }),
        c.status === 'approved' ? publishUi(c) : null));
  }
  function publishUi(c) {
    const when = h('input', { type: 'datetime-local' }), boxes = h('div'), err = h('div', { class: 'err' });
    api('GET', wsPath('/social-accounts')).then(r => boxes.replaceChildren(...r.data.filter(a => a.is_active).map(a => h('label', {}, h('input', { type: 'checkbox', value: a.id, style: 'width:auto' }), ` ${a.external_account_name} (${a.platform})`))));
    return h('div', { class: 'card', style: 'width:100%' }, h('b', {}, 'Publish approved content'), boxes, h('label', {}, 'Schedule (optional)'), when, err,
      h('button', { onclick: () => guard(async () => {
        const ids = [...boxes.querySelectorAll('input:checked')].map(i => i.value);
        await api('POST', brandPath(`/content/${c.id}/publish`), { social_account_ids: ids, scheduled_at: when.value ? new Date(when.value).toISOString() : undefined });
        toast('Publish queued'); S.route = 'calendar'; render();
      }, err) }, 'Publish'));
  }
};

SCREENS.accounts = async view => {
  const err = h('div', { class: 'err' });
  const accts = (await api('GET', wsPath('/social-accounts'))).data;
  view.replaceChildren(...head('Social accounts', 'Connect once and pick any/all pages and profiles the login can manage. Tokens are encrypted server-side and never shown here.'),
    h('div', { class: 'card' }, h('div', { class: 'row' },
      h('button', { onclick: () => guard(async () => {
        sessionStorage.setItem('l2s_ws', S.ws.id);
        const r = await api('POST', wsPath('/social/meta/connect')); location.href = r.authorization_url;
      }, err) }, 'Connect Facebook Pages + Instagram (Meta)'),
      h('span', { class: 'muted' }, 'LinkedIn, Pinterest and X are documented integration points and not yet implemented.')), err),
    h('div', { class: 'card' }, h('table', {}, h('tr', {}, ['Account', 'Platform', 'Status'].map(x => h('th', {}, x))),
      accts.map(a => h('tr', {}, h('td', {}, a.external_account_name), h('td', {}, a.platform), h('td', {}, a.is_active ? (a.token_expired ? statusBadge('failed') : statusBadge('ready')) : statusBadge('inactive')),
        h('td', {}, a.is_active ? h('button', { class: 'danger', onclick: () => guard(async () => { await api('DELETE', wsPath(`/social-accounts/${a.id}`)); render(); }) }, 'Disconnect') : null))))));
};
async function accountsCallback(view, q) {
  const wsId = sessionStorage.getItem('l2s_ws'); if (wsId) S.ws = S.workspaces.find(w => w.id === wsId) || S.ws;
  const err = h('div', { class: 'err' });
  view.replaceChildren(...head('Choose accounts to connect', 'Every page/profile this login can manage is listed. Select any or all.'), err);
  await guard(async () => {
    const r = await api('GET', `/social/meta/callback?code=${encodeURIComponent(q.get('code'))}&state=${encodeURIComponent(q.get('state'))}`);
    history.replaceState({}, '', location.pathname);
    const boxes = r.accounts.map(a => h('label', {}, h('input', { type: 'checkbox', checked: true, value: a.external_account_id, style: 'width:auto' }), ` ${a.name} — ${a.type}`));
    view.append(h('div', { class: 'card' }, boxes, h('div', { class: 'row', style: 'margin-top:10px' },
      h('button', { onclick: () => guard(async () => {
        const ids = boxes.map(l => l.querySelector('input')).filter(i => i.checked).map(i => i.value);
        await api('POST', wsPath('/social-accounts/activate'), { selection_token: r.selection_token, external_account_ids: ids });
        toast(`${ids.length} account(s) connected`); S.route = 'accounts'; render();
      }, err) }, 'Connect selected'))));
  }, err);
}

SCREENS.calendar = async view => {
  const items = (await api('GET', brandPath('/content'))).data, rows = [];
  for (const c of items) { const pubs = (await api('GET', brandPath(`/content/${c.id}/publications`))).data; pubs.forEach(p => rows.push(p)); }
  rows.sort((a, b) => String(b.scheduled_at || b.published_at).localeCompare(String(a.scheduled_at || a.published_at)));
  view.replaceChildren(...head('Publishing status', 'Scheduled, in-flight, published and failed posts.'),
    h('div', { class: 'card' }, rows.length ? h('table', {}, h('tr', {}, ['Platform', 'Status', 'Scheduled', 'Published', 'Post', 'Error'].map(x => h('th', {}, x))),
      rows.map(p => h('tr', {}, h('td', {}, p.platform), h('td', {}, statusBadge(p.status)), h('td', {}, p.scheduled_at || '—'), h('td', {}, p.published_at || '—'),
        h('td', {}, p.external_post_url && /^https:\/\//.test(p.external_post_url) ? h('a', { href: p.external_post_url, target: '_blank', rel: 'noopener noreferrer' }, 'open') : '—'), h('td', { class: 'muted' }, p.error_message || ''))))
      : h('span', { class: 'muted' }, 'Nothing published or scheduled yet.')));
  if (rows.some(p => ['scheduled', 'publishing'].includes(p.status)) && S.route === 'calendar') poll(() => render());
};

SCREENS.analytics = async view => {
  const d = await api('GET', brandPath('/analytics'));
  const tbl = obj => Object.keys(obj || {}).length ? h('table', {}, Object.entries(obj).map(([k, m]) => h('tr', {}, h('td', {}, k), h('td', {}, Object.entries(m).map(([mk, v]) => `${mk}: ${v}`).join(' · '))))) : h('span', { class: 'muted' }, 'No data yet (metrics sync runs every 6 hours for published posts).');
  view.replaceChildren(...head('Analytics', 'Latest metrics per published post, last 30 days.'), h('div', { class: 'card' }, h('h3', {}, 'By platform'), tbl(d.by_platform)), h('div', { class: 'card' }, h('h3', {}, 'By content'), tbl(d.by_content)));
};

SCREENS.settings = async view => {
  const members = (await api('GET', wsPath('/members'))).data, err = h('div', { class: 'err' });
  const email = h('input', { type: 'email', placeholder: 'colleague@company.com' }), role = h('select', {}, ['VIEWER', 'APPROVER', 'EDITOR', 'ADMIN'].map(r => h('option', { value: r }, r)));
  view.replaceChildren(...head('Settings & members', `Workspace: ${S.ws.name} · your role: ${S.ws.role}`),
    h('div', { class: 'card' }, h('table', {}, members.map(m => h('tr', {}, h('td', {}, m.name), h('td', {}, m.email), h('td', {}, statusBadge(m.role)))))),
    h('div', { class: 'card' }, h('h3', {}, 'Invite member'), h('div', { class: 'row' }, h('div', { class: 'grow' }, email), role,
      h('button', { onclick: () => guard(async () => { await api('POST', wsPath('/members'), { email: email.value, role: role.value }); toast('Invitation sent'); render(); }, err) }, 'Invite')), err,
      h('p', { class: 'muted' }, 'Roles: VIEWER reads · EDITOR creates/edits · APPROVER approves & publishes · ADMIN manages members and accounts.')));
};


let rzpLoading = null;
const loadCheckout = () => rzpLoading || (rzpLoading = new Promise((ok, no) => {
  const el = h('script', { src: 'https://checkout.razorpay.com/v1/checkout.js' });
  el.onload = ok; el.onerror = () => { rzpLoading = null; no(new Error('Could not load Razorpay Checkout.')); }; document.head.append(el);
}));
const rupees = paise => '₹' + (paise / 100).toLocaleString('en-IN');

SCREENS.billing = async view => {
  const err = h('div', { class: 'err' });
  view.replaceChildren(...head('Billing', 'Pay securely with Razorpay (UPI, cards, netbanking, wallets). Prices are set on the server.'), err);
  await guard(async () => {
    const [info, pays] = await Promise.all([api('GET', wsPath('/billing/plans')), api('GET', wsPath('/billing/payments'))]);
    const pay = plan => guard(async () => {
      const o = await api('POST', wsPath('/billing/orders'), { plan: plan.key });
      await loadCheckout();
      const rzp = new window.Razorpay({
        key: o.key_id, amount: o.amount, currency: o.currency, order_id: o.order_id,
        name: 'Link2Social AI', description: `${plan.name} plan`, prefill: { name: S.user.name, email: S.user.email },
        handler: r => guard(async () => {
          await api('POST', wsPath('/billing/verify'), { razorpay_order_id: r.razorpay_order_id, razorpay_payment_id: r.razorpay_payment_id, razorpay_signature: r.razorpay_signature });
          toast('Payment successful'); S.workspaces = (await api('GET', '/workspaces')).data; S.ws = S.workspaces.find(w => w.id === S.ws.id); render();
        }),
        modal: { ondismiss: () => toast('Payment cancelled') },
      });
      rzp.open();
    });
    view.append(
      h('div', { class: 'card' }, h('b', {}, 'Current plan: '), info.current_plan ? `${info.current_plan} (until ${new Date(info.plan_expires_at).toLocaleDateString()})` : 'none',
        info.configured ? null : h('div', { class: 'err' }, 'Razorpay keys are not configured on the server yet.')),
      h('div', { class: 'grid' }, info.plans.map(p => h('div', { class: 'card' }, h('h3', {}, p.name), h('div', {}, `${rupees(p.amount_paise)} / ${p.days} days`),
        h('div', { style: 'margin-top:10px' }, h('button', { disabled: !info.configured, onclick: () => pay(p) }, 'Pay with Razorpay'))))),
      h('div', { class: 'card' }, h('h3', {}, 'Payment history'), pays.data.length ? h('table', {}, pays.data.map(x => h('tr', {}, h('td', {}, x.created_at), h('td', {}, x.plan), h('td', {}, rupees(x.amount_paise)), h('td', {}, statusBadge(x.status === 'created' ? 'pending_review' : x.status))))) : h('span', { class: 'muted' }, 'No payments yet.')));
  }, err);
};

SCREENS.audit = async view => {
  const err = h('div', { class: 'err' });
  view.replaceChildren(...head('Audit & security', 'Who did what, plus security events (blocked SSRF attempts, denied access, hash mismatches).'), err);
  await guard(async () => {
    const [a, s] = await Promise.all([api('GET', wsPath('/audit-logs')), api('GET', wsPath('/security-events'))]);
    view.append(h('div', { class: 'card' }, h('h3', {}, 'Security events'), h('table', {}, s.data.map(e => h('tr', {}, h('td', {}, e.created_at), h('td', {}, statusBadge(e.severity === 'critical' ? 'failed' : e.severity === 'warning' ? 'pending_review' : 'ready')), h('td', {}, e.event_type))))),
      h('div', { class: 'card' }, h('h3', {}, 'Audit log'), h('table', {}, a.data.map(e => h('tr', {}, h('td', {}, e.created_at), h('td', {}, e.action), h('td', { class: 'muted' }, e.ip_address || ''))))));
  }, err);
};


// ---------- platform admin (website content, plans, payment gateway) ----------
const rs = paise => '₹' + (paise / 100).toLocaleString('en-IN');
const field = (label, el) => [h('label', {}, label), el];
const dt = v => v ? new Date(v).toLocaleString() : '—';

SCREENS.admin_pages = async view => {
  const err = h('div', { class: 'err' });
  view.replaceChildren(...head('Website pages', 'Edit the public pages. Syntax: "# Heading", "## Subheading", "- list item", blank line = new paragraph. Tokens: {{business_name}} {{contact_email}} {{contact_phone}} {{address}} {{jurisdiction}} {{site_url}}. Text is always escaped, so no HTML is needed.'), err);
  await guard(async () => {
    const pages = (await api('GET', '/admin/pages')).data;
    let cur = S.ctx.adminPage || pages[0].slug;
    const editor = h('div');
    const draw = () => {
      const p = pages.find(x => x.slug === cur);
      const title = h('input', { value: p.title }), meta = h('input', { value: p.meta_description || '', maxlength: 300 });
      const body = h('textarea', { style: 'min-height:340px;font-family:monospace' }, p.body), pub = h('input', { type: 'checkbox', checked: p.is_published, style: 'width:auto' });
      const legal = ['privacy-policy', 'refund-policy', 'terms-and-conditions', 'disclaimer'].includes(cur);
      editor.replaceChildren(h('div', { class: 'card' },
        legal ? h('div', { class: 'badge warn' }, 'Default text is a template. Review it (ideally with a legal advisor) before going live.') : null,
        ...field('Title', title), ...field('Search description (optional)', meta), ...field('Content', body),
        h('label', {}, pub, ' Published'),
        h('div', { class: 'row', style: 'margin-top:12px' },
          h('button', { onclick: () => guard(async () => {
            await api('PUT', `/admin/pages/${cur}`, { title: title.value, body: body.value, meta_description: meta.value || null, is_published: pub.checked });
            Object.assign(p, { title: title.value, body: body.value, meta_description: meta.value, is_published: pub.checked }); toast('Page saved');
          }) }, 'Save page'),
          h('a', { href: cur === 'home' ? '/' : `/${cur}`, target: '_blank', rel: 'noopener' }, 'View live page ↗'))));
    };
    const sel = h('select', { onchange: e => { cur = S.ctx.adminPage = e.target.value; draw(); } }, pages.map(p => h('option', { value: p.slug, selected: p.slug === cur }, `${p.title}${p.is_published ? '' : ' (unpublished)'}`)));
    view.append(h('div', { class: 'card' }, ...field('Page', sel)), editor); draw();
  }, err);
};

SCREENS.admin_settings = async view => {
  const err = h('div', { class: 'err' });
  view.replaceChildren(...head('Site details', 'Shown in the site header/footer, the Contact page, and used in the legal page tokens.'), err);
  await guard(async () => {
    const d = (await api('GET', '/admin/settings')).data;
    const labels = { business_name: 'Business name', tagline: 'Tagline', contact_email: 'Contact email', contact_phone: 'Contact phone', address: 'Address', footer_text: 'Footer text', jurisdiction: 'Governing law / jurisdiction' };
    const f = Object.fromEntries(Object.keys(labels).map(k => [k, h(k === 'address' ? 'textarea' : 'input', k === 'address' ? {} : { value: d[k] || '' }, k === 'address' ? (d[k] || '') : null)]));
    view.append(h('div', { class: 'card' }, Object.entries(labels).map(([k, l]) => field(l, f[k])).flat(),
      h('div', { style: 'margin-top:12px' }, h('button', { onclick: () => guard(async () => { await api('PUT', '/admin/settings', Object.fromEntries(Object.entries(f).map(([k, el]) => [k, el.value]))); toast('Saved'); }) }, 'Save'))));
  }, err);
};

SCREENS.admin_messages = async view => {
  const err = h('div', { class: 'err' });
  view.replaceChildren(...head('Contact messages', 'Messages sent through the public Contact Us form.'), err);
  await guard(async () => {
    const r = await api('GET', '/admin/contact-messages');
    view.append(h('div', { class: 'card' }, r.data.length ? h('table', {}, r.data.map(m => h('tr', {},
      h('td', {}, dt(m.created_at)), h('td', {}, h('b', {}, m.name), h('div', { class: 'muted' }, m.email + (m.phone ? ' · ' + m.phone : ''))), h('td', {}, m.message),
      h('td', {}, h('button', { class: 'ghost', onclick: () => guard(async () => { await api('PATCH', `/admin/contact-messages/${m.id}`, { is_read: !m.is_read }); render(); }) }, m.is_read ? 'Mark unread' : 'Mark read'))))) : h('span', { class: 'muted' }, 'No messages yet.')));
  }, err);
};

SCREENS.admin_plans = async view => {
  const err = h('div', { class: 'err' });
  view.replaceChildren(...head('Plans & pricing', 'Plans shown on the public site and in Billing. A plan key cannot change after creation; deactivate a plan instead of deleting it.'), err);
  await guard(async () => {
    const plans = (await api('GET', '/admin/plans')).data;
    let editing = null;
    const form = h('div');
    const draw = () => {
      const p = editing || { key: '', name: '', description: '', amount_paise: 49900, days: 30, is_active: true, sort_order: 0 };
      const key = h('input', { value: p.key, disabled: !!editing }), name = h('input', { value: p.name }), desc = h('input', { value: p.description || '' });
      const price = h('input', { type: 'number', min: 1, step: '0.01', value: p.amount_paise / 100 }), days = h('input', { type: 'number', min: 1, value: p.days });
      const sort = h('input', { type: 'number', min: 0, value: p.sort_order }), act = h('input', { type: 'checkbox', checked: p.is_active, style: 'width:auto' });
      form.replaceChildren(h('div', { class: 'card' }, h('h3', {}, editing ? `Edit "${p.name}"` : 'Add a plan'),
        ...(editing ? [] : field('Key (letters, numbers, dashes; e.g. business)', key)), ...field('Name', name), ...field('Description (optional)', desc),
        ...field('Price (₹)', price), ...field('Duration (days)', days), ...field('Sort order', sort), h('label', {}, act, ' Active (visible and purchasable)'),
        h('div', { class: 'row', style: 'margin-top:12px' }, h('button', { onclick: () => guard(async () => {
          const body = { name: name.value, description: desc.value || null, amount_paise: Math.round(parseFloat(price.value) * 100), days: +days.value, is_active: act.checked, sort_order: +sort.value || 0 };
          if (editing) await api('PUT', `/admin/plans/${editing.id}`, body); else await api('POST', '/admin/plans', { ...body, key: key.value });
          toast('Plan saved'); render();
        }) }, editing ? 'Save changes' : 'Add plan'), editing ? h('button', { class: 'ghost', onclick: () => { editing = null; draw(); } }, 'Cancel') : null)));
    };
    view.append(h('div', { class: 'card' }, h('table', {}, h('tr', {}, ['Key', 'Name', 'Price', 'Days', 'Status', ''].map(x => h('th', {}, x))),
      plans.map(p => h('tr', {}, h('td', {}, p.key), h('td', {}, p.name), h('td', {}, rs(p.amount_paise)), h('td', {}, p.days), h('td', {}, statusBadge(p.is_active ? 'ready' : 'rejected')),
        h('td', {}, h('button', { class: 'ghost', onclick: () => { editing = p; draw(); window.scrollTo(0, document.body.scrollHeight); } }, 'Edit')))))), form);
    draw();
  }, err);
};

SCREENS.admin_gateway = async view => {
  const err = h('div', { class: 'err' });
  view.replaceChildren(...head('Payment gateway (Razorpay)', 'Enter your Razorpay credentials. Secrets are stored encrypted, are write-only, and are never shown again.'), err);
  await guard(async () => {
    const g = (await api('GET', '/admin/payment-gateway')).data;
    const key = h('input', { value: g.key_id || '', placeholder: 'rzp_test_… or rzp_live_…' });
    const sec = h('input', { type: 'password', autocomplete: 'off', placeholder: g.key_secret_set ? `Saved (${g.key_secret_hint}) — leave blank to keep` : 'Key secret' });
    const wh = h('input', { type: 'password', autocomplete: 'off', placeholder: g.webhook_secret_set ? 'Saved — leave blank to keep' : 'Webhook secret' });
    view.append(h('div', { class: 'card' },
      h('div', {}, 'Status: ', g.key_secret_set && g.key_id ? statusBadge('ready') : statusBadge('failed'), ` credentials source: ${g.source}`),
      ...field('Key ID', key), ...field('Key secret', sec), ...field('Webhook secret', wh),
      h('div', { style: 'margin-top:12px' }, h('button', { onclick: () => guard(async () => { await api('PUT', '/admin/payment-gateway', { key_id: key.value || null, key_secret: sec.value || null, webhook_secret: wh.value || null }); toast('Payment gateway saved'); render(); }) }, 'Save')),
      h('p', { class: 'muted' }, 'In Razorpay Dashboard → Webhooks, set the URL to:'), h('code', {}, g.webhook_url), h('p', { class: 'muted' }, 'Events: payment.captured, order.paid, payment.failed. Use the same webhook secret here.')));
  }, err);
};

SCREENS.admin_payments = async view => {
  const err = h('div', { class: 'err' });
  view.replaceChildren(...head('All payments', 'Payments across every workspace.'), err);
  await guard(async () => {
    const r = await api('GET', '/admin/payments');
    view.append(h('div', { class: 'card' }, r.data.length ? h('table', {}, h('tr', {}, ['Date', 'Workspace', 'Plan', 'Amount', 'Status'].map(x => h('th', {}, x))),
      r.data.map(x => h('tr', {}, h('td', {}, dt(x.created_at)), h('td', {}, x.workspace), h('td', {}, x.plan), h('td', {}, rs(x.amount_paise)), h('td', {}, statusBadge(x.status === 'created' ? 'pending_review' : x.status))))) : h('span', { class: 'muted' }, 'No payments yet.')));
  }, err);
};

// ---------- start ----------
(async () => {
  try { await csrf(); const u = await api('GET', '/user'); S.user = u.data || u; await boot(); }
  catch { S.user = null; render(); }
})();
