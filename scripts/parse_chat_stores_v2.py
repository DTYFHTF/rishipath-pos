#!/usr/bin/env python3
"""
Parse WhatsApp chat export to extract retail store data.
Handles Unicode bullet points and various formatting quirks.
"""

import re
import json
import unicodedata

CHAT_FILE = "/Users/dtyfhtf/Downloads/_chat.txt"
OUT_FILE = "/Users/dtyfhtf/Herd/rishipath-pos/scripts/parsed_stores.json"


def normalize(text):
    """Clean up unicode mess, control chars, extra whitespace."""
    # Remove zero-width and control chars
    text = re.sub(r'[\u200e\u200f\u200b\u200c\u200d\ufeff\u202a\u202c\u2069\u2066]', '', text)
    text = text.replace('\r', '')
    text = re.sub(r'\s+', ' ', text).strip()
    return text


def parse_chat(filepath):
    with open(filepath, "r", encoding="utf-8") as f:
        raw = f.read()

    # Normalize
    raw = raw.replace('\r\n', '\n').replace('\r', '\n')

    # Split into messages: each starts with [date, time]
    # But continuation lines don't start with [
    msg_pattern = re.compile(r'^\[(\d{2}/\d{2}/\d{4},\s*\d{2}:\d{2}:\d{2})\]\s*(.*?)(?=\n\[\d{2}/\d{2}/\d{4}|\Z)', re.MULTILINE | re.DOTALL)
    messages = msg_pattern.findall(raw)

    stores = {}
    current_num = None

    for timestamp, body in messages:
        # Strip timestamp bracket
        body_clean = re.sub(r'^\[\d{2}/\d{2}/\d{4},\s*\d{2}:\d{2}:\d{2}\]\s*', '', '[' + timestamp + '] ' + body)
        body_clean = body if not body_clean else body_clean
        body = normalize(body)

        # Remove sender prefix — only from the FIRST line, before first ":"
        # WhatsApp format: "SenderName: message text"
        first_line = body.split('●')[0].split('\n')[0] if '●' in body else body.split('\n')[0]
        # Sender is text before first ": " on first line, not containing digits followed by "."
        sender_match = re.match(r'^([A-Za-z][A-Za-z\s]+):\s*(.*)', first_line)
        if sender_match and not re.match(r'^\d+\.', sender_match.group(2).strip()):
            # Strip sender from body
            text = re.sub(r'^' + re.escape(sender_match.group(1)) + r':\s*', '', body, count=1)
        else:
            text = body

        # Skip system messages
        if 'message was deleted' in text.lower() or 'omitted' in text.lower():
            continue

        # Check for numbered store entry: "5. Masala pasal" or "*1. Mina Store*"
        store_match = re.match(r'\*?(\d+)\.\s*(.*?)(?:\*)?(?:\s|$)', text)
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

            # Parse rest of text for bullet data
            parse_fields(stores[num], text)
            continue

        # Standalone Google Maps URL
        url_match = re.search(r'(https://maps\.app\.goo\.gl/\S+)', text)
        if url_match and current_num and current_num in stores:
            if not stores[current_num]["google_location_url"]:
                url = url_match.group(1).rstrip('.')
                # Remove trailing unicode junk
                url = re.sub(r'[^\x20-\x7e]', '', url)
                stores[current_num]["google_location_url"] = url
            continue

        # Additional bullet points for current store
        if current_num and current_num in stores:
            parse_fields(stores[current_num], text)

    return stores


def parse_fields(store, text):
    """Extract Contact Person, Contact No, Address, Google URL from text."""

    # Contact Person
    cp = re.search(r'Contact\s*Person\s*:?\s*(.*?)(?=Contact\s*No|Address|Google|$)', text, re.I)
    if cp:
        val = cp.group(1).strip().strip('.').strip()
        if val and val.lower() not in ('n/a', '......', '…', '', 'no contact'):
            store["contact_person"] = val

    # Contact No
    cn = re.search(r'Contact\s*No\.?\s*:?\s*([\d\s,\-+()]+)', text, re.I)
    if cn:
        val = cn.group(1).strip().strip(',').strip()
        if val and val.lower() not in ('n/a', 'no contact', ''):
            store["contact_number"] = val

    # Address
    addr = re.search(r'Address\s*:?\s*(.*?)(?=Google|$)', text, re.I)
    if addr:
        val = addr.group(1).strip().strip(',').strip()
        if val and val.lower() not in ('n/a', ''):
            store["address"] = val

    # Google Location URL
    gl = re.search(r'(https://maps\.app\.goo\.gl/\S+)', text)
    if gl:
        url = gl.group(1).rstrip('.').strip()
        url = re.sub(r'[^\x20-\x7e]', '', url).strip()
        if url:
            store["google_location_url"] = url


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
    with_name = sum(1 for s in sorted_stores if s["store_name"])
    with_person = sum(1 for s in sorted_stores if s["contact_person"])
    with_contact = sum(1 for s in sorted_stores if s["contact_number"])
    with_address = sum(1 for s in sorted_stores if s["address"])
    with_url = sum(1 for s in sorted_stores if s["google_location_url"])

    print(f"\nWith store name:      {with_name}/{len(sorted_stores)}")
    print(f"With contact person:  {with_person}/{len(sorted_stores)}")
    print(f"With contact number:  {with_contact}/{len(sorted_stores)}")
    print(f"With address:         {with_address}/{len(sorted_stores)}")
    print(f"With Google Maps URL: {with_url}/{len(sorted_stores)}")

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

    # Show samples
    print("\n--- First 15 stores ---")
    for s in output[:15]:
        print(f"  #{s['number']:3d} | {s['store_name'][:30]:30s} | {s['contact_number'][:20]:20s} | {s['address'][:40]:40s} | {'URL' if s['google_location_url'] else '---'}")

    print("\n--- Last 10 stores ---")
    for s in output[-10:]:
        print(f"  #{s['number']:3d} | {s['store_name'][:30]:30s} | {s['contact_number'][:20]:20s} | {s['address'][:40]:40s} | {'URL' if s['google_location_url'] else '---'}")


if __name__ == "__main__":
    main()
