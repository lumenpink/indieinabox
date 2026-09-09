import hashlib
import requests
import re
import html
import sys

BASE_URL = "http://localhost:1380"
HEADERS = {"Host": "yarndtest.2lp.in"}

def solve_pow(challenge, difficulty):
    target = '0' * (difficulty // 4)
    nonce = 0
    while True:
        # try both challenge+nonce and nonce+challenge
        h = hashlib.sha256(f"{challenge}{nonce}".encode()).hexdigest()
        if h.startswith(target):
            return str(nonce)
        nonce += 1

def register():
    s = requests.Session()
    resp = s.get(f"{BASE_URL}/register", headers=HEADERS)
    csrf = html.unescape(re.search(r'name="csrf_token" value="([^"]+)"', resp.text).group(1))
    challenge = re.search(r'name="pow_challenge" value="([^"]+)"', resp.text).group(1)
    timestamp = re.search(r'name="pow_timestamp" value="([^"]+)"', resp.text).group(1)
    signature = html.unescape(re.search(r'name="pow_signature" value="([^"]+)"', resp.text).group(1))
    diff = int(re.search(r'name="pow_difficulty" value="([^"]+)"', resp.text).group(1))

    nonce = solve_pow(challenge, diff)
    print(f"Solved PoW! Nonce: {nonce}")

    data = {
        "csrf_token": csrf,
        "username": "tester",
        "password": "password123",
        "email": "test@test.local",
        "pow_challenge": challenge,
        "pow_timestamp": timestamp,
        "pow_signature": signature,
        "pow_difficulty": str(diff),
        "pow_nonce": nonce
    }
    r = s.post(f"{BASE_URL}/register", headers=HEADERS, data=data)
    print(r.status_code)
    if "invalid proof" in r.text.lower():
        print("PoW failed format 1. Trying format 2 (nonce + challenge)...")
        # I won't do format 2 in this same session because CSRF/PoW might be consumed.
        # But let's see.
    else:
        print("Success or other error!")

if __name__ == "__main__":
    register()
