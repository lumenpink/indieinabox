# Nostr Protocol Integration

IndieInABox provides native support for the [Nostr](https://github.com/nostr-protocol/nostr) decentralized communication protocol, enabling decentralized content syndication (POSSE), multi-relay publishing, and DNS-based identity verification.

---

## Supported NIPs (Nostr Implementation Possibilities)

- **NIP-01**: Basic protocol flow, canonical event JSON serialization, SHA-256 event IDs, and 64-byte Schnorr signatures over secp256k1 (BIP-340).
- **NIP-05**: DNS-based identity verification via `/.well-known/nostr.json` mapping usernames (e.g. `user@example.com`) to public keys and relays.
- **NIP-19**: Bech32-encoded entities for user-facing keys (`npub1...` for public keys and `nsec1...` for private keys).

---

## Architecture & Core Components

All Nostr logic is organized within the bounded context `Indieinabox\Nostr`:

```text
app/Nostr/
├── Bip340.php                  # Secp256k1 curve math, tagged hashes, and BIP-340 Schnorr signatures
├── NostrKey.php                # Keypair generation, x-only pubkey derivation, Bech32 (npub/nsec)
├── NostrEvent.php              # NIP-01 canonical event model, serialization, and signing
├── NostrRelayClient.php        # Native WebSocket (wss://) transport for multi-relay publishing
└── NostrSyndicationService.php # Post syndication orchestrator and frontmatter provenance
```

### 1. BIP-340 Schnorr Signatures (`Bip340`)

Nostr requires 64-byte Schnorr signatures over the `secp256k1` elliptic curve:
- Implemented with deterministic tagged hashes (`BIP0340/aux`, `BIP0340/nonce`, `BIP0340/challenge`).
- Uses PHP's native `gmp` extension for high-performance arbitrary-precision modular arithmetic.
- Verified against official Bitcoin Core BIP-340 test vectors for both signing and verification.

### 2. Key Management (`NostrKey`)

- **Private Keys**: 32-byte cryptographically secure random bytes (`random_bytes(32)`), represented in lowercase hex or Bech32 (`nsec1...`).
- **Public Keys**: 32-byte *x-only* coordinates derived from $P = d \cdot G$, represented in lowercase hex or Bech32 (`npub1...`).
- Helper methods provide seamless conversion between raw bytes, hex strings, and Bech32 encodings.

### 3. NIP-01 Event Model (`NostrEvent`)

- **Kind 1 (Short Text Notes)**: Formats notes or post excerpts with title, link, and hashtags.
- **Canonical Serialization**: Serializes `[0, <pubkey_hex>, <created_at>, <kind>, <tags>, <content>]` using unescaped slashes and unicode.
- **Event ID**: Lowercase 64-character SHA-256 hex digest of the serialized string.
- **Signature (`sig`)**: 64-byte (128 hex chars) BIP-340 Schnorr signature.

### 4. Multi-Relay WebSocket Publishing (`NostrRelayClient`)

Because Nostr relays are independent stores and do not communicate with each other, publishing to multiple relays is essential for broad availability:
- Native WebSocket client using standard PHP stream sockets over TLS (`stream_socket_client('ssl://...')`).
- Sends `["EVENT", <event_json>]` and parses `["OK", <id>, <true|false>, <message>]`.
- Pre-configured with resilient default public relays:
  - `wss://relay.damus.io`
  - `wss://nos.lol`
  - `wss://relay.nostr.band`
  - `wss://nostr.mom`
- Each relay can be enabled, disabled, or customized via the admin configuration.

### 5. NIP-05 DNS Identity Verification

Provides human-readable addresses in the format `name@domain.com`:
- Dynamic endpoint: `GET /.well-known/nostr.json?name=<user>`
- Static build output: `public_html/.well-known/nostr.json` generated during `SiteBuilder` build for static hosting environments.
- Serves mandatory CORS headers: `Access-Control-Allow-Origin: *`.
- Payload structure:
  ```json
  {
    "names": {
      "lumen": "63b057107b4f2ac7f7ddc0bb574e97f8320fd865676329ce84e7ed455c57211e"
    },
    "relays": {
      "63b057107b4f2ac7f7ddc0bb574e97f8320fd865676329ce84e7ed455c57211e": [
        "wss://relay.damus.io",
        "wss://nos.lol",
        "wss://relay.nostr.band",
        "wss://nostr.mom"
      ]
    }
  }
  ```

---

## Configuration Settings

Nostr is **disabled by default** and only runs when explicitly activated:

| Setting Key | Type | Default | Description |
|---|---|---|---|
| `nostr_enabled` | bool | `false` | Master toggle for Nostr features. |
| `nostr_privkey` | string | `""` | Author's private key (`nsec1...` or 64-hex). |
| `nostr_pubkey` | string | `""` | Author's public key (`npub1...` or 64-hex). |
| `nostr_nip05_name` | string | `""` | NIP-05 local-part (e.g. `lumen` for `lumen@fqdn`). Defaults to site author/nick. |
| `nostr_relays` | array | *4 defaults* | List of relay URLs with active status (`{url, active}`). |
| `nostr_auto_syndicate` | bool | `false` | Automatically publish new notes to Nostr on post creation. |
