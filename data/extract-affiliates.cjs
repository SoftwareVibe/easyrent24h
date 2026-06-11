'use strict';
// Streaming extractor for easyrent24h dump (Node 22). Single pass, line-based with
// quote-aware tokenizer that survives multi-line string values.
const fs = require('fs');
const readline = require('readline');
const path = require('path');

const DUMP = process.argv[2] || 'C:\\Users\\Davide\\source\\WordpressSites\\WordPressSitesBackups\\easyRent\\easyrent24h.com\\dbjkglnikefpaj.sql';
let dbgRows = { wp39689_posts: 0, wp39689_postmeta: 0 };
const OUT = path.join(__dirname, 'affiliates-export.json');

const customTables = ['vendors', 'payments', 'azionistato', 'menus', 'vendite', 'users'];
const targets = new Set([...customTables, 'wp39689_posts', 'wp39689_postmeta']);

const data = {};           // table -> { columns: [], rows: [][] } for custom tables
const createStmts = {};    // table -> CREATE TABLE text
const coupons = [];        // shop_coupon rows from wp39689_posts
const couponMeta = [];     // postmeta rows with key coupon_amount/discount_type
let postsCols = null, postmetaCols = null;

// ---- statement state ----
let mode = 'scan';         // scan | create | values
let curTable = null;
let curCols = null;
let createBuf = [];

// tokenizer state (persists across lines while mode === 'values')
let inStr = false, esc = false, depth = 0, tok = '', tokIsStr = false, row = [];

function resetTok() { inStr = false; esc = false; depth = 0; tok = ''; tokIsStr = false; row = []; }

function pushVal() {
  let v;
  if (tokIsStr) v = tok;
  else {
    const t = tok.trim();
    if (t === '' ) { tok=''; tokIsStr=false; return; }
    if (/^NULL$/i.test(t)) v = null;
    else if (/^-?\d+$/.test(t)) v = (Math.abs(+t) <= Number.MAX_SAFE_INTEGER) ? +t : t;
    else if (/^-?\d*\.\d+$/.test(t)) v = +t;
    else v = t;
  }
  row.push(v);
  tok = ''; tokIsStr = false;
}

function emitRow(table, cols, vals) {
  if (customTables.includes(table)) {
    data[table].rows.push(vals);
  } else if (table === 'wp39689_posts') {
    dbgRows.wp39689_posts++;
    const o = {};
    cols.forEach((c, i) => o[c] = vals[i]);
    if (o.post_type === 'shop_coupon') {
      coupons.push({ id: o.ID, code: o.post_title, status: o.post_status, post_name: o.post_name, post_date: o.post_date, post_excerpt: o.post_excerpt });
    }
  } else if (table === 'wp39689_postmeta') {
    dbgRows.wp39689_postmeta++;
    const o = {};
    cols.forEach((c, i) => o[c] = vals[i]);
    if (o.meta_key === 'coupon_amount' || o.meta_key === 'discount_type') {
      couponMeta.push({ post_id: o.post_id, key: o.meta_key, value: o.meta_value });
    }
  }
}

// returns true when statement finished (unquoted ';' at depth 0)
function feedValues(line) {
  for (let i = 0; i < line.length; i++) {
    const ch = line[i];
    if (inStr) {
      if (esc) { // backslash escape
        const map = { n: '\n', r: '\r', t: '\t', '0': '\0', Z: '\x1a' };
        tok += (map[ch] !== undefined ? map[ch] : ch);
        esc = false;
      } else if (ch === '\\') esc = true;
      else if (ch === "'") {
        if (line[i + 1] === "'") { tok += "'"; i++; } // doubled quote
        else { inStr = false; }
      } else tok += ch;
      continue;
    }
    if (ch === "'") { inStr = true; if (!tokIsStr) tok = ''; tokIsStr = true; continue; }
    if (ch === '(') { if (depth === 0) { depth = 1; tok = ''; tokIsStr = false; row = []; continue; } depth++; tok += ch; continue; }
    if (ch === ')') {
      depth--;
      if (depth === 0) { pushVal(); emitRow(curTable, curCols, row); row = []; continue; }
      tok += ch; continue;
    }
    if (ch === ',' && depth === 1) { pushVal(); continue; }
    if (ch === ';' && depth === 0) return true;
    if (depth >= 1) tok += ch;
  }
  if (inStr) tok += '\n'; // literal newline inside a string value
  return false;
}

const insertRe = /^INSERT INTO `([^`]+)` \(([^)]+)\) VALUES\s*(.*)$/;
const createRe = /^CREATE TABLE `([^`]+)`/;

for (const t of customTables) data[t] = { columns: null, rows: [] };

const rl = readline.createInterface({ input: fs.createReadStream(DUMP, { encoding: 'utf8' }), crlfDelay: Infinity });

let lineNo = 0;
rl.on('line', (line) => {
  lineNo++;
  if (mode === 'values') {
    if (feedValues(line)) { mode = 'scan'; curTable = null; resetTok(); }
    return;
  }
  if (mode === 'create') {
    createBuf.push(line);
    if (/^\)/.test(line)) { createStmts[curTable] = createBuf.join('\n'); mode = 'scan'; curTable = null; }
    return;
  }
  // scan mode
  const mC = createRe.exec(line);
  if (mC && targets.has(mC[1]) && customTables.includes(mC[1])) {
    curTable = mC[1]; createBuf = [line]; mode = 'create';
    return;
  }
  const mI = insertRe.exec(line);
  if (mI && targets.has(mI[1])) {
    curTable = mI[1];
    curCols = mI[2].split(',').map(s => s.trim().replace(/`/g, ''));
    if (customTables.includes(curTable)) data[curTable].columns = curCols;
    else if (curTable === 'wp39689_posts') postsCols = curCols;
    else postmetaCols = curCols;
    resetTok();
    mode = 'values';
    const rest = mI[3];
    if (rest && feedValues(rest)) { mode = 'scan'; curTable = null; resetTok(); }
  }
});

rl.on('close', () => {
  const toObjs = (t) => {
    const { columns, rows } = data[t];
    if (!columns) return [];
    return rows.map(r => { const o = {}; columns.forEach((c, i) => o[c] = r[i]); return o; });
  };

  const vendors = toObjs('vendors');
  const payments = toObjs('payments');
  const azionistato = toObjs('azionistato');
  const vendite = toObjs('vendite');
  const menusRows = toObjs('menus');
  const users = toObjs('users').map(u => {
    const o = { id: u.id, username: u.username, type: u.type, created: u.created, password: '***' };
    if (u.email !== undefined) o.email = u.email;
    return o;
  });
  // anonymize any password-like field in vendors too
  for (const v of vendors) for (const k of Object.keys(v)) if (/pass/i.test(k)) v[k] = '***';

  const metaByPost = {};
  for (const m of couponMeta) {
    (metaByPost[m.post_id] = metaByPost[m.post_id] || {})[m.key] = m.value;
  }
  const shop_coupons = coupons.map(c => ({
    id: c.id,
    code: c.code,
    status: c.status,
    amount: (metaByPost[c.id] || {}).coupon_amount ?? null,
    discount_type: (metaByPost[c.id] || {}).discount_type ?? null,
    post_name: c.post_name,
    post_date: c.post_date,
    description: c.post_excerpt || ''
  }));

  const out = {
    vendors,
    payments,
    azionistato,
    menus_schema: (createStmts['menus'] || '') + '\n-- esempio righe:\n' + JSON.stringify(menusRows.slice(0, 3)),
    vendite,
    legacy_users: users,
    shop_coupons,
    _schemas: createStmts
  };
  fs.writeFileSync(OUT, JSON.stringify(out, null, 2), 'utf8');

  console.log('DONE. lines:', lineNo, 'dbgRows:', JSON.stringify(dbgRows));
  console.log('vendors:', vendors.length, 'payments:', payments.length, 'azionistato:', azionistato.length,
    'menus rows:', menusRows.length, 'vendite:', vendite.length, 'legacy_users:', users.length,
    'shop_coupons:', shop_coupons.length, 'couponMeta rows:', couponMeta.length);
});
