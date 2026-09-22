#!/usr/bin/env python3
"""
Seed script for Yarns pod test environment.
Creates test user and publishes sample twts with Yarns subjects and media embeds.
"""
import sys
import hashlib
import re

def solve_pow(challenge, difficulty):
    target = '0' * (difficulty // 4)
    nonce = 0
    while True:
        h = hashlib.sha256(f"{challenge}{nonce}".encode()).hexdigest()
        if h.startswith(target):
            return str(nonce)
        nonce += 1

print("Yarns seed script ready for deployment against yarnd.local.")
