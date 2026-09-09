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
        h = hashlib.sha256(f"{challenge}{nonce}".encode()).hexdigest()
        if h.startswith(target):
            return str(nonce)
        nonce += 1

def register_user(session, username, password, email):
    resp = session.get(f"{BASE_URL}/register", headers=HEADERS)
    csrf = html.unescape(re.search(r'name="csrf_token" value="([^"]+)"', resp.text).group(1))
    challenge = re.search(r'name="pow_challenge" value="([^"]+)"', resp.text).group(1)
    timestamp = re.search(r'name="pow_timestamp" value="([^"]+)"', resp.text).group(1)
    signature = html.unescape(re.search(r'name="pow_signature" value="([^"]+)"', resp.text).group(1))
    diff = int(re.search(r'name="pow_difficulty" value="([^"]+)"', resp.text).group(1))

    nonce = solve_pow(challenge, diff)
    
    data = {
        "csrf_token": csrf,
        "username": username,
        "password": password,
        "email": email,
        "pow_challenge": challenge,
        "pow_timestamp": timestamp,
        "pow_signature": signature,
        "pow_difficulty": str(diff),
        "pow_nonce": nonce
    }
    r = session.post(f"{BASE_URL}/register", headers=HEADERS, data=data)
    if "invalid proof" in r.text.lower() or "error" in r.text.lower():
        # Maybe username taken, that's fine
        pass
    
    # Now login
    resp = session.get(f"{BASE_URL}/login", headers=HEADERS)
    csrf = html.unescape(re.search(r'name="csrf_token" value="([^"]+)"', resp.text).group(1))
    data = {
        "csrf_token": csrf,
        "username": username,
        "password": password
    }
    r = session.post(f"{BASE_URL}/login", headers=HEADERS, data=data)
    if r.status_code in [200, 302] and "invalid" not in r.text.lower():
        print(f"[+] Logged in as {username}")
        return True
    return False

def post_twt(session, text):
    resp = session.get(f"{BASE_URL}/", headers=HEADERS)
    match = re.search(r'name="csrf_token" value="([^"]+)"', resp.text)
    if not match:
        print("[-] Could not find CSRF token for posting")
        return False
    csrf = html.unescape(match.group(1))
    data = {
        "csrf_token": csrf,
        "text": text
    }
    r = session.post(f"{BASE_URL}/post", headers=HEADERS, data=data)
    if r.status_code in [200, 302]:
        print(f"[+] Posted: {text[:20]}...")
        return True
    return False

def main():
    print("Seeding Yarnd...")
    users = [
        {"username": "alice", "password": "password123", "email": "alice@yarn.local", "posts": ["Hello Yarn.social! This is Alice.", "Testing some #federation features."]},
        {"username": "bob", "password": "password123", "email": "bob@yarn.local", "posts": ["Hey @<alice yarndtest.2lp.in>! Welcome to the network.", "Having a great day testing the new pod."]}
    ]

    for u in users:
        s = requests.Session()
        register_user(s, u["username"], u["password"], u["email"])
        for p in u["posts"]:
            post_twt(s, p)

if __name__ == "__main__":
    main()
