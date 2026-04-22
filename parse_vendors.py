#!/usr/bin/env python3
"""Parse vendorList.xlsx and emit sql/import_vendors.sql"""

import openpyxl
import re

wb = openpyxl.load_workbook('/home/user/mediapurchasing/vendorList_decoded.xlsx')

# ── helpers ────────────────────────────────────────────────────────────────────

def clean(v):
    if v is None:
        return ''
    return str(v).strip().replace('\xa0', ' ').replace('\r', '').replace('\n', ' ')

def is_email(s):
    return bool(s and '@' in s and '.' in s and ' ' not in s.strip())

def looks_like_address(s):
    s = s.strip()
    if not s:
        return False
    if re.match(r'^\d', s):
        return True
    if re.match(r'^P\.?O\.?\s*Box', s, re.I):
        return True
    if re.search(r'\b[A-Z]{2}\s+\d{5}\b', s):
        return True
    if s.startswith('http') or s.startswith('www.') or s.startswith('couleecreative'):
        return True
    return False

def looks_like_url(s):
    return s.startswith('http') or s.startswith('www.')

def q(s):
    """SQL string or NULL for empty."""
    if not s:
        return 'NULL'
    s = str(s).replace("'", "''")
    return "'" + s + "'"

def qs(s):
    """SQL string, never NULL (for NOT NULL columns)."""
    s = str(s) if s else ''
    return "'" + s.replace("'", "''") + "'"

def parse_city_state_zip(addr_lines):
    for line in addr_lines:
        m = re.search(r'^(.*?),?\s+([A-Z]{2})\s+(\d{5}(?:-\d{4})?)', line)
        if m:
            return m.group(1).strip(), m.group(2), m.group(3)
    return '', '', ''

SKIP_CATS = {'Billboards', 'Other Media Outlets', 'Theaters', 'Casinos',
             'Chambers with Event Calendars'}
STOP_ROWS = {'SALES REPS', 'DO NOT USE FOR MEDIA'}
GENERIC_NAMES = {'press release', 'press releases', 'newsroom', 'news',
                 'advertising', 'classifieds', 'editor', 'director',
                 'list of media links', 'general mailbox'}

# ── state machine per sheet ────────────────────────────────────────────────────

vendors = []

def emit_vendor(state, vendors):
    """Build a vendor record from accumulated state and append to vendors list."""
    co = state['company']
    if not co:
        return
    if 'DO NOT USE' in co.upper():
        return

    good = [c for c in state['contacts']
            if 'DO NOT USE' not in (c.get('note') or '').upper()]

    with_email = [c for c in good if c.get('email')]

    primary = None
    for c in with_email:
        if (c.get('name') or '').lower().strip() not in GENERIC_NAMES:
            primary = c
            break
    if primary is None and with_email:
        primary = with_email[0]
    if primary is None and good:
        primary = good[0]
    if primary is None:
        primary = {'name': '', 'email': '', 'phone': '', 'note': ''}

    others = [c for c in good if c is not primary]

    note_parts = ['Market: ' + state['market']]
    if state.get('press_release_section'):
        note_parts.append('Section: Press Release Contacts')
    for c in others:
        bits = [x for x in [c.get('name'), c.get('email'), c.get('phone')] if x]
        if bits:
            note_parts.append(' | '.join(bits))
    if primary.get('note'):
        note_parts.append(primary['note'])

    addr_lines = state['addr_lines']
    addr_street = addr_lines[0] if addr_lines else ''
    city, st, zip_ = parse_city_state_zip(addr_lines)

    vendors.append({
        'company_name': co,
        'contact_name': primary.get('name', ''),
        'email':        primary.get('email', ''),
        'phone':        primary.get('phone', ''),
        'address':      addr_street,
        'city':         city,
        'state':        st,
        'zip':          zip_,
        'category':     state['category'],
        'notes':        '\n'.join(note_parts),
    })


for sheet_name in wb.sheetnames:
    ws = wb[sheet_name]
    market = re.sub(r'\s*Media Contacts$', '', sheet_name).strip()

    state = {
        'market':               market,
        'category':             'General',
        'company':              None,
        'addr_lines':           [],
        'contacts':             [],
        'skip':                 False,
        'press_release_section': False,
    }

    for row_idx, row in enumerate(ws.iter_rows(), 1):
        if row_idx == 1:
            continue

        vals  = [clean(c.value) for c in row[:5]]
        col_a, col_b, col_c, col_d = vals[0], vals[1], vals[2], vals[3]
        col_e = vals[4] if len(vals) > 4 else ''

        bold_a   = bool(row[0].font and row[0].font.bold)
        italic_a = bool(row[0].font and row[0].font.italic)

        if not any(vals[:4]):
            continue

        if col_a in STOP_ROWS:
            emit_vendor(state, vendors)
            state.update({'company': None, 'addr_lines': [], 'contacts': []})
            state['skip'] = True
            continue

        # Category header
        if bold_a and italic_a and col_a:
            emit_vendor(state, vendors)
            state.update({'company': None, 'addr_lines': [], 'contacts': []})
            state['category'] = col_a
            state['skip'] = col_a in SKIP_CATS
            state['press_release_section'] = False
            continue

        # "PRESS RELEASE CONTACTS" sub-header
        if bold_a and not italic_a and col_a.upper() == 'PRESS RELEASE CONTACTS':
            emit_vendor(state, vendors)
            state.update({'company': None, 'addr_lines': [], 'contacts': []})
            state['press_release_section'] = True
            state['skip'] = False
            continue

        if state['skip']:
            continue

        # Detect contact data in this row
        email = ''
        if is_email(col_c) and 'DO NOT USE' not in col_c.upper():
            email = col_c.lower().strip()
        elif is_email(col_b) and 'DO NOT USE' not in col_b.upper():
            email = col_b.lower().strip()
        elif is_email(col_d):
            email = col_d.lower().strip()

        rep = col_b if (col_b and not is_email(col_b) and not looks_like_url(col_b)) else ''
        phone = col_d[:100] if (col_d and not is_email(col_d)) else ''
        if phone and not re.search(r'\d', phone):
            phone = ''

        has_contact = bool(email or rep or phone)

        # Address line in col_a
        if col_a and looks_like_address(col_a):
            if state['company'] is not None:
                state['addr_lines'].append(col_a)
            if has_contact and state['company'] is not None:
                state['contacts'].append({'name': rep, 'email': email,
                                          'phone': phone, 'note': col_e})
            continue

        # New company (bold non-italic col_a, not address)
        if bold_a and not italic_a and col_a and not looks_like_address(col_a):
            emit_vendor(state, vendors)
            state.update({'company': col_a, 'addr_lines': [], 'contacts': []})
            if has_contact:
                state['contacts'].append({'name': rep, 'email': email,
                                          'phone': phone, 'note': col_e})
            continue

        # Contact-only row
        if has_contact and state['company'] is not None:
            state['contacts'].append({'name': rep, 'email': email,
                                      'phone': phone, 'note': col_e})

    emit_vendor(state, vendors)  # final company in sheet

# ── deduplicate by email ───────────────────────────────────────────────────────

seen = set()
deduped = []
for v in vendors:
    key = v['email'].lower() if v['email'] else None
    if key and key in seen:
        continue
    if key:
        seen.add(key)
    deduped.append(v)

vendors = deduped
print(f"Total vendor records: {len(vendors)}")

# ── generate SQL ───────────────────────────────────────────────────────────────

header = """\
-- Vendor import from vendorList.xlsx
-- Generated automatically — review before running
-- Run against the mediapurchasing database

SET NAMES utf8mb4;

INSERT INTO vendors
  (company_name, contact_name, email, phone,
   address, billing_email, media_category, notes,
   is_active, created_at, updated_at)
VALUES
"""

rows_sql = []
for v in vendors:
    # Use raw address line (already contains city/state when parsed from spreadsheet)
    address = v['address']

    # contact_name and email are NOT NULL in schema — qs() returns '' not NULL
    rows_sql.append(
        f"  ({qs(v['company_name'])}, {qs(v['contact_name'])}, {qs(v['email'])}, {q(v['phone'])},\n"
        f"   {q(address)}, NULL, {q(v['category'])}, {q(v['notes'])},\n"
        f"   1, NOW(), NOW())"
    )

sql = header + ',\n'.join(rows_sql) + ';\n'

out_path = '/home/user/mediapurchasing/sql/import_vendors.sql'
with open(out_path, 'w', encoding='utf-8') as f:
    f.write(sql)

print(f"SQL written to {out_path}")
print("\nSample records:")
for v in vendors[:8]:
    print(f"  [{v['category']:18s}] {v['company_name']:35s} | {v['contact_name']:22s} | {v['email']}")
