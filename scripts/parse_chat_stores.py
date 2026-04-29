#!/usr/bin/env python3
"""
Parse WhatsApp chat export to extract retail store data.
Output: JSON with all stores, cross-referenced with the raw table data.
"""

import re
import json
import sys

CHAT_FILE = "/Users/dtyfhtf/Downloads/_chat.txt"

def parse_chat(filepath):
    with open(filepath, "r", encoding="utf-8") as f:
        lines = f.readlines()

    # Merge continuation lines (lines not starting with [date])
    messages = []
    for line in lines:
        line = line.rstrip("\n")
        if re.match(r"^\[", line):
            messages.append(line)
        elif messages:
            messages[-1] += "\n" + line

    stores = {}
    current_store_num = None

    for msg in messages:
        # Extract text after the timestamp/sender
        m = re.match(r"^\[([^\]]+)\]\s+(?:[^:]+:\s*)?(.*)", msg, re.DOTALL)
        if not m:
            continue
        timestamp = m.group(1)
        text = m.group(2).strip()

        # Check if this starts a new numbered store entry
        store_match = re.match(r"^\*?(\d+)\.\s*(.*?)(?:\*)?$", text.split("\n")[0].strip())
        if store_match:
            num = int(store_match.group(1))
            name = store_match.group(2).strip().strip("*").strip()
            current_store_num = num

            if num not in stores:
                stores[num] = {
                    "number": num,
                    "store_name": "",
                    "contact_person": "",
                    "contact_number": "",
                    "address": "",
                    "google_location_url": "",
                    "raw_lines": [],
                    "timestamp": timestamp,
                }

            if name and name.lower() not in ("n/a", ""):
                stores[num]["store_name"] = name
            stores[num]["raw_lines"].append(text)

            # Parse bullet points in the same message
            for line in text.split("\n"):
                line = line.strip()
                parse_bullet(stores[num], line)

            continue

        # If we have a current store, check for additional info
        if current_store_num and current_store_num in stores:
            store = stores[current_store_num]

            # Google Maps URL on its own line
            url_match = re.search(r"(https://maps\.app\.goo\.gl/\S+)", text)
            if url_match and not store["google_location_url"]:
                store["google_location_url"] = url_match.group(1)
                store["raw_lines"].append(text)
                continue

            # Bullet point lines
            for line in text.split("\n"):
                line = line.strip()
                if line.startswith("●") or line.startswith("•"):
                    parse_bullet(store, line)
                    store["raw_lines"].append(line)

    return stores


def parse_bullet(store, line):
    line = line.strip()

    # Contact Person
    cp = re.match(r"[●•]\s*Contact\s*Person\s*:\s*(.*)", line, re.I)
    if cp:
        val = cp.group(1).strip().strip(".")
        if val and val.lower() not in ("n/a", "......", "…", ""):
            store["contact_person"] = val
        return

    # Contact No
    cn = re.match(r"[●•]\s*Contact\s*No\.?\s*:\s*(.*)", line, re.I)
    if cn:
        val = cn.group(1).strip().strip(".")
        if val and val.lower() not in ("n/a", "no contact", "......", ""):
            store["contact_number"] = val
        return

    # Address
    addr = re.match(r"[●•]\s*Address\s*:\s*(.*)", line, re.I)
    if addr:
        val = addr.group(1).strip().strip(".")
        if val and val.lower() not in ("n/a", ""):
            store["address"] = val
        return

    # Google Location
    gl = re.match(r"[●•]\s*Google\s*(?:Current\s*)?Location\s*:?\s*(.*)", line, re.I)
    if gl:
        val = gl.group(1).strip().strip(".")
        url = re.search(r"(https://maps\.app\.goo\.gl/\S+)", val)
        if url:
            store["google_location_url"] = url.group(1)
        return


def main():
    stores = parse_chat(CHAT_FILE)

    # Sort by number
    sorted_stores = sorted(stores.values(), key=lambda s: s["number"])

    # Print summary
    print(f"Total stores parsed: {len(sorted_stores)}")
    print(f"Store numbers: {sorted_stores[0]['number']} to {sorted_stores[-1]['number']}")
    print()

    # Find gaps
    all_nums = set(s["number"] for s in sorted_stores)
    expected = set(range(1, max(all_nums) + 1))
    missing = expected - all_nums
    if missing:
        print(f"Missing store numbers: {sorted(missing)}")
    print()

    # Stats
    with_name = sum(1 for s in sorted_stores if s["store_name"])
    with_contact = sum(1 for s in sorted_stores if s["contact_number"])
    with_person = sum(1 for s in sorted_stores if s["contact_person"])
    with_address = sum(1 for s in sorted_stores if s["address"])
    with_url = sum(1 for s in sorted_stores if s["google_location_url"])

    print(f"With store name:      {with_name}")
    print(f"With contact person:  {with_person}")
    print(f"With contact number:  {with_contact}")
    print(f"With address:         {with_address}")
    print(f"With Google Maps URL: {with_url}")
    print()

    # Output JSON (without raw_lines for cleanliness)
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

    out_path = "/Users/dtyfhtf/Herd/rishipath-pos/scripts/parsed_stores.json"
    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(output, f, indent=2, ensure_ascii=False)
    print(f"JSON written to {out_path}")

    # Print first 10 for verification
    print("\n--- First 10 stores ---")
    for s in output[:10]:
        print(json.dumps(s, ensure_ascii=False))

    # Print last 5 for verification
    print("\n--- Last 5 stores ---")
    for s in output[-5:]:
        print(json.dumps(s, ensure_ascii=False))


if __name__ == "__main__":
    main()
