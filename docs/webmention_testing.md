# Webmention Testing & Interoperability Guide

Indieinabox is designed to adhere strictly to the [W3C Webmention Recommendation](https://www.w3.org/TR/webmention/) and the IndieWeb standards. This document provides instructions for validating and testing Webmention support across automated test suites, CLI tools, and online compliance test harnesses.

---

## 1. Compliance Harnesses & Validators

Three major validators are recognized in the IndieWeb and W3C ecosystem:

1. **[webmention.rocks](https://webmention.rocks/)** (by Aaron Parecki / IndieWeb):
   - Interactive receiver test cases (Tests 1–23): HTTP `Link` headers, `<link>` tags, `<a>` tags, query parameters, relative vs. absolute URLs, redirects, and unlinked mention rejection.
   - Interactive sender test endpoints.
2. **[IndieWebify.me](https://indiewebify.me/)**:
   - **Level 1 (Identity / h-card):** Validates the presence of semantic author microformats (`p-name`, `u-url`, `u-photo`, `p-note`, `rel="me"`).
   - **Level 2 (Receiving Webmentions):** Validates endpoint discovery (`<link rel="webmention">` or HTTP `Link` header) and verifies incoming ping delivery.
   - **Level 3 (Publishing & Outbound Delivery):** Validates `h-entry` markup and outbound webmention dispatching.
3. **W3C Webmention Implementation Report Suite**:
   - Verification of strict discovery precedence (`Link` header > `<link>` > `<a>`), document order resolution, exact target matching, and HTTP 202/201 status codes.

---

## 2. Automated Testing (Unit & Compliance Suites)

Indieinabox includes automated test suites covering all W3C and webmention.rocks edge cases offline:

```bash
# Run all unit tests
composer test

# Run all tests in both development and compiled single-file mode
composer test:all
```

Key test suites:
- `tests/Unit/Webmention/WebmentionRocksComplianceTest.php`: Simulates webmention.rocks test vectors (relative URLs, unlinked mentions, interaction extraction).
- `tests/Unit/Webmention/W3CTestSuiteTest.php`: Verifies W3C discovery priority, RFC 3986 relative URL resolution, document order evaluation, and `ThemeData::getHCard()` compliance.
- `tests/Unit/Webmention/PayloadParserTest.php`: Verifies Microformats 2 parsing (`mf2/mf2`), Whostyles V2 hashing, and interaction classification (`like`, `repost`, `reply`, `bookmark`, `rsvp`).

---

## 3. Dedicated CLI Testing Tool

Indieinabox provides a built-in CLI command to test endpoint discovery, ping remote endpoints, and validate local `h-card` markup:

### Test Endpoint Discovery
```bash
php indieinabox.php test-webmention https://webmention.rocks/test/1
```
Output:
```text
Checking target: https://webmention.rocks/test/1
HTTP Status: 200
Discovered Webmention Endpoint: https://webmention.rocks/test/1/webmention
Discovery Method: HTTP Link Header

Tip: To send a live webmention ping to this endpoint, pass --source <source-url>
```

### Send a Live Ping
```bash
php indieinabox.php test-webmention https://webmention.rocks/test/1 --source https://your-domain.com/notes/my-post
```
Output:
```text
Discovered Webmention Endpoint: https://webmention.rocks/test/1/webmention
Discovery Method: HTTP Link Header

Sending Webmention ping...
Source: https://your-domain.com/notes/my-post
Target: https://webmention.rocks/test/1
Response Code: 202
Result: SUCCESS (Webmention accepted)
```

### Validate IndieWebify.me Level 1 (`h-card`)
```bash
# Validate against current site configuration
php indieinabox.php test-webmention --validate-hcard

# Validate against a generated HTML file or remote URL
php indieinabox.php test-webmention --validate-hcard public_html/index.html
php indieinabox.php test-webmention --validate-hcard https://my-site.com
```

---

## 4. Manual Testing with webmention.rocks

To test live bidirectional webmentions against [webmention.rocks](https://webmention.rocks/) from a local environment:

### Step 1: Start Local Server & Public Tunnel
1. Start the local PHP built-in web server:
   ```bash
   php -S localhost:8080 build.php
   ```
2. In a separate terminal, expose port 8080 to the public web using a tunnel service:
   ```bash
   ngrok http 8080
   # or Cloudflare Tunnel:
   cloudflared tunnel --url http://localhost:8080
   ```
3. Update your Indieinabox site FQDN to match the public tunnel URL:
   ```bash
   php indieinabox.php config set --key fqdn --value https://your-subdomain.ngrok-free.app
   ```

### Step 2: Testing the Receiver (Tests 1 to 23)
1. Open [webmention.rocks](https://webmention.rocks/) in your browser.
2. Select any receiver test (for example, **Test 1: HTTP Link header** or **Test 23: Target with query parameter**).
3. In the webmention.rocks form:
   - Provide a post URL on your site as the **Webmention Target** (e.g. `https://your-subdomain.ngrok-free.app/notes/hello-world`).
   - Click **Send Webmention**.
4. Trigger the background worker to verify and process the queue:
   ```bash
   php indieinabox.php cron
   ```
5. The incoming mention will be verified, parsed via `PayloadParser` with Microformats 2, and saved to the post's interactions and your admin inbox.

### Step 3: Testing the Sender
1. Create a note linking to a webmention.rocks test endpoint:
   ```bash
   php indieinabox.php post create --text "Testing webmention delivery to https://webmention.rocks/test/1"
   ```
2. Trigger the outbound webmention dispatcher:
   ```bash
   php indieinabox.php cron
   ```
   Or send immediately using the CLI tool:
   ```bash
   php indieinabox.php test-webmention https://webmention.rocks/test/1 --source https://your-subdomain.ngrok-free.app/notes/...
   ```
3. Refresh the webmention.rocks test page to confirm your post appears on the success board.

---

## 5. Manual Testing with IndieWebify.me

### Level 1: Semantic Profile (`h-card`)
1. Navigate to [https://indiewebify.me/validate-h-card/](https://indiewebify.me/validate-h-card/).
2. Enter your homepage URL.
3. Confirm that:
   - Your name is marked up as `p-name`.
   - Your homepage link is marked up as `u-url` and includes `rel="me"`.
   - Your avatar is marked up as `u-photo`.
   - Your bio is marked up as `p-note`.

### Level 2: Webmention Receiving
1. Navigate to [https://indiewebify.me/send-webmentions/](https://indiewebify.me/send-webmentions/).
2. Enter your homepage or post URL.
3. IndieWebify.me will discover `<link rel="webmention" href="...">` and send a test webmention ping.
4. Run `php indieinabox.php cron` to confirm processing.

---

## 6. W3C Specification Compliance Matrix

| Requirement | W3C Section | Indieinabox Implementation | Status |
|---|---|---|---|
| **Header Precedence** | §3.1.2 | `WebmentionSender::parseHeaderEndpoint()` is checked before HTML body parsing. | Pass |
| **Document Order** | §3.1.2 | `DOMXPath` queries `//*(self::link \| self::a)` preserving HTML document order. | Pass |
| **Relative URL Resolution** | §3.1.2 / RFC 3986 | `WebmentionSender::resolveUrl()` resolves root, relative path, and parent `..` segments. | Pass |
| **Link Verification** | §3.2.2 | `SourceVerifier::verifyLink()` rejects unlinked plain text and requires exact target link. | Pass |
| **Interaction Parsing** | IndieWeb MF2 | `PayloadParser` parses author `h-card`, clean `e-content`, and interaction types (`like`, `repost`, `reply`, `bookmark`, `rsvp`). | Pass |
| **Async Response (202)** | §3.2.1 | `WebmentionHandler` responds with HTTP 202 Accepted and queues verification. | Pass |
