#!/usr/bin/env python3
"""
Parse WhatsApp chat export to extract retail store data.
Handles Unicode bullet points, various senders, and formatting quirks.
"""

import re
import json


CHAT_FILE = "/Users/dtyfhtf/Downloads/_chat.txt"
OUT_FILE = "/Users/dtyfhtf/Herd/rishipath-pos/scripts/parsed_stores.json"


def clean(text):
    """Remove zero-width chars, extra whitespace."""
    text = re.sub(r'[\u200e\u200f\u200b\u200c\u200d\ufeff\u202a\u202c\u2069\u2066\u2068]', '', text)
    text = text.replace('\r', '')
    return text.strip()


def parse_chat(filepath):
    with open(filepath, "r", encoding="utf-8") as f:
        raw = f.read()

    raw = clean(raw)

    # Split into individual messages by timestamp
    msgs = re.split(r'(?=\[\d{2}/\d{2}/\d{4},\s*\d{2}:\d{2}:\d{2}\])', raw)
    msgs = [m.strip() for m in msgs if m.strip()]

    stores = {}
    current_num = None

    for msg in msgs:
        # Extract timestamp and full body
        tm = re.match(r'\[([^\]]+)\]\s*(.*)', msg, re.DOTALL)
        if not tm:
            continue
        timestamp = tm.group(1)
        body = tm.group(2).strip()

        # Skip system messages
        if 'message was deleted' in body.lower() or 'omitted' in body.lower():
            continue

        # Remove sender prefix: "Revered H H Acharyaji: " — only if first line
        # has "Name: " pattern, and what follows is a store entry or bullet
        # Simple approach: if body has "Name: *N." or "Name: N." strip the name part
        body_stripped = re.sub(r'^[A-Za-z][A-Za-z\s]+:\s*', '', body.split('\n')[0], count=1)
        # Reconstruct with remaining lines
        lines = body.split('\n')
        lines[0] = body_stripped
        full_text = '\n'.join(lines)

        # Check for numbered store entry anywhere in first few chars
        # Patterns: "*1. Mina Store*", "2. Anima kirana store", "345. Store name"
        first_line = full_text.split('\n')[0].strip()
        store_match = re.match(r'\*?(\d+)\.\s*(.*?)(?:\*\s*)?$', first_line)
        if store_match:
            num = int(store_match.group(1))
            name_raw = store_match.group(2).strip().rstrip('*').strip()

            current_num = num

            if num not in stores:
                stores[num] = {
                    "number": num,
                    "store_name": "",
                    "contact_person": "",
                    "contact_number": "",
                    "address": "",
                    "google_location_url": "",
                }

            if name_raw and name_raw.lower() not in ("n/a", ""):
                stores[num]["store_name"] = name_raw

            # Parse all lines for bullet data
            for line in full_text.split('\n'):
                parse_line(stores[num], line.strip())
            continue

        # Not a numbered entry — check for maps URL or additional data for current store
        if current_num is None or current_num not in stores:
            continue

        store = stores[current_num]

        # Standalone Google Maps URL
        url_match = re.search(r'(https://maps\.app\.goo\.gl/[A-Za-z0-9_-]+)', full_text)
        if url_match:
            if not store["google_location_url"]:
                store["google_location_url"] = url_match.group(1)
            continue

        # Parse any bullet lines
        for line in full_text.split('\n'):
            parse_line(store, line.strip())

    return stores


def parse_line(store, line):
    """Parse a single line for bullet-point data."""
    # Remove bullet char
    line = re.sub(r'^[●•]\s*', '', line).strip()
    if not line:
        return

    # Contact Person
    cp = re.match(r'Contact\s*Person\s*:?\s*(.*)', line, re.I)
    if cp:
        val = cp.group(1).strip().strip('.').strip()
        if val and val.lower() not in ('n/a', '......', '…', '', 'no contact', '.....'):
            store["contact_person"] = val
        return

    # Contact No
    cn = re.match(r'Contact\s*No\.?\s*:?\s*(.*)', line, re.I)
    if cn:
        val = cn.group(1).strip().strip('.').strip()
        if val and val.lower() not in ('n/a', 'no contact', ''):
            store["contact_number"] = val
        return

    # Address
    addr = re.match(r'Address\s*:?\s*(.*)', line, re.I)
    if addr:
        val = addr.group(1).strip().strip(',').strip()
        if val and val.lower() not in ('n/a', ''):
            store["address"] = val
        return

    # Google Location URL
    gl = re.match(r'Google\s*(?:Current\s*)?Location\s*:?\s*(.*)', line, re.I)
    if gl:
        val = gl.group(1).strip()
        url_m = re.search(r'(https://maps\.app\.goo\.gl/[A-Za-z0-9_-]+)', val)
        if url_m:
            store["google_location_url"] = url_m.group(1)
        return


def main():
    stores = parse_chat(CHAT_FILE)
    sorted_stores = sorted(stores.values(), key=lambda s: s["number"])

    print(f"Total stores parsed: {len(sorted_stores)}")
    if sorted_stores:
        print(f"Store numbers: {sorted_stores[0]['number']} to {sorted_stores[-1]['number']}")

    # Find gaps
    all_nums = set(s["number"] for s in sorted_stores)
    expected = set(range(1, max(all_nums) + 1))
    missing = sorted(expected - all_nums)
    if missing:
        print(f"Missing store numbers ({len(missing)}): {missing}")

    # Stats
    n = len(sorted_stores)
    with_name = sum(1 for s in sorted_stores if s["store_name"])
    with_person = sum(1 for s in sorted_stores if s["contact_person"])
    with_contact = sum(1 for s in sorted_stores if s["contact_number"])
    with_address = sum(1 for s in sorted_stores if s["address"])
    with_url = sum(1 for s in sorted_stores if s["google_location_url"])

    print(f"\nWith store name:      {with_name}/{n}")
    print(f"With contact person:  {with_person}/{n}")
    print(f"With contact number:  {with_contact}/{n}")
    print(f"With address:         {with_address}/{n}")
    print(f"With Google Maps URL: {with_url}/{n}")

    # Write JSON
    output = []
    for s in sorted_stores:
        output.append({
            "number": s["number"],
            "store_name": s["store_name"],
            "contact_person": s["contact_person"],
            "contact_number": s["contact_number"],
            "address": s["address"],
            "google_location_url": s["google_location_url"],
        })

    with open(OUT_FILE, "w", encoding="utf-8") as f:
        json.dump(output, f, indent=2, ensure_ascii=False)
    print(f"\nJSON written to {OUT_FILE}")

    # Show all stores in table
    print(f"\n{'#':>4} | {'Store Name':<35} | {'Contact':<22} | {'Person':<25} | {'Address':<40} | URL")
    print("-" * 160)
    for s in output:
        url_mark = "✓" if s["google_location_url"] else ""
        print(f"{s['number']:4d} | {s['store_name'][:35]:<35} | {s['contact_number'][:22]:<22} | {s['contact_person'][:25]:<25} | {s['address'][:40]:<40} | {url_mark}")


if __name__ == "__main__":
    main()
