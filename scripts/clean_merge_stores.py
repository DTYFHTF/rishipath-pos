#!/usr/bin/env python3
"""
Clean, normalize, and merge retail store data from chat parse + raw table.
Cross-reference both sources to produce final accurate dataset.
Also extracts lat/lng from Google Maps short URLs by following redirects.
"""

import json
import re
import sys
import urllib.request
import urllib.error
import ssl
import time

PARSED_FILE = "/Users/dtyfhtf/Herd/rishipath-pos/scripts/parsed_stores.json"
OUT_FILE = "/Users/dtyfhtf/Herd/rishipath-pos/scripts/final_stores.json"

# ------ Raw table data from the user (the "spreadsheet" data) ------
# This is the authoritative reference for stores 1-77 (the raw table the user provided)
RAW_TABLE = [
    {"number": 1, "store_name": "Mina Store", "contact_person": "", "contact_number": "9841034360", "address_parts": ["Bagdol Inar Chok", "Lalitpur 4"], "google_location_url": ""},
    {"number": 2, "store_name": "Anima Kirana Store", "contact_person": "", "contact_number": "01-5190266", "address_parts": ["Lalitpur 4"], "google_location_url": ""},
    {"number": 3, "store_name": "", "contact_person": "Tek Bahadur", "contact_number": "", "address_parts": ["Rastrapati Marga", "Lalitpur 4"], "google_location_url": ""},
    {"number": 4, "store_name": "", "contact_person": "Bikram", "contact_number": "9864590553", "address_parts": ["Lalitpur 4", "Bagdol", "Rastrapati Marga"], "google_location_url": ""},
    {"number": 5, "store_name": "Masala Pasal", "contact_person": "Masala", "contact_number": "", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/Ped2Q31JV5TJ2s4Y6"},
    {"number": 6, "store_name": "Gorkhali Khadhya Bhandar", "contact_person": "", "contact_number": "9841516863, 9851319543", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/LSGYpghcxVxA9S6F7"},
    {"number": 7, "store_name": "Annapurna Organic Store", "contact_person": "", "contact_number": "9851198319", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/AqYFWLT5qPMHpdxP9"},
    {"number": 8, "store_name": "JSA Enterprises", "contact_person": "Santosh Shrestha", "contact_number": "9845907637", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/NC3cnJ4iHuu1ZWWz7"},
    {"number": 9, "store_name": "Big Mart", "contact_person": "Head Office Baluwatar", "contact_number": "9802314463", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/KFYpv9fF8mKAwv9x5"},
    {"number": 10, "store_name": "Om Aakash Kirana Pasal", "contact_person": "Suman Kumar Shrestha", "contact_number": "9844565539", "address_parts": ["Lalitpur 4", "Baghdol"], "google_location_url": "https://maps.app.goo.gl/fCwxRhvoRgV3NPbz9"},
    {"number": 11, "store_name": "Kirana Pasal", "contact_person": "", "contact_number": "", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/PkVHkzvYtrB9atju7"},
    {"number": 12, "store_name": "MR. Kirana Store", "contact_person": "Mukesh", "contact_number": "9843414626", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/MjmDFsHfYYBzbEoJ9"},
    {"number": 13, "store_name": "Nabadeep Dairy", "contact_person": "", "contact_number": "9841795886", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/XRY1D96sfXd1UA8E6"},
    {"number": 14, "store_name": "Met Store", "contact_person": "", "contact_number": "9827130094", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/kmAekApMovfwSsE66"},
    {"number": 15, "store_name": "Radharani Store", "contact_person": "", "contact_number": "9841433793", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/yeqHtjN3LvipRxuGA"},
    {"number": 16, "store_name": "Astamatrika Puja Samagri Pasal", "contact_person": "", "contact_number": "9860163737, 9841757941", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/HRGjv1jGBvUWAR8d9"},
    {"number": 17, "store_name": "S and M Grocery", "contact_person": "", "contact_number": "9841233003", "address_parts": ["Lalitpur 4", "Baghdol", "Aphaldol"], "google_location_url": "https://maps.app.goo.gl/shkpaWtnKi8BcbPb7"},
    {"number": 18, "store_name": "Durga Cold Store", "contact_person": "", "contact_number": "9849108072", "address_parts": ["Lalitpur 4", "Bagdol", "Aphaldol"], "google_location_url": "https://maps.app.goo.gl/Ch1gHnkyKcWXa4Q39"},
    {"number": 19, "store_name": "Bina Masala Pasal", "contact_person": "", "contact_number": "9823955935, 9822067280", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/CP3ZVp76LyZSfa4d7"},
    {"number": 20, "store_name": "Pasal", "contact_person": "", "contact_number": "", "address_parts": ["Lalitpur 4", "Bagdol"], "google_location_url": "https://maps.app.goo.gl/NaLZFuXS8YHYffzX7"},
    {"number": 21, "store_name": "R L Store", "contact_person": "Laxman Maharjan", "contact_number": "9860558873", "address_parts": ["Lalitpur 4", "Bagdhol", "Aphaldol"], "google_location_url": "https://maps.app.goo.gl/omTfqEm28khJZUue7"},
    {"number": 22, "store_name": "Prandevi Store", "contact_person": "", "contact_number": "980914994", "address_parts": ["Lalitpur 4", "Baghdol"], "google_location_url": "https://maps.app.goo.gl/2nwxhcC8L6LuizCa8"},
    {"number": 23, "store_name": "Megendra Store", "contact_person": "", "contact_number": "9843470056", "address_parts": ["Lalitpur 4", "Dhobighat"], "google_location_url": "https://maps.app.goo.gl/7umv5XpATiyb9bK87"},
    {"number": 24, "store_name": "", "contact_person": "Devendra", "contact_number": "9865705293", "address_parts": ["Lalitpur 4", "Dhobighat"], "google_location_url": "https://maps.app.goo.gl/gmpMdy39n3wXQd2n8"},
    {"number": 25, "store_name": "Karki Store", "contact_person": "", "contact_number": "98411610019", "address_parts": ["Lalitpur 4", "Dhobighat"], "google_location_url": "https://maps.app.goo.gl/Y21ABFDh4cPcQTLr5"},
    {"number": 26, "store_name": "Kritika and Ritika Store", "contact_person": "", "contact_number": "9813033301", "address_parts": ["Lalitpur 4", "Dhobighat"], "google_location_url": "https://maps.app.goo.gl/GghJrFzmrAcpUnvA6"},
    {"number": 27, "store_name": "New Maharjan Store", "contact_person": "", "contact_number": "01-5188214", "address_parts": ["Lalitpur 4", "Dhobighat"], "google_location_url": "https://maps.app.goo.gl/VTFahdCWMncqQJnd7"},
    {"number": 28, "store_name": "Tulasi Store", "contact_person": "Nhuchhe Maya Maharjan", "contact_number": "9843676581, 9841930506", "address_parts": ["Lalitpur 3", "Dhobighat"], "google_location_url": "https://maps.app.goo.gl/PsoJSoxMaTRrExNb9"},
    {"number": 29, "store_name": "Bajrangawoli Cold Store", "contact_person": "", "contact_number": "9841784088", "address_parts": ["Lalitpur 3", "Dhobighat", "Sangam Basti"], "google_location_url": "https://maps.app.goo.gl/CsMPD8RpcdW4t8nh6"},
    {"number": 30, "store_name": "Binod Kirana and Phalful Pasal", "contact_person": "", "contact_number": "9861342181", "address_parts": ["Lalitpur 4", "Baghdol", "Rastrapati Marga"], "google_location_url": "https://maps.app.goo.gl/j96TQzCCQyNzm4kf6"},
    {"number": 31, "store_name": "Krishna Store", "contact_person": "Sunita Khadka", "contact_number": "9840427854", "address_parts": ["Lalitpur 4", "Baghdol"], "google_location_url": "https://maps.app.goo.gl/d6MWLuF529G7dh987"},
    {"number": 32, "store_name": "Buddhi Store", "contact_person": "Santosh Shrestha", "contact_number": "9808864124", "address_parts": ["Lalitpur 4", "Baghdol"], "google_location_url": "https://maps.app.goo.gl/AkYDNZYwNHX9FVG99"},
    {"number": 33, "store_name": "Rabisonu Khadya Store", "contact_person": "Niranjan Prasad", "contact_number": "9803843336", "address_parts": ["Lalitpur 4", "Baghdol"], "google_location_url": "https://maps.app.goo.gl/Vp6hFgwr7Yiio7b67"},
    {"number": 34, "store_name": "ThapaJi Khadka Store", "contact_person": "Tilak Bahadur", "contact_number": "9742550893", "address_parts": ["Lalitpur 4", "Bishnudevi"], "google_location_url": "https://maps.app.goo.gl/5VpamW6quZUzcoug6"},
    {"number": 35, "store_name": "Best Buy Stores", "contact_person": "Bishnu Prasad", "contact_number": "9868176653", "address_parts": ["Lalitpur 13", "Nakhu Dobato"], "google_location_url": "https://maps.app.goo.gl/gbTcQgNitiCxAwrA9"},
    {"number": 36, "store_name": "Pan Pasal", "contact_person": "", "contact_number": "9801832991", "address_parts": ["Lalitpur 13", "Bishnudevi Mandir", "Nakhu"], "google_location_url": "https://maps.app.goo.gl/88ph3KG1bk6RnrY38"},
    {"number": 37, "store_name": "Shiva Baba Khadhnna", "contact_person": "", "contact_number": "9806876464", "address_parts": ["Lalitpur 13", "Nakhu"], "google_location_url": "https://maps.app.goo.gl/MbowXPwMeqcgHSm2A"},
    {"number": 38, "store_name": "B K S Store", "contact_person": "", "contact_number": "9860589501", "address_parts": ["Lalitpur 13", "Nakhu"], "google_location_url": "https://maps.app.goo.gl/ychWs6gfmsEnJnTP9"},
    {"number": 39, "store_name": "Kirana Pasal", "contact_person": "", "contact_number": "", "address_parts": ["Lalitpur 13", "Nakhu"], "google_location_url": "https://maps.app.goo.gl/qH1iDMCqFVNsJ2k8A"},
    {"number": 41, "store_name": "Usha Store", "contact_person": "Binod Gurung", "contact_number": "9848020787", "address_parts": ["Lalitpur 13", "Nakhu"], "google_location_url": "https://maps.app.goo.gl/KvbYbV88peb3Y2zC8"},
    {"number": 42, "store_name": "Khusi Kirana Store", "contact_person": "", "contact_number": "9768716661, 9768716662", "address_parts": ["Lalitpur 13", "Nakhu"], "google_location_url": "https://maps.app.goo.gl/fRVy62AF3vymQDou8"},
    {"number": 43, "store_name": "Riddhi Siddhi Masala Tatha Khadhyanna", "contact_person": "", "contact_number": "9849395184, 9818334779", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/HfgkZbUBacwi32Q1A"},
    {"number": 44, "store_name": "Kristi Kirana Pasal", "contact_person": "", "contact_number": "9864001811", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/7AeP4dhHtkWxJwWEA"},
    {"number": 45, "store_name": "Bharatb Kirana Pasal", "contact_person": "", "contact_number": "", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/TZHMRJVhp8vEsUT"},
    {"number": 46, "store_name": "Kusunti Hygenic Khadya Store", "contact_person": "", "contact_number": "9843470000, 9841303202", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/EppZJw9HuTT6cK8e8"},
    {"number": 47, "store_name": "Gamro Dairy and Kirana Pasal", "contact_person": "", "contact_number": "9849062058", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/m3ZPFePQ7TJYggzy6"},
    {"number": 48, "store_name": "Kripa Laxmi Store", "contact_person": "Krishna", "contact_number": "9869799847", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/hR3XaT5SgWxbvKs4A"},
    {"number": 49, "store_name": "Shree Sai Kirana Store", "contact_person": "", "contact_number": "9829074535", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/nN4sFy2GQdDPUgqf9"},
    {"number": 50, "store_name": "Subba Store", "contact_person": "", "contact_number": "9860695289", "address_parts": ["Lalitpur 13", "Ranibu Chok"], "google_location_url": "https://maps.app.goo.gl/V96Y6TzSZoT77jp16"},
    {"number": 51, "store_name": "Sabina Store", "contact_person": "Sabina Store", "contact_number": "9869749103", "address_parts": ["Lalitpur 3", "Dhobighat"], "google_location_url": "https://maps.app.goo.gl/zRtVuGSSXzNHnFat7"},
    {"number": 52, "store_name": "Kirana Pasal", "contact_person": "Gopi Maharjan", "contact_number": "9849709017", "address_parts": ["Lalitpur 3", "Dhobighat"], "google_location_url": "https://maps.app.goo.gl/V5g9uW86tZLrF4CD7"},
    {"number": 53, "store_name": "Nisha Dudh Dahi Pasal", "contact_person": "", "contact_number": "9864003847, 9807852402", "address_parts": ["Lalitpur 3", "Dhobighat"], "google_location_url": "https://maps.app.goo.gl/9yPrmtvwafssVVcQ7"},
    {"number": 54, "store_name": "Krishna Store", "contact_person": "Krishna", "contact_number": "9803143462", "address_parts": ["Lalitpur 3", "Dhobighat"], "google_location_url": "https://maps.app.goo.gl/jTVxwwBdLGYR7N8C9"},
    {"number": 55, "store_name": "B.G Maharjan", "contact_person": "", "contact_number": "9851176658, 01-5184368", "address_parts": ["Lalitpur 3", "Dhobighat", "Naya Bato"], "google_location_url": "https://maps.app.goo.gl/FYwom9115gfKpDQs5"},
    {"number": 56, "store_name": "Buddha Laxmi", "contact_person": "Phurba", "contact_number": "9810192694", "address_parts": ["Lalitpur 3", "Dhobighat", "Shanti Basti"], "google_location_url": "https://maps.app.goo.gl/ZNUxELqAtMqs7K5S6"},
    {"number": 57, "store_name": "Wholesale Suppliers", "contact_person": "Tej Prasad Sharma", "contact_number": "9818224809", "address_parts": ["Lalitpur 2", "Sanepa"], "google_location_url": "https://maps.app.goo.gl/RKdFsyvKuP5schRr8"},
    {"number": 58, "store_name": "Patanjali Store", "contact_person": "", "contact_number": "9803641595", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/LacMHbuoU518j9tHA"},
    {"number": 59, "store_name": "Parajuli Store", "contact_person": "", "contact_number": "01-5916181", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/cyMiFEMm5Htajgg58"},
    {"number": 60, "store_name": "Masala Pasal", "contact_person": "", "contact_number": "9841025206", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/6QnEhJftWeb2zC2NA"},
    {"number": 61, "store_name": "Raini Kirana Pasal", "contact_person": "Sangita Rai", "contact_number": "9813049905", "address_parts": ["Lalitpur 3", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/VPiz1vBktNtGUfhy9"},
    {"number": 62, "store_name": "Kc Store", "contact_person": "", "contact_number": "", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/VqoZMeFwzDPfAYHJ7"},
    {"number": 63, "store_name": "Pasal", "contact_person": "", "contact_number": "", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/5V9nBa44fXh3mcfS8"},
    {"number": 64, "store_name": "Samjhana Kirana", "contact_person": "Krishna Tamang", "contact_number": "9843021146", "address_parts": ["Lalitpur 13", "Kusunti"], "google_location_url": "https://maps.app.goo.gl/rVCp5ZBkmQxwMXB59"},
    {"number": 65, "store_name": "Shree R R Khadhyana Store", "contact_person": "", "contact_number": "9851156371, 01-5592365", "address_parts": ["Lalitpur 13", "Nakhu"], "google_location_url": "https://maps.app.goo.gl/u6K6hug1YrkoD2zc8"},
    {"number": 66, "store_name": "Surya Store", "contact_person": "", "contact_number": "01-5590245", "address_parts": ["Lalitpur 3", "Nakhu"], "google_location_url": "https://maps.app.goo.gl/bA5Jb6s3koaKPMM38"},
    {"number": 67, "store_name": "Karyabinayak Dairy", "contact_person": "", "contact_number": "9861616040, 9764576535", "address_parts": ["Lalitpur 13", "Nakhu"], "google_location_url": "https://maps.app.goo.gl/jkiVuK33Z7HteFR57"},
    {"number": 68, "store_name": "Anuska Kirana Pasal", "contact_person": "Kamal", "contact_number": "9841788998", "address_parts": ["Lalitpur 25", "Safal Marg"], "google_location_url": "https://maps.app.goo.gl/WpRsrtxc2ZxMbh1r5"},
    {"number": 69, "store_name": "Bomjan Cold Store", "contact_person": "Pragesh", "contact_number": "9865095812", "address_parts": ["Lalitpur 25", "Fulbari Marga"], "google_location_url": "https://maps.app.goo.gl/yaEY3pvmKqf6n2dX9"},
    {"number": 70, "store_name": "Gautam Store", "contact_person": "Safal Gautam", "contact_number": "9847336606", "address_parts": ["Lalitpur 25", "Fulbari Marga"], "google_location_url": "https://maps.app.goo.gl/Ujci9cddkgpS2xKq6"},
    {"number": 71, "store_name": "Pasal", "contact_person": "", "contact_number": "", "address_parts": ["Lalitpur 25", "Fulbari Marga"], "google_location_url": "https://maps.app.goo.gl/KS5s7AAE79myRFsv5"},
    {"number": 72, "store_name": "Kirana Pasal", "contact_person": "", "contact_number": "9803268419", "address_parts": ["Lalitpur 25", "Fulbari Marga"], "google_location_url": "https://maps.app.goo.gl/8UNPyHtSyXRrnnV96"},
    {"number": 73, "store_name": "Wholesale Shop", "contact_person": "Ujjal", "contact_number": "9765349887", "address_parts": ["Lalitpur 25", "Fulbari Marga"], "google_location_url": "https://maps.app.goo.gl/WHtjYur9TthwT6Eb6"},
    {"number": 74, "store_name": "Ranibu Traders", "contact_person": "", "contact_number": "9841547949", "address_parts": ["Lalitpur 13", "Ranibu"], "google_location_url": "https://maps.app.goo.gl/3zN94xvc7FALtRsW7"},
    {"number": 75, "store_name": "Chandra Surya Store", "contact_person": "Mukunda Lungeli", "contact_number": "9812010929", "address_parts": ["Lalitpur 14", "Ranibu"], "google_location_url": "https://maps.app.goo.gl/FMsPg7NAiezrDNBy9"},
    {"number": 76, "store_name": "Shuvam Mart", "contact_person": "", "contact_number": "01-5440892", "address_parts": ["Lalitpur 25", "Fulbaru Marg", "Bhaisepati"], "google_location_url": "https://maps.app.goo.gl/wdjE9ZGuUXVUXDS88"},
    {"number": 77, "store_name": "Tulasi Masala Mill", "contact_person": "", "contact_number": "9861118256", "address_parts": ["Lalitpur 25", "Sayapatri Marga"], "google_location_url": "https://maps.app.goo.gl/PaAQipBHzRnWpPYu9"},
]


def title_case(s):
    """Title-case a name, preserving connecting words and acronyms."""
    if not s:
        return s
    words = s.split()
    result = []
    for i, w in enumerate(words):
        if i > 0 and w.lower() in ('and', 'n', 'tatha'):
            result.append(w.lower())
        elif w.isupper() and len(w) <= 4:
            # Preserve short acronyms like JSA, BKS, B.G
            result.append(w)
        else:
            result.append(w.capitalize())
    return ' '.join(result)


def normalize_address(addr):
    """Clean up address — fix common misspellings, title case."""
    if not addr:
        return addr
    # Fix common misspellings
    replacements = {
        'Lalutpur': 'Lalitpur',
        'Ldlitpur': 'Lalitpur',
        'Lalitpyr': 'Lalitpur',
        'Lalirpur': 'Lalitpur',
        'nakhhu': 'Nakhu',
        'baghdol': 'Bagdol',
        'Baghdol': 'Bagdol',
        'bagdhol': 'Bagdol',
        'Bagdhol': 'Bagdol',
        'bagdole': 'Bagdol',
        'Bagdole': 'Bagdol',
        'bhasepati': 'Bhaisepati',
        'dhspakhel': 'Dhapakhel',
    }
    for old, new in replacements.items():
        addr = addr.replace(old, new)
    # Title case each part
    parts = [p.strip() for p in addr.split(',')]
    parts = [title_case(p) for p in parts if p]
    return ', '.join(parts)


def extract_city_area(address):
    """Extract city and area from address. Lalitpur N is the ward, everything else is area/landmark."""
    city = "Lalitpur"
    area = ""
    landmark = ""

    if not address:
        return city, area, landmark

    parts = [p.strip() for p in address.split(',')]
    non_ward = []
    for p in parts:
        if re.match(r'^Lalitpur\s+\d+$', p, re.I):
            continue  # Skip ward designation
        non_ward.append(p)

    if len(non_ward) >= 2:
        area = non_ward[0]
        landmark = non_ward[1]
    elif len(non_ward) == 1:
        area = non_ward[0]

    return city, area, landmark


def resolve_google_maps_url(url):
    """Follow redirect and read body to extract lat/lng from Google Maps short URL."""
    if not url:
        return None, None

    def check_coords(text):
        """Check text for coordinate patterns, return (lat, lng) or None."""
        # URL-decode first to normalize %2C, %21 etc.
        from urllib.parse import unquote
        text = unquote(text)

        patterns = [
            r'@(-?\d+\.\d+),(-?\d+\.\d+)',
            r'[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)',
            r'center=(-?\d+\.\d+),(-?\d+\.\d+)',  # staticmap center
            r'!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)',
            r'!2d(-?\d+\.\d+)!3d(-?\d+\.\d+)',  # lng, lat order in pb= param
        ]
        for p in patterns:
            m = re.search(p, text)
            if m:
                v1, v2 = float(m.group(1)), float(m.group(2))
                # !2d is longitude, !3d is latitude (reversed)
                if '!2d' in p:
                    lat, lng = v2, v1
                else:
                    lat, lng = v1, v2
                # Sanity: Nepal is roughly lat 26-30, lng 80-89
                if 26 <= lat <= 30 and 80 <= lng <= 89:
                    return lat, lng
                elif 26 <= lng <= 30 and 80 <= lat <= 89:
                    return lng, lat
        return None

    try:
        req = urllib.request.Request(url)
        req.add_header('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)')
        resp = urllib.request.urlopen(req, timeout=10)
        final_url = resp.url

        # Check URL first
        result = check_coords(final_url)
        if result:
            return result

        # Read body for embedded coords
        body = resp.read(10000).decode('utf-8', errors='ignore')
        result = check_coords(body)
        if result:
            return result

    except Exception:
        pass

    return None, None


def main():
    resolve_latlng = "--resolve-latlng" in sys.argv

    # Load parsed chat data
    with open(PARSED_FILE, "r") as f:
        chat_stores = json.load(f)

    # Index by number
    chat_by_num = {s["number"]: s for s in chat_stores}

    # Index raw table by number
    raw_by_num = {s["number"]: s for s in RAW_TABLE}

    # Merge: raw table takes priority for stores 1-77, chat for 78+
    all_numbers = sorted(set(list(chat_by_num.keys()) + list(raw_by_num.keys())))

    final = []
    for num in all_numbers:
        raw = raw_by_num.get(num)
        chat = chat_by_num.get(num)

        store = {
            "number": num,
            "store_name": "",
            "contact_person": "",
            "contact_number": "",
            "address": "",
            "area": "",
            "landmark": "",
            "city": "Lalitpur",
            "google_location_url": "",
        }

        # For stores 1-77, prefer the raw table data (which was manually curated)
        if raw:
            store["store_name"] = raw["store_name"]
            store["contact_person"] = raw["contact_person"]
            store["contact_number"] = raw["contact_number"]
            store["address"] = ', '.join(raw["address_parts"])
            store["google_location_url"] = raw["google_location_url"]

            # If raw is missing info but chat has it, use chat
            if chat:
                if not store["store_name"] and chat["store_name"]:
                    store["store_name"] = chat["store_name"]
                if not store["contact_person"] and chat["contact_person"]:
                    store["contact_person"] = chat["contact_person"]
                if not store["contact_number"] and chat["contact_number"]:
                    store["contact_number"] = chat["contact_number"]
                if not store["google_location_url"] and chat["google_location_url"]:
                    store["google_location_url"] = chat["google_location_url"]
        elif chat:
            store["store_name"] = chat["store_name"]
            store["contact_person"] = chat["contact_person"]
            store["contact_number"] = chat["contact_number"]
            store["address"] = chat["address"]
            store["google_location_url"] = chat["google_location_url"]

        # Normalize
        store["store_name"] = title_case(store["store_name"])
        store["contact_person"] = title_case(store["contact_person"])
        store["address"] = normalize_address(store["address"])

        # Extract city/area from address
        city, area, landmark = extract_city_area(store["address"])
        store["city"] = city
        store["area"] = area
        store["landmark"] = landmark

        # Clean contact number: remove trailing chars
        store["contact_number"] = re.sub(r'[^0-9,\s\-+]', '', store["contact_number"]).strip().rstrip(',')

        final.append(store)

    # Write intermediate result (before lat/lng)
    with open(OUT_FILE, "w") as f:
        json.dump(final, f, indent=2, ensure_ascii=False)

    print(f"Total stores: {len(final)}")
    print(f"With name: {sum(1 for s in final if s['store_name'])}")
    print(f"With contact: {sum(1 for s in final if s['contact_number'])}")
    print(f"With person: {sum(1 for s in final if s['contact_person'])}")
    print(f"With URL: {sum(1 for s in final if s['google_location_url'])}")
    print(f"Written to {OUT_FILE}")

    # Now resolve lat/lng from Google Maps URLs (only if flag passed)
    if not resolve_latlng:
        for store in final:
            store["latitude"] = None
            store["longitude"] = None
        print("\nSkipping lat/lng resolution (use --resolve-latlng to enable)")
    else:
        print(f"\nResolving lat/lng from {sum(1 for s in final if s['google_location_url'])} Google Maps URLs...")
        print("(This requires network access and may take a while)")

        resolved = 0
        failed = 0
        for i, store in enumerate(final):
            url = store.get("google_location_url", "")
            if not url:
                store["latitude"] = None
                store["longitude"] = None
                continue

            lat, lng = resolve_google_maps_url(url)
            store["latitude"] = lat
            store["longitude"] = lng

            if lat and lng:
                resolved += 1
            else:
                failed += 1

            if (i + 1) % 20 == 0:
                print(f"  Progress: {i+1}/{len(final)} (resolved: {resolved}, failed: {failed})")
                time.sleep(0.3)  # Be respectful

        print(f"\nLat/Lng Resolution: {resolved} resolved, {failed} failed out of {resolved+failed} URLs")

    # Write final result with lat/lng
    with open(OUT_FILE, "w") as f:
        json.dump(final, f, indent=2, ensure_ascii=False)

    print(f"Final data written to {OUT_FILE}")


if __name__ == "__main__":
    main()
